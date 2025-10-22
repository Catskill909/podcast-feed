<?php
/**
 * Delete Ad API Endpoint
 * Handles AJAX requests for deleting web and mobile banner ads
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
    
    // Get parameters
    $adType = $_POST['ad_type'] ?? '';
    $adId = $_POST['ad_id'] ?? '';
    
    if (!in_array($adType, ['web', 'mobile'])) {
        throw new Exception('Invalid ad type');
    }
    
    if (empty($adId)) {
        throw new Exception('Ad ID is required');
    }
    
    // Delete based on type
    if ($adType === 'web') {
        $result = $manager->deleteWebAd($adId);
    } else {
        $result = $manager->deleteMobileAd($adId);
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
