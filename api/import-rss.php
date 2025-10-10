<?php
/**
 * RSS Feed Import API Endpoint
 * Handles fetching and parsing RSS feeds for import preview
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/RssFeedParser.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get feed URL from POST data
$feedUrl = $_POST['feed_url'] ?? '';

if (empty($feedUrl)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Feed URL is required'
    ]);
    exit;
}

// Create parser and fetch feed
$parser = new RssFeedParser();
$result = $parser->fetchAndParse($feedUrl);

// Return result
if ($result['success']) {
    http_response_code(200);
    echo json_encode($result);
} else {
    http_response_code(400);
    echo json_encode($result);
}
