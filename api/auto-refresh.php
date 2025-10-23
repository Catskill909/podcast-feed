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
            // Use simple direct fetch (same as View Feed and manual refresh)
            $feedUrl = $podcast['feed_url'];
            $separator = (strpos($feedUrl, '?') === false) ? '?' : '&';
            $cacheBustUrl = $feedUrl . $separator . '_t=' . time();
            
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\n"
                ]
            ]);
            
            $xmlContent = @file_get_contents($cacheBustUrl, false, $context);
            
            if ($xmlContent === false) {
                $stats['errors']++;
                continue;
            }
            
            // Parse XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml === false) {
                $stats['errors']++;
                continue;
            }
            
            // Get latest episode date
            $latestEpisodeDate = null;
            $episodeCount = 0;
            
            if ($xml->channel && $xml->channel->item) {
                $episodeCount = count($xml->channel->item);
                $firstItem = $xml->channel->item[0];
                if ($firstItem->pubDate) {
                    $pubDate = (string)$firstItem->pubDate;
                    $timestamp = strtotime($pubDate);
                    if ($timestamp) {
                        $latestEpisodeDate = date('Y-m-d H:i:s', $timestamp);
                    }
                }
            }
            
            $result = [
                'success' => true,
                'latest_episode_date' => $latestEpisodeDate,
                'episode_count' => $episodeCount
            ];
            
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
