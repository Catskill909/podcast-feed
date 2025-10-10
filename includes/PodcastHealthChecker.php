<?php
require_once __DIR__ . '/../config/config.php';

/**
 * PodcastHealthChecker Class
 * Validates podcast feeds for health and availability
 * Checks: Feed URL accessibility, RSS 2.0 structure, iTunes namespace, image availability
 */
class PodcastHealthChecker
{
    private $timeout = 10; // seconds
    private $userAgent = 'PodFeed Builder Health Check/1.0';
    
    /**
     * Perform comprehensive health check on a podcast
     * @param array $podcast Podcast data with feed_url and cover_image
     * @return array Health check results
     */
    public function checkPodcastHealth($podcast)
    {
        $results = [
            'podcast_id' => $podcast['id'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'healthy', // healthy, warning, critical
            'checks' => []
        ];
        
        // Check 1: Feed URL accessibility
        $feedCheck = $this->checkFeedUrl($podcast['feed_url']);
        $results['checks']['feed_url'] = $feedCheck;
        
        // Check 2: RSS 2.0 structure validation
        if ($feedCheck['status'] === 'pass') {
            $rssCheck = $this->validateRssStructure($feedCheck['content']);
            $results['checks']['rss_structure'] = $rssCheck;
            
            // Check 3: iTunes namespace validation
            $itunesCheck = $this->validateItunesNamespace($feedCheck['content']);
            $results['checks']['itunes_namespace'] = $itunesCheck;
        } else {
            $results['checks']['rss_structure'] = [
                'status' => 'skip',
                'message' => 'Skipped due to feed URL failure'
            ];
            $results['checks']['itunes_namespace'] = [
                'status' => 'skip',
                'message' => 'Skipped due to feed URL failure'
            ];
        }
        
        // Check 4: Cover image accessibility
        if (!empty($podcast['cover_image'])) {
            $imageCheck = $this->checkImageUrl($podcast['cover_image']);
            $results['checks']['cover_image'] = $imageCheck;
        } else {
            $results['checks']['cover_image'] = [
                'status' => 'warning',
                'message' => 'No cover image set'
            ];
        }
        
        // Determine overall status
        $results['overall_status'] = $this->calculateOverallStatus($results['checks']);
        
        return $results;
    }
    
    /**
     * Check if feed URL is accessible
     */
    public function checkFeedUrl($url)
    {
        $startTime = microtime(true);
        
        $ch = curl_init();
        
        // Enable SSL verification in production
        $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_NOBODY => false, // We need the content for validation
        ]);
        
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000); // ms
        
        curl_close($ch);
        
        // Evaluate response
        if ($error) {
            return [
                'status' => 'fail',
                'message' => 'Connection error: ' . $error,
                'http_code' => $httpCode,
                'response_time' => $responseTime . 'ms'
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'status' => 'fail',
                'message' => 'HTTP error: ' . $httpCode,
                'http_code' => $httpCode,
                'response_time' => $responseTime . 'ms'
            ];
        }
        
        // Check response time
        $status = 'pass';
        $message = 'Feed accessible';
        
        if ($responseTime > 5000) {
            $status = 'warning';
            $message = 'Feed accessible but slow (>5s)';
        } elseif ($responseTime > 2000) {
            $status = 'warning';
            $message = 'Feed accessible but slow (>2s)';
        }
        
        return [
            'status' => $status,
            'message' => $message,
            'http_code' => $httpCode,
            'response_time' => $responseTime . 'ms',
            'content' => $content // Store for further validation
        ];
    }
    
    /**
     * Validate RSS 2.0 structure
     */
    public function validateRssStructure($xmlContent)
    {
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            return [
                'status' => 'fail',
                'message' => 'Invalid XML structure',
                'details' => 'XML parsing failed'
            ];
        }
        
        $issues = [];
        
        // Check root element
        if ($xml->getName() !== 'rss') {
            $issues[] = 'Root element is not <rss>';
        }
        
        // Check RSS version
        $version = (string) $xml['version'];
        if ($version !== '2.0') {
            $issues[] = 'RSS version is not 2.0 (found: ' . ($version ?: 'none') . ')';
        }
        
        // Check for channel element
        if (!isset($xml->channel)) {
            $issues[] = 'Missing required <channel> element';
        } else {
            $channel = $xml->channel;
            
            // Check required RSS 2.0 channel elements
            $requiredElements = ['title', 'link', 'description'];
            foreach ($requiredElements as $element) {
                if (!isset($channel->$element) || empty((string) $channel->$element)) {
                    $issues[] = "Missing or empty required element: <$element>";
                }
            }
            
            // Check for items
            if (!isset($channel->item) || count($channel->item) === 0) {
                $issues[] = 'No <item> elements found (no episodes)';
            }
        }
        
        if (empty($issues)) {
            return [
                'status' => 'pass',
                'message' => 'Valid RSS 2.0 structure',
                'details' => 'All required elements present'
            ];
        } else {
            return [
                'status' => 'fail',
                'message' => 'RSS 2.0 validation failed',
                'details' => implode('; ', $issues)
            ];
        }
    }
    
    /**
     * Validate iTunes namespace and tags
     */
    public function validateItunesNamespace($xmlContent)
    {
        libxml_use_internal_errors(true);
        
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            return [
                'status' => 'skip',
                'message' => 'Cannot validate iTunes namespace (invalid XML)'
            ];
        }
        
        // Get namespaces
        $namespaces = $xml->getNamespaces(true);
        
        // Check if iTunes namespace is declared
        if (!isset($namespaces['itunes'])) {
            return [
                'status' => 'warning',
                'message' => 'iTunes namespace not declared',
                'details' => 'Feed may not appear correctly in Apple Podcasts'
            ];
        }
        
        $channel = $xml->channel;
        $itunes = $channel->children($namespaces['itunes']);
        
        $issues = [];
        $warnings = [];
        
        // Check recommended iTunes tags
        $recommendedTags = [
            'author' => 'Author information',
            'summary' => 'Podcast summary',
            'image' => 'Podcast artwork',
            'category' => 'Podcast category',
            'explicit' => 'Explicit content flag'
        ];
        
        foreach ($recommendedTags as $tag => $description) {
            if (!isset($itunes->$tag)) {
                $warnings[] = "Missing recommended <itunes:$tag> ($description)";
            }
        }
        
        // Check image format if present
        if (isset($itunes->image)) {
            $imageHref = (string) $itunes->image['href'];
            if (empty($imageHref)) {
                $issues[] = '<itunes:image> present but href attribute is empty';
            }
        }
        
        // Check explicit tag format if present
        if (isset($itunes->explicit)) {
            $explicit = strtolower((string) $itunes->explicit);
            if (!in_array($explicit, ['yes', 'no', 'true', 'false'])) {
                $issues[] = '<itunes:explicit> has invalid value (should be yes/no or true/false)';
            }
        }
        
        // Determine status
        if (!empty($issues)) {
            return [
                'status' => 'fail',
                'message' => 'iTunes namespace validation failed',
                'details' => implode('; ', $issues)
            ];
        } elseif (!empty($warnings)) {
            return [
                'status' => 'warning',
                'message' => 'iTunes namespace present but incomplete',
                'details' => implode('; ', $warnings)
            ];
        } else {
            return [
                'status' => 'pass',
                'message' => 'iTunes namespace properly configured',
                'details' => 'All recommended tags present'
            ];
        }
    }
    
    /**
     * Check if image URL is accessible
     */
    public function checkImageUrl($imagePath)
    {
        // Check if it's a local file or URL
        if (strpos($imagePath, 'http') === 0) {
            // Remote URL
            $url = $imagePath;
        } else {
            // Local file
            $fullPath = COVERS_DIR . '/' . $imagePath;
            
            if (!file_exists($fullPath)) {
                return [
                    'status' => 'fail',
                    'message' => 'Image file not found',
                    'path' => $imagePath
                ];
            }
            
            if (!is_readable($fullPath)) {
                return [
                    'status' => 'fail',
                    'message' => 'Image file not readable',
                    'path' => $imagePath
                ];
            }
            
            return [
                'status' => 'pass',
                'message' => 'Image file exists and is readable',
                'path' => $imagePath,
                'size' => $this->formatBytes(filesize($fullPath))
            ];
        }
        
        // Check remote image
        $startTime = microtime(true);
        
        $ch = curl_init();
        
        $sslVerify = (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_NOBODY => true, // HEAD request only
        ]);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        $responseTime = round((microtime(true) - $startTime) * 1000);
        
        curl_close($ch);
        
        if ($error) {
            return [
                'status' => 'fail',
                'message' => 'Image connection error: ' . $error,
                'url' => $url
            ];
        }
        
        if ($httpCode !== 200) {
            return [
                'status' => 'fail',
                'message' => 'Image HTTP error: ' . $httpCode,
                'url' => $url
            ];
        }
        
        // Check if it's actually an image
        if (strpos($contentType, 'image/') !== 0) {
            return [
                'status' => 'warning',
                'message' => 'URL does not return an image (Content-Type: ' . $contentType . ')',
                'url' => $url
            ];
        }
        
        return [
            'status' => 'pass',
            'message' => 'Image accessible',
            'url' => $url,
            'content_type' => $contentType,
            'response_time' => $responseTime . 'ms'
        ];
    }
    
    /**
     * Calculate overall health status from individual checks
     */
    private function calculateOverallStatus($checks)
    {
        $hasFail = false;
        $hasWarning = false;
        
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hasFail = true;
            } elseif ($check['status'] === 'warning') {
                $hasWarning = true;
            }
        }
        
        if ($hasFail) {
            return 'critical';
        } elseif ($hasWarning) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
    
    /**
     * Format bytes to human-readable size
     */
    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Check all podcasts and return summary
     */
    public function checkAllPodcasts($podcasts)
    {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_checked' => count($podcasts),
            'healthy' => 0,
            'warning' => 0,
            'critical' => 0,
            'podcasts' => []
        ];
        
        foreach ($podcasts as $podcast) {
            $healthCheck = $this->checkPodcastHealth($podcast);
            
            // Count by status
            switch ($healthCheck['overall_status']) {
                case 'healthy':
                    $results['healthy']++;
                    break;
                case 'warning':
                    $results['warning']++;
                    break;
                case 'critical':
                    $results['critical']++;
                    break;
            }
            
            $results['podcasts'][] = [
                'id' => $podcast['id'],
                'title' => $podcast['title'],
                'status' => $healthCheck['overall_status'],
                'checks' => $healthCheck['checks']
            ];
        }
        
        return $results;
    }
}
