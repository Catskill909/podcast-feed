<?php
/**
 * Get Ad Data API Endpoint
 * Returns current ads and settings
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/AdsManager.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $manager = new AdsManager();
    
    $data = [
        'success' => true,
        'web_ads' => $manager->getWebAds(),
        'mobile_ads' => $manager->getMobileAds(),
        'settings' => $manager->getSettings()
    ];
    
    http_response_code(200);
    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
