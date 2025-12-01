<?php
/**
 * FeedScanner - Lazy scan mechanism for automatic feed updates
 * 
 * Provides self-healing updates: any data request that finds stale data
 * will trigger an automatic refresh before serving.
 * 
 * Usage:
 *   $scanner = new FeedScanner();
 *   if ($scanner->needsScan()) {
 *       $scanner->scan();
 *   }
 */

require_once __DIR__ . '/PodcastManager.php';
require_once __DIR__ . '/RssFeedParser.php';

class FeedScanner {
    
    private $lockFile;
    private $scanInterval;
    private $podcastManager;
    
    /**
     * @param int $scanInterval Minimum seconds between scans (default: 300 = 5 minutes)
     */
    public function __construct($scanInterval = 300) {
        $this->lockFile = __DIR__ . '/../data/last-lazy-scan.txt';
        $this->scanInterval = $scanInterval;
        $this->podcastManager = new PodcastManager();
    }
    
    /**
     * Check if a scan is needed based on last scan time
     * 
     * @return bool True if scan is needed
     */
    public function needsScan() {
        if (!file_exists($this->lockFile)) {
            return true;
        }
        
        $lastScan = (int)file_get_contents($this->lockFile);
        $elapsed = time() - $lastScan;
        
        return $elapsed >= $this->scanInterval;
    }
    
    /**
     * Get seconds until next scan is allowed
     * 
     * @return int Seconds until next scan (0 if scan is due)
     */
    public function getNextScanIn() {
        if (!file_exists($this->lockFile)) {
            return 0;
        }
        
        $lastScan = (int)file_get_contents($this->lockFile);
        $elapsed = time() - $lastScan;
        $remaining = $this->scanInterval - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Perform scan of all external podcast feeds
     * Updates XML with latest episode data if changes detected
     * 
     * @return array Stats about the scan operation
     */
    public function scan() {
        // Update lock immediately to prevent concurrent scans
        file_put_contents($this->lockFile, time());
        
        $stats = [
            'total' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'triggered_by' => 'lazy_scan',
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        try {
            $podcasts = $this->podcastManager->getAllPodcasts();
            
            foreach ($podcasts as $podcast) {
                // Skip self-hosted feeds (localhost URLs)
                if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i', $podcast['feed_url'])) {
                    continue;
                }
                
                $stats['total']++;
                
                try {
                    $result = $this->fetchFeedMetadata($podcast['feed_url']);
                    
                    if ($result['success']) {
                        // Check if data changed
                        $hasChanges = false;
                        
                        if (isset($result['latest_episode_date']) && 
                            $result['latest_episode_date'] !== $podcast['latest_episode_date']) {
                            $hasChanges = true;
                        }
                        
                        if (isset($result['episode_count']) && 
                            $result['episode_count'] != $podcast['episode_count']) {
                            $hasChanges = true;
                        }
                        
                        if ($hasChanges) {
                            $updateData = [
                                'latest_episode_date' => $result['latest_episode_date'] ?? '',
                                'episode_count' => $result['episode_count'] ?? '0'
                            ];
                            
                            $updateResult = $this->podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
                            
                            if ($updateResult['success']) {
                                $stats['updated']++;
                            } else {
                                $stats['errors']++;
                            }
                        } else {
                            $stats['skipped']++;
                        }
                    } else {
                        $stats['errors']++;
                    }
                } catch (Exception $e) {
                    $stats['errors']++;
                    error_log('FeedScanner error for ' . $podcast['title'] . ': ' . $e->getMessage());
                }
            }
            
            // Log the scan activity
            $this->logScan($stats);
            
        } catch (Exception $e) {
            error_log('FeedScanner fatal error: ' . $e->getMessage());
            $stats['errors']++;
        }
        
        return $stats;
    }
    
    /**
     * Fetch metadata from a single feed URL
     * Uses direct fetch with cache busting
     * 
     * @param string $feedUrl The RSS feed URL
     * @return array Result with success flag and metadata
     */
    private function fetchFeedMetadata($feedUrl) {
        // Add cache buster
        $separator = (strpos($feedUrl, '?') === false) ? '?' : '&';
        $cacheBustUrl = $feedUrl . $separator . '_t=' . time();
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "Cache-Control: no-cache\r\nPragma: no-cache\r\nUser-Agent: PodcastFeedScanner/1.0\r\n"
            ]
        ]);
        
        $xmlContent = @file_get_contents($cacheBustUrl, false, $context);
        
        if ($xmlContent === false) {
            return ['success' => false, 'error' => 'Failed to fetch feed'];
        }
        
        // Parse XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);
        
        if ($xml === false) {
            return ['success' => false, 'error' => 'Invalid XML'];
        }
        
        // Extract metadata
        $latestEpisodeDate = null;
        $episodeCount = 0;
        
        if ($xml->channel && $xml->channel->item) {
            $episodeCount = count($xml->channel->item);
            $firstItem = $xml->channel->item[0];
            
            if ($firstItem->pubDate) {
                $pubDate = (string)$firstItem->pubDate;
                $timestamp = strtotime($pubDate);
                if ($timestamp) {
                    $latestEpisodeDate = date('Y-m-d H:i:s', $timestamp);
                }
            }
        }
        
        return [
            'success' => true,
            'latest_episode_date' => $latestEpisodeDate,
            'episode_count' => $episodeCount
        ];
    }
    
    /**
     * Log scan activity for debugging
     * 
     * @param array $stats Scan statistics
     */
    private function logScan($stats) {
        $logFile = __DIR__ . '/../logs/lazy-scan.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logEntry = sprintf(
            "[%s] Lazy scan: %d total, %d updated, %d skipped, %d errors\n",
            date('Y-m-d H:i:s'),
            $stats['total'],
            $stats['updated'],
            $stats['skipped'],
            $stats['errors']
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Get last scan timestamp
     * 
     * @return int Unix timestamp of last scan, or 0 if never scanned
     */
    public function getLastScanTime() {
        if (!file_exists($this->lockFile)) {
            return 0;
        }
        return (int)file_get_contents($this->lockFile);
    }
    
    /**
     * Force a scan regardless of cooldown
     * 
     * @return array Scan statistics
     */
    public function forceScan() {
        return $this->scan();
    }
}
