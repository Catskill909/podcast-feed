<?php
/**
 * Simplified Episode API - bypasses PodcastManager for testing
 */
header('Content-Type: application/json');

// Get feed URL directly from query parameter for testing
$feedUrl = $_GET['feed_url'] ?? '';

if (empty($feedUrl)) {
    echo json_encode(['success' => false, 'error' => 'feed_url parameter required']);
    exit;
}

try {
    ini_set('memory_limit', '256M');
    set_time_limit(60);
    
    // Fetch feed
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
    
    if ($httpCode !== 200 || empty($content)) {
        throw new Exception("Failed to fetch feed: HTTP $httpCode" . ($error ? " - $error" : ''));
    }
    
    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    
    if ($xml === false) {
        throw new Exception('Failed to parse XML');
    }
    
    $episodes = [];
    $maxEpisodes = 50;
    
    if ($xml->getName() === 'rss') {
        $channel = $xml->channel;
        $namespaces = $xml->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? null;
        
        foreach ($channel->item as $item) {
            if (count($episodes) >= $maxEpisodes) break;
            
            $itunes = $itunesNs ? $item->children($itunesNs) : null;
            $enclosure = $item->enclosure;
            $audioUrl = $enclosure ? (string)$enclosure['url'] : '';
            
            if (empty($audioUrl)) continue;
            
            $episodes[] = [
                'id' => 'ep_' . md5($audioUrl),
                'title' => (string)$item->title ?: 'Untitled',
                'description' => substr(strip_tags((string)$item->description), 0, 300),
                'pub_date' => (string)$item->pubDate,
                'audio_url' => $audioUrl,
                'duration' => $itunes ? (string)$itunes->duration : '',
                'image_url' => '',
                'episode_number' => count($episodes) + 1
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_episodes' => count($episodes),
            'episodes' => $episodes
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
