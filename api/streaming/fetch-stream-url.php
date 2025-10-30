<?php
/**
 * Fetch Stream URL API
 * Resolves the live Icecast stream URL by reading the remote M3U playlist.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/StreamingProxy.php';

$defaultPlaylist = 'http://docs.pacifica.org/wpfw/wpfw.m3u';
$playlistUrl = $_GET['playlist'] ?? $defaultPlaylist;

if (!filter_var($playlistUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid playlist URL'
    ]);
    exit;
}

try {
    $proxy = new StreamingProxy();
    $streamUrl = $proxy->fetchStreamUrl($playlistUrl);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'playlist_url' => $playlistUrl,
        'stream_url' => $streamUrl
    ]);
} catch (Throwable $e) {
    error_log('StreamingProxy fetch-stream-url error: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to resolve stream URL',
        'error' => $e->getMessage()
    ]);
}
