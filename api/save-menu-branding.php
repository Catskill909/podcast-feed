<?php

/**
 * Save Menu Branding API
 * Handles saving site title and logo configuration
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/MenuManager.php';

try {
    $manager = new MenuManager();

    $data = [
        'site_title' => $_POST['site_title'] ?? '',
        'logo_type' => $_POST['logo_type'] ?? 'icon',
        'logo_icon' => $_POST['logo_icon'] ?? '',
        'logo_image' => $_POST['logo_image'] ?? ''
    ];

    $logoFile = $_FILES['logo_file'] ?? null;

    $result = $manager->saveBranding($data, $logoFile);

    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
