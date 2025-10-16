<?php
/**
 * Get Podcast Episodes API
 * Fetches and parses all episodes from a podcast's RSS feed
 */

// Prevent any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

try {
    // Get podcast ID from query parameter
    $podcastId = $_GET['podcast_id'] ?? '';
    
    if (empty($podcastId)) {
        throw new Exception('Podcast ID is required');
    }
    
    // Get podcast from database
    $podcastManager = new PodcastManager();
    $podcast = $podcastManager->getPodcast($podcastId);
    
    if (!$podcast) {
        throw new Exception('Podcast not found');
    }
    
    // Fetch and parse RSS feed
    $feedUrl = $podcast['feed_url'];
    
    if (empty($feedUrl)) {
        throw new Exception('Feed URL is empty');
    }
    
    $episodes = parseEpisodes($feedUrl);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => [
            'podcast_id' => $podcastId,
            'podcast_title' => $podcast['title'],
            'total_episodes' => count($episodes),
            'episodes' => $episodes
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    exit;
}

/**
 * Parse episodes from RSS feed
 */
function parseEpisodes($feedUrl) {
    // Increase memory limit and execution time for large feeds
    ini_set('memory_limit', '256M');
    set_time_limit(60);
    
    // Fetch feed content
    $xmlContent = fetchFeedContent($feedUrl);
    
    if (!$xmlContent) {
        throw new Exception('Unable to fetch feed');
    }
    
    // Parse XML
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlContent);
    
    if ($xml === false) {
        $errors = libxml_get_errors();
        $errorMsg = !empty($errors) ? $errors[0]->message : 'Unknown XML error';
        throw new Exception('Unable to parse feed XML: ' . trim($errorMsg));
    }
    
    $episodes = [];
    $maxEpisodes = 50; // Limit to 50 most recent episodes for performance
    
    // Detect feed type
    $rootName = $xml->getName();
    
    if ($rootName === 'rss') {
        // RSS 2.0 feed
        $channel = $xml->channel;
        $namespaces = $xml->getNamespaces(true);
        $itunesNs = $namespaces['itunes'] ?? null;
        
        $episodeNumber = 1;
        foreach ($channel->item as $item) {
            // Stop if we've reached the max episodes
            if (count($episodes) >= $maxEpisodes) {
                break;
            }
            
            $itunes = $itunesNs ? $item->children($itunesNs) : null;
            
            // Extract enclosure (audio file)
            $enclosure = $item->enclosure;
            $audioUrl = $enclosure ? (string)$enclosure['url'] : '';
            
            // Skip if no audio URL
            if (empty($audioUrl)) {
                continue;
            }
            
            // Extract episode data
            $episode = [
                'id' => 'ep_' . md5($audioUrl . $episodeNumber),
                'title' => (string)$item->title ?: 'Untitled Episode',
                'description' => strip_tags((string)($item->description ?? $item->summary ?? '')),
                'pub_date' => (string)$item->pubDate,
                'audio_url' => $audioUrl,
                'duration' => $itunes ? (string)$itunes->duration : '',
                'image_url' => extractEpisodeImage($item, $itunes),
                'file_size' => $enclosure ? formatBytes((int)$enclosure['length']) : '',
                'episode_number' => $episodeNumber
            ];
            
            $episodeNumber++;
            
            // Format duration if needed
            if ($episode['duration']) {
                $episode['duration'] = formatDuration($episode['duration']);
            }
            
            // Limit description length
            if (strlen($episode['description']) > 300) {
                $episode['description'] = substr($episode['description'], 0, 300) . '...';
            }
            
            $episodes[] = $episode;
        }
        
    } elseif ($rootName === 'feed') {
        // Atom feed
        $namespaces = $xml->getNamespaces(true);
        
        $episodeNumber = 1;
        foreach ($xml->entry as $entry) {
            // Stop if we've reached the max episodes
            if (count($episodes) >= $maxEpisodes) {
                break;
            }
            
            // Find audio enclosure
            $audioUrl = '';
            foreach ($entry->link as $link) {
                if (isset($link['rel']) && (string)$link['rel'] === 'enclosure') {
                    $audioUrl = (string)$link['href'];
                    break;
                }
            }
            
            if (empty($audioUrl)) {
                continue;
            }
            
            $episode = [
                'id' => 'ep_' . md5($audioUrl . $episodeNumber),
                'title' => (string)$entry->title ?: 'Untitled Episode',
                'description' => strip_tags((string)($entry->summary ?? $entry->content ?? '')),
                'pub_date' => (string)$entry->published ?? (string)$entry->updated,
                'audio_url' => $audioUrl,
                'duration' => '',
                'image_url' => '',
                'file_size' => '',
                'episode_number' => $episodeNumber
            ];
            
            $episodeNumber++;
            
            if (strlen($episode['description']) > 300) {
                $episode['description'] = substr($episode['description'], 0, 300) . '...';
            }
            
            $episodes[] = $episode;
        }
    }
    
    return $episodes;
}

/**
 * Fetch feed content using cURL
 */
function fetchFeedContent($url) {
    $ch = curl_init();
    
    if ($ch === false) {
        throw new Exception('Failed to initialize cURL');
    }
    
    $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 30, // Increased timeout
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PodFeed/1.0)',
        CURLOPT_SSL_VERIFYPEER => $sslVerify,
        CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => [
            'Accept: application/rss+xml, application/xml, text/xml, */*'
        ],
    ]);
    
    $content = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errno = curl_errno($ch);
    
    curl_close($ch);
    
    // Check for cURL errors
    if ($errno !== 0) {
        throw new Exception("Failed to fetch feed: $error (Error $errno)");
    }
    
    // Check HTTP status
    if ($httpCode !== 200) {
        throw new Exception("Feed returned HTTP $httpCode");
    }
    
    // Check if content is empty
    if (empty($content)) {
        throw new Exception('Feed returned empty content');
    }
    
    return $content;
}

/**
 * Extract episode image from item
 */
function extractEpisodeImage($item, $itunes) {
    // Try iTunes image first
    if ($itunes && isset($itunes->image)) {
        $imageUrl = (string)$itunes->image['href'];
        if (!empty($imageUrl)) {
            return $imageUrl;
        }
    }
    
    // Try media:thumbnail or media:content
    $namespaces = $item->getNamespaces(true);
    if (isset($namespaces['media'])) {
        $media = $item->children($namespaces['media']);
        
        if (isset($media->thumbnail)) {
            return (string)$media->thumbnail['url'];
        }
        
        if (isset($media->content)) {
            foreach ($media->content as $content) {
                if (isset($content['medium']) && (string)$content['medium'] === 'image') {
                    return (string)$content['url'];
                }
            }
        }
    }
    
    // Try enclosure with image type
    foreach ($item->enclosure as $enclosure) {
        $type = (string)$enclosure['type'];
        if (strpos($type, 'image/') === 0) {
            return (string)$enclosure['url'];
        }
    }
    
    return '';
}

/**
 * Format duration from seconds or HH:MM:SS to readable format
 */
function formatDuration($duration) {
    // If already formatted (HH:MM:SS or MM:SS), return as is
    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $duration)) {
        return $duration;
    }
    
    // If it's just seconds
    if (is_numeric($duration)) {
        $seconds = (int)$duration;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        } else {
            return sprintf('%d:%02d', $minutes, $secs);
        }
    }
    
    return $duration;
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}
