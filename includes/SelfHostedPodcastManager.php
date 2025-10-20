<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/SelfHostedXMLHandler.php';
require_once __DIR__ . '/ImageUploader.php';
require_once __DIR__ . '/AudioUploader.php';

/**
 * SelfHostedPodcastManager Class
 * Manages self-hosted podcast CRUD operations
 */
class SelfHostedPodcastManager
{
    private $xmlHandler;
    private $imageUploader;
    private $audioUploader;

    public function __construct()
    {
        $this->xmlHandler = new SelfHostedXMLHandler();
        $this->imageUploader = new ImageUploader();
        $this->audioUploader = new AudioUploader();
    }

    /**
     * Create new podcast
     */
    public function createPodcast($data, $imageFile = null)
    {
        try {
            // Validate input
            $validation = $this->validatePodcastData($data);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            $coverImage = '';

            // Handle image upload
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $tempId = 'shp_' . time() . '_' . uniqid();
                $uploadResult = $this->imageUploader->uploadImage($imageFile, $tempId);
                
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }
                
                $coverImage = $uploadResult['filename'];
            }

            // Add cover image to data
            $data['cover_image'] = $coverImage;

            // Add to XML
            $podcastId = $this->xmlHandler->addPodcast($data);

            // Rename image file with real ID
            if ($coverImage) {
                $newFilename = $this->renameImageFile($coverImage, $podcastId);
                $this->xmlHandler->updatePodcast($podcastId, ['cover_image' => $newFilename]);
            }

            $this->logOperation('CREATE_SELF_HOSTED', $podcastId, $data['title']);

