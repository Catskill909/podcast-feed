<?php
/**
 * PodcastAudioDownloader Class
 * Downloads audio files from remote URLs and uses existing AudioUploader
 * to bypass PHP upload limits
 * 
 * Strategy: Download to temp → Pass to AudioUploader → Bypass PHP limits
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/AudioUploader.php';

class PodcastAudioDownloader
{
    private $audioUploader;
    private $maxFileSize;
    private $timeout;
    private $userAgent;

    public function __construct()
    {
        $this->audioUploader = new AudioUploader();
        $this->maxFileSize = 500 * 1024 * 1024; // 500MB
        $this->timeout = 600; // 10 minutes per file (for large files)
        $this->userAgent = 'PodFeed Cloner/1.0';
        
        // Increase PHP execution time for cloning
        @set_time_limit(0); // No limit
        @ini_set('max_execution_time', '0');
    }

    /**
     * Download audio file from URL and upload to local storage
     * Uses existing AudioUploader to bypass PHP limits
     */
    public function downloadAudioFromUrl($url, $podcastId, $episodeId)
    {
        try {
            // Validate URL
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid audio URL');
            }

            // Check remote file size
            $remoteSize = $this->getRemoteFileSize($url);
            if ($remoteSize === false) {
                throw new Exception('Unable to determine remote file size');
            }

            if ($remoteSize > $this->maxFileSize) {
                throw new Exception('File too large: ' . $this->formatFileSize($remoteSize) . ' (max: ' . $this->formatFileSize($this->maxFileSize) . ')');
            }

            // Download to temp location
            $tempFile = $this->downloadToTemp($url);

            if (!$tempFile || !file_exists($tempFile)) {
                throw new Exception('Failed to download audio file');
            }

            // Create file array that looks like $_FILES
            // Detect file extension from URL
            $urlPath = parse_url($url, PHP_URL_PATH);
            $extension = pathinfo($urlPath, PATHINFO_EXTENSION);
            $defaultName = 'audio.' . (in_array(strtolower($extension), ['mp3', 'm4a']) ? strtolower($extension) : 'mp3');
            
            $fileArray = [
                'tmp_name' => $tempFile,
                'name' => basename($urlPath) ?: $defaultName,
                'type' => 'audio/mpeg',
                'size' => filesize($tempFile),
                'error' => UPLOAD_ERR_OK
            ];

            // Use existing AudioUploader (bypasses PHP limits)
            $result = $this->audioUploader->uploadAudio($fileArray, $podcastId, $episodeId);

            // Clean up temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            return $result;

        } catch (Exception $e) {
            error_log('PodcastAudioDownloader Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Download file from URL to temp location
     */
    private function downloadToTemp($url)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'podcast_audio_');
        
        $ch = curl_init($url);
        $fp = fopen($tempFile, 'wb');

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => (defined('ENVIRONMENT') && ENVIRONMENT === 'production'),
            CURLOPT_FAILONERROR => true
        ]);

        $success = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            error_log("Download failed: HTTP $httpCode - $error - URL: $url");
            return false;
        }

        return $tempFile;
    }

    /**
     * Get remote file size using HEAD request
     */
    public function getRemoteFileSize($url)
    {
        $ch = curl_init($url);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_SSL_VERIFYPEER => (defined('ENVIRONMENT') && ENVIRONMENT === 'production')
        ]);

        curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $size < 0) {
            return false;
        }

        return $size;
    }

    /**
     * Validate remote audio file
     */
    public function validateRemoteAudioFile($url)
    {
        // Check URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return [
                'valid' => false,
                'message' => 'Invalid URL format'
            ];
        }

        // Check if URL ends with .mp3 or .m4a
        if (!preg_match('/\.(mp3|m4a)$/i', parse_url($url, PHP_URL_PATH))) {
            return [
                'valid' => false,
                'message' => 'URL must point to an MP3 or M4A file'
            ];
        }

        // Check if file is accessible
        $size = $this->getRemoteFileSize($url);
        if ($size === false) {
            return [
                'valid' => false,
                'message' => 'File not accessible or does not exist'
            ];
        }

        // Check file size
        if ($size > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File too large: ' . $this->formatFileSize($size)
            ];
        }

        return [
            'valid' => true,
            'message' => 'File is valid',
            'size' => $size,
            'size_formatted' => $this->formatFileSize($size)
        ];
    }

    /**
     * Format file size for display
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
     * Download with progress callback
     * For future enhancement - real-time progress tracking
     */
    public function downloadWithProgress($url, $podcastId, $episodeId, $progressCallback = null)
    {
        // Future enhancement: Add progress tracking
        // For now, just use standard download
        return $this->downloadAudioFromUrl($url, $podcastId, $episodeId);
    }
}
