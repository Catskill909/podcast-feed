<?php
require_once __DIR__ . '/../config/config.php';

/**
 * RssFeedParser Class
 * Handles fetching and parsing RSS/Atom podcast feeds
 * Extracts: title, description, image, episode count
 */
class RssFeedParser
{
    private $timeout = 10; // seconds
    private $userAgent = 'PodFeed Builder/1.0';
    
    /**
     * Fetch and parse RSS feed from URL
     * @param string $url RSS feed URL
     * @return array Result with success status and data/error
     */
    public function fetchAndParse($url)
    {
        try {
            // Validate URL format
            if (!$this->isValidUrl($url)) {
                return [
                    'success' => false,
                    'error' => 'Invalid URL format'
                ];
            }
            
            // Fetch feed content
            $xmlContent = $this->fetchFeedContent($url);
            if (!$xmlContent) {
                return [
                    'success' => false,
                    'error' => 'Unable to fetch feed. Please check the URL and try again.'
                ];
            }
            
            // Parse XML
            $parsedData = $this->parseXmlContent($xmlContent, $url);
            
            if (!$parsedData) {
                return [
                    'success' => false,
                    'error' => 'Unable to parse feed. Invalid RSS/Atom format.'
                ];
            }
            
            return [
                'success' => true,
                'data' => $parsedData
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate URL format
     */
    private function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Fetch feed content using cURL
     */
    private function fetchFeedContent($url)
    {
        $ch = curl_init();
        
        // Enable SSL verification in production, disable in development
        $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_ENCODING => '', // Accept all encodings
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error || $httpCode !== 200) {
            error_log("RSS Fetch Error: HTTP $httpCode - $error");
            return false;
        }
        
        return $content;
    }
    
    /**
     * Parse XML content and extract podcast data
     */
    private function parseXmlContent($xmlContent, $feedUrl)
    {
        // Suppress XML parsing warnings
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            error_log("XML Parse Error: " . print_r($errors, true));
            libxml_clear_errors();
            return false;
        }
        
        // Detect feed type (RSS 2.0, Atom, etc.)
        $feedType = $this->detectFeedType($xml);
        
        // Extract data based on feed type
        switch ($feedType) {
            case 'rss':
                return $this->parseRssFeed($xml, $feedUrl);
            case 'atom':
                return $this->parseAtomFeed($xml, $feedUrl);
            default:
                return false;
        }
    }
    
    /**
     * Detect feed type
     */
    private function detectFeedType($xml)
    {
        $rootName = $xml->getName();
        
        if ($rootName === 'rss') {
            return 'rss';
        } elseif ($rootName === 'feed') {
            return 'atom';
        }
        
        return 'unknown';
    }
    
    /**
     * Parse RSS 2.0 feed
     */
    private function parseRssFeed($xml, $feedUrl)
    {
        $channel = $xml->channel;
        
        if (!$channel) {
            return false;
        }
        
        // Register iTunes namespace
        $namespaces = $xml->getNamespaces(true);
        $itunes = isset($namespaces['itunes']) ? $channel->children($namespaces['itunes']) : null;
        
        // Extract title (required)
        $title = $this->extractText($channel->title);
        if (empty($title)) {
            return false;
        }
        
        // Extract description
        $description = $this->extractText($channel->description);
        if (empty($description) && $itunes) {
            $description = $this->extractText($itunes->summary);
        }
        
        // Extract image
        $imageUrl = $this->extractRssImage($channel, $itunes);
        
        // Count episodes
        $episodeCount = count($channel->item);
        
        // Get latest episode date
        $latestEpisodeDate = $this->getLatestEpisodeDate($channel->item);
        
        return [
            'title' => $title,
            'description' => $description,
            'image_url' => $imageUrl,
            'episode_count' => $episodeCount,
            'latest_episode_date' => $latestEpisodeDate,
            'feed_url' => $feedUrl,
            'feed_type' => 'RSS 2.0'
        ];
    }
    
    /**
     * Parse Atom feed
     */
    private function parseAtomFeed($xml, $feedUrl)
    {
        // Extract title (required)
        $title = $this->extractText($xml->title);
        if (empty($title)) {
            return false;
        }
        
        // Extract description/subtitle
        $description = $this->extractText($xml->subtitle);
        if (empty($description)) {
            $description = $this->extractText($xml->summary);
        }
        
        // Extract image (Atom uses logo or icon)
        $imageUrl = $this->extractText($xml->logo);
        if (empty($imageUrl)) {
            $imageUrl = $this->extractText($xml->icon);
        }
        
        // Count entries
        $episodeCount = count($xml->entry);
        
        // Get latest episode date
        $latestEpisodeDate = $this->getLatestEpisodeDateAtom($xml->entry);
        
        return [
            'title' => $title,
            'description' => $description,
            'image_url' => $imageUrl,
            'episode_count' => $episodeCount,
            'latest_episode_date' => $latestEpisodeDate,
            'feed_url' => $feedUrl,
            'feed_type' => 'Atom'
        ];
    }
    
    /**
     * Extract image URL from RSS feed
     */
    private function extractRssImage($channel, $itunes)
    {
        // Try iTunes image first (usually better quality)
        if ($itunes && isset($itunes->image)) {
            $itunesImage = (string) $itunes->image->attributes()->href;
            if (!empty($itunesImage)) {
                return $itunesImage;
            }
        }
        
        // Try standard RSS image
        if (isset($channel->image->url)) {
            $imageUrl = $this->extractText($channel->image->url);
            if (!empty($imageUrl)) {
                return $imageUrl;
            }
        }
        
        // Try media:thumbnail or media:content
        $namespaces = $channel->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $media = $channel->children($namespaces['media']);
            if (isset($media->thumbnail)) {
                $thumbUrl = (string) $media->thumbnail->attributes()->url;
                if (!empty($thumbUrl)) {
                    return $thumbUrl;
                }
            }
        }
        
        return '';
    }
    
