<?php
/**
 * SelfHostedXMLHandler Class
 * Handles XML operations for self-hosted podcasts
 */

class SelfHostedXMLHandler
{
    private $xmlFile;
    private $xml;

    public function __construct()
    {
        $this->xmlFile = DATA_DIR . '/self-hosted-podcasts.xml';
        $this->initializeXML();
    }

    /**
     * Initialize XML file if it doesn't exist
     */
    private function initializeXML()
    {
        if (!file_exists($this->xmlFile)) {
            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            
            $root = $xml->createElement('selfhostedpodcasts');
            $xml->appendChild($root);
            
            $xml->save($this->xmlFile);
            chmod($this->xmlFile, 0666);
        }
        
        $this->loadXML();
    }

    /**
     * Load XML file
     */
    private function loadXML()
    {
        $this->xml = new DOMDocument('1.0', 'UTF-8');
        $this->xml->formatOutput = true;
        $this->xml->preserveWhiteSpace = false;
        
        if (file_exists($this->xmlFile)) {
            $this->xml->load($this->xmlFile);
        }
    }

    /**
     * Save XML file
     */
    private function saveXML()
    {
        // Create backup before saving
        if (file_exists($this->xmlFile)) {
            $backupFile = DATA_DIR . '/backup/self-hosted-podcasts-' . date('Y-m-d-His') . '.xml';
            @copy($this->xmlFile, $backupFile);
        }
        
        $this->xml->save($this->xmlFile);
        chmod($this->xmlFile, 0666);
    }

    /**
     * Add new podcast
     */
    public function addPodcast($data)
    {
        $root = $this->xml->documentElement;
        
        // Generate unique ID
        $id = 'shp_' . time() . '_' . uniqid();
        
        // Create podcast element
        $podcast = $this->xml->createElement('podcast');
        
        // Add fields
        $this->addElement($podcast, 'id', $id);
        $this->addCDataElement($podcast, 'title', $data['title']);
        $this->addCDataElement($podcast, 'description', $data['description'] ?? '');
        $this->addCDataElement($podcast, 'author', $data['author'] ?? '');
        $this->addElement($podcast, 'email', $data['email'] ?? '');
        $this->addElement($podcast, 'website_url', $data['website_url'] ?? '');
        $this->addElement($podcast, 'cover_image', $data['cover_image'] ?? '');
        
        // iTunes metadata
        $this->addElement($podcast, 'category', $data['category'] ?? '');
        $this->addElement($podcast, 'subcategory', $data['subcategory'] ?? '');
        $this->addElement($podcast, 'language', $data['language'] ?? 'en-us');
        $this->addElement($podcast, 'explicit', $data['explicit'] ?? 'no');
        $this->addCDataElement($podcast, 'copyright', $data['copyright'] ?? '');
        $this->addElement($podcast, 'podcast_type', $data['podcast_type'] ?? 'episodic');
        
        // Owner info
        $this->addCDataElement($podcast, 'owner_name', $data['owner_name'] ?? $data['author'] ?? '');
        $this->addElement($podcast, 'owner_email', $data['owner_email'] ?? $data['email'] ?? '');
        
        // Optional fields
        $this->addCDataElement($podcast, 'subtitle', $data['subtitle'] ?? '');
        $this->addCDataElement($podcast, 'keywords', $data['keywords'] ?? '');
        $this->addElement($podcast, 'complete', $data['complete'] ?? 'no');
        
        // System fields
        $this->addElement($podcast, 'created_date', date('Y-m-d H:i:s'));
        $this->addElement($podcast, 'updated_date', date('Y-m-d H:i:s'));
        $this->addElement($podcast, 'status', 'active');
        $this->addElement($podcast, 'is_cloned', $data['is_cloned'] ?? 'no');
        
        // Create empty episodes container
        $episodes = $this->xml->createElement('episodes');
        $podcast->appendChild($episodes);
        
        $root->appendChild($podcast);
        $this->saveXML();
        
        return $id;
    }

