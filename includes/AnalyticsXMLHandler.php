<?php
/**
 * Analytics XML Handler
 * Manages XML storage for analytics data
 */

class AnalyticsXMLHandler
{
    private $xmlFile;
    private $backupDir;

    public function __construct()
    {
        $this->xmlFile = __DIR__ . '/../data/analytics.xml';
        $this->backupDir = __DIR__ . '/../data/backup/';

        // Ensure directories exist
        $this->ensureDirectories();

        // Initialize XML file if it doesn't exist
        if (!file_exists($this->xmlFile)) {
            $this->initializeXML();
        }
    }

    /**
     * Ensure required directories exist
     */
    private function ensureDirectories()
    {
        $dataDir = dirname($this->xmlFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }

    /**
     * Initialize empty XML structure
     */
    private function initializeXML()
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $root = $xml->createElement('analytics');
        $root->setAttribute('version', '1.0');
        $root->setAttribute('created', date('c'));
        $xml->appendChild($root);

        $xml->save($this->xmlFile);
        chmod($this->xmlFile, 0644);
    }

    /**
     * Load XML document
     */
    private function loadXML()
    {
        if (!file_exists($this->xmlFile)) {
            $this->initializeXML();
        }

        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;

        if (!@$xml->load($this->xmlFile)) {
            throw new Exception('Failed to load analytics XML file');
        }

        return $xml;
    }

    /**
     * Save XML document with backup
     */
    private function saveXML($xml)
    {
        // Create backup
        if (file_exists($this->xmlFile)) {
            $backupFile = $this->backupDir . 'analytics_' . date('Y-m-d_His') . '.xml';
            copy($this->xmlFile, $backupFile);

            // Keep only last 10 backups
            $this->cleanupBackups();
        }

        // Save XML
        if (!$xml->save($this->xmlFile)) {
            throw new Exception('Failed to save analytics XML file');
        }

        chmod($this->xmlFile, 0644);
        return true;
    }

