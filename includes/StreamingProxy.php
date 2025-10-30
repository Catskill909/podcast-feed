<?php
/**
 * StreamingProxy
 * Lightweight helper for fetching remote audio stream URLs and live metadata
 */

class StreamingProxy
{
    private const DEFAULT_USER_AGENT = 'Podcast Feed Streaming Proxy/1.0';
    private const DEFAULT_TIMEOUT = 10;

    /**
     * Fetch the stream URL from a remote M3U playlist.
     * Returns the first non-comment line.
     */
    public function fetchStreamUrl(string $m3uUrl): string
    {
        $contents = $this->httpGet($m3uUrl, [
            'Accept' => 'audio/x-mpegurl, application/vnd.apple.mpegurl, */*',
        ], 5);

        $lines = preg_split('/\r?\n/', $contents);
        if (!$lines) {
            throw new RuntimeException('M3U playlist is empty');
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }
            if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
                continue;
            }
            return $trimmed;
        }

        throw new RuntimeException('No valid stream URL found in playlist');
    }

    /**
     * Fetch and normalize metadata from the Confessor playlist API.
     */
    public function fetchMetadata(string $metadataUrl): array
    {
        $json = $this->httpGet($metadataUrl, [
            'Accept' => 'application/json, text/javascript, */*;q=0.1',
        ]);

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Metadata JSON parse error: ' . json_last_error_msg());
        }

        if (!is_array($decoded) || count($decoded) < 2) {
            throw new RuntimeException('Unexpected metadata response format');
        }

        $global = $decoded[0]['global'] ?? [];
        $current = $decoded[1]['current'] ?? [];
        $next = $decoded[2]['next'] ?? [];

        $nowPlayingTitle = $current['pl_song'] ?? '';
        $nowPlayingArtist = $current['pl_artist'] ?? '';

        if ($nowPlayingTitle === '') {
            $nowPlayingTitle = $current['sh_name'] ?? ''; // Fallback to show title
        }

        if ($nowPlayingArtist === '') {
            $nowPlayingArtist = $current['sh_djname'] ?? '';
        }

        $pledgeUrl = $current['pledge'] ?? $global['gl_pledgeurl'] ?? '';
        $artwork = $current['sh_photo'] ?? '';
        if ($artwork === '' && !empty($global['gl_pixurl']) && !empty($global['gl_stapix'])) {
            $artwork = rtrim($global['gl_pixurl'], '/') . '/' . ltrim($global['gl_stapix'], '/');
        }

        return [
            'station' => [
                'id' => $global['gl_id'] ?? null,
                'name' => $global['gl_station'] ?? 'WPFW',
                'city' => $global['gl_city'] ?? '',
                'description' => $global['gl_desc'] ?? '',
                'listen_url' => $global['listenurl'] ?? ($global['gl_streamurl'] ?? ''),
                'pledge_url' => $global['gl_pledgeurl'] ?? '',
                'image' => $artwork,
            ],
            'current' => [
                'title' => $nowPlayingTitle,
                'artist' => $nowPlayingArtist,
                'show' => $current['sh_name'] ?? '',
                'hosts' => $current['sh_djname'] ?? '',
                'pl_song' => $current['pl_song'] ?? '',
                'pl_artist' => $current['pl_artist'] ?? '',
                'description' => $current['sh_desc'] ?? '',
                'start' => $current['cur_start'] ?? '',
                'end' => $current['cur_end'] ?? '',
                'pledge_url' => $pledgeUrl,
                'artwork' => $artwork,
                'id' => $current['ph_id'] ?? $current['sh_altid'] ?? null,
            ],
            'next' => [
                'title' => $next['sh_name'] ?? '',
                'hosts' => $next['sh_djname'] ?? '',
                'start' => $next['nxt_start'] ?? ($next['ph_start'] ?? ''),
                'end' => $next['nxt_end'] ?? '',
                'id' => $next['ph_id'] ?? $next['sh_altid'] ?? null,
            ],
            'raw' => $decoded,
        ];
    }

    /**
     * Basic HTTP GET wrapper with cURL.
     */
    private function httpGet(string $url, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): string
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialise cURL');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_ENCODING => '',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        if ($errno !== 0) {
            throw new RuntimeException(sprintf('Network error: %s (code %d)', $error ?: 'Unknown', $errno));
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException(sprintf('Remote server returned HTTP %d', $httpCode));
        }

        if ($response === false || $response === '') {
            throw new RuntimeException('Remote server returned empty response');
        }

        return $response;
    }
}
