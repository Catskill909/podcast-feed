<?php

/**
 * Regression test for the embed RSS proxy allowlist.
 *
 * Covers the class of bug that broke the embed preview: a feed that the admin
 * added to the directory was rejected by the proxy, so the player fell through
 * to third-party CORS proxies and rendered nothing when those were down.
 *
 * Run: php test-proxy-allowlist.php
 */

require_once __DIR__ . '/includes/FeedUrlGuard.php';

$ownHost = 'podcast.supersoul.top';
$failures = [];
$checked = 0;

function check(string $label, bool $actual, bool $expected, array &$failures): void
{
    if ($actual !== $expected) {
        $failures[] = sprintf(
            '%s: expected %s, got %s',
            $label,
            $expected ? 'ALLOWED' : 'BLOCKED',
            $actual ? 'ALLOWED' : 'BLOCKED'
        );
    }
}

// 1. THE CLASS: every feed in the directory must be proxyable. This fails for
//    any podcast whose host is not covered, not just the ones reported broken.
$sources = [
    __DIR__ . '/data/podcasts.xml',
    __DIR__ . '/data/self-hosted-podcasts.xml',
];

foreach ($sources as $source) {
    if (!is_readable($source)) {
        continue;
    }
    $xml = simplexml_load_file($source);
    if ($xml === false) {
        $failures[] = "could not parse $source";
        continue;
    }
    foreach ($xml->xpath('//feed_url') ?: [] as $feedUrl) {
        $url = trim((string) $feedUrl);
        if ($url === '') {
            continue;
        }
        $checked++;
        check("directory feed $url", feedUrlIsAllowed($url, $ownHost), true, $failures);
    }
}

// 2. The master feed the embed player loads on startup.
check(
    'master feed',
    feedUrlIsAllowed('https://podcast.supersoul.top/feed.php', $ownHost),
    true,
    $failures
);

// 3. Hosts that must stay blocked. The lookalikes are what the old substring
//    match let through.
$mustBlock = [
    'https://podbean.com.attacker.example/x.xml',
    'https://podcast.supersoul.top.attacker.example/x.xml',
    'https://not-democracynow.org.evil.example/x.xml',
    'https://evil.example.com/feed.xml',
    'http://169.254.169.254/latest/meta-data/',
    'file:///etc/passwd',
    'gopher://127.0.0.1:11211/',
    'not a url',
];

foreach ($mustBlock as $url) {
    check("blocked $url", feedUrlIsAllowed($url, $ownHost), false, $failures);
}

// 4. feedUrlHostIsPublic: the guard used where an allowlist cannot apply
//    (download-proxy.php). Private and loopback space must never resolve.
$privateUrls = [
    'http://127.0.0.1/',
    'http://localhost/',
    'http://169.254.169.254/latest/meta-data/',
    'http://10.0.0.1/',
    'http://192.168.1.1/',
    'http://172.16.0.1/',
    'http://[::1]/',
    'http://0.0.0.0/',
];

foreach ($privateUrls as $url) {
    check("private host $url", feedUrlHostIsPublic($url), false, $failures);
}

// A public host must still pass, or downloads break.
check('public host', feedUrlHostIsPublic('https://example.com/a.mp3'), true, $failures);

// Report
if ($failures === []) {
    echo "PASS - {$checked} directory feeds allowed, " . count($mustBlock) . " hostile URLs blocked, "
        . count($privateUrls) . " private hosts rejected\n";
    exit(0);
}

echo "FAIL\n";
foreach ($failures as $failure) {
    echo "  - $failure\n";
}
exit(1);
