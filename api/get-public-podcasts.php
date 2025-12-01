<?php
/**
 * Get Public Podcasts API
 * Returns only active podcasts for public browsing
 * 
 * Data freshness is handled by:
 * - Cron job (every 15 min) - primary
 * - Browser auto-refresh (every 5 min on page visit) - backup
 * - feed.php lazy scan (every 20 min) - emergency fallback
 * 
 * This endpoint just serves cached data for fast page loads.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/PodcastManager.php';

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
