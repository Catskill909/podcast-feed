<?php
require_once __DIR__ . '/../config/config.php';

/**
 * XMLHandler Class
 * Manages XML file operations for podcast directory
 */
class XMLHandler
{
    private $xmlFile;
    private $dom;

    public function __construct()
    {
        $this->xmlFile = PODCASTS_XML;
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->formatOutput = true;
        $this->dom->preserveWhiteSpace = false;

        // Initialize XML file if it doesn't exist
        if (!file_exists($this->xmlFile)) {
            $this->createInitialXML();
        }
    }

    /**
     * Create initial XML structure
     */
    private function createInitialXML()
    {
        $root = $this->dom->createElement('podcast_directory');
        $root->setAttribute('version', '1.0');
        $root->setAttribute('last_updated', date('c'));

        // Metadata section
        $metadata = $this->dom->createElement('metadata');
        $totalPodcasts = $this->dom->createElement('total_podcasts', '0');
        $created = $this->dom->createElement('created', date('c'));
        $generator = $this->dom->createElement('generator', APP_NAME . ' v' . APP_VERSION);

        $metadata->appendChild($totalPodcasts);
        $metadata->appendChild($created);
        $metadata->appendChild($generator);

        // Podcasts section
        $podcasts = $this->dom->createElement('podcasts');

        $root->appendChild($metadata);
        $root->appendChild($podcasts);
        $this->dom->appendChild($root);

        $this->saveXML();
    }

    /**
     * Load XML file
     */
    public function loadXML(): bool
    {
        if (!file_exists($this->xmlFile)) {
            $this->createInitialXML();
            return true;
        }

        $result = $this->dom->load($this->xmlFile);
        if (!$result) {
            throw new Exception('Failed to load XML file');
        }

        return true;
    }

    /**
     * Save XML file
     */
    public function saveXML(): bool
    {
        // Update last_updated timestamp
        $root = $this->dom->documentElement;
        $root->setAttribute('last_updated', date('c'));

        // Update total podcasts count
        $totalElement = $this->dom->getElementsByTagName('total_podcasts')->item(0);
        if ($totalElement) {
            $podcastCount = $this->dom->getElementsByTagName('podcast')->length;
            $totalElement->textContent = $podcastCount;
        }

        // Create backup before saving
        $this->createBackup();

        // Ensure XML file is writable
        if (file_exists($this->xmlFile) && !is_writable($this->xmlFile)) {
            @chmod($this->xmlFile, 0666);
        }
        
        // Ensure data directory is writable
        if (!is_writable(DATA_DIR)) {
            @chmod(DATA_DIR, 0777);
        }

        $result = @$this->dom->save($this->xmlFile);
        if (!$result) {
            throw new Exception('Failed to save XML file - check directory permissions');
        }
        
        // Set permissions on the saved file
        @chmod($this->xmlFile, 0666);

        return true;
    }

    /**
     * Create backup of XML file
     */
    private function createBackup()
    {
        if (file_exists($this->xmlFile)) {
            $backupFile = BACKUP_DIR . '/podcasts_' . date('Y-m-d_H-i-s') . '.xml';
            
            // Ensure backup directory is writable
            if (!is_writable(BACKUP_DIR)) {
                @chmod(BACKUP_DIR, 0777);
            }
            
            if (!@copy($this->xmlFile, $backupFile)) {
                // Backup failed but don't stop the operation
                error_log("Warning: Failed to create backup at $backupFile");
            } else {
                @chmod($backupFile, 0666);
            }

            // Keep only last 10 backups
            $this->cleanupBackups();
        }
    }

