<?php

/**
 * Save Menu Item API
 * Handles adding and updating menu items
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/MenuManager.php';

try {
    $manager = new MenuManager();

    $action = $_POST['action'] ?? 'add'; // 'add' or 'update'
    $id = $_POST['id'] ?? null;

    $url = $_POST['url'] ?? '';
    
    // Auto-add https:// to domain names without protocol
    if (!empty($url) && 
        !preg_match('/^(https?:\/\/|\/|\.\/|#)/', $url) && 
        preg_match('/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?(\.[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?)+/', $url)) {
        $url = 'https://' . $url;
    }
    
    $data = [
        'label' => $_POST['label'] ?? '',
        'url' => $url,
        'icon_type' => $_POST['icon_type'] ?? 'none',
        'icon_value' => $_POST['icon_value'] ?? '',
        'target' => $_POST['target'] ?? '_self'
    ];

    $iconFile = $_FILES['icon_file'] ?? null;

    if ($action === 'update' && $id) {
        $result = $manager->updateMenuItem($id, $data, $iconFile);
    } else {
        $result = $manager->addMenuItem($data, $iconFile);
    }

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
