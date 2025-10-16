<?php
// Direct test of feed parsing
header('Content-Type: text/plain');

$feedUrl = $_GET['url'] ?? 'https://feed.podbean.com/laborradiopodcastweekly/feed.xml';

echo "Testing feed: $feedUrl\n\n";

// Fetch
echo "Fetching...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $feedUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PodFeed/1.0)',
    CURLOPT_SSL_VERIFYPEER => false,
]);

$content = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Error: " . ($error ?: 'none') . "\n";
echo "Content length: " . strlen($content) . " bytes\n\n";

if ($httpCode === 200 && !empty($content)) {
    echo "Parsing XML...\n";
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        echo "XML Parse Error!\n";
        foreach (libxml_get_errors() as $error) {
            echo "  - " . $error->message . "\n";
        }
    } else {
        echo "XML parsed successfully!\n";
        echo "Root element: " . $xml->getName() . "\n";
        
        if ($xml->getName() === 'rss') {
            $itemCount = count($xml->channel->item);
            echo "Items found: $itemCount\n";
            
            if ($itemCount > 0) {
                echo "\nFirst item:\n";
                $item = $xml->channel->item[0];
                echo "  Title: " . (string)$item->title . "\n";
                echo "  PubDate: " . (string)$item->pubDate . "\n";
                echo "  Has enclosure: " . (isset($item->enclosure) ? 'yes' : 'no') . "\n";
                if (isset($item->enclosure)) {
                    echo "  Enclosure URL: " . (string)$item->enclosure['url'] . "\n";
                }
            }
        }
    }
} else {
    echo "Failed to fetch feed!\n";
}
