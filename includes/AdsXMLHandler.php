<?php
/**
 * AdsXMLHandler Class
 * Handles XML operations for ad management (web and mobile banners)
 */

class AdsXMLHandler
{
    private $xmlFile;
    private $xml;

    public function __construct()
    {
        $this->xmlFile = DATA_DIR . '/ads-config.xml';
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
            
            $root = $xml->createElement('adsconfig');
            
            // Settings section
            $settings = $xml->createElement('settings');
            $this->addElement($settings, 'web_ads_enabled', '0', $xml);
            $this->addElement($settings, 'mobile_ads_enabled', '0', $xml);
            $this->addElement($settings, 'web_ads_rotation_duration', '10', $xml);
            $this->addElement($settings, 'web_ads_fade_duration', '1.2', $xml);
            $root->appendChild($settings);
            
            // Web ads section
            $webAds = $xml->createElement('webads');
            $root->appendChild($webAds);
            
            // Mobile ads section
            $mobileAds = $xml->createElement('mobileads');
            $root->appendChild($mobileAds);
            
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
            $backupFile = DATA_DIR . '/backup/ads-config-' . date('Y-m-d-His') . '.xml';
            @copy($this->xmlFile, $backupFile);
        }
        
        $this->xml->save($this->xmlFile);
        chmod($this->xmlFile, 0666);
    }

    /**
     * Add element helper
     */
    private function addElement($parent, $name, $value, $doc = null)
    {
        $doc = $doc ?? $this->xml;
        $element = $doc->createElement($name);
        $element->appendChild($doc->createTextNode($value));
        $parent->appendChild($element);
        return $element;
    }

    /**
     * Add web banner ad
     */
    public function addWebAd($filename, $filepath)
    {
        $root = $this->xml->documentElement;
        $webAds = $root->getElementsByTagName('webads')->item(0);
        
        // Generate unique ID
        $id = 'wad_' . time() . '_' . uniqid();
        
        // Create ad element
        $ad = $this->xml->createElement('ad');
        $this->addElement($ad, 'id', $id);
        $this->addElement($ad, 'filename', $filename);
        $this->addElement($ad, 'filepath', $filepath);
        $this->addElement($ad, 'click_url', '');
        $this->addElement($ad, 'display_order', $this->getNextDisplayOrder('web'));
        $this->addElement($ad, 'created_at', date('Y-m-d H:i:s'));
        
        $webAds->appendChild($ad);
        $this->saveXML();
        
        return $id;
    }

    /**
     * Add mobile banner ad
     */
    public function addMobileAd($filename, $filepath, $dimensions = '320x50')
    {
        $root = $this->xml->documentElement;
        $mobileAds = $root->getElementsByTagName('mobileads')->item(0);
        
        // Generate unique ID
        $id = 'mad_' . time() . '_' . uniqid();
        
        // Create ad element
        $ad = $this->xml->createElement('ad');
        $this->addElement($ad, 'id', $id);
        $this->addElement($ad, 'filename', $filename);
        $this->addElement($ad, 'filepath', $filepath);
        $this->addElement($ad, 'dimensions', $dimensions);
        $this->addElement($ad, 'click_url', '');
        $this->addElement($ad, 'display_order', $this->getNextDisplayOrder('mobile'));
        $this->addElement($ad, 'created_at', date('Y-m-d H:i:s'));
        
        $mobileAds->appendChild($ad);
        $this->saveXML();
        
        return $id;
    }

    /**
     * Get next display order
     */
    private function getNextDisplayOrder($type)
    {
        $section = $type === 'web' ? 'webads' : 'mobileads';
        $root = $this->xml->documentElement;
        $adsSection = $root->getElementsByTagName($section)->item(0);
        $ads = $adsSection->getElementsByTagName('ad');
        
        return $ads->length;
    }

    /**
     * Get all web ads
     */
    public function getWebAds()
    {
        $root = $this->xml->documentElement;
        $webAds = $root->getElementsByTagName('webads')->item(0);
        $ads = $webAds->getElementsByTagName('ad');
        
        $result = [];
        foreach ($ads as $ad) {
            $clickUrlNode = $ad->getElementsByTagName('click_url')->item(0);
            // Fallback to old 'url' field for backward compatibility
            if (!$clickUrlNode) {
                $clickUrlNode = $ad->getElementsByTagName('url')->item(0);
            }
            $result[] = [
                'id' => $ad->getElementsByTagName('id')->item(0)->nodeValue,
                'filename' => $ad->getElementsByTagName('filename')->item(0)->nodeValue,
                'filepath' => $ad->getElementsByTagName('filepath')->item(0)->nodeValue,
                'url' => APP_URL . '/uploads/ads/web/' . $ad->getElementsByTagName('filename')->item(0)->nodeValue,
                'click_url' => $clickUrlNode ? $clickUrlNode->nodeValue : '',
                'display_order' => $ad->getElementsByTagName('display_order')->item(0)->nodeValue,
                'created_at' => $ad->getElementsByTagName('created_at')->item(0)->nodeValue
            ];
        }
        
        // Sort by display order
        usort($result, function($a, $b) {
            return $a['display_order'] - $b['display_order'];
        });
        
        return $result;
    }

    /**
     * Get all mobile ads
     */
    public function getMobileAds()
    {
        $root = $this->xml->documentElement;
        $mobileAds = $root->getElementsByTagName('mobileads')->item(0);
        $ads = $mobileAds->getElementsByTagName('ad');
        
        $result = [];
        foreach ($ads as $ad) {
            $dimensionsNode = $ad->getElementsByTagName('dimensions')->item(0);
            $clickUrlNode = $ad->getElementsByTagName('click_url')->item(0);
            // Fallback to old 'url' field for backward compatibility
            if (!$clickUrlNode) {
                $clickUrlNode = $ad->getElementsByTagName('url')->item(0);
            }
            $filename = $ad->getElementsByTagName('filename')->item(0)->nodeValue;
            $result[] = [
                'id' => $ad->getElementsByTagName('id')->item(0)->nodeValue,
                'filename' => $filename,
                'filepath' => $ad->getElementsByTagName('filepath')->item(0)->nodeValue,
                'url' => APP_URL . '/uploads/ads/mobile/' . $filename,
                'dimensions' => $dimensionsNode ? $dimensionsNode->nodeValue : '320x50',
                'click_url' => $clickUrlNode ? $clickUrlNode->nodeValue : '',
                'display_order' => $ad->getElementsByTagName('display_order')->item(0)->nodeValue,
                'created_at' => $ad->getElementsByTagName('created_at')->item(0)->nodeValue
            ];
        }
        
        // Sort by display order
        usort($result, function($a, $b) {
            return $a['display_order'] - $b['display_order'];
        });
        
        return $result;
    }

    /**
     * Delete web ad
     */
    public function deleteWebAd($id)
    {
        $root = $this->xml->documentElement;
        $webAds = $root->getElementsByTagName('webads')->item(0);
        $ads = $webAds->getElementsByTagName('ad');
        
        foreach ($ads as $ad) {
            $adId = $ad->getElementsByTagName('id')->item(0)->nodeValue;
            if ($adId === $id) {
                $filepath = $ad->getElementsByTagName('filepath')->item(0)->nodeValue;
                $webAds->removeChild($ad);
                $this->saveXML();
                return $filepath;
            }
        }
        
        return null;
    }

    /**
     * Delete mobile ad
     */
    public function deleteMobileAd($id)
    {
        $root = $this->xml->documentElement;
        $mobileAds = $root->getElementsByTagName('mobileads')->item(0);
        $ads = $mobileAds->getElementsByTagName('ad');
        
        foreach ($ads as $ad) {
            $adId = $ad->getElementsByTagName('id')->item(0)->nodeValue;
            if ($adId === $id) {
                $filepath = $ad->getElementsByTagName('filepath')->item(0)->nodeValue;
                $mobileAds->removeChild($ad);
                $this->saveXML();
                return $filepath;
            }
        }
        
        return null;
    }

    /**
     * Get setting value
     */
    public function getSetting($key)
    {
        $root = $this->xml->documentElement;
        $settings = $root->getElementsByTagName('settings')->item(0);
        $setting = $settings->getElementsByTagName($key)->item(0);
        
        return $setting ? $setting->nodeValue : null;
    }

    /**
     * Update setting
     */
    public function updateSetting($key, $value)
    {
        $root = $this->xml->documentElement;
        $settings = $root->getElementsByTagName('settings')->item(0);
        $setting = $settings->getElementsByTagName($key)->item(0);
        
        if ($setting) {
            $setting->nodeValue = $value;
        } else {
            $this->addElement($settings, $key, $value);
        }
        
        $this->saveXML();
        return true;
    }

    /**
     * Get all settings
     */
    public function getAllSettings()
    {
        return [
            'web_ads_enabled' => $this->getSetting('web_ads_enabled') === '1',
            'mobile_ads_enabled' => $this->getSetting('mobile_ads_enabled') === '1',
            'web_ads_rotation_duration' => (int)$this->getSetting('web_ads_rotation_duration'),
            'web_ads_fade_duration' => (float)($this->getSetting('web_ads_fade_duration') ?: 1.2)
        ];
    }

    /**
     * Update display order for web ads
     */
    public function updateWebAdsOrder($orderedIds)
    {
        $root = $this->xml->documentElement;
        $webAds = $root->getElementsByTagName('webads')->item(0);
        $ads = $webAds->getElementsByTagName('ad');
        
        foreach ($ads as $ad) {
            $adId = $ad->getElementsByTagName('id')->item(0)->nodeValue;
            $newOrder = array_search($adId, $orderedIds);
            if ($newOrder !== false) {
                $orderElement = $ad->getElementsByTagName('display_order')->item(0);
                $orderElement->nodeValue = $newOrder;
            }
        }
        
        $this->saveXML();
        return true;
    }

    /**
     * Update display order for mobile ads
     */
    public function updateMobileAdsOrder($orderedIds)
    {
        $root = $this->xml->documentElement;
        $mobileAds = $root->getElementsByTagName('mobileads')->item(0);
        $ads = $mobileAds->getElementsByTagName('ad');
        
        foreach ($ads as $ad) {
            $adId = $ad->getElementsByTagName('id')->item(0)->nodeValue;
            $newOrder = array_search($adId, $orderedIds);
            if ($newOrder !== false) {
                $orderElement = $ad->getElementsByTagName('display_order')->item(0);
                $orderElement->nodeValue = $newOrder;
            }
        }
        
        $this->saveXML();
        return true;
    }

    /**
     * Update ad URL
     */
    public function updateAdUrl($adId, $adType, $url)
    {
        $root = $this->xml->documentElement;
        $adsSection = $adType === 'web' ? 
            $root->getElementsByTagName('webads')->item(0) : 
            $root->getElementsByTagName('mobileads')->item(0);
        
        $ads = $adsSection->getElementsByTagName('ad');
        
        foreach ($ads as $ad) {
            $id = $ad->getElementsByTagName('id')->item(0)->nodeValue;
            if ($id === $adId) {
                $urlElement = $ad->getElementsByTagName('click_url')->item(0);
                if ($urlElement) {
                    $urlElement->nodeValue = $url;
                } else {
                    $this->addElement($ad, 'click_url', $url);
                }
                $this->saveXML();
                return true;
            }
        }
        
        return false;
    }
}
