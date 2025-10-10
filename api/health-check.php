<?php
/**
 * Podcast Health Check API Endpoint
 * Performs health checks on podcast feeds
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/PodcastHealthChecker.php';
require_once __DIR__ . '/../includes/PodcastManager.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    exit;
}

// Get podcast ID from POST data (optional - if not provided, check all)
$podcastId = $_POST['podcast_id'] ?? null;

try {
    $podcastManager = new PodcastManager();
    $healthChecker = new PodcastHealthChecker();
    
    if ($podcastId) {
        // Check single podcast
        $podcast = $podcastManager->getPodcast($podcastId);
        
        if (!$podcast) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Podcast not found'
            ]);
            exit;
        }
        
        $result = $healthChecker->checkPodcastHealth($podcast);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
        
    } else {
        // Check all podcasts
        $podcasts = $podcastManager->getAllPodcasts(false); // Get all, including inactive
        
        $result = $healthChecker->checkAllPodcasts($podcasts);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Health check failed: ' . $e->getMessage()
    ]);
}
