<?php
/**
 * RSS Import Validation API Endpoint
 * Validates RSS feeds BEFORE import to ensure quality
 * 
 * IMPORTANT: This is SEPARATE from import-rss.php
 * This endpoint only validates, it does NOT import
 */

require_once __DIR__ . '/../includes/RssImportValidator.php';

// CRITICAL: Set these AFTER includes to override config.php settings
// Prevent HTML error output (must be after config.php is loaded)
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit;
}

// Get feed URL from POST data (support both form data and JSON)
$feedUrlToValidate = '';

// Try JSON body first
$jsonInputData = file_get_contents('php://input');
if (!empty($jsonInputData)) {
    $decodedJsonData = json_decode($jsonInputData, true);
    if (isset($decodedJsonData['feed_url'])) {
        $feedUrlToValidate = $decodedJsonData['feed_url'];
    }
}

// Fallback to form data
if (empty($feedUrlToValidate) && isset($_POST['feed_url'])) {
    $feedUrlToValidate = $_POST['feed_url'];
}

// Validate input
if (empty($feedUrlToValidate)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Feed URL is required'
    ]);
    exit;
}

try {
    // Create validator instance
    $rssValidator = new RssImportValidator();
    
    // Run validation
    $validationResults = $rssValidator->validateFeedForImport($feedUrlToValidate);
    
    // Format response
    $responseData = [
        'success' => true,
        'validation' => [
            'can_import' => $validationResults['can_import'],
            'validation_level' => $validationResults['validation_level'],
            'feed_url' => $feedUrlToValidate,
            'response_time' => $validationResults['response_time_seconds'],
            
            // Critical checks summary
            'critical' => [
                'total' => count($validationResults['critical_checks']),
                'passed' => count(array_filter($validationResults['critical_checks'], function($check) {
                    return $check['passed'];
                })),
                'checks' => $validationResults['critical_checks']
            ],
            
            // Warning checks summary
            'warnings' => [
                'total' => count($validationResults['warning_checks']),
                'passed' => count(array_filter($validationResults['warning_checks'], function($check) {
                    return $check['passed'];
                })),
                'checks' => $validationResults['warning_checks']
            ],
            
            // Errors (blocking)
            'errors' => $validationResults['validation_errors'],
            
            // Warnings (non-blocking)
            'warning_messages' => $validationResults['validation_warnings'],
            
            // Feed metadata
            'feed_info' => $validationResults['feed_metadata']
        ]
    ];
    
    // Set appropriate HTTP status
    if ($validationResults['can_import']) {
        http_response_code(200);
    } else {
        http_response_code(422); // Unprocessable Entity
    }
    
    echo json_encode($responseData);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Validation error: ' . $e->getMessage()
    ]);
}
