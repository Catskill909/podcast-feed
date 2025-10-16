<?php
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Test endpoint working',
    'podcast_id' => $_GET['podcast_id'] ?? 'none'
]);