    /**
     * Extract text content safely
     */
    private function extractText($element)
    {
        if (!$element) {
            return '';
        }
        
        $text = (string) $element;
        return trim(strip_tags($text));
    }
    
    /**
     * Get latest episode date from RSS items
     */
    private function getLatestEpisodeDate($items)
    {
        if (empty($items) || count($items) === 0) {
            return null;
        }
        
        $latestDate = null;
        $latestTimestamp = 0;
        
        foreach ($items as $item) {
            // Try pubDate first (RSS standard)
            $pubDate = $this->extractText($item->pubDate);
            
            // If no pubDate, try dc:date (Dublin Core)
            if (empty($pubDate)) {
                $namespaces = $item->getNamespaces(true);
                if (isset($namespaces['dc'])) {
                    $dc = $item->children($namespaces['dc']);
                    $pubDate = $this->extractText($dc->date);
                }
            }
            
            if (!empty($pubDate)) {
                $timestamp = strtotime($pubDate);
                if ($timestamp && $timestamp > $latestTimestamp) {
                    $latestTimestamp = $timestamp;
                    $latestDate = date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        
        return $latestDate;
    }
    
    /**
     * Get latest episode date from Atom entries
     */
    private function getLatestEpisodeDateAtom($entries)
    {
        if (empty($entries) || count($entries) === 0) {
            return null;
        }
        
        $latestDate = null;
        $latestTimestamp = 0;
        
        foreach ($entries as $entry) {
            // Try published first
            $published = $this->extractText($entry->published);
            
            // If no published, try updated
            if (empty($published)) {
                $published = $this->extractText($entry->updated);
            }
            
            if (!empty($published)) {
                $timestamp = strtotime($published);
                if ($timestamp && $timestamp > $latestTimestamp) {
                    $latestTimestamp = $timestamp;
                    $latestDate = date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        
        return $latestDate;
    }
    
    /**
     * Fetch only metadata from a feed (quick check for latest episode)
     * This is a lightweight version that only gets what we need for sorting
     */
    public function fetchFeedMetadata($url)
    {
        try {
            $xmlContent = $this->fetchFeedContent($url);
            if (!$xmlContent) {
                return [
                    'success' => false,
                    'error' => 'Unable to fetch feed'
                ];
            }
            
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml === false) {
                return [
                    'success' => false,
                    'error' => 'Invalid XML'
                ];
            }
            
            $feedType = $this->detectFeedType($xml);
            $latestEpisodeDate = null;
            $episodeCount = 0;
            
            if ($feedType === 'rss') {
                $channel = $xml->channel;
                if ($channel) {
                    $episodeCount = count($channel->item);
                    $latestEpisodeDate = $this->getLatestEpisodeDate($channel->item);
                }
            } elseif ($feedType === 'atom') {
                $episodeCount = count($xml->entry);
                $latestEpisodeDate = $this->getLatestEpisodeDateAtom($xml->entry);
            }
            
            return [
                'success' => true,
                'latest_episode_date' => $latestEpisodeDate,
                'episode_count' => $episodeCount
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Download cover image from URL and save locally
     * @param string $imageUrl Remote image URL
     * @param string $podcastId Podcast ID for filename
     * @return array Result with success status and filename/error
     */
    public function downloadCoverImage($imageUrl, $podcastId)
    {
        try {
            if (empty($imageUrl)) {
                return [
                    'success' => false,
                    'error' => 'No image URL provided'
                ];
            }
            
            // Validate image URL
            if (!$this->isValidUrl($imageUrl)) {
                return [
                    'success' => false,
                    'error' => 'Invalid image URL'
                ];
            }
            
            // Fetch image
            $imageData = $this->fetchImageData($imageUrl);
            if (!$imageData) {
                return [
                    'success' => false,
                    'error' => 'Unable to download image'
                ];
            }
            
            // Validate image
            $imageInfo = $this->validateImageData($imageData);
            if (!$imageInfo['valid']) {
                return [
                    'success' => false,
                    'error' => $imageInfo['error']
                ];
            }
            
            // Generate filename
            $extension = $imageInfo['extension'];
            $filename = $podcastId . '.' . $extension;
            $filepath = COVERS_DIR . '/' . $filename;
            
            // Ensure covers directory exists
            if (!is_dir(COVERS_DIR)) {
                mkdir(COVERS_DIR, 0755, true);
            }
            
            // Save image
            if (file_put_contents($filepath, $imageData) === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to save image'
                ];
            }
            
            return [
                'success' => true,
                'filename' => $filename
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error downloading image: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Fetch image data using cURL
     */
    private function fetchImageData($url)
    {
        $ch = curl_init();
        
        // Enable SSL verification in production, disable in development
        $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
        ]);
        
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return $data;
    }
    
    /**
     * Validate image data
     */
    private function validateImageData($imageData)
    {
        // Check if data is valid image
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);
        
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        if (!isset($allowedTypes[$mimeType])) {
            return [
                'valid' => false,
                'error' => 'Invalid image format. Allowed: JPG, PNG, GIF, WebP'
            ];
        }
        
        // Check file size (max 5MB)
        $size = strlen($imageData);
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if ($size > $maxSize) {
            return [
                'valid' => false,
                'error' => 'Image too large. Maximum size: 5MB'
            ];
        }
        
        return [
            'valid' => true,
            'extension' => $allowedTypes[$mimeType]
        ];
    }
}
