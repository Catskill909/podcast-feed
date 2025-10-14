<?php
/**
 * Debug script to check episode dates in database
 */

require_once __DIR__ . '/includes/PodcastManager.php';

$manager = new PodcastManager();
$podcasts = $manager->getAllPodcasts();

echo "=== EPISODE DATE DEBUG ===\n\n";
echo "Current server time: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($podcasts as $podcast) {
    echo "Podcast: " . $podcast['title'] . "\n";
    echo "  Feed URL: " . $podcast['feed_url'] . "\n";
    echo "  Created Date: " . $podcast['created_date'] . "\n";
    echo "  Updated Date: " . $podcast['updated_date'] . "\n";
    echo "  Latest Episode Date: " . ($podcast['latest_episode_date'] ?: 'NOT SET') . "\n";
    echo "  Episode Count: " . $podcast['episode_count'] . "\n";
    
    if (!empty($podcast['latest_episode_date'])) {
        $epDate = strtotime($podcast['latest_episode_date']);
        $now = time();
        
        // NEW: Calendar-day comparison
        $epDay = strtotime(date('Y-m-d', $epDate));
        $today = strtotime(date('Y-m-d', $now));
        $daysDiff = (int)floor(($today - $epDay) / 86400);
        
        echo "  Calendar days difference: $daysDiff\n";
        echo "  Display as: ";
        
        if ($daysDiff < 0) {
            echo "Today\n";
        } elseif ($daysDiff == 0) {
            echo "Today\n";
        } elseif ($daysDiff == 1) {
            echo "Yesterday\n";
        } elseif ($daysDiff < 7) {
            echo "$daysDiff days ago\n";
        } else {
            echo date('M j, Y', $epDate) . "\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}
