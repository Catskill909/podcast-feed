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

// Fetch the feed
$context = stream_context_create([
    'http' => [
        'timeout' => 10,
        'user_agent' => 'PodFeed Builder/1.0',
        'follow_location' => true,
        'max_redirects' => 3
    ]
]);

$feedContent = @file_get_contents($feedUrl, false, $context);

if ($feedContent === false) {
    http_response_code(502);
    echo '<?xml version="1.0"?><error>Failed to fetch feed from source</error>';
    exit;
}

// Return the feed content
echo $feedContent;
