<?php
/**
 * Feed Proxy API
 * Fetches external RSS feeds to avoid CORS issues
 */

header('Content-Type: application/xml; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Get feed URL from query parameter
$feedUrl = $_GET['url'] ?? '';

if (empty($feedUrl)) {
    http_response_code(400);
    echo '<?xml version="1.0"?><error>Feed URL is required</error>';
    exit;
}

// Validate URL
if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo '<?xml version="1.0"?><error>Invalid feed URL</error>';
    exit;
}

// Check if this is a self-hosted feed (local URL)
$parsedUrl = parse_url($feedUrl);
$isLocalFeed = in_array($parsedUrl['host'] ?? '', ['localhost', '127.0.0.1']) && 
               strpos($feedUrl, 'self-hosted-feed.php') !== false;

if ($isLocalFeed) {
    // For self-hosted feeds, call the script directly to avoid HTTP loop
    parse_str($parsedUrl['query'] ?? '', $queryParams);
    $podcastId = $queryParams['id'] ?? '';
    
    if ($podcastId) {
        // Include the feed generator directly
        $_GET['id'] = $podcastId;
        ob_start();
        include __DIR__ . '/../self-hosted-feed.php';
        $feedContent = ob_get_clean();
    } else {
        http_response_code(400);
        echo '<?xml version="1.0"?><error>Invalid self-hosted feed ID</error>';
        exit;
    }
} else {
    // Fetch external feed via HTTP with cache-busting
    $separator = (strpos($feedUrl, '?') === false) ? '?' : '&';
    $cacheBustUrl = $feedUrl . $separator . '_t=' . time() . '&_nocache=1';

    $feedContent = false;
    $lastError = '';
    $maxAttempts = 2; // one retry absorbs transient upstream/network blips

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        if (function_exists('curl_init')) {
            // Preferred path: cURL (works even when allow_url_fopen is off,
            // and gives us the real error/status instead of a blind failure)
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $cacheBustUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 5,  // fail fast on dead hosts
                CURLOPT_TIMEOUT => 15,        // allow slow-but-alive sources
                CURLOPT_USERAGENT => 'PodFeed Builder/1.0',
                CURLOPT_HTTPHEADER => [
                    'Cache-Control: no-cache, no-store, must-revalidate',
                    'Pragma: no-cache',
                    'Expires: 0',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            // Note: curl_close() intentionally omitted — it's a no-op since PHP 8.0
            // (the handle is freed when $ch goes out of scope) and deprecated in 8.5.

            if ($response !== false && $response !== '' && $httpCode >= 200 && $httpCode < 300) {
                $feedContent = $response;
                break;
            }
            $lastError = $curlErr !== '' ? $curlErr : ('HTTP ' . $httpCode);
        } else {
            // Fallback for hosts without the cURL extension (unchanged behavior)
            $context = stream_context_create([
                'http' => [
                    'timeout' => 15,
                    'user_agent' => 'PodFeed Builder/1.0',
                    'follow_location' => true,
                    'max_redirects' => 3,
                    'header' => "Cache-Control: no-cache, no-store, must-revalidate\r\n" .
                               "Pragma: no-cache\r\n" .
                               "Expires: 0\r\n"
                ]
            ]);
            $response = @file_get_contents($cacheBustUrl, false, $context);
            if ($response !== false && $response !== '') {
                $feedContent = $response;
                break;
            }
            $lastError = 'file_get_contents failed';
        }

        // Brief pause before retrying (only if another attempt remains)
        if ($attempt < $maxAttempts) {
            usleep(300000); // 0.3s
        }
    }

    if ($feedContent === false) {
        // Record which feed failed and why, so recurrences can be diagnosed
        error_log('fetch-feed.php: failed for ' . $feedUrl . ' after ' . $maxAttempts . ' attempts: ' . $lastError);
        http_response_code(502);
        echo '<?xml version="1.0"?><error>Failed to fetch feed from source</error>';
        exit;
    }
}

// Return the feed content
echo $feedContent;
