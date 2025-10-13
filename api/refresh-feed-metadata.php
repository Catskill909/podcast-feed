<?php
/**
 * Refresh Feed Metadata API
 * Fetches latest episode date and episode count from podcast feed
 */

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $podcastId = $_POST['podcast_id'] ?? '';
    
    if (empty($podcastId)) {
        throw new Exception('Podcast ID is required');
    }
    
    $podcastManager = new PodcastManager();
    $podcast = $podcastManager->getPodcast($podcastId);
    
    if (!$podcast) {
        throw new Exception('Podcast not found');
    }
    
    // Fetch feed metadata
    $parser = new RssFeedParser();
    $result = $parser->fetchFeedMetadata($podcast['feed_url']);
    
    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'Failed to fetch feed metadata');
    }
    
    // Update podcast with new metadata
    $updateData = [
        'latest_episode_date' => $result['latest_episode_date'] ?? '',
        'episode_count' => $result['episode_count'] ?? '0'
    ];
    
    $updateResult = $podcastManager->updatePodcastMetadata($podcastId, $updateData);
    
    if (!$updateResult['success']) {
        throw new Exception($updateResult['message'] ?? 'Failed to update podcast');
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'latest_episode_date' => $result['latest_episode_date'],
            'episode_count' => $result['episode_count'],
            'latest_episode_date_formatted' => $result['latest_episode_date'] ? 
                date('M j, Y', strtotime($result['latest_episode_date'])) : 'Unknown'
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
