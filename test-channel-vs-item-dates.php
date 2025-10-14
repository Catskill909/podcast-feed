<?php
/**
 * Test to see if we're parsing CHANNEL pubDate vs ITEM pubDate
 */

require_once __DIR__ . '/includes/RssFeedParser.php';

$feeds = [
    'Labor Radio' => 'https://feed.podbean.com/laborradiopodcastweekly/feed.xml',
    '3rd & Fairfax' => 'https://3rdandfairfax.libsyn.com/rss'
];

foreach ($feeds as $name => $url) {
    echo "=== $name ===\n";
    echo "URL: $url\n\n";
    
    // Fetch raw XML
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $xmlContent = curl_exec($ch);
    curl_close($ch);
    
    if (!$xmlContent) {
        echo "Failed to fetch\n\n";
        continue;
    }
    
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    
    if ($xml === false) {
        echo "Failed to parse XML\n\n";
        continue;
    }
    
    $channel = $xml->channel;
    
    // Get CHANNEL pubDate
    $channelPubDate = (string)$channel->pubDate;
    echo "CHANNEL <pubDate>: $channelPubDate\n";
    if ($channelPubDate) {
        echo "  Parsed: " . date('Y-m-d H:i:s', strtotime($channelPubDate)) . "\n";
    }
    
    echo "\n";
    
    // Get first 3 ITEM pubDates
    echo "ITEM <pubDate> tags (first 3 episodes):\n";
    $count = 0;
    foreach ($channel->item as $item) {
        if ($count >= 3) break;
        $itemPubDate = (string)$item->pubDate;
        $itemTitle = (string)$item->title;
        echo "  [$count] $itemTitle\n";
        echo "      pubDate: $itemPubDate\n";
        if ($itemPubDate) {
            echo "      Parsed: " . date('Y-m-d H:i:s', strtotime($itemPubDate)) . "\n";
        }
        $count++;
    }
    
    echo "\n";
    
    // Now use our parser
    $parser = new RssFeedParser();
    $result = $parser->fetchFeedMetadata($url);
    
    if ($result['success']) {
        echo "OUR PARSER extracted:\n";
        echo "  Latest Episode Date: " . $result['latest_episode_date'] . "\n";
    }
    
    echo "\n" . str_repeat("=", 80) . "\n\n";
}
