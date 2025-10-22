<?php
require_once __DIR__ . '/../config/config.php';

/**
 * AdsImageUploader Class
 * Handles ad banner image upload with strict dimension validation
 */
class AdsImageUploader
{
    private $webAdsDimensions = ['width' => 728, 'height' => 90];
    private $mobileAdsDimensions = [
        'phone' => ['width' => 320, 'height' => 50],
        'tablet' => ['width' => 728, 'height' => 90]
    ];
    private $maxFileSize = 2 * 1024 * 1024; // 2MB
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    /**
     * Upload web banner ad (728x90)
     */
    public function uploadWebAd($file): array
    {
        return $this->uploadAd($file, 'web');
    }

    /**
     * Upload mobile banner ad (320x50)
     */
    public function uploadMobileAd($file): array
    {
        return $this->uploadAd($file, 'mobile');
    }

    /**
     * Upload ad banner with validation
     */
    private function uploadAd($file, $type): array
    {
        try {
            // Basic file validation
            $validation = $this->validateUpload($file);
            if (!$validation['valid']) {
                throw new Exception($validation['message']);
            }

            // Strict dimension validation
            $dimensionValidation = $this->validateDimensions($file['tmp_name'], $type);
            if (!$dimensionValidation['valid']) {
                throw new Exception($dimensionValidation['message']);
            }

            // Generate unique filename
            $filename = $this->generateFilename($file, $type);
            $targetDir = $this->getTargetDirectory($type);
            $targetPath = $targetDir . '/' . $filename;

            // Ensure directory exists
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to upload image. Please try again.');
            }

            // Set proper permissions
            chmod($targetPath, 0644);

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $targetPath,
                'url' => $this->getImageUrl($filename, $type)
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
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size too large. Maximum 2MB allowed.'
            ];
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Please upload JPG, PNG, or GIF.'
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'Invalid file extension. Allowed: JPG, PNG, GIF.'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate image dimensions (STRICT)
     */
    private function validateDimensions($tmpFile, $type): array
    {
        $imageInfo = getimagesize($tmpFile);
        
        if ($imageInfo === false) {
            return [
                'valid' => false,
                'message' => 'Invalid image file.'
            ];
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        if ($type === 'web') {
            $required = $this->webAdsDimensions;
            $typeName = 'Web Banner';
            
            if ($width !== $required['width'] || $height !== $required['height']) {
                return [
                    'valid' => false,
                    'message' => "{$typeName} must be exactly {$required['width']}x{$required['height']}px. Your image is {$width}x{$height}px.",
                    'actual_dimensions' => "{$width}x{$height}",
                    'required_dimensions' => "{$required['width']}x{$required['height']}"
                ];
            }
        } else {
            // Mobile: accept either phone (320x50) or tablet (728x90)
            $phoneSize = $this->mobileAdsDimensions['phone'];
            $tabletSize = $this->mobileAdsDimensions['tablet'];
            
            $isPhone = ($width === $phoneSize['width'] && $height === $phoneSize['height']);
            $isTablet = ($width === $tabletSize['width'] && $height === $tabletSize['height']);
            
            if (!$isPhone && !$isTablet) {
                return [
                    'valid' => false,
                    'message' => "Mobile Banner must be either {$phoneSize['width']}x{$phoneSize['height']}px (Phone) or {$tabletSize['width']}x{$tabletSize['height']}px (Tablet). Your image is {$width}x{$height}px.",
                    'actual_dimensions' => "{$width}x{$height}",
                    'required_dimensions' => "{$phoneSize['width']}x{$phoneSize['height']} or {$tabletSize['width']}x{$tabletSize['height']}"
                ];
            }
        }
        
        return ['valid' => true];
    }

    /**
     * Generate unique filename
     */
    private function generateFilename($file, $type): string
    {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $prefix = $type === 'web' ? 'web_ad' : 'mobile_ad';
        $timestamp = time();
        $random = uniqid();
        
        return "{$prefix}_{$timestamp}_{$random}.{$extension}";
    }

    /**
     * Get target directory for ad type
     */
    private function getTargetDirectory($type): string
    {
        $baseDir = UPLOADS_DIR . '/ads';
        return $type === 'web' ? $baseDir . '/web' : $baseDir . '/mobile';
    }

    /**
     * Get image URL
     */
    private function getImageUrl($filename, $type): string
    {
        $path = $type === 'web' ? 'web' : 'mobile';
        return APP_URL . "/uploads/ads/{$path}/{$filename}";
    }

    /**
     * Delete ad image file
     */
    public function deleteAdImage($filepath): bool
    {
        if (file_exists($filepath)) {
            return @unlink($filepath);
        }
        return false;
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage($errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'File is too large. Maximum 2MB allowed.';
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
                return 'Unknown upload error.';
        }
    }

    /**
     * Get required dimensions for ad type
     */
    public function getRequiredDimensions($type): array
    {
        return $type === 'web' ? $this->webAdsDimensions : $this->mobileAdsDimensions;
    }
}