    /**
     * Clean up old backup files
     */
    private function cleanupBackups()
    {
        $backupFiles = glob(BACKUP_DIR . '/podcasts_*.xml');
        if (count($backupFiles) > 10) {
            // Sort by modification time, oldest first
            usort($backupFiles, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Remove oldest files
            $filesToRemove = array_slice($backupFiles, 0, count($backupFiles) - 10);
            foreach ($filesToRemove as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Add a new podcast entry
     */
    public function addPodcast($data)
    {
        $this->loadXML();

        // Check for duplicates
        if ($this->isDuplicate($data['title'], $data['feed_url'])) {
            throw new Exception(ERROR_MESSAGES['duplicate_entry']);
        }

        $podcastsNode = $this->dom->getElementsByTagName('podcasts')->item(0);

        // Generate unique ID
        $id = 'pod_' . time() . '_' . uniqid();

        // Create podcast element
        $podcast = $this->dom->createElement('podcast');
        $podcast->setAttribute('id', $id);

        // Add child elements
        $title = $this->dom->createElement('title');
        $title->appendChild($this->dom->createCDATASection($data['title']));

        $feedUrl = $this->dom->createElement('feed_url');
        $feedUrl->appendChild($this->dom->createCDATASection($data['feed_url']));

        $description = $this->dom->createElement('description');
        $description->appendChild($this->dom->createCDATASection($data['description'] ?? ''));

        $coverImage = $this->dom->createElement('cover_image', $data['cover_image']);
        $created = $this->dom->createElement('created_date', date('c'));
        $updated = $this->dom->createElement('updated_date', date('c'));
        $status = $this->dom->createElement('status', 'active');
        
        // Add latest episode date and episode count if provided
        $latestEpisodeDate = $this->dom->createElement('latest_episode_date', $data['latest_episode_date'] ?? '');
        $episodeCount = $this->dom->createElement('episode_count', $data['episode_count'] ?? '0');
        
        // Add health monitoring fields (initialized)
        $healthStatus = $this->dom->createElement('health_status', 'healthy');
        $lastCheckDate = $this->dom->createElement('last_check_date', '');
        $lastSuccessDate = $this->dom->createElement('last_success_date', date('Y-m-d H:i:s'));
        $consecutiveFailures = $this->dom->createElement('consecutive_failures', '0');
        $totalFailures = $this->dom->createElement('total_failures', '0');
        $totalChecks = $this->dom->createElement('total_checks', '0');
        $avgResponseTime = $this->dom->createElement('avg_response_time', '0');
        $successRate = $this->dom->createElement('success_rate', '100');
        $lastError = $this->dom->createElement('last_error', '');
        $lastErrorDate = $this->dom->createElement('last_error_date', '');
        $autoDisabled = $this->dom->createElement('auto_disabled', 'false');
        $autoDisabledDate = $this->dom->createElement('auto_disabled_date', '');

        $podcast->appendChild($title);
        $podcast->appendChild($feedUrl);
        $podcast->appendChild($description);
        $podcast->appendChild($coverImage);
        $podcast->appendChild($created);
        $podcast->appendChild($updated);
        $podcast->appendChild($status);
        $podcast->appendChild($latestEpisodeDate);
        $podcast->appendChild($episodeCount);
        $podcast->appendChild($healthStatus);
        $podcast->appendChild($lastCheckDate);
        $podcast->appendChild($lastSuccessDate);
        $podcast->appendChild($consecutiveFailures);
        $podcast->appendChild($totalFailures);
        $podcast->appendChild($totalChecks);
        $podcast->appendChild($avgResponseTime);
        $podcast->appendChild($successRate);
        $podcast->appendChild($lastError);
        $podcast->appendChild($lastErrorDate);
        $podcast->appendChild($autoDisabled);
        $podcast->appendChild($autoDisabledDate);

        $podcastsNode->appendChild($podcast);

        $this->saveXML();

        return $id;
    }

    /**
     * Update existing podcast entry
     */
    public function updatePodcast($id, $data)
    {
        $this->loadXML();

        $xpath = new DOMXPath($this->dom);
        $podcast = $xpath->query("//podcast[@id='$id']")->item(0);

        if (!$podcast) {
            throw new Exception('Podcast not found');
        }

        // Update fields
        if (isset($data['title'])) {
            $titleNode = $xpath->query("title", $podcast)->item(0);
            $titleNode->nodeValue = '';
            $titleNode->appendChild($this->dom->createCDATASection($data['title']));
        }

        if (isset($data['feed_url'])) {
            $feedUrlNode = $xpath->query("feed_url", $podcast)->item(0);
            $feedUrlNode->nodeValue = '';
            $feedUrlNode->appendChild($this->dom->createCDATASection($data['feed_url']));
        }

        if (isset($data['description'])) {
            $descriptionNode = $xpath->query("description", $podcast)->item(0);
            if ($descriptionNode) {
                $descriptionNode->nodeValue = '';
                $descriptionNode->appendChild($this->dom->createCDATASection($data['description']));
            } else {
                // Create description node if it doesn't exist (for backwards compatibility)
                $newDescription = $this->dom->createElement('description');
                $newDescription->appendChild($this->dom->createCDATASection($data['description']));
                $feedUrlNode = $xpath->query("feed_url", $podcast)->item(0);
                $podcast->insertBefore($newDescription, $feedUrlNode->nextSibling);
            }
        }

        if (isset($data['cover_image'])) {
            $coverNode = $xpath->query("cover_image", $podcast)->item(0);
            $coverNode->nodeValue = $data['cover_image'];
        }

        if (isset($data['status'])) {
            $statusNode = $xpath->query("status", $podcast)->item(0);
            if ($statusNode) {
                $statusNode->nodeValue = $data['status'];
            }
        }
        
        // Update latest episode date if provided
        if (isset($data['latest_episode_date'])) {
            $latestEpisodeDateNode = $xpath->query("latest_episode_date", $podcast)->item(0);
            if ($latestEpisodeDateNode) {
                $latestEpisodeDateNode->nodeValue = $data['latest_episode_date'];
            } else {
                // Create node if it doesn't exist (backwards compatibility)
                $newLatestEpisodeDate = $this->dom->createElement('latest_episode_date', $data['latest_episode_date']);
                $statusNode = $xpath->query("status", $podcast)->item(0);
                $podcast->insertBefore($newLatestEpisodeDate, $statusNode->nextSibling);
            }
        }
        
        // Update episode count if provided
        if (isset($data['episode_count'])) {
            $episodeCountNode = $xpath->query("episode_count", $podcast)->item(0);
            if ($episodeCountNode) {
                $episodeCountNode->nodeValue = $data['episode_count'];
            } else {
                // Create node if it doesn't exist (backwards compatibility)
                $newEpisodeCount = $this->dom->createElement('episode_count', $data['episode_count']);
                $latestEpisodeDateNode = $xpath->query("latest_episode_date", $podcast)->item(0);
                if ($latestEpisodeDateNode) {
                    $podcast->insertBefore($newEpisodeCount, $latestEpisodeDateNode->nextSibling);
                } else {
                    $statusNode = $xpath->query("status", $podcast)->item(0);
                    $podcast->insertBefore($newEpisodeCount, $statusNode->nextSibling);
                }
            }
        }

        // Update timestamp
        $updatedNode = $xpath->query("updated_date", $podcast)->item(0);
        $updatedNode->nodeValue = date('c');

        $this->saveXML();

        return true;
    }

    /**
     * Delete podcast entry
     */
    public function deletePodcast($id)
    {
        $this->loadXML();

        $xpath = new DOMXPath($this->dom);
        $podcast = $xpath->query("//podcast[@id='$id']")->item(0);

        if (!$podcast) {
            throw new Exception('Podcast not found');
        }

        $podcast->parentNode->removeChild($podcast);
        $this->saveXML();

        return true;
    }

    /**
     * Get single podcast by ID
     */
    public function getPodcast($id): ?array
    {
        $this->loadXML();

        $xpath = new DOMXPath($this->dom);
        $podcast = $xpath->query("//podcast[@id='$id']")->item(0);

        if (!$podcast) {
            return null;
        }

        return $this->podcastNodeToArray($podcast);
    }

    /**
     * Get all podcasts
     */
    public function getAllPodcasts(): array
    {
        try {
            $this->loadXML();

            $podcasts = [];
            $podcastNodes = $this->dom->getElementsByTagName('podcast');

            foreach ($podcastNodes as $node) {
                $podcasts[] = $this->podcastNodeToArray($node);
            }

            return $podcasts;
        } catch (Exception $e) {
            // If XML loading fails, return empty array
            return [];
        }
    }

    /**
     * Convert podcast DOM node to array
     */
    private function podcastNodeToArray($node): array
    {
        $xpath = new DOMXPath($this->dom);

        return [
            'id' => $node->getAttribute('id'),
            'title' => $xpath->query('title', $node)->item(0)->nodeValue ?? '',
            'feed_url' => $xpath->query('feed_url', $node)->item(0)->nodeValue ?? '',
            'description' => $xpath->query('description', $node)->item(0)->nodeValue ?? '',
            'cover_image' => $xpath->query('cover_image', $node)->item(0)->nodeValue ?? '',
            'created_date' => $xpath->query('created_date', $node)->item(0)->nodeValue ?? '',
            'updated_date' => $xpath->query('updated_date', $node)->item(0)->nodeValue ?? '',
            'status' => $xpath->query('status', $node)->item(0)->nodeValue ?? 'active',
            'latest_episode_date' => $xpath->query('latest_episode_date', $node)->item(0)->nodeValue ?? '',
            'episode_count' => $xpath->query('episode_count', $node)->item(0)->nodeValue ?? '0',
            // Health monitoring fields
            'health_status' => $xpath->query('health_status', $node)->item(0)->nodeValue ?? 'healthy',
            'last_check_date' => $xpath->query('last_check_date', $node)->item(0)->nodeValue ?? '',
            'last_success_date' => $xpath->query('last_success_date', $node)->item(0)->nodeValue ?? '',
            'consecutive_failures' => $xpath->query('consecutive_failures', $node)->item(0)->nodeValue ?? '0',
            'total_failures' => $xpath->query('total_failures', $node)->item(0)->nodeValue ?? '0',
            'total_checks' => $xpath->query('total_checks', $node)->item(0)->nodeValue ?? '0',
            'avg_response_time' => $xpath->query('avg_response_time', $node)->item(0)->nodeValue ?? '0',
            'success_rate' => $xpath->query('success_rate', $node)->item(0)->nodeValue ?? '100',
            'last_error' => $xpath->query('last_error', $node)->item(0)->nodeValue ?? '',
            'last_error_date' => $xpath->query('last_error_date', $node)->item(0)->nodeValue ?? '',
            'auto_disabled' => $xpath->query('auto_disabled', $node)->item(0)->nodeValue ?? 'false',
            'auto_disabled_date' => $xpath->query('auto_disabled_date', $node)->item(0)->nodeValue ?? ''
        ];
    }

    /**
     * Check for duplicate entries
     */
    private function isDuplicate($title, $feedUrl)
    {
        $podcasts = $this->getAllPodcasts();

        foreach ($podcasts as $podcast) {
            if (
                strtolower(trim($podcast['title'])) === strtolower(trim($title)) ||
                trim($podcast['feed_url']) === trim($feedUrl)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate RSS feed XML
     */
    public function generateRSSFeed($sortBy = 'episodes', $sortOrder = 'desc')
    {
        try {
            $this->loadXML();

            $rss = new DOMDocument('1.0', 'UTF-8');
            $rss->formatOutput = true;

            // RSS root element
            $rssRoot = $rss->createElement('rss');
            $rssRoot->setAttribute('version', '2.0');
            $rssRoot->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
            $rssRoot->setAttribute('xmlns:podfeed', 'http://podfeed.studio/xmlns');

            // Channel element
            $channel = $rss->createElement('channel');

            // Channel metadata
            $channel->appendChild($rss->createElement('title', 'Available Podcasts Directory'));
            $channel->appendChild($rss->createElement('description', 'Directory of available podcasts for mobile app integration'));
            $channel->appendChild($rss->createElement('link', APP_URL));

            // Self link
            $atomLink = $rss->createElement('atom:link');
            $atomLink->setAttribute('href', APP_URL . '/feed.php');
            $atomLink->setAttribute('rel', 'self');
            $atomLink->setAttribute('type', 'application/rss+xml');
            $channel->appendChild($atomLink);

            // Force UTC for RSS feed timestamps (RSS 2.0 standard)
            $utcDate = new DateTime('now', new DateTimeZone('UTC'));
            $channel->appendChild($rss->createElement('lastBuildDate', $utcDate->format('r')));
            $channel->appendChild($rss->createElement('generator', APP_NAME . ' v' . APP_VERSION));

            // Add podcast items (sorted)
            $podcasts = $this->getAllPodcasts();
            $podcasts = $this->sortPodcasts($podcasts, $sortBy, $sortOrder);
            foreach ($podcasts as $podcast) {
                if (isset($podcast['status']) && $podcast['status'] === 'active') {
                    $item = $rss->createElement('item');

                    // Use plain text instead of CDATA for better compatibility
                    $titleText = htmlspecialchars($podcast['title'] ?? 'Untitled', ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('title', $titleText));

                    $descText = !empty($podcast['description']) ? $podcast['description'] : 'Available podcast feed';
                    $descText = htmlspecialchars($descText, ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('description', $descText));

                    $linkText = htmlspecialchars($podcast['feed_url'] ?? '', ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('link', $linkText));

                    $item->appendChild($rss->createElement('guid', $podcast['id'] ?? ''));
                    
                    // Use latest_episode_date if available, fallback to created_date
                    if (!empty($podcast['latest_episode_date'])) {
                        $pubDate = date('r', strtotime($podcast['latest_episode_date']));
                    } else {
                        $pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');
                    }
                    $item->appendChild($rss->createElement('pubDate', $pubDate));

                    // Add episode count
                    if (isset($podcast['episode_count'])) {
                        $item->appendChild($rss->createElement('podfeed:episodeCount', $podcast['episode_count']));
                    }

                    // Add isNew flag and date information
                    if (!empty($podcast['latest_episode_date'])) {
                        $isNew = $this->isNewEpisode($podcast['latest_episode_date']) ? 'true' : 'false';
                        $item->appendChild($rss->createElement('podfeed:isNew', $isNew));
                        
                        // Add relative date
                        $relativeDate = $this->formatRelativeDate($podcast['latest_episode_date']);
                        $item->appendChild($rss->createElement('podfeed:relativeDate', $relativeDate));
                        
                        // Add ISO 8601 date for easy parsing
                        $isoDate = date('Y-m-d', strtotime($podcast['latest_episode_date']));
                        $item->appendChild($rss->createElement('podfeed:latestEpisodeDate', $isoDate));
                    }

                    // Add cover image as enclosure
                    if (!empty($podcast['cover_image'])) {
                        $enclosure = $rss->createElement('enclosure');
                        $enclosure->setAttribute('url', APP_URL . '/uploads/covers/' . $podcast['cover_image']);
                        
                        // Determine correct MIME type based on file extension
                        $ext = strtolower(pathinfo($podcast['cover_image'], PATHINFO_EXTENSION));
                        $mimeType = 'image/jpeg';
                        if ($ext === 'png') {
                            $mimeType = 'image/png';
                        } elseif ($ext === 'gif') {
                            $mimeType = 'image/gif';
                        }
                        
                        $enclosure->setAttribute('type', $mimeType);
                        $item->appendChild($enclosure);
                    }

                    $channel->appendChild($item);
                }
            }

            $rssRoot->appendChild($channel);
            $rss->appendChild($rssRoot);

            return $rss->saveXML();
        } catch (Exception $e) {
            // Return a basic RSS feed on error
            $errorRss = new DOMDocument('1.0', 'UTF-8');
            $errorRss->formatOutput = true;

            $rssRoot = $errorRss->createElement('rss');
            $rssRoot->setAttribute('version', '2.0');

            $channel = $errorRss->createElement('channel');
            $channel->appendChild($errorRss->createElement('title', 'Podcast Directory Error'));
            $channel->appendChild($errorRss->createElement('description', 'Error generating podcast feed'));
            $channel->appendChild($errorRss->createElement('link', APP_URL));

            $rssRoot->appendChild($channel);
            $errorRss->appendChild($rssRoot);

            return $errorRss->saveXML();
        }
    }
    
    /**
     * Generate RSS feed XML from provided podcast data
     * This version accepts fresh podcast data instead of reading from XML
     * Used by PodcastManager to ensure latest episode dates are current
     */
    public function generateRSSFeedFromData($podcasts, $sortBy = 'episodes', $sortOrder = 'desc')
    {
        try {
            $rss = new DOMDocument('1.0', 'UTF-8');
            $rss->formatOutput = true;

            // RSS root element
            $rssRoot = $rss->createElement('rss');
            $rssRoot->setAttribute('version', '2.0');
            $rssRoot->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
            $rssRoot->setAttribute('xmlns:podfeed', 'http://podfeed.studio/xmlns');

            // Channel element
            $channel = $rss->createElement('channel');

            // Channel metadata
            $channel->appendChild($rss->createElement('title', 'Available Podcasts Directory'));
            $channel->appendChild($rss->createElement('description', 'Directory of available podcasts for mobile app integration'));
            $channel->appendChild($rss->createElement('link', APP_URL));

            // Self link
            $atomLink = $rss->createElement('atom:link');
            $atomLink->setAttribute('href', APP_URL . '/feed.php');
            $atomLink->setAttribute('rel', 'self');
            $atomLink->setAttribute('type', 'application/rss+xml');
            $channel->appendChild($atomLink);

            // Force UTC for RSS feed timestamps (RSS 2.0 standard)
            $utcDate = new DateTime('now', new DateTimeZone('UTC'));
            $channel->appendChild($rss->createElement('lastBuildDate', $utcDate->format('r')));
            $channel->appendChild($rss->createElement('generator', APP_NAME . ' v' . APP_VERSION));

            // Sort podcasts
            $podcasts = $this->sortPodcasts($podcasts, $sortBy, $sortOrder);
            
            // Add podcast items
            foreach ($podcasts as $podcast) {
                if (isset($podcast['status']) && $podcast['status'] === 'active') {
                    $item = $rss->createElement('item');

                    // Use plain text instead of CDATA for better compatibility
                    $titleText = htmlspecialchars($podcast['title'] ?? 'Untitled', ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('title', $titleText));

                    $descText = !empty($podcast['description']) ? $podcast['description'] : 'Available podcast feed';
                    $descText = htmlspecialchars($descText, ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('description', $descText));

                    $linkText = htmlspecialchars($podcast['feed_url'] ?? '', ENT_XML1, 'UTF-8');
                    $item->appendChild($rss->createElement('link', $linkText));

                    $item->appendChild($rss->createElement('guid', $podcast['id'] ?? ''));
                    
                    // Use latest_episode_date if available, fallback to created_date
                    if (!empty($podcast['latest_episode_date'])) {
                        $pubDate = date('r', strtotime($podcast['latest_episode_date']));
                    } else {
                        $pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');
                    }
                    $item->appendChild($rss->createElement('pubDate', $pubDate));

                    // Add episode count
                    if (isset($podcast['episode_count'])) {
                        $item->appendChild($rss->createElement('podfeed:episodeCount', $podcast['episode_count']));
                    }

                    // Add isNew flag and date information
                    if (!empty($podcast['latest_episode_date'])) {
                        $isNew = $this->isNewEpisode($podcast['latest_episode_date']) ? 'true' : 'false';
                        $item->appendChild($rss->createElement('podfeed:isNew', $isNew));
                        
                        // Add relative date
                        $relativeDate = $this->formatRelativeDate($podcast['latest_episode_date']);
                        $item->appendChild($rss->createElement('podfeed:relativeDate', $relativeDate));
                        
                        // Add ISO 8601 date for easy parsing
                        $isoDate = date('Y-m-d', strtotime($podcast['latest_episode_date']));
                        $item->appendChild($rss->createElement('podfeed:latestEpisodeDate', $isoDate));
                    }

                    // Add cover image as enclosure
                    if (!empty($podcast['cover_image'])) {
                        $enclosure = $rss->createElement('enclosure');
                        $enclosure->setAttribute('url', APP_URL . '/uploads/covers/' . $podcast['cover_image']);
                        
                        // Determine correct MIME type based on file extension
                        $ext = strtolower(pathinfo($podcast['cover_image'], PATHINFO_EXTENSION));
                        $mimeType = 'image/jpeg';
                        if ($ext === 'png') {
                            $mimeType = 'image/png';
                        } elseif ($ext === 'gif') {
                            $mimeType = 'image/gif';
                        }
                        
                        $enclosure->setAttribute('type', $mimeType);
                        $item->appendChild($enclosure);
                    }

                    $channel->appendChild($item);
                }
            }

            $rssRoot->appendChild($channel);
            $rss->appendChild($rssRoot);

            return $rss->saveXML();
        } catch (Exception $e) {
            // Return a basic RSS feed on error
            $errorRss = new DOMDocument('1.0', 'UTF-8');
            $errorRss->formatOutput = true;

            $rssRoot = $errorRss->createElement('rss');
            $rssRoot->setAttribute('version', '2.0');

            $channel = $errorRss->createElement('channel');
            $channel->appendChild($errorRss->createElement('title', 'Podcast Directory Error'));
            $channel->appendChild($errorRss->createElement('description', 'Error generating podcast feed'));
            $channel->appendChild($errorRss->createElement('link', APP_URL));

            $rssRoot->appendChild($channel);
            $errorRss->appendChild($rssRoot);

            return $errorRss->saveXML();
        }
    }
    
    /**
     * Sort podcasts array by specified criteria
     */
    private function sortPodcasts($podcasts, $sortBy, $sortOrder)
    {
        usort($podcasts, function($a, $b) use ($sortBy, $sortOrder) {
            $result = 0;
            
            switch($sortBy) {
                case 'episodes':
                    // Sort by latest episode date
                    $dateA = !empty($a['latest_episode_date']) ? strtotime($a['latest_episode_date']) : 0;
                    $dateB = !empty($b['latest_episode_date']) ? strtotime($b['latest_episode_date']) : 0;
                    
                    // If no episode date, fall back to created date
                    if ($dateA === 0) {
                        $dateA = strtotime($a['created_date'] ?? '1970-01-01');
                    }
                    if ($dateB === 0) {
                        $dateB = strtotime($b['created_date'] ?? '1970-01-01');
                    }
                    
                    $result = $dateB - $dateA; // Compare B to A for natural desc order
                    break;
                    
                case 'date':
                    // Sort by created date
                    $dateA = strtotime($a['created_date'] ?? '1970-01-01');
                    $dateB = strtotime($b['created_date'] ?? '1970-01-01');
                    $result = $dateB - $dateA; // Compare B to A for natural desc order
                    break;
                    
                case 'title':
                    // Sort alphabetically by title
                    $result = strcasecmp($a['title'] ?? '', $b['title'] ?? '');
                    break;
                    
                case 'status':
                    // Sort by status (active first if desc, inactive first if asc)
                    $statusA = ($a['status'] ?? 'inactive') === 'active' ? 1 : 0;
                    $statusB = ($b['status'] ?? 'inactive') === 'active' ? 1 : 0;
                    $result = $statusB - $statusA; // Compare B to A for natural desc order
                    break;
            }
            
            // Apply sort order (desc is natural, asc inverts)
            return ($sortOrder === 'asc') ? -$result : $result;
        });
        
        return $podcasts;
    }

    /**
     * Format date as relative time (Today, Yesterday, X days ago, etc.)
     * Matches the logic in browse.js for consistency
     */
    private function formatRelativeDate($dateString)
    {
        if (empty($dateString)) {
            return 'Unknown';
        }
        
        try {
            $date = new DateTime($dateString);
            $now = new DateTime();
            
            // Reset to midnight for accurate day comparison
            $dateOnly = new DateTime($date->format('Y-m-d'));
            $nowOnly = new DateTime($now->format('Y-m-d'));
            
            $diff = $nowOnly->diff($dateOnly);
            $diffDays = (int)$diff->format('%a'); // Absolute days (unsigned)
            
            if ($diffDays === 0) return 'Today';
            if ($diffDays === 1) return 'Yesterday';
            if ($diffDays < 7) return $diffDays . ' days ago';
            if ($diffDays < 30) {
                $weeks = floor($diffDays / 7);
                return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago';
            }
            
            return $date->format('M j, Y');
        } catch (Exception $e) {
            return $dateString;
        }
    }

    /**
     * Check if episode is new (within last 7 days)
     * Matches the logic in browse.js
     */
    private function isNewEpisode($dateString)
    {
        if (empty($dateString)) {
            return false;
        }
        
        try {
            $episodeDate = new DateTime($dateString);
            $now = new DateTime();
            $diff = $now->diff($episodeDate);
            $diffDays = (int)$diff->format('%a');
            
            return $diffDays <= 7;
        } catch (Exception $e) {
            return false;
        }
    }
}
