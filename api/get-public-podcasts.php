<?php
/**
 * Get Public Podcasts API
 * Returns only active podcasts for public browsing
 * 
 * LAZY SCAN: Automatically updates stale feed data before serving
 * This ensures users always get reasonably fresh data even if cron fails
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/FeedScanner.php';

// LAZY SCAN: Backup mechanism if cron fails
// Only triggers if data is >20 minutes old (cron runs every 15 min)
$scanner = new FeedScanner(); // 20-minute interval (default)
if ($scanner->needsScan()) {
    $scanner->scan();
}

try {
    $podcastManager = new PodcastManager();
    
    // Get all podcasts with metadata
    $allPodcasts = $podcastManager->getAllPodcasts(true);
    
    // Filter to only active podcasts
    $activePodcasts = array_filter($allPodcasts, function($podcast) {
        return $podcast['status'] === 'active';
    });
    
    // Re-index array after filtering
    $activePodcasts = array_values($activePodcasts);
    
    // Format response for public consumption
    $publicPodcasts = array_map(function($podcast) {
        return [
            'id' => $podcast['id'],
            'title' => $podcast['title'],
            'description' => $podcast['description'] ?? '',
            'feed_url' => $podcast['feed_url'],
            'episode_count' => (int)($podcast['episode_count'] ?? 0),
            'latest_episode_date' => $podcast['latest_episode_date'] ?? null,
            'cover_url' => $podcast['image_info']['url'] ?? null,
            'has_cover' => !empty($podcast['cover_image'])
        ];
    }, $activePodcasts);
    
    echo json_encode([
        'success' => true,
        'count' => count($publicPodcasts),
        'podcasts' => $publicPodcasts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch podcasts',
        'message' => $e->getMessage()
    ]);
}
