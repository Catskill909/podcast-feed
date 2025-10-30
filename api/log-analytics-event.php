<?php
/**
 * Log Analytics Event API
 * Endpoint for logging play and download events
 */

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON input'
    ]);
    exit;
}

// Validate required fields
$requiredFields = ['type', 'podcastId', 'episodeId', 'sessionId'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Missing required field: $field"
        ]);
        exit;
    }
}

// Rate limiting: simple check for excessive requests
// Store in session to prevent abuse (max 50 events per session per minute)
session_start();
$rateLimitKey = 'analytics_rate_limit_' . session_id();
$currentMinute = date('Y-m-d H:i');

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['minute' => $currentMinute, 'count' => 0];
}

if ($_SESSION[$rateLimitKey]['minute'] === $currentMinute) {
    $_SESSION[$rateLimitKey]['count']++;
    
    if ($_SESSION[$rateLimitKey]['count'] > 50) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please try again later.'
        ]);
        exit;
    }
} else {
    // Reset counter for new minute
    $_SESSION[$rateLimitKey] = ['minute' => $currentMinute, 'count' => 1];
}

// Load Analytics Manager
require_once __DIR__ . '/../includes/AnalyticsManager.php';

try {
    $analyticsManager = new AnalyticsManager();

    // Extract data
    $type = $data['type'];
    $podcastId = $data['podcastId'];
    $episodeId = $data['episodeId'];
    $sessionId = $data['sessionId'];

    // Optional metadata
    $metadata = [];
    if (isset($data['episodeTitle'])) {
        $metadata['episodeTitle'] = $data['episodeTitle'];
    }
    if (isset($data['podcastTitle'])) {
        $metadata['podcastTitle'] = $data['podcastTitle'];
    }
    if (isset($data['audioUrl'])) {
        $metadata['audioUrl'] = $data['audioUrl'];
    }

    // Log event
    $result = $analyticsManager->logEvent($type, $podcastId, $episodeId, $sessionId, $metadata);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
