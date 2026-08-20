<?php

/**
 * Hosts the RSS proxy is allowed to fetch.
 *
 * Derived from the admin-curated podcast directory rather than a hand-kept
 * list: the directory IS the set of feeds the player can ever request, so a
 * podcast added through the admin UI becomes proxyable immediately. A static
 * list silently 403s every newly added podcast, which is what broke playback.
 *
 * Kept in its own file so test-proxy-allowlist.php can exercise it without
 * running the proxy's request handling.
 *
 * @param string $ownHost The host serving this request (may include a port).
 * @return array<string, true> Lowercase hostnames, used as a set.
 */
function feedAllowedHosts(string $ownHost = ''): array
{
    // The canonical directory host. script.js fetches the master feed from this
    // host by absolute URL, so it must be allowed even when the proxy is being
    // served from somewhere else (local dev, a staging domain).
    $hosts = ['podcast.supersoul.top' => true];

    // The host serving this request: master feed (feed.php) and self-hosted feeds.
    $ownHost = strtolower(trim($ownHost));
    if ($ownHost !== '') {
        // Strip port; feed URLs are compared on hostname only.
        $hosts[preg_replace('/:\d+$/', '', $ownHost)] = true;
    }

    $sources = [
        __DIR__ . '/../data/podcasts.xml',
        __DIR__ . '/../data/self-hosted-podcasts.xml',
    ];

    foreach ($sources as $source) {
        if (!is_readable($source)) {
            continue;
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($source);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            continue;
        }

        foreach ($xml->xpath('//feed_url') ?: [] as $feedUrl) {
            $host = parse_url(trim((string) $feedUrl), PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[strtolower($host)] = true;
            }
        }
    }

    return $hosts;
}

/**
 * Whether the proxy may fetch $url.
 *
 * Exact hostname match. The previous stripos() substring check also matched
 * lookalike hosts such as "podbean.com.attacker.example", turning the proxy
 * into an open relay for those.
 */
function feedUrlIsAllowed(string $url, string $ownHost = ''): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    $host = parse_url($url, PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : '';

    return $host !== '' && isset(feedAllowedHosts($ownHost)[$host]);
}
