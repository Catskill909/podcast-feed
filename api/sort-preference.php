<?php
/**
 * Sort Preference API
 * Handles reading and updating the default sort preference
 * This controls how feed.php generates the RSS feed for external apps
 */

require_once __DIR__ . '/../includes/SortPreferenceManager.php';

header('Content-Type: application/json');

$sortPrefManager = new SortPreferenceManager();

// GET: Return current preference
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $preference = $sortPrefManager->getPreference();
        $sortKey = $sortPrefManager->getPreferenceAsSortKey();
        
        echo json_encode([
            'success' => true,
            'sortKey' => $sortKey,
            'sort' => $preference['sort'],
            'order' => $preference['order'],
            'last_updated' => $preference['last_updated']
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to read sort preference'
        ]);
    }
    exit;
}

// POST: Update preference
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['sortKey'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing sortKey parameter'
            ]);
            exit;
        }

        $sortKey = $input['sortKey'];
        
        // Convert frontend sort key to backend parameters
        $params = $sortPrefManager->convertSortKey($sortKey);
        
        // Save preference
        $result = $sortPrefManager->savePreference($params['sort'], $params['order']);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Sort preference saved successfully',
                'sortKey' => $sortKey,
                'sort' => $params['sort'],
                'order' => $params['order']
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to save sort preference'
            ]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Server error: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode([
    'success' => false,
    'error' => 'Method not allowed'
]);
