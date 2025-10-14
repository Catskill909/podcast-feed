<?php
/**
 * Test fetching actual feed data
 */

require_once __DIR__ . '/includes/RssFeedParser.php';

$feeds = [
    'Labor Radio' => 'https://feed.podbean.com/laborradiopodcastweekly/feed.xml',
    'Radio Catskill' => 'https://archive.wjffradio.org/xml/radiochatskil.xml'
];

$parser = new RssFeedParser();

foreach ($feeds as $name => $url) {
    echo "=== $name ===\n";
    echo "URL: $url\n\n";
    
    $result = $parser->fetchFeedMetadata($url);
    
    if ($result['success']) {
        echo "✓ Successfully fetched feed\n";
        echo "Latest Episode Date: " . ($result['latest_episode_date'] ?: 'NOT FOUND') . "\n";
        echo "Episode Count: " . $result['episode_count'] . "\n";
    } else {
        echo "✗ Failed: " . $result['error'] . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
}
