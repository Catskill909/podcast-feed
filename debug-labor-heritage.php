<?php
/**
 * Debug Labor Heritage Power Hour date parsing
 */

require_once __DIR__ . '/includes/RssFeedParser.php';
require_once __DIR__ . '/includes/PodcastManager.php';

echo "<h1>Labor Heritage Power Hour - Date Debug</h1>";

// 1. Check what's in the XML
$manager = new PodcastManager();
$podcasts = $manager->getAllPodcasts();

$laborHeritage = null;
foreach ($podcasts as $p) {
    if (stripos($p['title'], 'Labor Heritage') !== false) {
        $laborHeritage = $p;
        break;
    }
}

if ($laborHeritage) {
    echo "<h2>Current XML Data:</h2>";
    echo "<pre>";
    echo "ID: " . $laborHeritage['id'] . "\n";
    echo "Title: " . $laborHeritage['title'] . "\n";
    echo "Latest Episode Date (stored): " . ($laborHeritage['latest_episode_date'] ?? 'NULL') . "\n";
    echo "Episode Count: " . ($laborHeritage['episode_count'] ?? 'NULL') . "\n";
    echo "</pre>";
    
    // 2. Fetch fresh from RSS
    echo "<h2>Fresh RSS Feed Parse:</h2>";
    $parser = new RssFeedParser();
    $parser->clearCache($laborHeritage['feed_url']);
    $result = $parser->fetchFeedMetadata($laborHeritage['feed_url']);
    
    echo "<pre>";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    if ($result['success']) {
        echo "Latest Episode Date (parsed): " . ($result['latest_episode_date'] ?? 'NULL') . "\n";
        echo "Episode Count: " . ($result['episode_count'] ?? 'NULL') . "\n";
        
        // Show timestamp conversion
        if (!empty($result['latest_episode_date'])) {
            $timestamp = strtotime($result['latest_episode_date']);
            echo "\nTimestamp: $timestamp\n";
            echo "Human readable: " . date('Y-m-d H:i:s', $timestamp) . "\n";
            echo "Formatted: " . date('M j, Y', $timestamp) . "\n";
            
            // Calculate days ago
            $now = time();
            $diff = $now - $timestamp;
            $daysAgo = floor($diff / 86400);
            echo "Days ago: $daysAgo\n";
        }
    } else {
        echo "Error: " . ($result['error'] ?? 'Unknown') . "\n";
    }
    echo "</pre>";
    
} else {
    echo "<p style='color: red;'>Labor Heritage Power Hour not found in podcasts.xml</p>";
}
