<?php
/**
 * Get Podcast Preview Data
 * Fetches RSS feed metadata for preview modal
 */

// Prevent HTML error output
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

try {
    // Get podcast ID
    $podcastId = $_GET['id'] ?? '';
    
    if (empty($podcastId)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Podcast ID is required'
        ]);
        exit;
    }
    
    // Get podcast from database
    $podcastManager = new PodcastManager();
    $podcast = $podcastManager->getPodcast($podcastId);
    
    if (!$podcast) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Podcast not found'
        ]);
        exit;
    }
    
    // Try to fetch RSS data for fresh information
    $parser = new RssFeedParser();
    $feedData = $parser->fetchAndParse($podcast['feed_url']); // ← FIXED: Correct method name
    
    if ($feedData['success']) {
        // Use RSS data (fresher information) but always use local stored image
        $response = [
            'success' => true,
            'data' => [
                'id' => $podcast['id'],
                'title' => $feedData['data']['title'],
                'description' => $feedData['data']['description'],
                'image_url' => $podcast['image_info']['url'] ?? null, // Always use local stored image
                'image_width' => $podcast['image_info']['width'] ?? null,
                'image_height' => $podcast['image_info']['height'] ?? null,
                'episode_count' => $feedData['data']['episode_count'],
                'latest_episode_date' => $feedData['data']['latest_episode_date'],
                'category' => $feedData['data']['category'] ?? 'Uncategorized',
                'feed_url' => $podcast['feed_url'],
                'feed_type' => $feedData['data']['feed_type'],
                'status' => $podcast['status'],
                'created_date' => $podcast['created_date']
            ]
        ];
    } else {
        // Fallback to database data if RSS fetch fails
        $response = [
            'success' => true,
            'data' => [
                'id' => $podcast['id'],
                'title' => $podcast['title'],
                'description' => $podcast['description'] ?? 'No description available',
                'image_url' => $podcast['image_info']['url'] ?? null,
                'image_width' => $podcast['image_info']['width'] ?? null,
                'image_height' => $podcast['image_info']['height'] ?? null,
                'episode_count' => $podcast['episode_count'] ?? 0,
                'latest_episode_date' => $podcast['latest_episode_date'] ?? null,
                'category' => 'Unknown',
                'feed_url' => $podcast['feed_url'],
                'feed_type' => 'RSS',
                'status' => $podcast['status'],
                'created_date' => $podcast['created_date']
            ]
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
