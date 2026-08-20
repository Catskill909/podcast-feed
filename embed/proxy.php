<?php

/**
 * RSS Feed Proxy
 * Fetches RSS feeds server-side to avoid CORS issues.
 * Works locally AND when deployed.
 */

// Enable CORS for all origins (allows embeds to work)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/xml; charset=UTF-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function proxyError(int $status, string $message): never
{
    http_response_code($status);
    echo '<?xml version="1.0"?><error>' . htmlspecialchars($message) . '</error>';
    exit;
}

require_once __DIR__ . '/../includes/FeedUrlGuard.php';

// Get the feed URL from query parameter
$feedUrl = isset($_GET['url']) ? trim((string) $_GET['url']) : '';

if ($feedUrl === '') {
    proxyError(400, 'Missing url parameter');
}

if (!filter_var($feedUrl, FILTER_VALIDATE_URL)) {
    proxyError(400, 'Invalid URL');
}

$scheme = strtolower((string) parse_url($feedUrl, PHP_URL_SCHEME));
if (!in_array($scheme, ['http', 'https'], true)) {
    proxyError(400, 'Only http and https URLs are supported');
}

if (!feedUrlIsAllowed($feedUrl, (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
    proxyError(403, 'Domain not allowed');
}

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $feedUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    // Podcast hosts redirect heavily, so redirects are followed - but confine
    // them to http/https so a redirect cannot reach file:// or gopher://.
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_USERAGENT => 'Podcast Player/1.0 (RSS Feed Aggregator)',
    CURLOPT_HTTPHEADER => [
        'Accept: application/rss+xml, application/xml, text/xml, */*'
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    proxyError($httpCode ?: 500, $error ?: 'Failed to fetch feed');
}

echo $response;
