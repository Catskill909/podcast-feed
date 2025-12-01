<?php

/**
 * RSS Feed Endpoint
 * Generates XML feed for podcast directory
 * 
 * LAZY SCAN: Automatically updates stale feed data before serving
 * This ensures users always get reasonably fresh data even if cron fails
 */

require_once __DIR__ . '/includes/PodcastManager.php';
require_once __DIR__ . '/includes/SortPreferenceManager.php';
require_once __DIR__ . '/includes/FeedScanner.php';

// LAZY SCAN: Backup mechanism if cron fails
// Only triggers if data is >20 minutes old (cron runs every 15 min)
$scanner = new FeedScanner(); // 20-minute interval (default)
if ($scanner->needsScan()) {
    $scanner->scan();
}

// Set proper headers for XML output
header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Load saved sort preference as default
    $sortPrefManager = new SortPreferenceManager();
    $savedPreference = $sortPrefManager->getPreference();
    
    // Get sort parameters from URL (can override saved preference)
    $sortBy = $_GET['sort'] ?? $savedPreference['sort']; // Use saved preference as default
    $sortOrder = $_GET['order'] ?? $savedPreference['order']; // Use saved preference as default
    
    // Validate parameters
    $allowedSorts = ['episodes', 'date', 'title', 'status'];
    $allowedOrders = ['asc', 'desc'];
    
    if (!in_array($sortBy, $allowedSorts)) {
        $sortBy = 'episodes';
    }
    if (!in_array($sortOrder, $allowedOrders)) {
        $sortOrder = 'desc';
    }
    
    $podcastManager = new PodcastManager();
    $rssXml = $podcastManager->getRSSFeed($sortBy, $sortOrder);
    
    // Add debug comment to XML
    $debugComment = "<!-- Sorted by: $sortBy, Order: $sortOrder, Generated: " . date('Y-m-d H:i:s') . " -->\n";
    $rssXml = str_replace('<rss ', $debugComment . '<rss ', $rssXml);

    if ($rssXml === false) {
        // Return error in RSS format
        http_response_code(500);
        echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Podcast Directory Error</title>
        <description>Error generating podcast feed</description>
        <item>
            <title>Feed Generation Error</title>
            <description>Unable to generate podcast directory feed at this time</description>
        </item>
    </channel>
</rss>';
    } else {
        echo $rssXml;
    }
} catch (Exception $e) {
    // Log error and return error RSS
    error_log('RSS Feed Error: ' . $e->getMessage());

    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
    <channel>
        <title>Podcast Directory Error</title>
        <description>Error generating podcast feed</description>
        <item>
            <title>System Error</title>
            <description>A system error occurred while generating the feed</description>
        </item>
    </channel>
</rss>';
}
