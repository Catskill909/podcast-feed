<?php
// Start output buffering to catch any stray output
ob_start();

// Suppress all errors and warnings to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/AdsXMLHandler.php';
    
    // Clear any output that might have been generated
    ob_clean();
    
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit;
    }

    $adId = $_POST['ad_id'] ?? '';
    $adType = $_POST['ad_type'] ?? '';
    $url = $_POST['url'] ?? '';
    
    if (empty($adId) || empty($adType)) {
        throw new Exception('Missing required parameters');
    }
    
    if (!in_array($adType, ['web', 'mobile'])) {
        throw new Exception('Invalid ad type');
    }
    
    $xmlHandler = new AdsXMLHandler();
    $result = $xmlHandler->updateAdUrl($adId, $adType, $url);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'URL updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update URL');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Error $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
