<?php
/**
 * Refresh All Feeds Metadata API
 * Batch updates latest episode dates for all podcasts
 */

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

// CRITICAL: Set these AFTER includes to override config.php settings
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $podcastManager = new PodcastManager();
    $podcasts = $podcastManager->getAllPodcasts();
    
    $parser = new RssFeedParser();
    $results = [
        'total' => count($podcasts),
        'updated' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    foreach ($podcasts as $podcast) {
        try {
            // Fetch feed metadata
            $result = $parser->fetchFeedMetadata($podcast['feed_url']);
            
            if ($result['success']) {
                // Update podcast with new metadata
                $updateData = [
                    'latest_episode_date' => $result['latest_episode_date'] ?? '',
                    'episode_count' => $result['episode_count'] ?? '0'
                ];
                
                $updateResult = $podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
                
                if ($updateResult['success']) {
                    $results['updated']++;
                    $results['details'][] = [
                        'id' => $podcast['id'],
                        'title' => $podcast['title'],
                        'status' => 'success',
                        'latest_episode_date' => $result['latest_episode_date'],
                        'episode_count' => $result['episode_count']
                    ];
                } else {
                    $results['failed']++;
                    $results['details'][] = [
                        'id' => $podcast['id'],
                        'title' => $podcast['title'],
                        'status' => 'failed',
                        'error' => 'Update failed'
                    ];
                }
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'id' => $podcast['id'],
                    'title' => $podcast['title'],
                    'status' => 'failed',
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'id' => $podcast['id'],
                'title' => $podcast['title'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
