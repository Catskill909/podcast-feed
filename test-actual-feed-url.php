<?php
/**
 * Test the actual feed URL stored in database for Radio Catskill
 */

require_once __DIR__ . '/includes/PodcastManager.php';
require_once __DIR__ . '/includes/RssFeedParser.php';

$manager = new PodcastManager();
$podcasts = $manager->getAllPodcasts();

// Find Radio Catskill
$radioCatskill = null;
foreach ($podcasts as $podcast) {
    if (strpos($podcast['title'], 'Radio Chatskill') !== false) {
        $radioCatskill = $podcast;
        break;
    }
}

if (!$radioCatskill) {
    die("Radio Catskill not found in database\n");
}

echo "=== Radio Catskill Feed Test ===\n\n";
echo "Feed URL in database: " . $radioCatskill['feed_url'] . "\n";
echo "Latest Episode Date in DB: " . $radioCatskill['latest_episode_date'] . "\n";
echo "Updated Date in DB: " . $radioCatskill['updated_date'] . "\n\n";

echo "Fetching from actual feed URL...\n";
$parser = new RssFeedParser();
$result = $parser->fetchFeedMetadata($radioCatskill['feed_url']);

if ($result['success']) {
    echo "✓ Successfully fetched\n";
    echo "Latest Episode Date from feed: " . ($result['latest_episode_date'] ?: 'NOT FOUND') . "\n";
    echo "Episode Count from feed: " . $result['episode_count'] . "\n\n";
    
    if ($result['latest_episode_date'] !== $radioCatskill['latest_episode_date']) {
        echo "⚠️  MISMATCH DETECTED!\n";
        echo "Database has: " . $radioCatskill['latest_episode_date'] . "\n";
        echo "Feed has: " . $result['latest_episode_date'] . "\n";
        echo "\nDifference: Database is OUTDATED\n";
    } else {
        echo "✓ Dates match - database is up to date\n";
    }
} else {
    echo "✗ Failed to fetch: " . $result['error'] . "\n";
}
