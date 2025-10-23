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
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'PodFeed Builder/1.0',
            'follow_location' => true,
            'max_redirects' => 3,
            'header' => "Cache-Control: no-cache, no-store, must-revalidate\r\n" .
                       "Pragma: no-cache\r\n" .
                       "Expires: 0\r\n"
        ]
    ]);

    $feedContent = @file_get_contents($cacheBustUrl, false, $context);

    if ($feedContent === false) {
        http_response_code(502);
        echo '<?xml version="1.0"?><error>Failed to fetch feed from source</error>';
        exit;
    }
}

// Return the feed content
echo $feedContent;
