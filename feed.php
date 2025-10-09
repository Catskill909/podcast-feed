<?php

/**
 * RSS Feed Endpoint
 * Generates XML feed for podcast directory
 */

require_once __DIR__ . '/includes/PodcastManager.php';

// Set proper headers for XML output
header('Content-Type: application/rss+xml; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT'); // Cache for 1 hour

try {
    $podcastManager = new PodcastManager();
    $rssXml = $podcastManager->getRSSFeed();

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
