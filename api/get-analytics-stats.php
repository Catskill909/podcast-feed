<?php
/**
 * Get Analytics Statistics API
 * Returns aggregated analytics data for dashboard
 */

header('Content-Type: application/json');

// Only accept GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

// Get time range parameter (default: 7d)
$range = isset($_GET['range']) ? $_GET['range'] : '7d';

// Validate range
$validRanges = ['7d', '30d', '90d', 'all'];
if (!in_array($range, $validRanges)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid range. Must be one of: ' . implode(', ', $validRanges)
    ]);
    exit;
}

// Load Analytics Manager
require_once __DIR__ . '/../includes/AnalyticsManager.php';

try {
    $analyticsManager = new AnalyticsManager();

    // Get dashboard statistics
    $stats = $analyticsManager->getDashboardStats($range);

    http_response_code(200);
    echo json_encode($stats);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
