<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/XMLHandler.php';
require_once __DIR__ . '/ImageUploader.php';

/**
 * PodcastManager Class
 * Main class for managing podcast CRUD operations
 */
class PodcastManager
{
    private $xmlHandler;
    private $imageUploader;

    public function __construct()
    {
        $this->xmlHandler = new XMLHandler();
        $this->imageUploader = new ImageUploader();
    }

    /**
     * Create a new podcast entry
     */
    public function createPodcast($data, $imageFile = null)
    {
        try {
            // Validate input data
            $validation = $this->validatePodcastData($data);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            $coverImage = '';

            // Handle image upload if provided
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                // Generate temporary ID for filename
                $tempId = 'pod_' . time() . '_' . uniqid();

                $uploadResult = $this->imageUploader->uploadImage($imageFile, $tempId);
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }

                $coverImage = $uploadResult['filename'];
            }
            // Handle RSS image URL if provided (from RSS import)
            elseif (!empty($data['rss_image_url'])) {
                require_once __DIR__ . '/RssFeedParser.php';
                
                // Generate temporary ID for filename
                $tempId = 'pod_' . time() . '_' . uniqid();
                
                $parser = new RssFeedParser();
                $downloadResult = $parser->downloadCoverImage($data['rss_image_url'], $tempId);
                
                if ($downloadResult['success']) {
                    $coverImage = $downloadResult['filename'];
                } else {
                    // Log the error but continue without image
                    error_log('RSS Image Download Failed: ' . ($downloadResult['error'] ?? 'Unknown error') . ' - URL: ' . $data['rss_image_url']);
                }
            }

            // Prepare data for XML
            $podcastData = [
                'title' => trim($data['title']),
                'feed_url' => trim($data['feed_url']),
                'description' => trim($data['description'] ?? ''),
                'cover_image' => $coverImage
            ];

            // Add to XML
            $podcastId = $this->xmlHandler->addPodcast($podcastData);

            // If we used a temporary ID for the image, rename it to use the real ID
            if ($coverImage && strpos($coverImage, 'pod_') === 0) {
                $newFilename = $this->renameImageFile($coverImage, $podcastId);

                // Update XML with correct filename
                $this->xmlHandler->updatePodcast($podcastId, ['cover_image' => $newFilename]);
            }

            $this->logOperation('CREATE', $podcastId, $data['title']);

