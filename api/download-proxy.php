<?php
/**
 * Download Proxy for Cross-Origin Audio Files
 * 
 * This proxy fetches audio files from external URLs and serves them with
 * Content-Disposition: attachment headers to force download behavior.
 * 
 * This is necessary because:
 * 1. Fetch + Blob fails on cross-origin URLs without CORS headers
 * 2. The HTML5 download attribute is ignored for cross-origin URLs
 * 
 * Usage: /api/download-proxy.php?url=ENCODED_URL&filename=ENCODED_FILENAME
 */

// Increase limits for large audio files
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

// Get parameters
$url = isset($_GET['url']) ? $_GET['url'] : '';
$filename = isset($_GET['filename']) ? $_GET['filename'] : 'download.mp3';

// Validate URL
if (empty($url)) {
    http_response_code(400);
    die('Missing URL parameter');
}

// Decode URL if needed
$url = urldecode($url);

// Validate it's a proper URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    die('Invalid URL');
}

// Security: Only allow http/https protocols
$parsed = parse_url($url);
if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
    http_response_code(400);
    die('Invalid protocol');
}

// Sanitize filename
$filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
if (empty($filename)) {
    $filename = 'download.mp3';
}

// Ensure .mp3 extension if not present
if (!preg_match('/\.(mp3|m4a|wav|ogg|aac)$/i', $filename)) {
    $filename .= '.mp3';
}

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_CONNECTTIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PodFeed/1.0)',
    CURLOPT_HEADER => false,
    // Stream directly to output
    CURLOPT_WRITEFUNCTION => function($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    },
    // Get headers to determine content type and length
    CURLOPT_HEADERFUNCTION => function($ch, $header) use ($filename) {
        $len = strlen($header);
        $header = trim($header);
        
        // Pass through content-length header
        if (stripos($header, 'content-length:') === 0) {
            header($header);
        }
        
        return $len;
    }
]);

// First, do a HEAD request to get content info
$headCh = curl_init();
curl_setopt_array($headCh, [
    CURLOPT_URL => $url,
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PodFeed/1.0)',
]);

curl_exec($headCh);
$httpCode = curl_getinfo($headCh, CURLINFO_HTTP_CODE);
$contentLength = curl_getinfo($headCh, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
$contentType = curl_getinfo($headCh, CURLINFO_CONTENT_TYPE);
curl_close($headCh);

// Check if resource exists
if ($httpCode !== 200) {
    http_response_code($httpCode ?: 404);
    die('Resource not found or unavailable');
}

// Set headers for download
header('Content-Type: audio/mpeg');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

if ($contentLength > 0) {
    header('Content-Length: ' . $contentLength);
}

// Disable output buffering for streaming
if (ob_get_level()) {
    ob_end_clean();
}

// Execute the download
$result = curl_exec($ch);

if ($result === false) {
    $error = curl_error($ch);
    error_log("Download proxy error: $error for URL: $url");
}

curl_close($ch);