    /**
     * Cleanup old backups (keep last 10)
     */
    private function cleanupBackups()
    {
        $backups = glob($this->backupDir . 'analytics_*.xml');
        if (count($backups) > 10) {
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $toDelete = array_slice($backups, 0, count($backups) - 10);
            foreach ($toDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Log an analytics event
     * 
     * @param string $type Event type (play or download)
     * @param string $podcastId Podcast ID
     * @param string $episodeId Episode ID
     * @param string $sessionId Session ID for unique visitor tracking
     * @param array $metadata Additional metadata (title, audioUrl, etc.)
     * @return bool Success status
     */
    public function logEvent($type, $podcastId, $episodeId, $sessionId, $metadata = [])
    {
        try {
            $xml = $this->loadXML();
            $root = $xml->documentElement;

            $date = date('Y-m-d');

            // Find or create day element
            $dayElement = null;
            $days = $xml->getElementsByTagName('day');
            foreach ($days as $day) {
                if ($day->getAttribute('date') === $date) {
                    $dayElement = $day;
                    break;
                }
            }

            if (!$dayElement) {
                $dayElement = $xml->createElement('day');
                $dayElement->setAttribute('date', $date);
                $root->appendChild($dayElement);
            }

            // Find or create metric element
            $metricElement = null;
            $metrics = $dayElement->getElementsByTagName('metric');
            foreach ($metrics as $metric) {
                if ($metric->getAttribute('type') === $type &&
                    $metric->getAttribute('podcast_id') === $podcastId &&
                    $metric->getAttribute('episode_id') === $episodeId) {
                    $metricElement = $metric;
                    break;
                }
            }

            if (!$metricElement) {
                // Create new metric
                $metricElement = $xml->createElement('metric');
                $metricElement->setAttribute('type', $type);
                $metricElement->setAttribute('podcast_id', $podcastId);
                $metricElement->setAttribute('episode_id', $episodeId);
                $metricElement->setAttribute('count', '0');
                $metricElement->setAttribute('unique_visitors', '0');
                $metricElement->setAttribute('created', date('c'));

                // Add metadata
                if (isset($metadata['episodeTitle'])) {
                    $metricElement->setAttribute('episode_title', $metadata['episodeTitle']);
                }
                if (isset($metadata['podcastTitle'])) {
                    $metricElement->setAttribute('podcast_title', $metadata['podcastTitle']);
                }
                if (isset($metadata['audioUrl'])) {
                    $metricElement->setAttribute('audio_url', $metadata['audioUrl']);
                }

                $dayElement->appendChild($metricElement);
            }

            // Update counts
            $currentCount = (int)$metricElement->getAttribute('count');
            $metricElement->setAttribute('count', (string)($currentCount + 1));
            $metricElement->setAttribute('last_updated', date('c'));

            // Track unique visitors using session IDs
            $uniqueVisitors = $metricElement->getAttribute('unique_visitors');
            $sessionIds = $metricElement->getAttribute('session_ids');
            
            if (empty($sessionIds)) {
                $sessionIdsArray = [];
            } else {
                $sessionIdsArray = explode(',', $sessionIds);
            }

            if (!in_array($sessionId, $sessionIdsArray)) {
                $sessionIdsArray[] = $sessionId;
                $metricElement->setAttribute('session_ids', implode(',', $sessionIdsArray));
                $metricElement->setAttribute('unique_visitors', (string)count($sessionIdsArray));
            }

            return $this->saveXML($xml);

        } catch (Exception $e) {
            error_log('Analytics XML Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get analytics data for a date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Analytics data
     */
    public function getDataByDateRange($startDate, $endDate)
    {
        try {
            $xml = $this->loadXML();
            $data = [];

            $days = $xml->getElementsByTagName('day');
            foreach ($days as $day) {
                $date = $day->getAttribute('date');
                
                if ($date >= $startDate && $date <= $endDate) {
                    $dayData = [
                        'date' => $date,
                        'metrics' => []
                    ];

                    $metrics = $day->getElementsByTagName('metric');
                    foreach ($metrics as $metric) {
                        $dayData['metrics'][] = [
                            'type' => $metric->getAttribute('type'),
                            'podcast_id' => $metric->getAttribute('podcast_id'),
                            'podcast_title' => $metric->getAttribute('podcast_title'),
                            'episode_id' => $metric->getAttribute('episode_id'),
                            'episode_title' => $metric->getAttribute('episode_title'),
                            'audio_url' => $metric->getAttribute('audio_url'),
                            'count' => (int)$metric->getAttribute('count'),
                            'unique_visitors' => (int)$metric->getAttribute('unique_visitors'),
                            'created' => $metric->getAttribute('created'),
                            'last_updated' => $metric->getAttribute('last_updated')
                        ];
                    }

                    $data[] = $dayData;
                }
            }

            return $data;

        } catch (Exception $e) {
            error_log('Analytics XML Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all analytics data
     * 
     * @return array All analytics data
     */
    public function getAllData()
    {
        try {
            $xml = $this->loadXML();
            $data = [];

            $days = $xml->getElementsByTagName('day');
            foreach ($days as $day) {
                $dayData = [
                    'date' => $day->getAttribute('date'),
                    'metrics' => []
                ];

                $metrics = $day->getElementsByTagName('metric');
                foreach ($metrics as $metric) {
                    $dayData['metrics'][] = [
                        'type' => $metric->getAttribute('type'),
                        'podcast_id' => $metric->getAttribute('podcast_id'),
                        'podcast_title' => $metric->getAttribute('podcast_title'),
                        'episode_id' => $metric->getAttribute('episode_id'),
                        'episode_title' => $metric->getAttribute('episode_title'),
                        'audio_url' => $metric->getAttribute('audio_url'),
                        'count' => (int)$metric->getAttribute('count'),
                        'unique_visitors' => (int)$metric->getAttribute('unique_visitors'),
                        'created' => $metric->getAttribute('created'),
                        'last_updated' => $metric->getAttribute('last_updated')
                    ];
                }

                $data[] = $dayData;
            }

            return $data;

        } catch (Exception $e) {
            error_log('Analytics XML Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Clear old analytics data (older than specified days)
     * 
     * @param int $daysToKeep Number of days to keep
     * @return bool Success status
     */
    public function cleanupOldData($daysToKeep = 365)
    {
        try {
            $xml = $this->loadXML();
            $root = $xml->documentElement;

            $cutoffDate = date('Y-m-d', strtotime("-$daysToKeep days"));

            $days = $xml->getElementsByTagName('day');
            $toRemove = [];

            foreach ($days as $day) {
                if ($day->getAttribute('date') < $cutoffDate) {
                    $toRemove[] = $day;
                }
            }

            foreach ($toRemove as $day) {
                $root->removeChild($day);
            }

            if (count($toRemove) > 0) {
                return $this->saveXML($xml);
            }

            return true;

        } catch (Exception $e) {
            error_log('Analytics XML Error: ' . $e->getMessage());
            return false;
        }
    }
}
