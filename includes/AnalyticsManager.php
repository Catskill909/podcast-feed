<?php
/**
 * Analytics Manager
 * Business logic for analytics tracking and reporting
 */

require_once __DIR__ . '/AnalyticsXMLHandler.php';

class AnalyticsManager
{
    private AnalyticsXMLHandler $xmlHandler;
    private string $logFile;

    public function __construct()
    {
        $this->xmlHandler = new AnalyticsXMLHandler();
        $this->logFile = __DIR__ . '/../logs/operations.log';
    }

    /**
     * Log an analytics event
     * 
     * @param string $type Event type (play or download)
     * @param string $podcastId Podcast ID
     * @param string $episodeId Episode ID
     * @param string $sessionId Session ID
     * @param array $metadata Additional metadata
     * @return array Response with success status
     */
    public function logEvent(?string $type, ?string $podcastId, ?string $episodeId, ?string $sessionId, array $metadata = [])
    {
        // Validate event type
        if (!in_array($type, ['play', 'download'])) {
            return [
                'success' => false,
                'error' => 'Invalid event type. Must be "play" or "download".'
            ];
        }

        // Validate required fields
        if (empty($podcastId) || empty($episodeId) || empty($sessionId)) {
            return [
                'success' => false,
                'error' => 'Missing required fields: podcastId, episodeId, or sessionId.'
            ];
        }

        // Sanitize inputs
        $type = $this->sanitize($type);
        $podcastId = $this->sanitize($podcastId);
        $episodeId = $this->sanitize($episodeId);
        $sessionId = $this->sanitize($sessionId);

        // Sanitize metadata
        $cleanMetadata = [];
        if (isset($metadata['episodeTitle'])) {
            $cleanMetadata['episodeTitle'] = $this->sanitize($metadata['episodeTitle']);
        }
        if (isset($metadata['podcastTitle'])) {
            $cleanMetadata['podcastTitle'] = $this->sanitize($metadata['podcastTitle']);
        }
        if (isset($metadata['audioUrl'])) {
            $cleanMetadata['audioUrl'] = $this->sanitize($metadata['audioUrl']);
        }

        // Log to XML
        $success = $this->xmlHandler->logEvent($type, $podcastId, $episodeId, $sessionId, $cleanMetadata);

        if ($success) {
            // Log to operations log
            $this->logOperation('ANALYTICS_EVENT', [
                'type' => $type,
                'podcast_id' => $podcastId,
                'episode_id' => $episodeId,
                'session_id' => substr($sessionId, 0, 8) . '...' // Truncate for privacy
            ]);

            return [
                'success' => true,
                'message' => 'Event logged successfully'
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to log event'
        ];
    }

    /**
     * Get dashboard statistics for a given time range
     * 
     * @param string $range Time range (7d, 30d, 90d, all)
     * @return array Dashboard statistics
     */
    public function getDashboardStats(string $range = '7d')
    {
        // Calculate date range
        $endDate = date('Y-m-d');
        
        switch ($range) {
            case '7d':
                $startDate = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $startDate = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90d':
                $startDate = date('Y-m-d', strtotime('-90 days'));
                break;
            case 'all':
                $startDate = '2000-01-01'; // Far past date
                break;
            default:
                $startDate = date('Y-m-d', strtotime('-7 days'));
        }

        // Get data from XML
        $data = $this->xmlHandler->getDataByDateRange($startDate, $endDate);

        // Aggregate statistics
        $stats = $this->aggregateStats($data);

        return [
            'success' => true,
            'range' => $range,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'overview' => $stats['overview'],
            'dailySeries' => $stats['dailySeries'],
            'topEpisodes' => $stats['topEpisodes'],
            'topPodcasts' => $stats['topPodcasts'],
            // Newest recorded day across ALL data, not just this range. Without
            // it an empty range is indistinguishable from having no data at
            // all, which is what made a stale-but-populated store report
            // "No Analytics Data Yet".
            'lastEventDate' => $this->getLastEventDate(),
            'updatedAt' => date('c')
        ];
    }

    /**
     * Newest day that has any recorded metric, or null if the store is empty.
     */
    private function getLastEventDate(): ?string
    {
        $latest = null;

        foreach ($this->xmlHandler->getAllData() as $day) {
            if (empty($day['metrics'])) {
                continue;
            }
            $date = $day['date'] ?? '';
            if ($date !== '' && ($latest === null || $date > $latest)) {
                $latest = $date;
            }
        }

        return $latest;
    }

    /**
     * Aggregate statistics from raw data
     * 
     * @param array $data Raw analytics data
     * @return array Aggregated statistics
     */
    private function aggregateStats(array $data)
    {
        $totalPlays = 0;
        $totalDownloads = 0;
        $uniqueListeners = [];
        $dailyData = [];
        $episodeStats = [];
        $podcastStats = [];

        // Process each day
        foreach ($data as $day) {
            $date = $day['date'];
            $dayPlays = 0;
            $dayDownloads = 0;

            foreach ($day['metrics'] as $metric) {
                $type = $metric['type'];
                $count = $metric['count'];
                $uniqueVisitors = $metric['unique_visitors'];
                $episodeId = $metric['episode_id'];
                $podcastId = $metric['podcast_id'];

                // Aggregate totals
                if ($type === 'play') {
                    $totalPlays += $count;
                    $dayPlays += $count;
                } elseif ($type === 'download') {
                    $totalDownloads += $count;
                    $dayDownloads += $count;
                }

                // Track unique listeners (approximate - session IDs)
                $uniqueListeners[$episodeId] = max(
                    $uniqueListeners[$episodeId] ?? 0,
                    $uniqueVisitors
                );

                // Aggregate episode stats
                if (!isset($episodeStats[$episodeId])) {
                    $episodeStats[$episodeId] = [
                        'episodeId' => $episodeId,
                        'episodeTitle' => $metric['episode_title'] ?? 'Unknown Episode',
                        'podcastId' => $podcastId,
                        'podcastTitle' => $metric['podcast_title'] ?? 'Unknown Podcast',
                        'plays' => 0,
                        'downloads' => 0,
                        'uniqueListeners' => $uniqueVisitors
                    ];
                }

                if ($type === 'play') {
                    $episodeStats[$episodeId]['plays'] += $count;
                } elseif ($type === 'download') {
                    $episodeStats[$episodeId]['downloads'] += $count;
                }

                // Aggregate podcast stats
                if (!isset($podcastStats[$podcastId])) {
                    $podcastStats[$podcastId] = [
                        'podcastId' => $podcastId,
                        'podcastTitle' => $metric['podcast_title'] ?? 'Unknown Podcast',
                        'plays' => 0,
                        'downloads' => 0,
                        'episodes' => []
                    ];
                }

                if ($type === 'play') {
                    $podcastStats[$podcastId]['plays'] += $count;
                } elseif ($type === 'download') {
                    $podcastStats[$podcastId]['downloads'] += $count;
                }

                if (!in_array($episodeId, $podcastStats[$podcastId]['episodes'])) {
                    $podcastStats[$podcastId]['episodes'][] = $episodeId;
                }
            }

            // Store daily data
            $dailyData[] = [
                'date' => $date,
                'plays' => $dayPlays,
                'downloads' => $dayDownloads
            ];
        }

        // Calculate unique listeners (sum of unique visitors across all episodes)
        $totalUniqueListeners = array_sum($uniqueListeners);

        // Calculate play-to-download rate
        $playToDownloadRate = $totalPlays > 0 ? round($totalDownloads / $totalPlays, 2) : 0;

        // Sort episodes by plays
        usort($episodeStats, function (array $a, array $b) {
            return $b['plays'] - $a['plays'];
        });

        // Sort podcasts by plays
        usort($podcastStats, function (array $a, array $b) {
            return $b['plays'] - $a['plays'];
        });

        // Get top 10 episodes and podcasts
        $topEpisodes = array_slice($episodeStats, 0, 10);
        $topPodcasts = array_slice($podcastStats, 0, 10);

        // Add episode count to podcast stats
        foreach ($topPodcasts as &$podcast) {
            $podcast['episodeCount'] = count($podcast['episodes']);
            unset($podcast['episodes']); // Remove episode IDs from output
        }

        return [
            'overview' => [
                'totalPlays' => $totalPlays,
                'totalDownloads' => $totalDownloads,
                'uniqueListeners' => $totalUniqueListeners,
                'playToDownloadRate' => $playToDownloadRate
            ],
            'dailySeries' => $dailyData,
            'topEpisodes' => $topEpisodes,
            'topPodcasts' => $topPodcasts
        ];
    }


    /**
     * Sanitize input string
     * 
     * @param string $input Input string
     * @return string Sanitized string
     */
    private function sanitize(?string $input)
    {
        return htmlspecialchars(strip_tags(trim((string) $input)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Log operation to file
     * 
     * @param string $action Action name
     * @param array $details Action details
     */
    private function logOperation(string $action, array $details = [])
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $detailsStr = !empty($details) ? json_encode($details) : '';
        $logEntry = "[$timestamp] $action $detailsStr\n";

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Cleanup old analytics data
     * 
     * @param int $daysToKeep Number of days to keep
     * @return bool Success status
     */
    public function cleanupOldData(int $daysToKeep = 365)
    {
        return $this->xmlHandler->cleanupOldData($daysToKeep);
    }
}
