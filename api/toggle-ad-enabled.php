<?php
/**
 * Toggle Ad Enabled State API
 * Toggles the enabled/disabled state of an individual ad
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/AdsXMLHandler.php';
require_once __DIR__ . '/../config/config.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['ad_id']) || !isset($input['ad_type'])) {
        throw new Exception('Missing required parameters');
    }
    
    $adId = $input['ad_id'];
    $adType = $input['ad_type'];
    
    // Validate ad type
    if (!in_array($adType, ['web', 'mobile'])) {
        throw new Exception('Invalid ad type');
    }
    
    // Toggle the enabled state
    $handler = new AdsXMLHandler();
    $success = $handler->toggleAdEnabled($adId, $adType);
    
    if (!$success) {
        throw new Exception('Failed to toggle ad state');
    }
    
    // Get updated ad data to return the new state
    $ads = $adType === 'web' ? $handler->getWebAds() : $handler->getMobileAds();
    $updatedAd = null;
    foreach ($ads as $ad) {
        if ($ad['id'] === $adId) {
            $updatedAd = $ad;
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ad state toggled successfully',
        'enabled' => $updatedAd ? $updatedAd['enabled'] : true
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