    /**
     * Update podcast
     */
    public function updatePodcast($id, $data)
    {
        $xpath = new DOMXPath($this->xml);
        $podcast = $xpath->query("//podcast[id='$id']")->item(0);
        
        if (!$podcast) {
            throw new Exception('Podcast not found');
        }
        
        // Update fields
        foreach ($data as $key => $value) {
            $element = $xpath->query("$key", $podcast)->item(0);
            
            if ($element) {
                // Update existing element
                if (in_array($key, ['title', 'description', 'author', 'copyright', 'owner_name', 'subtitle', 'keywords'])) {
                    // CDATA elements
                    $element->nodeValue = '';
                    $cdata = $this->xml->createCDATASection($value);
                    $element->appendChild($cdata);
                } else {
                    $element->nodeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
                }
            } else {
                // Add new element
                if (in_array($key, ['title', 'description', 'author', 'copyright', 'owner_name', 'subtitle', 'keywords'])) {
                    $this->addCDataElement($podcast, $key, $value);
                } else {
                    $this->addElement($podcast, $key, $value);
                }
            }
        }
        
        // Update timestamp
        $updatedDate = $xpath->query("updated_date", $podcast)->item(0);
        if ($updatedDate) {
            $updatedDate->nodeValue = date('Y-m-d H:i:s');
        }
        
        $this->saveXML();
        return true;
    }

    /**
     * Delete podcast
     */
    public function deletePodcast($id)
    {
        $xpath = new DOMXPath($this->xml);
        $podcast = $xpath->query("//podcast[id='$id']")->item(0);
        
        if (!$podcast) {
            throw new Exception('Podcast not found');
        }
        
        $podcast->parentNode->removeChild($podcast);
        $this->saveXML();
        
        return true;
    }

    /**
     * Get single podcast
     */
    public function getPodcast($id)
    {
        $xpath = new DOMXPath($this->xml);
        $podcast = $xpath->query("//podcast[id='$id']")->item(0);
        
        if (!$podcast) {
            return null;
        }
        
        return $this->podcastNodeToArray($podcast, $xpath);
    }

    /**
     * Get all podcasts
     */
    public function getAllPodcasts()
    {
        $xpath = new DOMXPath($this->xml);
        $podcasts = $xpath->query('//podcast');
        
        $result = [];
        foreach ($podcasts as $podcast) {
            $result[] = $this->podcastNodeToArray($podcast, $xpath);
        }
        
        return $result;
    }

    /**
     * Add episode to podcast
     */
    public function addEpisode($podcastId, $episodeData)
    {
        $xpath = new DOMXPath($this->xml);
        $podcast = $xpath->query("//podcast[id='$podcastId']")->item(0);
        
        if (!$podcast) {
            throw new Exception('Podcast not found');
        }
        
        $episodes = $xpath->query("episodes", $podcast)->item(0);
        if (!$episodes) {
            $episodes = $this->xml->createElement('episodes');
            $podcast->appendChild($episodes);
        }
        
        // Generate unique episode ID
        $episodeId = 'ep_' . time() . '_' . uniqid();
        
        // Create episode element
        $episode = $this->xml->createElement('episode');
        
        $this->addElement($episode, 'id', $episodeId);
        $this->addCDataElement($episode, 'title', $episodeData['title']);
        $this->addCDataElement($episode, 'description', $episodeData['description'] ?? '');
        $this->addElement($episode, 'audio_url', $episodeData['audio_url']);
        $this->addElement($episode, 'duration', $episodeData['duration'] ?? '0');
        $this->addElement($episode, 'file_size', $episodeData['file_size'] ?? '0');
        $this->addElement($episode, 'pub_date', $episodeData['pub_date'] ?? date('Y-m-d H:i:s'));
        $this->addElement($episode, 'episode_number', $episodeData['episode_number'] ?? '');
        $this->addElement($episode, 'season_number', $episodeData['season_number'] ?? '');
        $this->addElement($episode, 'episode_type', $episodeData['episode_type'] ?? 'full');
        $this->addElement($episode, 'explicit', $episodeData['explicit'] ?? 'no');
        $this->addElement($episode, 'episode_image', $episodeData['episode_image'] ?? '');
        $this->addElement($episode, 'guid', $episodeId);
        $this->addElement($episode, 'status', $episodeData['status'] ?? 'published');
        $this->addElement($episode, 'created_date', date('Y-m-d H:i:s'));
        $this->addElement($episode, 'updated_date', date('Y-m-d H:i:s'));
        
        $episodes->appendChild($episode);
        
        // Update podcast timestamp
        $updatedDate = $xpath->query("updated_date", $podcast)->item(0);
        if ($updatedDate) {
            $updatedDate->nodeValue = date('Y-m-d H:i:s');
        }
        
        $this->saveXML();
        return $episodeId;
    }

