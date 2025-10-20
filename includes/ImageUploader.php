<?php
require_once __DIR__ . '/../config/config.php';

/**
 * ImageUploader Class
 * Handles image upload, validation, and management
 */
class ImageUploader
{

    /**
     * Upload and validate image file
     */
    public function uploadImage($file, $podcastId, $isEpisodeImage = false): array
    {
        try {
            // Basic file validation
            $validation = $this->validateUpload($file);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Validate image dimensions (skip for episode images - they're optional and inherit podcast cover)
            if (!$isEpisodeImage) {
                $dimensionValidation = $this->validateImageDimensions($file['tmp_name']);
                if (!$dimensionValidation['valid']) {
                    throw new Exception($dimensionValidation['message']);
                }
            }

            // Generate unique filename
            $filename = $this->generateFilename($file, $podcastId);
            $targetPath = COVERS_DIR . '/' . $filename;

            // Move uploaded file (or copy if it's a downloaded file, not an upload)
            if (is_uploaded_file($file['tmp_name'])) {
                // Real uploaded file
                if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                    throw new Exception(ERROR_MESSAGES['upload_failed']);
                }
            } else {
                // Downloaded file (from cloning)
                if (!copy($file['tmp_name'], $targetPath)) {
                    throw new Exception('Failed to copy image file');
                }
                // Clean up temp file
                @unlink($file['tmp_name']);
            }

            // Set proper permissions
            chmod($targetPath, 0644);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath,
                'url' => APP_URL . '/uploads/covers/' . $filename
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate file upload
     */
    private function validateUpload($file): array
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'message' => $this->getUploadErrorMessage($file['error'])
            ];
        }

        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['file_too_large']
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['invalid_format']
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['invalid_format']
            ];
        }

        return ['valid' => true, 'message' => 'File validation passed'];
    }

    /**
     * Validate image dimensions
     */
    public function validateImageDimensions($imagePath)
    {
        $imageInfo = getimagesize($imagePath);

        if (!$imageInfo) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['invalid_format']
            ];
        }

        $width = $imageInfo[0];
        $height = $imageInfo[1];

        // Check minimum dimensions
        if ($width < MIN_IMAGE_WIDTH || $height < MIN_IMAGE_HEIGHT) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['image_too_small']
            ];
        }

        // Check maximum dimensions
        if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
            return [
                'valid' => false,
                'message' => ERROR_MESSAGES['image_too_large']
            ];
        }

        return [
            'valid' => true,
            'message' => 'Image dimensions are valid',
            'width' => $width,
            'height' => $height
        ];
    }

    /**
     * Generate unique filename
     */
    private function generateFilename($file, $podcastId)
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $timestamp = time();
        $random = substr(uniqid(), -6);

        return $podcastId . '_' . $timestamp . '_' . $random . '.' . $extension;
    }

    /**
     * Delete image file
     */
    public function deleteImage($filename)
    {
        if (empty($filename)) {
            return true; // Nothing to delete
        }

        $filePath = COVERS_DIR . '/' . $filename;

        if (file_exists($filePath)) {
            // Ensure file is writable before deleting
            if (!is_writable($filePath)) {
                @chmod($filePath, 0666);
            }
            
            // Ensure directory is writable
            if (!is_writable(COVERS_DIR)) {
                @chmod(COVERS_DIR, 0777);
            }
            
            if (!@unlink($filePath)) {
                error_log("Warning: Failed to delete image file: $filePath");
                return false;
            }
            return true;
        }

        return true; // File doesn't exist, consider it deleted
    }

    /**
     * Get image information
     */
    public function getImageInfo($filename)
    {
        if (empty($filename)) {
            return null;
        }

        $filePath = COVERS_DIR . '/' . $filename;

        if (!file_exists($filePath)) {
            return null;
        }

        $imageInfo = getimagesize($filePath);
        $fileSize = filesize($filePath);
        
        return [
            'filename' => $filename,
            'path' => $filePath,
            'url' => 'uploads/covers/' . $filename, // Simple relative path - frontend will resolve correctly
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
            'mime_type' => $imageInfo['mime'],
            'file_size' => $fileSize,
            'file_size_formatted' => $this->formatFileSize($fileSize)
        ];
    }

    /**
     * Format file size for display
     */
    private function formatFileSize($bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ERROR_MESSAGES['file_too_large'];
            case UPLOAD_ERR_PARTIAL:
                return 'File was only partially uploaded. Please try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing temporary folder.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk.';
            case UPLOAD_ERR_EXTENSION:
                return 'File upload stopped by extension.';
            default:
                return ERROR_MESSAGES['upload_failed'];
        }
    }

    /**
     * Clean up orphaned image files
     */
    public function cleanupOrphanedImages($validFilenames = []): int
    {
        $files = glob(COVERS_DIR . '/*');
        $deletedCount = 0;

        if ($files === false) {
            return 0;
        }

        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);

                // Skip if filename is in valid list
                if (in_array($filename, $validFilenames)) {
                    continue;
                }

                // Delete orphaned file
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }

    /**
     * Get directory usage statistics
     */
    public function getStorageStats(): array
    {
        try {
            // Ensure directory exists
            if (!is_dir(COVERS_DIR)) {
                return [
                    'file_count' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 bytes',
                    'directory' => COVERS_DIR
                ];
            }

            $files = glob(COVERS_DIR . '/*');
            if ($files === false) {
                $files = [];
            }

            $totalSize = 0;
            $fileCount = 0;

            foreach ($files as $file) {
                if (is_file($file)) {
                    $fileSize = filesize($file);
                    if ($fileSize !== false) {
                        $totalSize += $fileSize;
                        $fileCount++;
                    }
                }
            }

            return [
                'file_count' => $fileCount,
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatFileSize($totalSize),
                'directory' => COVERS_DIR
            ];
        } catch (Exception $e) {
            // Return default stats on error
            return [
                'file_count' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 bytes',
                'directory' => COVERS_DIR
            ];
        }
    }

    /**
     * Validate image from URL (for future use)
     */
    public function validateImageFromUrl($url): array
    {
        $headers = get_headers($url, 1);

        if (!$headers || strpos($headers[0], '200') === false) {
            return ['valid' => false, 'message' => 'Invalid image URL'];
        }

        $contentType = isset($headers['Content-Type']) ? $headers['Content-Type'] : '';
        if (is_array($contentType)) {
            $contentType = $contentType[0];
        }

        if (!in_array($contentType, ALLOWED_MIME_TYPES)) {
            return ['valid' => false, 'message' => 'Invalid image format from URL'];
        }

        return ['valid' => true, 'message' => 'URL image is valid'];
    }
}
