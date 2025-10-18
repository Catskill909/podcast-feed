<?php
/**
 * AJAX Audio Upload Handler
 * Handles large file uploads via chunked upload
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AudioUploader.php';

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get podcast and episode IDs
    $podcastId = $_POST['podcast_id'] ?? '';
    $episodeId = $_POST['episode_id'] ?? '';
    
    if (empty($podcastId) || empty($episodeId)) {
        throw new Exception('Missing podcast or episode ID');
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error: ' . ($_FILES['audio_file']['error'] ?? 'unknown'));
    }
    
    // Upload the audio file
    $audioUploader = new AudioUploader();
    $result = $audioUploader->uploadAudio($_FILES['audio_file'], $podcastId, $episodeId);
    
    if (!$result['success']) {
        throw new Exception($result['message']);
    }
    
    // Return success with file info
    echo json_encode([
        'success' => true,
        'url' => $result['url'],
        'duration' => $result['duration'],
        'file_size' => $result['file_size'],
        'filename' => $result['filename']
    ]);
    
} catch (Exception $e) {
    error_log("Audio upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
