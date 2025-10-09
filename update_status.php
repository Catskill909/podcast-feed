<?php
require_once __DIR__ . '/includes/PodcastManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    $status = $input['status'] ?? '';

    if (!$id || !in_array($status, ['active', 'inactive'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit;
    }

    $podcastManager = new PodcastManager();
    $result = $podcastManager->updateStatus($id, $status);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
