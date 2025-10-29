<?php

/**
 * Toggle Menu Item API
 * Handles enabling/disabling menu items
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/MenuManager.php';

try {
    $manager = new MenuManager();

    $id = $_POST['id'] ?? '';
    $active = isset($_POST['active']) ? (bool)$_POST['active'] : true;

    if (empty($id)) {
        echo json_encode([
            'success' => false,
            'message' => 'Menu item ID is required'
        ]);
        exit;
    }

    $result = $manager->toggleMenuItem($id, $active);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