            return [
                'success' => true,
                'id' => $podcastId,
                'message' => 'Podcast imported successfully'
            ];
        } catch (Exception $e) {
            $this->logError('CREATE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update existing podcast entry
     */
    public function updatePodcast($id, $data, $imageFile = null)
    {
        try {
            // Check if podcast exists
            $existingPodcast = $this->xmlHandler->getPodcast($id);
            if (!$existingPodcast) {
                throw new Exception('Podcast not found');
            }

            // Validate input data
            $validation = $this->validatePodcastData($data, $id);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            $updateData = [
                'title' => trim($data['title']),
                'feed_url' => trim($data['feed_url']),
                'description' => trim($data['description'] ?? '')
            ];

            // Handle image upload if provided
            if ($imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = $this->imageUploader->uploadImage($imageFile, $id);
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }

                // Delete old image if it exists
                if ($existingPodcast['cover_image']) {
                    $this->imageUploader->deleteImage($existingPodcast['cover_image']);
                }

                $updateData['cover_image'] = $uploadResult['filename'];
            }

            // Update XML
            $this->xmlHandler->updatePodcast($id, $updateData);

            $this->logOperation('UPDATE', $id, $data['title']);

            return [
                'success' => true,
                'id' => $id,
                'message' => 'Podcast updated successfully'
            ];
        } catch (Exception $e) {
            $this->logError('UPDATE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete podcast entry
     */
    public function deletePodcast($id)
    {
        try {
            // Get podcast data before deletion
            $podcast = $this->xmlHandler->getPodcast($id);
            if (!$podcast) {
                throw new Exception('Podcast not found');
            }

            // Delete image file if it exists
            if ($podcast['cover_image']) {
                $this->imageUploader->deleteImage($podcast['cover_image']);
            }

            // Delete from XML
            $this->xmlHandler->deletePodcast($id);

            $this->logOperation('DELETE', $id, $podcast['title']);

            return [
                'success' => true,
                'message' => 'Podcast deleted successfully'
            ];
        } catch (Exception $e) {
            $this->logError('DELETE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update podcast status
     */
    public function updatePodcastStatus($id, $status)
    {
        try {
            // Check if podcast exists
            $existingPodcast = $this->xmlHandler->getPodcast($id);
            if (!$existingPodcast) {
                throw new Exception('Podcast not found');
            }

            // Validate status
            if (!in_array($status, ['active', 'inactive'])) {
                throw new Exception('Invalid status value');
            }

            // Update status
            $this->xmlHandler->updatePodcast($id, ['status' => $status]);

            $this->logOperation('STATUS_CHANGE', $id, $existingPodcast['title'] . ' - ' . $status);

            $statusText = $status === 'active' ? 'activated' : 'deactivated';
            return [
                'success' => true,
                'message' => "Podcast {$statusText} successfully"
            ];
        } catch (Exception $e) {
            $this->logError('STATUS_UPDATE_ERROR', $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get single podcast by ID
     */
    public function getPodcast($id)
    {
        try {
            $podcast = $this->xmlHandler->getPodcast($id);
            if (!$podcast) {
                return null;
            }

            // Add image information
            if ($podcast['cover_image']) {
                $imageInfo = $this->imageUploader->getImageInfo($podcast['cover_image']);
                $podcast['image_info'] = $imageInfo;
            }

            return $podcast;
        } catch (Exception $e) {
            $this->logError('GET_ERROR', $e->getMessage());
            return null;
        }
    }

    /**
     * Get all podcasts
     */
    public function getAllPodcasts($includeImageInfo = false): array
    {
        try {
            $podcasts = $this->xmlHandler->getAllPodcasts();

            // Ensure we always have an array
            if (!is_array($podcasts)) {
                $podcasts = [];
            }

            if ($includeImageInfo) {
                foreach ($podcasts as &$podcast) {
                    if ($podcast['cover_image']) {
                        $imageInfo = $this->imageUploader->getImageInfo($podcast['cover_image']);
                        $podcast['image_info'] = $imageInfo;
                    }
                }
            }

            return $podcasts;
        } catch (Exception $e) {
            $this->logError('GET_ALL_ERROR', $e->getMessage());
            return [];
        }
    }

    /**
     * Search podcasts
     */
    public function searchPodcasts($query)
    {
        try {
            $allPodcasts = $this->getAllPodcasts();
            $results = [];

            $searchTerm = strtolower(trim($query));

            foreach ($allPodcasts as $podcast) {
                if (
                    strpos(strtolower($podcast['title']), $searchTerm) !== false ||
                    strpos(strtolower($podcast['feed_url']), $searchTerm) !== false ||
                    strpos(strtolower($podcast['description'] ?? ''), $searchTerm) !== false
                ) {
                    $results[] = $podcast;
                }
            }

            return $results;
        } catch (Exception $e) {
            $this->logError('SEARCH_ERROR', $e->getMessage());
            return [];
        }
    }

    /**
     * Get RSS feed XML
     */
    public function getRSSFeed()
    {
        try {
            return $this->xmlHandler->generateRSSFeed();
        } catch (Exception $e) {
            $this->logError('RSS_ERROR', $e->getMessage());
            return false;
        }
    }

    /**
     * Get system statistics
     */
    public function getStats(): array
    {
        try {
            $podcasts = $this->getAllPodcasts();
            $storageStats = $this->imageUploader->getStorageStats();

            // Ensure we have arrays to work with
            if (!is_array($podcasts)) {
                $podcasts = [];
            }
            if (!is_array($storageStats)) {
                $storageStats = [
                    'file_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 bytes',
                    'directory' => ''
                ];
            }

            return [
                'total_podcasts' => count($podcasts),
                'active_podcasts' => count(array_filter($podcasts, function ($p) {
                    return isset($p['status']) && $p['status'] === 'active';
                })),
                'storage_stats' => $storageStats,
                'last_updated' => !empty($podcasts) ? max(array_column($podcasts, 'updated_date')) : null
            ];
        } catch (Exception $e) {
            $this->logError('STATS_ERROR', $e->getMessage());
            return [
                'total_podcasts' => 0,
                'active_podcasts' => 0,
                'storage_stats' => [
                    'file_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 bytes',
                    'directory' => ''
                ],
                'last_updated' => null
            ];
        }
    }

    /**
     * Validate podcast data
     */
    private function validatePodcastData($data, $excludeId = null)
    {
        // Check required fields
        if (empty($data['title'])) {
            return ['valid' => false, 'message' => ERROR_MESSAGES['title_required']];
        }

        if (empty($data['feed_url'])) {
            return ['valid' => false, 'message' => ERROR_MESSAGES['invalid_url']];
        }

        // Validate title length
        if (strlen($data['title']) > 200) {
            return ['valid' => false, 'message' => ERROR_MESSAGES['title_too_long']];
        }

        // Validate URL format
        if (!filter_var($data['feed_url'], FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'message' => ERROR_MESSAGES['invalid_url']];
        }

        // Check for duplicates (excluding current podcast if updating)
        $existingPodcasts = $this->getAllPodcasts();
        foreach ($existingPodcasts as $podcast) {
            if ($excludeId && $podcast['id'] === $excludeId) {
                continue;
            }

            if (
                strtolower(trim($podcast['title'])) === strtolower(trim($data['title'])) ||
                trim($podcast['feed_url']) === trim($data['feed_url'])
            ) {
                return ['valid' => false, 'message' => ERROR_MESSAGES['duplicate_entry']];
            }
        }

        return ['valid' => true, 'message' => 'Validation passed'];
    }

    /**
     * Rename image file after podcast creation
     */
    private function renameImageFile($oldFilename, $newPodcastId)
    {
        $oldPath = COVERS_DIR . '/' . $oldFilename;
        
        // Extract file extension from old filename
        $extension = pathinfo($oldFilename, PATHINFO_EXTENSION);
        
        // Create new filename with podcast ID
        $newFilename = $newPodcastId . '.' . $extension;
        $newPath = COVERS_DIR . '/' . $newFilename;

        if (file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        return $newFilename;
    }

    /**
     * Log operations
     */
    private function logOperation($operation, $podcastId, $title)
    {
        $logFile = LOGS_DIR . '/operations.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $operation: ID=$podcastId, Title=\"$title\"\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Log errors
     */
    private function logError($operation, $message)
    {
        $logFile = LOGS_DIR . '/error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] ERROR in $operation: $message\n";

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Clean up orphaned files
     */
    public function cleanup()
    {
        try {
            $podcasts = $this->getAllPodcasts();
            $validFilenames = array_filter(array_column($podcasts, 'cover_image'));

            $deletedCount = $this->imageUploader->cleanupOrphanedImages($validFilenames);

            return [
                'success' => true,
                'deleted_files' => $deletedCount,
                'message' => "Cleaned up $deletedCount orphaned image files"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
