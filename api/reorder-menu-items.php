<?php

/**
 * Reorder Menu Items API
 * Handles drag-and-drop reordering of menu items
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/MenuManager.php';

try {
    $manager = new MenuManager();

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    $order = $data['order'] ?? [];

    if (empty($order) || !is_array($order)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order data'
        ]);
        exit;
    }

    $result = $manager->reorderMenuItems($order);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
