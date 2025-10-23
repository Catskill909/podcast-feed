<?php
/**
 * Browser-Based Auto-Refresh API
 * Triggered when users visit the site - no cron needed
 * Only refreshes if last refresh was > 5 minutes ago
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

try {
    $lastRefreshFile = __DIR__ . '/../data/last-auto-refresh.txt';
    $refreshInterval = 300; // 5 minutes in seconds (changed from 1800/30min)
    
    // Check if we need to refresh
    $shouldRefresh = true;
    if (file_exists($lastRefreshFile)) {
        $lastRefresh = (int)file_get_contents($lastRefreshFile);
        $timeSinceRefresh = time() - $lastRefresh;
        
        if ($timeSinceRefresh < $refreshInterval) {
            // Too soon, skip refresh
            echo json_encode([
                'success' => true,
                'refreshed' => false,
                'message' => 'Recent refresh found',
                'next_refresh_in' => $refreshInterval - $timeSinceRefresh
            ]);
            exit;
        }
    }
    
    // Update timestamp immediately to prevent concurrent refreshes
    file_put_contents($lastRefreshFile, time());
    
    // Get all podcasts
    $podcastManager = new PodcastManager();
    $podcasts = $podcastManager->getAllPodcasts();
    $parser = new RssFeedParser();
    
    $stats = [
        'total' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    
    // Only process external feeds (skip localhost/self-hosted)
    foreach ($podcasts as $podcast) {
        // Skip self-hosted feeds
        if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i', $podcast['feed_url'])) {
            continue;
        }
        
        $stats['total']++;
        
        try {
            $result = $parser->fetchFeedMetadata($podcast['feed_url']);
            
            if ($result['success']) {
                // Check if data changed
                $hasChanges = false;
                
                if (isset($result['latest_episode_date']) && 
                    $result['latest_episode_date'] !== $podcast['latest_episode_date']) {
                    $hasChanges = true;
                }
                
                if (isset($result['episode_count']) && 
                    $result['episode_count'] != $podcast['episode_count']) {
                    $hasChanges = true;
                }
                
                if ($hasChanges) {
                    $updateData = [
                        'latest_episode_date' => $result['latest_episode_date'] ?? '',
                        'episode_count' => $result['episode_count'] ?? '0'
                    ];
                    
                    $updateResult = $podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
                    
                    if ($updateResult['success']) {
                        $stats['updated']++;
                    } else {
                        $stats['errors']++;
                    }
                } else {
                    $stats['skipped']++;
                }
            } else {
                $stats['errors']++;
            }
        } catch (Exception $e) {
            $stats['errors']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'refreshed' => true,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
