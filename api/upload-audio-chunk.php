<?php
/**
 * AJAX Audio Upload Handler
 * Handles large file uploads via chunked upload
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/AudioUploader.php';

// Log everything for debugging
error_log("=== AJAX AUDIO UPLOAD STARTED ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("ERROR: Method not allowed");
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
    $response = [
        'success' => true,
        'url' => $result['url'],
        'duration' => $result['duration'],
        'file_size' => $result['file_size'],
        'filename' => $result['filename']
    ];
    
    error_log("SUCCESS: Returning response: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("ERROR: Audio upload failed: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
