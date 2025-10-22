<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AdsXMLHandler.php';
require_once __DIR__ . '/AdsImageUploader.php';

/**
 * AdsManager Class
 * Manages ad banner operations (web and mobile)
 */
class AdsManager
{
    private $xmlHandler;
    private $imageUploader;

    public function __construct()
    {
        $this->xmlHandler = new AdsXMLHandler();
        $this->imageUploader = new AdsImageUploader();
    }

    /**
     * Upload web banner ad
     */
    public function uploadWebAd($file): array
    {
        try {
            // Upload image with strict validation
            $uploadResult = $this->imageUploader->uploadWebAd($file);
            
            if (!$uploadResult['success']) {
                throw new Exception($uploadResult['message']);
            }

            // Add to XML
            $adId = $this->xmlHandler->addWebAd(
                $uploadResult['filename'],
                $uploadResult['path']
            );

            return [
                'success' => true,
                'id' => $adId,
                'filename' => $uploadResult['filename'],
                'url' => $uploadResult['url'],
                'message' => 'Web banner ad uploaded successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload mobile banner ad
     */
    public function uploadMobileAd($file): array
    {
        try {
            // Upload image with strict validation
            $uploadResult = $this->imageUploader->uploadMobileAd($file);
            
            if (!$uploadResult['success']) {
                throw new Exception($uploadResult['message']);
            }

            // Detect dimensions
            $imageInfo = getimagesize($uploadResult['path']);
            $dimensions = $imageInfo[0] . 'x' . $imageInfo[1];

            // Add to XML with dimensions
            $adId = $this->xmlHandler->addMobileAd(
                $uploadResult['filename'],
                $uploadResult['path'],
                $dimensions
            );

            return [
                'success' => true,
                'id' => $adId,
                'filename' => $uploadResult['filename'],
                'url' => $uploadResult['url'],
                'dimensions' => $dimensions,
                'message' => 'Mobile banner ad uploaded successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all web ads
     */
    public function getWebAds(): array
    {
        $ads = $this->xmlHandler->getWebAds();
        
        // Add URLs to each ad
        foreach ($ads as &$ad) {
            $ad['url'] = APP_URL . '/uploads/ads/web/' . $ad['filename'];
        }
        
        return $ads;
    }

    /**
     * Get all mobile ads
     */
    public function getMobileAds(): array
    {
        $ads = $this->xmlHandler->getMobileAds();
        
        // Add URLs to each ad
        foreach ($ads as &$ad) {
            $ad['url'] = APP_URL . '/uploads/ads/mobile/' . $ad['filename'];
        }
        
        return $ads;
    }

    /**
     * Delete web ad
     */
    public function deleteWebAd($id): array
    {
        try {
            // Get filepath and remove from XML
            $filepath = $this->xmlHandler->deleteWebAd($id);
            
            if (!$filepath) {
                throw new Exception('Ad not found');
            }

            // Delete image file
            $this->imageUploader->deleteAdImage($filepath);

            return [
                'success' => true,
                'message' => 'Web banner ad deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete mobile ad
     */
    public function deleteMobileAd($id): array
    {
        try {
            // Get filepath and remove from XML
            $filepath = $this->xmlHandler->deleteMobileAd($id);
            
            if (!$filepath) {
                throw new Exception('Ad not found');
            }

            // Delete image file
            $this->imageUploader->deleteAdImage($filepath);

            return [
                'success' => true,
                'message' => 'Mobile banner ad deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update settings
     */
    public function updateSettings($settings): array
    {
        try {
            foreach ($settings as $key => $value) {
                $this->xmlHandler->updateSetting($key, $value);
            }

            return [
                'success' => true,
                'message' => 'Settings updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all settings
     */
    public function getSettings(): array
    {
        return $this->xmlHandler->getAllSettings();
    }

    /**
     * Update web ads display order
     */
    public function updateWebAdsOrder($orderedIds): array
    {
        try {
            $this->xmlHandler->updateWebAdsOrder($orderedIds);
            
            return [
                'success' => true,
                'message' => 'Display order updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update mobile ads display order
     */
    public function updateMobileAdsOrder($orderedIds): array
    {
        try {
            $this->xmlHandler->updateMobileAdsOrder($orderedIds);
            
            return [
                'success' => true,
                'message' => 'Display order updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get required dimensions for ad type
     */
    public function getRequiredDimensions($type): array
    {
        return $this->imageUploader->getRequiredDimensions($type);
    }
}
