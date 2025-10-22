<?php
/**
 * Upload Ad API Endpoint
 * Handles AJAX requests for uploading web and mobile banner ads
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/AdsManager.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $manager = new AdsManager();
    
    // Get ad type
    $adType = $_POST['ad_type'] ?? '';
    
    if (!in_array($adType, ['web', 'mobile'])) {
        throw new Exception('Invalid ad type');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['ad_image']) || $_FILES['ad_image']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded');
    }
    
    // Upload based on type
    if ($adType === 'web') {
        $result = $manager->uploadWebAd($_FILES['ad_image']);
    } else {
        $result = $manager->uploadMobileAd($_FILES['ad_image']);
    }
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
