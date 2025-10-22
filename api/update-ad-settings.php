<?php
/**
 * Update Ad Settings API Endpoint
 * Handles AJAX requests for updating ad settings (toggles, rotation duration, order)
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
    
    // Get action type
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_settings':
            // Update general settings (toggles, duration)
            $settings = [];
            
            if (isset($_POST['web_ads_enabled'])) {
                $settings['web_ads_enabled'] = $_POST['web_ads_enabled'] === 'true' || $_POST['web_ads_enabled'] === '1' ? '1' : '0';
            }
            
            if (isset($_POST['mobile_ads_enabled'])) {
                $settings['mobile_ads_enabled'] = $_POST['mobile_ads_enabled'] === 'true' || $_POST['mobile_ads_enabled'] === '1' ? '1' : '0';
            }
            
            if (isset($_POST['web_ads_rotation_duration'])) {
                $duration = (int)$_POST['web_ads_rotation_duration'];
                if ($duration < 1) $duration = 5;
                $settings['web_ads_rotation_duration'] = (string)$duration;
            }
            
            if (isset($_POST['web_ads_fade_duration'])) {
                $fadeDuration = (float)$_POST['web_ads_fade_duration'];
                if ($fadeDuration < 0.5) $fadeDuration = 0.5;
                if ($fadeDuration > 3) $fadeDuration = 3;
                $settings['web_ads_fade_duration'] = (string)$fadeDuration;
            }
            
            $result = $manager->updateSettings($settings);
            break;
            
        case 'update_order':
            // Update display order
            $adType = $_POST['ad_type'] ?? '';
            $orderedIds = json_decode($_POST['ordered_ids'] ?? '[]', true);
            
            if (!in_array($adType, ['web', 'mobile'])) {
                throw new Exception('Invalid ad type');
            }
            
            if (!is_array($orderedIds) || empty($orderedIds)) {
                throw new Exception('Invalid order data');
            }
            
            if ($adType === 'web') {
                $result = $manager->updateWebAdsOrder($orderedIds);
            } else {
                $result = $manager->updateMobileAdsOrder($orderedIds);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
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
