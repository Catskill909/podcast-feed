<?php
/**
 * Fetch Metadata API
 * Proxies the Confessor now-playing API to avoid CORS and normalises response shape.
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

$defaultMetadataUrl = 'https://confessor.wpfwfm.org/playlist/_pl_current_ary.php';
$metadataUrl = $_GET['metadata_url'] ?? $defaultMetadataUrl;

if (!filter_var($metadataUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid metadata URL'
    ]);
    exit;
}

// Optional: pass through stream mount identifier for logging/diagnostics later
$mount = $_GET['mount'] ?? 'wpfw_128';

try {
    $proxy = new StreamingProxy();
    $metadata = $proxy->fetchMetadata($metadataUrl);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'mount' => $mount,
        'metadata_url' => $metadataUrl,
        'data' => $metadata,
    ]);
} catch (Throwable $e) {
    error_log('StreamingProxy fetch-metadata error: ' . $e->getMessage());
    http_response_code(502);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load live metadata',
        'error' => $e->getMessage()
    ]);
}
