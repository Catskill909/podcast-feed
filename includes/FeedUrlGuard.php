<?php

/**
 * Shared guards for server-side URL fetching.
 *
 * Any endpoint that fetches a caller-supplied URL must use one of these. An
 * unguarded fetcher is a server-side request forgery hole: it lets an anonymous
 * caller reach internal services and cloud metadata endpoints, and launder
 * traffic through the server's IP.
 */

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

/**
 * Whether $url resolves only to public, routable addresses.
 *
 * For fetchers that cannot use an allowlist - podcast audio is routinely served
 * from a different host than the feed (feed on podbean.com, media on
 * mcdn.podbean.com), so allowlisting feed hosts would break downloads. Blocking
 * private space is the correct guard there.
 *
 * Every resolved address is checked, not just the first: a hostname can return
 * both a public and a private record.
 */
function feedUrlHostIsPublic(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    $host = trim($host, '[]');

    // A literal IP is checked directly; a name is resolved first.
    $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : resolveHostAddresses($host);
    if ($addresses === []) {
        return false;
    }

    foreach ($addresses as $address) {
        $isPublic = filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($isPublic === false) {
            return false;
        }
    }

    return true;
}

/**
 * All A/AAAA records for a hostname.
 *
 * @return list<string>
 */
function resolveHostAddresses(string $host): array
{
    $addresses = [];

    $v4 = gethostbynamel($host);
    if (is_array($v4)) {
        $addresses = $v4;
    }

    $v6 = @dns_get_record($host, DNS_AAAA);
    if (is_array($v6)) {
        foreach ($v6 as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }
    }

    return array_values(array_unique($addresses));
}
