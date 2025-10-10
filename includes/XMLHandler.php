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

        $result = $this->dom->save($this->xmlFile);
        if (!$result) {
            throw new Exception('Failed to save XML file');
        }

        return true;
    }

    /**
     * Create backup of XML file
     */
    private function createBackup()
    {
        if (file_exists($this->xmlFile)) {
            $backupFile = BACKUP_DIR . '/podcasts_' . date('Y-m-d_H-i-s') . '.xml';
            copy($this->xmlFile, $backupFile);

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

        $podcast->appendChild($title);
        $podcast->appendChild($feedUrl);
        $podcast->appendChild($description);
        $podcast->appendChild($coverImage);
        $podcast->appendChild($created);
        $podcast->appendChild($updated);
        $podcast->appendChild($status);

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
            'status' => $xpath->query('status', $node)->item(0)->nodeValue ?? 'active'
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
    public function generateRSSFeed()
    {
        try {
            $this->loadXML();

            $rss = new DOMDocument('1.0', 'UTF-8');
            $rss->formatOutput = true;

            // RSS root element
            $rssRoot = $rss->createElement('rss');
            $rssRoot->setAttribute('version', '2.0');
            $rssRoot->setAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');

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

            $channel->appendChild($rss->createElement('lastBuildDate', date('r')));
            $channel->appendChild($rss->createElement('generator', APP_NAME . ' v' . APP_VERSION));

            // Add podcast items
            $podcasts = $this->getAllPodcasts();
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
                    $pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');
                    $item->appendChild($rss->createElement('pubDate', $pubDate));

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
}
