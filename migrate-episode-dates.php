<?php
/**
 * One-time migration script to populate episode dates in production
 * Run this once in production to fix the "Unknown" episode dates
 */

require_once __DIR__ . '/includes/PodcastManager.php';
require_once __DIR__ . '/includes/RssFeedParser.php';

echo "=== Episode Date Migration Script ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $podcastManager = new PodcastManager();
    $parser = new RssFeedParser();
    
    $podcasts = $podcastManager->getAllPodcasts();
    $total = count($podcasts);
    $updated = 0;
    $failed = 0;
    
    echo "Found {$total} podcasts to process\n\n";
    
    foreach ($podcasts as $index => $podcast) {
        $num = $index + 1;
        echo "[{$num}/{$total}] Processing: {$podcast['title']}\n";
        
        // Fetch feed metadata
        $result = $parser->fetchFeedMetadata($podcast['feed_url']);
        
        if ($result['success']) {
            // Update podcast with episode data
            $updateData = [
                'latest_episode_date' => $result['latest_episode_date'] ?? '',
                'episode_count' => $result['episode_count'] ?? '0'
            ];
            
            $updateResult = $podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
            
            if ($updateResult['success']) {
                echo "  ✓ Updated: Latest episode = " . ($result['latest_episode_date'] ?: 'None') . "\n";
                echo "  ✓ Episode count = " . ($result['episode_count'] ?: '0') . "\n";
                $updated++;
            } else {
                echo "  ✗ Failed to update database\n";
                $failed++;
            }
        } else {
            echo "  ✗ Failed to fetch feed: " . ($result['error'] ?? 'Unknown error') . "\n";
            $failed++;
        }
        
        echo "\n";
        
        // Small delay to be nice to servers
        if ($num < $total) {
            sleep(2);
        }
    }
    
    echo "=== Migration Complete ===\n";
    echo "Total: {$total}\n";
    echo "Updated: {$updated}\n";
    echo "Failed: {$failed}\n";
    echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