    /**
     * Update episode
     */
    public function updateEpisode($podcastId, $episodeId, $episodeData)
    {
        $xpath = new DOMXPath($this->xml);
        $episode = $xpath->query("//podcast[id='$podcastId']/episodes/episode[id='$episodeId']")->item(0);
        
        if (!$episode) {
            throw new Exception('Episode not found');
        }
        
        // Update fields
        foreach ($episodeData as $key => $value) {
            $element = $xpath->query("$key", $episode)->item(0);
            
            if ($element) {
                if (in_array($key, ['title', 'description'])) {
                    $element->nodeValue = '';
                    $cdata = $this->xml->createCDATASection($value);
                    $element->appendChild($cdata);
                } else {
                    $element->nodeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
                }
            }
        }
        
        // Update timestamp
        $updatedDate = $xpath->query("updated_date", $episode)->item(0);
        if ($updatedDate) {
            $updatedDate->nodeValue = date('Y-m-d H:i:s');
        }
        
        $this->saveXML();
        return true;
    }

    /**
     * Delete episode
     */
    public function deleteEpisode($podcastId, $episodeId)
    {
        $xpath = new DOMXPath($this->xml);
        $episode = $xpath->query("//podcast[id='$podcastId']/episodes/episode[id='$episodeId']")->item(0);
        
        if (!$episode) {
            throw new Exception('Episode not found');
        }
        
        $episode->parentNode->removeChild($episode);
        $this->saveXML();
        
        return true;
    }

    /**
     * Get all episodes for a podcast
     */
    public function getEpisodes($podcastId)
    {
        $xpath = new DOMXPath($this->xml);
        $episodes = $xpath->query("//podcast[id='$podcastId']/episodes/episode");
        
        $result = [];
        foreach ($episodes as $episode) {
            $result[] = $this->episodeNodeToArray($episode, $xpath);
        }
        
        return $result;
    }

    /**
     * Helper: Add element with text content
     */
    private function addElement($parent, $name, $value)
    {
        $element = $this->xml->createElement($name);
        $element->nodeValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
        $parent->appendChild($element);
    }

    /**
     * Helper: Add element with CDATA content
     */
    private function addCDataElement($parent, $name, $value)
    {
        $element = $this->xml->createElement($name);
        $cdata = $this->xml->createCDATASection($value);
        $element->appendChild($cdata);
        $parent->appendChild($element);
    }

    /**
     * Helper: Convert podcast node to array
     */
    private function podcastNodeToArray($podcast, $xpath)
    {
        $data = [];
        
        foreach ($podcast->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && $child->nodeName !== 'episodes') {
                $data[$child->nodeName] = $child->nodeValue;
            }
        }
        
        // Get episodes
        $episodes = $xpath->query("episodes/episode", $podcast);
        $data['episodes'] = [];
        foreach ($episodes as $episode) {
            $data['episodes'][] = $this->episodeNodeToArray($episode, $xpath);
        }
        
        $data['episode_count'] = count($data['episodes']);
        
        return $data;
    }

    /**
     * Helper: Convert episode node to array
     */
    private function episodeNodeToArray($episode, $xpath)
    {
        $data = [];
        
        foreach ($episode->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $data[$child->nodeName] = $child->nodeValue;
            }
        }
        
        return $data;
    }
}