            return [
                'success' => true,
                'id' => $podcastId,
                'message' => 'Self-hosted podcast created successfully'
            ];
        } catch (Exception $e) {
            $this->logError('CREATE_SELF_HOSTED_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update podcast
     */
    public function updatePodcast($id, $data, $imageFile = null)
    {
        try {
            // Check if podcast exists
            $existing = $this->xmlHandler->getPodcast($id);
            if (!$existing) {
                throw new Exception('Podcast not found');
            }

            // Validate input
            $validation = $this->validatePodcastData($data, $id);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Handle image upload
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $this->imageUploader->uploadImage($imageFile, $id);
                
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }

                // Delete old image
                if ($existing['cover_image']) {
                    $this->imageUploader->deleteImage($existing['cover_image']);
                }

                $data['cover_image'] = $uploadResult['filename'];
            }

            // Update XML
            $this->xmlHandler->updatePodcast($id, $data);

            $this->logOperation('UPDATE_SELF_HOSTED', $id, $data['title'] ?? $existing['title']);

            return [
                'success' => true,
                'id' => $id,
                'message' => 'Podcast updated successfully'
            ];
        } catch (Exception $e) {
            $this->logError('UPDATE_SELF_HOSTED_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete podcast
     */
    public function deletePodcast($id)
    {
        try {
            // Get podcast data
            $podcast = $this->xmlHandler->getPodcast($id);
            if (!$podcast) {
                throw new Exception('Podcast not found');
            }

            // Delete cover image
            if ($podcast['cover_image']) {
                $this->imageUploader->deleteImage($podcast['cover_image']);
            }

            // Delete episode images AND audio files
            if (!empty($podcast['episodes'])) {
                foreach ($podcast['episodes'] as $episode) {
                    // Delete episode image
                    if (!empty($episode['episode_image'])) {
                        $this->imageUploader->deleteImage($episode['episode_image']);
                    }
                    
                    // Delete audio file if hosted locally
                    if (!empty($episode['audio_url']) && strpos($episode['audio_url'], AUDIO_URL) !== false) {
                        $this->audioUploader->deleteAudio($id, $episode['id']);
                    }
                }
            }

            // Delete entire podcast audio directory
            $podcastAudioDir = UPLOADS_DIR . '/audio/' . $id;
            if (is_dir($podcastAudioDir)) {
                // Remove all files in directory
                $files = glob($podcastAudioDir . '/*');
                if ($files) {
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                        }
                    }
                }
                // Remove directory
                rmdir($podcastAudioDir);
            }

            // Clean up any clone progress files
            $progressFiles = glob(DATA_DIR . '/clone_progress_*_' . $id . '.json');
            if ($progressFiles) {
                foreach ($progressFiles as $file) {
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }

            // Delete from XML
            $this->xmlHandler->deletePodcast($id);

            $this->logOperation('DELETE_SELF_HOSTED', $id, $podcast['title']);

            return [
                'success' => true,
                'message' => 'Podcast deleted successfully'
            ];
        } catch (Exception $e) {
            $this->logError('DELETE_SELF_HOSTED_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get single podcast
     */
    public function getPodcast($id)
    {
        try {
            $podcast = $this->xmlHandler->getPodcast($id);
            
            if (!$podcast) {
                return null;
            }

            // Add image info
            if ($podcast['cover_image']) {
                $imageInfo = $this->imageUploader->getImageInfo($podcast['cover_image']);
                $podcast['image_info'] = $imageInfo;
            }

            return $podcast;
        } catch (Exception $e) {
            $this->logError('GET_SELF_HOSTED_ERROR', $e->getMessage());
            return null;
        }
    }

    /**
     * Get all podcasts
     */
    public function getAllPodcasts()
    {
        try {
            $podcasts = $this->xmlHandler->getAllPodcasts();
            
            // Add image info for each
            foreach ($podcasts as &$podcast) {
                if ($podcast['cover_image']) {
                    $imageInfo = $this->imageUploader->getImageInfo($podcast['cover_image']);
                    $podcast['image_info'] = $imageInfo;
                }
            }

            return $podcasts;
        } catch (Exception $e) {
            $this->logError('GET_ALL_SELF_HOSTED_ERROR', $e->getMessage());
            return [];
        }
    }

    /**
     * Add episode to podcast
     */
    public function addEpisode($podcastId, $episodeData, $imageFile = null, $audioFile = null)
    {
        error_log("[MANAGER] addEpisode called for podcast: $podcastId");
        error_log("[MANAGER] Episode data received: " . print_r($episodeData, true));
        error_log("[MANAGER] Image file: " . ($imageFile ? 'YES' : 'NO'));
        error_log("[MANAGER] Audio file: " . ($audioFile ? 'YES' : 'NO'));
        
        try {
            // Generate episode ID first
            $tempEpisodeId = 'ep_' . time() . '_' . uniqid();
            error_log("[MANAGER] Generated temp episode ID: $tempEpisodeId");

            // Handle audio file upload
            $hasAudioFile = ($audioFile && $audioFile['error'] !== UPLOAD_ERR_NO_FILE);
            
            if ($hasAudioFile) {
                $audioResult = $this->audioUploader->uploadAudio($audioFile, $podcastId, $tempEpisodeId);
                
                if (!$audioResult['success']) {
                    throw new Exception($audioResult['message']);
                }
                
                // Set audio URL to hosted file
                $episodeData['audio_url'] = $audioResult['url'];
                $episodeData['duration'] = $audioResult['duration'];
                $episodeData['file_size'] = $audioResult['file_size'];
            }

            // Validate episode data (skip audio URL validation if file was uploaded)
            $validation = $this->validateEpisodeData($episodeData, $hasAudioFile);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Handle episode image upload
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $this->imageUploader->uploadImage($imageFile, $tempEpisodeId, true);
                
                if ($uploadResult['success']) {
                    $episodeData['episode_image'] = $uploadResult['filename'];
                }
            }

            // Add episode
            $episodeId = $this->xmlHandler->addEpisode($podcastId, $episodeData);

            // Rename image if uploaded
            if (!empty($episodeData['episode_image'])) {
                $newFilename = $this->renameImageFile($episodeData['episode_image'], $episodeId);
                $this->xmlHandler->updateEpisode($podcastId, $episodeId, ['episode_image' => $newFilename]);
            }

            $this->logOperation('ADD_EPISODE', $episodeId, $episodeData['title']);

            return [
                'success' => true,
                'id' => $episodeId,
                'message' => 'Episode added successfully'
            ];
        } catch (Exception $e) {
            $this->logError('ADD_EPISODE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update episode
     */
    public function updateEpisode($podcastId, $episodeId, $episodeData, $imageFile = null, $audioFile = null)
    {
        try {
            // Get existing episode data
            $episodes = $this->xmlHandler->getEpisodes($podcastId);
            $existingEpisode = null;
            foreach ($episodes as $ep) {
                if ($ep['id'] === $episodeId) {
                    $existingEpisode = $ep;
                    break;
                }
            }

            if (!$existingEpisode) {
                throw new Exception('Episode not found');
            }

            // Handle audio file upload (if replacing)
            $hasAudioFile = ($audioFile && $audioFile['error'] !== UPLOAD_ERR_NO_FILE);
            
            if ($hasAudioFile) {
                // Delete old audio file if it exists and is hosted locally
                if (!empty($existingEpisode['audio_url']) && strpos($existingEpisode['audio_url'], AUDIO_URL) !== false) {
                    $this->audioUploader->deleteAudio($podcastId, $episodeId);
                }

                // Upload new audio file
                $audioResult = $this->audioUploader->uploadAudio($audioFile, $podcastId, $episodeId);
                
                if (!$audioResult['success']) {
                    throw new Exception($audioResult['message']);
                }
                
                // Update audio data
                $episodeData['audio_url'] = $audioResult['url'];
                $episodeData['duration'] = $audioResult['duration'];
                $episodeData['file_size'] = $audioResult['file_size'];
            }

            // Validate episode data (skip audio URL validation if file was uploaded)
            $validation = $this->validateEpisodeData($episodeData, $hasAudioFile);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Handle image upload (if replacing)
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                // Delete old image if exists
                if (!empty($existingEpisode['episode_image'])) {
                    $this->imageUploader->deleteImage($existingEpisode['episode_image']);
                }

                $uploadResult = $this->imageUploader->uploadImage($imageFile, $episodeId, true);
                
                if ($uploadResult['success']) {
                    $episodeData['episode_image'] = $uploadResult['filename'];
                }
            }

            // Update episode
            $this->xmlHandler->updateEpisode($podcastId, $episodeId, $episodeData);

            $this->logOperation('UPDATE_EPISODE', $episodeId, $episodeData['title']);

            return [
                'success' => true,
                'message' => 'Episode updated successfully'
            ];
        } catch (Exception $e) {
            $this->logError('UPDATE_EPISODE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete episode
     */
    public function deleteEpisode($podcastId, $episodeId)
    {
        try {
            // Get episodes to find the one being deleted
            $episodes = $this->xmlHandler->getEpisodes($podcastId);
            $episode = null;
            
            foreach ($episodes as $ep) {
                if ($ep['id'] === $episodeId) {
                    $episode = $ep;
                    break;
                }
            }

            if (!$episode) {
                throw new Exception('Episode not found');
            }

            // Delete episode image if exists
            if (!empty($episode['episode_image'])) {
                $this->imageUploader->deleteImage($episode['episode_image']);
            }

            // Delete audio file if exists (check if it's hosted on our server)
            if (!empty($episode['audio_url']) && strpos($episode['audio_url'], AUDIO_URL) !== false) {
                $this->audioUploader->deleteAudio($podcastId, $episodeId);
            }

            // Delete from XML
            $this->xmlHandler->deleteEpisode($podcastId, $episodeId);

            $this->logOperation('DELETE_EPISODE', $episodeId, $episode['title']);

            return [
                'success' => true,
                'message' => 'Episode deleted successfully'
            ];
        } catch (Exception $e) {
            $this->logError('DELETE_EPISODE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get all episodes for a podcast
     */
    public function getEpisodes($podcastId)
    {
        try {
            return $this->xmlHandler->getEpisodes($podcastId);
        } catch (Exception $e) {
            $this->logError('GET_EPISODES_ERROR', $e->getMessage());
            return [];
        }
    }

    /**
     * Validate podcast data
     */
    private function validatePodcastData($data, $excludeId = null)
    {
        // Required fields
        if (empty($data['title'])) {
            return ['valid' => false, 'message' => 'Podcast title is required'];
        }

        if (empty($data['description'])) {
            return ['valid' => false, 'message' => 'Podcast description is required'];
        }

        if (empty($data['author'])) {
            return ['valid' => false, 'message' => 'Author name is required'];
        }

        if (empty($data['email'])) {
            return ['valid' => false, 'message' => 'Email is required for iTunes compliance'];
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => 'Invalid email format'];
        }

        // Check for duplicates
        $existing = $this->getAllPodcasts();
        foreach ($existing as $podcast) {
            if ($excludeId && $podcast['id'] === $excludeId) {
                continue;
            }

            if (strtolower(trim($podcast['title'])) === strtolower(trim($data['title']))) {
                return ['valid' => false, 'message' => 'A podcast with this title already exists'];
            }
        }

        return ['valid' => true, 'message' => 'Validation passed'];
    }

    /**
     * Validate episode data
     */
    private function validateEpisodeData($data, $hasAudioFile = false)
    {
        if (empty($data['title'])) {
            return ['valid' => false, 'message' => 'Episode title is required'];
        }

        // If no audio file was uploaded, validate the URL
        if (!$hasAudioFile) {
            if (empty($data['audio_url'])) {
                return ['valid' => false, 'message' => 'Audio file or URL is required'];
            }

            // Validate URL format only if URL is provided
            if (!filter_var($data['audio_url'], FILTER_VALIDATE_URL)) {
                return ['valid' => false, 'message' => 'Invalid audio URL format'];
            }

            // Check if URL ends with .mp3
            if (!preg_match('/\.mp3$/i', $data['audio_url'])) {
                return ['valid' => false, 'message' => 'Audio URL must point to an MP3 file'];
            }
        }
        // If audio file WAS uploaded, we don't care about the URL field at all

        return ['valid' => true, 'message' => 'Validation passed'];
    }

    /**
     * Rename image file after creation
     */
    private function renameImageFile($oldFilename, $newId)
    {
        $oldPath = COVERS_DIR . '/' . $oldFilename;
        $extension = pathinfo($oldFilename, PATHINFO_EXTENSION);
        $newFilename = $newId . '.' . $extension;
        $newPath = COVERS_DIR . '/' . $newFilename;

        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        return $newFilename;
    }

    /**
     * Log operations
     */
    private function logOperation($operation, $id, $title)
    {
        $logFile = LOGS_DIR . '/operations.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $operation: ID=$id, Title=\"$title\"\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log errors
     */
    private function logError($operation, $message)
    {
        $logFile = LOGS_DIR . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ERROR in $operation: $message\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
