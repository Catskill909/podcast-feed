<?php
/**
 * PodcastFeedCloner Class
 * Main orchestrator for cloning external RSS feeds into My Podcasts
 * 
 * Process:
 * 1. Validate feed (reuse existing validator)
 * 2. Parse feed (reuse existing parser)
 * 3. Create podcast in My Podcasts
 * 4. Download all audio files and images
 * 5. Create all episodes
 * 6. Generate progress updates
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/RssImportValidator.php';
require_once __DIR__ . '/RssFeedParser.php';
require_once __DIR__ . '/SelfHostedPodcastManager.php';
require_once __DIR__ . '/PodcastAudioDownloader.php';
require_once __DIR__ . '/ImageUploader.php';

class PodcastFeedCloner
{
    private $validator;
    private $parser;
    private $selfHostedManager;
    private $audioDownloader;
    private $imageUploader;
    private $progressFile;
    private $jobId;

    public function __construct($jobId = null)
    {
        $this->validator = new RssImportValidator();
        $this->parser = new RssFeedParser();
        $this->selfHostedManager = new SelfHostedPodcastManager();
        $this->audioDownloader = new PodcastAudioDownloader();
        $this->imageUploader = new ImageUploader();
        
        $this->jobId = $jobId ?: 'clone_' . time() . '_' . uniqid();
        $this->progressFile = DATA_DIR . '/clone_progress_' . $this->jobId . '.json';
    }

    /**
     * Validate feed for cloning
     */
    public function validateFeedForCloning($feedUrl)
    {
        try {
            // Use existing validator (same as RSS import)
            $validation = $this->validator->validateFeedForImport($feedUrl);
            
            if (!$validation['can_import']) {
                return [
                    'success' => false,
                    'can_clone' => false,
                    'errors' => $validation['validation_errors'],
                    'message' => 'Feed validation failed'
                ];
            }

            // Parse feed to get episode count and size estimate
            $feedData = $this->parser->fetchAndParse($feedUrl);
            
            if (!$feedData['success']) {
                return [
                    'success' => false,
                    'can_clone' => false,
                    'message' => 'Failed to parse feed'
                ];
            }

            // Estimate storage requirements
            $estimate = $this->estimateStorageRequirements($feedData['data']);

            return [
                'success' => true,
                'can_clone' => true,
                'validation' => $validation,
                'feed_data' => $feedData['data'],
                'estimate' => $estimate,
                'warnings' => $validation['validation_warnings'] ?? []
            ];

        } catch (Exception $e) {
            error_log('Feed validation error: ' . $e->getMessage());
            return [
                'success' => false,
                'can_clone' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Estimate storage requirements
     */
    private function estimateStorageRequirements($feedData)
    {
        $episodeCount = 0;
        $totalSize = 0;
        $failedChecks = 0;

        // Count episodes from feed
        if (isset($feedData['episodes']) && is_array($feedData['episodes'])) {
            $episodeCount = count($feedData['episodes']);
            
            // Sample first 5 episodes to estimate average size
            $sampleSize = 0;
            $sampleCount = 0;
            
            foreach (array_slice($feedData['episodes'], 0, 5) as $episode) {
                if (!empty($episode['audio_url'])) {
                    $size = $this->audioDownloader->getRemoteFileSize($episode['audio_url']);
                    if ($size !== false) {
                        $sampleSize += $size;
                        $sampleCount++;
                    } else {
                        $failedChecks++;
                    }
                }
            }
            
            if ($sampleCount > 0) {
                $avgSize = $sampleSize / $sampleCount;
                $totalSize = $avgSize * $episodeCount;
            }
        }

        return [
            'episode_count' => $episodeCount,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'average_episode_size' => $episodeCount > 0 ? $totalSize / $episodeCount : 0,
            'average_episode_size_formatted' => $episodeCount > 0 ? $this->formatFileSize($totalSize / $episodeCount) : '0 B',
            'failed_checks' => $failedChecks,
            'estimated' => $failedChecks > 0
        ];
    }

    /**
     * Clone feed - Main orchestration method
     */
    public function cloneFeed($feedUrl, $options = [])
    {
        try {
            // Initialize progress
            $this->initializeProgress($feedUrl, $options);

            // Phase 1: Validate and parse
            $this->updateProgress([
                'phase' => 'validating',
                'message' => 'Validating feed...',
                'percent' => 5
            ]);

            $validation = $this->validateFeedForCloning($feedUrl);
            
            if (!$validation['success'] || !$validation['can_clone']) {
                throw new Exception($validation['message'] ?? 'Feed validation failed');
            }

            $feedData = $validation['feed_data'];

            // Phase 2: Create podcast
            $this->updateProgress([
                'phase' => 'creating_podcast',
                'message' => 'Creating podcast...',
                'percent' => 10
            ]);

            $podcastId = $this->createPodcast($feedData);

            // Phase 3: Clone episodes
            $this->updateProgress([
                'phase' => 'cloning_episodes',
                'message' => 'Cloning episodes...',
                'percent' => 15,
                'podcast_id' => $podcastId
            ]);

            $cloneResult = $this->cloneEpisodes($podcastId, $feedData, $options);

            // Phase 4: Finalize
            $this->updateProgress([
                'phase' => 'finalizing',
                'message' => 'Finalizing...',
                'percent' => 95
            ]);

            $feedUrl = APP_URL . "/self-hosted-feed.php?id=" . $podcastId;

            // Optional: Import to main directory
            if (!empty($options['import_to_directory'])) {
                $this->importToMainDirectory($podcastId, $feedUrl, $feedData);
            }

            // Complete
            $this->updateProgress([
                'phase' => 'complete',
                'message' => 'Cloning complete!',
                'percent' => 100,
                'podcast_id' => $podcastId,
                'feed_url' => $feedUrl,
                'episodes_cloned' => $cloneResult['success_count'],
                'episodes_failed' => $cloneResult['failed_count']
            ]);

            return [
                'success' => true,
                'podcast_id' => $podcastId,
                'feed_url' => $feedUrl,
                'episodes_cloned' => $cloneResult['success_count'],
                'episodes_failed' => $cloneResult['failed_count'],
                'failed_episodes' => $cloneResult['failed_episodes']
            ];

        } catch (Exception $e) {
            error_log('Clone feed error: ' . $e->getMessage());
            
            $this->updateProgress([
                'phase' => 'error',
                'message' => $e->getMessage(),
                'percent' => 0,
                'error' => true
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Create podcast in My Podcasts
     */
    private function createPodcast($feedData)
    {
        // Download cover image to temp (optional - continue without if fails)
        $coverImageFile = null;
        if (!empty($feedData['image_url'])) {
            error_log("Attempting to download cover image from: " . $feedData['image_url']);
            try {
                $coverImageFile = $this->downloadImageToTemp($feedData['image_url']);
                
                if ($coverImageFile && isset($coverImageFile['tmp_name']) && file_exists($coverImageFile['tmp_name'])) {
                    error_log("Cover image downloaded successfully: " . $coverImageFile['tmp_name']);
                } else {
                    error_log("WARNING: Cover image download failed, continuing without image");
                    $coverImageFile = null;
                }
            } catch (Exception $e) {
                error_log("WARNING: Cover image download exception: " . $e->getMessage());
                $coverImageFile = null;
            }
        }

        // Prepare podcast data
        // Clean up email - some feeds have invalid formats
        $email = $feedData['email'] ?? '';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'podcast@localhost.local';
        }
        
        $podcastData = [
            'title' => $feedData['title'] . ' (Cloned)',
            'description' => $feedData['description'],
            'author' => $feedData['author'] ?? 'Unknown',
            'email' => $email,
            'website_url' => $feedData['link'] ?? '',
            'category' => $feedData['category'] ?? 'General',
            'language' => $feedData['language'] ?? 'en-us',
            'explicit' => $feedData['explicit'] ?? 'no',
            'podcast_type' => 'episodic'
        ];

        error_log("Creating podcast with data: " . json_encode($podcastData));

        // Create podcast (reuse existing method)
        $result = $this->selfHostedManager->createPodcast($podcastData, $coverImageFile);

        error_log("Create podcast result: " . json_encode($result));

        if (!$result['success']) {
            throw new Exception('Failed to create podcast: ' . $result['message']);
        }

        return $result['id'];
    }

    /**
     * Clone all episodes
     */
    private function cloneEpisodes($podcastId, $feedData, $options)
    {
        $episodes = $feedData['episodes'] ?? [];
        $totalEpisodes = count($episodes);
        $successCount = 0;
        $failedCount = 0;
        $failedEpisodes = [];

        error_log("=== CLONE EPISODES DEBUG ===");
        error_log("Total episodes found in feed: " . $totalEpisodes);
        error_log("Feed data keys: " . implode(', ', array_keys($feedData)));
        if ($totalEpisodes > 0) {
            error_log("First episode data: " . json_encode($episodes[0]));
        }

        // Apply episode limit if set
        if (!empty($options['limit_episodes']) && $options['limit_episodes'] > 0) {
            $episodes = array_slice($episodes, 0, $options['limit_episodes']);
            $totalEpisodes = count($episodes);
            error_log("Limited to: " . $totalEpisodes . " episodes");
        }

        foreach ($episodes as $index => $episode) {
            $episodeNumber = $index + 1;
            
            try {
                // Update progress
                $this->updateProgress([
                    'phase' => 'cloning_episodes',
                    'current_episode' => $episodeNumber,
                    'total_episodes' => $totalEpisodes,
                    'episode_title' => $episode['title'] ?? 'Untitled',
                    'action' => 'downloading_audio',
                    'percent' => 15 + (($episodeNumber / $totalEpisodes) * 80)
                ]);

                // Generate episode ID (add small delay to prevent collisions)
                usleep(100000); // 0.1 second delay
                $episodeId = 'ep_' . time() . '_' . uniqid();
                
                error_log("Starting episode $episodeNumber: " . ($episode['title'] ?? 'Unknown'));
                error_log("Audio URL: " . ($episode['audio_url'] ?? 'Unknown'));

                // Download audio file (uses existing AudioUploader to bypass PHP limits)
                $audioResult = $this->audioDownloader->downloadAudioFromUrl(
                    $episode['audio_url'],
                    $podcastId,
                    $episodeId
                );

                if (!isset($audioResult['success']) || !$audioResult['success']) {
                    $errorMsg = $audioResult['message'] ?? 'Unknown audio download error';
                    error_log("Audio download failed for episode $episodeNumber: $errorMsg");
                    throw new Exception($errorMsg);
                }

                // Update progress - downloading image
                $this->updateProgress([
                    'action' => 'downloading_image'
                ]);

                // Download episode image if exists
                $episodeImageFile = null;
                if (!empty($episode['image_url']) && !empty($options['download_episode_images'])) {
                    $episodeImageFile = $this->downloadImageToTemp($episode['image_url']);
                }

                // Update progress - creating metadata
                $this->updateProgress([
                    'action' => 'creating_metadata'
                ]);

                // Prepare episode data
                $episodeData = [
                    'title' => $episode['title'] ?? 'Untitled Episode',
                    'description' => $episode['description'] ?? '',
                    'audio_url' => $audioResult['url'],
                    'duration' => $audioResult['duration'] ?? 0,
                    'file_size' => $audioResult['file_size'] ?? 0,
                    'pub_date' => $episode['pub_date'] ?? date('Y-m-d H:i:s'),
                    'status' => 'published',
                    'episode_number' => $episodeNumber,
                    'season_number' => 1,
                    'episode_type' => 'full',
                    'explicit' => $episode['explicit'] ?? 'no'
                ];

                // Create episode (reuse existing method)
                $episodeResult = $this->selfHostedManager->addEpisode(
                    $podcastId,
                    $episodeData,
                    $episodeImageFile,
                    null // Audio already uploaded
                );

                if ($episodeResult['success']) {
                    $successCount++;
                } else {
                    throw new Exception($episodeResult['message']);
                }

                // Clean up temp image
                if ($episodeImageFile && file_exists($episodeImageFile['tmp_name'])) {
                    unlink($episodeImageFile['tmp_name']);
                }

            } catch (Exception $e) {
                $failedCount++;
                $errorMsg = $e->getMessage();
                $failedEpisodes[] = [
                    'episode' => $episodeNumber,
                    'title' => $episode['title'] ?? 'Unknown',
                    'error' => $errorMsg,
                    'audio_url' => $episode['audio_url'] ?? 'Unknown'
                ];
                
                // DETAILED ERROR LOGGING
                error_log("========================================");
                error_log("EPISODE CLONE FAILURE #$episodeNumber");
                error_log("Title: " . ($episode['title'] ?? 'Unknown'));
                error_log("Audio URL: " . ($episode['audio_url'] ?? 'Unknown'));
                error_log("Error: " . $errorMsg);
                error_log("Stack trace: " . $e->getTraceAsString());
                error_log("========================================");
            }
        }

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'failed_episodes' => $failedEpisodes
        ];
    }

    /**
     * Import to main directory (optional)
     */
    private function importToMainDirectory($podcastId, $feedUrl, $feedData)
    {
        try {
            require_once __DIR__ . '/PodcastManager.php';
            $mainManager = new PodcastManager();

            // Get podcast details
            $podcast = $this->selfHostedManager->getPodcast($podcastId);
            
            if (!$podcast) {
                throw new Exception('Podcast not found');
            }

            // Prepare import data (same as RSS import)
            $importData = [
                'title' => $podcast['title'],
                'feed_url' => $feedUrl,
                'description' => $podcast['description'],
                'rss_image_url' => !empty($podcast['cover_image']) ? COVERS_URL . '/' . $podcast['cover_image'] : ''
            ];

            // Import (reuse existing method - same as RSS import)
            $importResult = $mainManager->createPodcast($importData, null);

            if ($importResult['success']) {
                // Auto-refresh metadata (same as RSS import in admin.php)
                $feedResult = $this->parser->fetchFeedMetadata($feedUrl);
                if ($feedResult['success']) {
                    $mainManager->updatePodcastMetadata($importResult['id'], [
                        'latest_episode_date' => $feedResult['latest_episode_date'] ?? '',
                        'episode_count' => $feedResult['episode_count'] ?? '0'
                    ]);
                }
            }

            return $importResult;

        } catch (Exception $e) {
            error_log('Import to directory failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Download image to temp location
     */
    private function downloadImageToTemp($imageUrl)
    {
        try {
            if (empty($imageUrl)) {
                return null;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'podcast_image_');
            
            // Use cURL for better reliability
            $ch = curl_init($imageUrl);
            $fp = fopen($tempFile, 'wb');
            
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_USERAGENT => 'PodFeed Cloner/1.0',
                CURLOPT_SSL_VERIFYPEER => false, // For dev/testing
                CURLOPT_FAILONERROR => true
            ]);
            
            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            fclose($fp);
            
            if (!$success || $httpCode !== 200) {
                error_log("Image download failed: HTTP $httpCode - $error - URL: $imageUrl");
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
                return null;
            }

            // Detect image type
            $imageInfo = getimagesize($tempFile);
            $mimeType = $imageInfo ? $imageInfo['mime'] : 'image/jpeg';

            // Create file array that looks like $_FILES
            return [
                'tmp_name' => $tempFile,
                'name' => basename(parse_url($imageUrl, PHP_URL_PATH)) ?: 'cover.jpg',
                'type' => $mimeType,
                'size' => filesize($tempFile),
                'error' => UPLOAD_ERR_OK
            ];

        } catch (Exception $e) {
            error_log('Image download failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Initialize progress tracking
     */
    private function initializeProgress($feedUrl, $options)
    {
        $progress = [
            'job_id' => $this->jobId,
            'feed_url' => $feedUrl,
            'options' => $options,
            'phase' => 'starting',
            'message' => 'Initializing...',
            'percent' => 0,
            'started_at' => time(),
            'updated_at' => time()
        ];

        file_put_contents($this->progressFile, json_encode($progress));
    }

    /**
     * Update progress
     */
    public function updateProgress($data)
    {
        $current = $this->getProgress();
        
        $progress = array_merge($current, $data, [
            'updated_at' => time()
        ]);

        file_put_contents($this->progressFile, json_encode($progress));
    }

    /**
     * Get current progress
     */
    public function getProgress()
    {
        if (!file_exists($this->progressFile)) {
            return [];
        }

        $content = file_get_contents($this->progressFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Clean up progress file
     */
    public function cleanupProgress()
    {
        if (file_exists($this->progressFile)) {
            unlink($this->progressFile);
        }
    }

    /**
     * Format file size
     */
    private function formatFileSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Get job ID
     */
    public function getJobId()
    {
        return $this->jobId;
    }
}
