<?php

/**
 * Delete Menu Item API
 * Handles deleting menu items
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/MenuManager.php';

try {
    $manager = new MenuManager();

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Menu item ID is required'
        ]);
        exit;
    }

    $result = $manager->deleteMenuItem($id);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
