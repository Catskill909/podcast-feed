<?php
/**
 * AudioUploader Class
 * Handles MP3 audio file uploads for self-hosted podcasts
 * Works in both local development and Coolify production
 */

class AudioUploader
{
    private $audioDir;
    private $audioUrl;
    private $maxFileSize;
    private $allowedMimeTypes = ['audio/mpeg', 'audio/mp3'];
    private $allowedExtensions = ['mp3'];

    public function __construct()
    {
        // Audio directory in uploads (persistent volume in Coolify)
        $this->audioDir = UPLOADS_DIR . '/audio';
        $this->audioUrl = UPLOADS_URL . '/audio';
        
        // Max file size: 500MB (configurable)
        $this->maxFileSize = 500 * 1024 * 1024;
        
        // Ensure audio directory exists
        $this->ensureDirectoryExists();
    }

    /**
     * Upload audio file for an episode
     */
    public function uploadAudio($file, $podcastId, $episodeId)
    {
        try {
            // Validate file
            $validation = $this->validateAudioFile($file);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }

            // Create podcast directory if it doesn't exist
            $podcastDir = $this->audioDir . '/' . $podcastId;
            if (!file_exists($podcastDir)) {
                mkdir($podcastDir, 0755, true);
                chmod($podcastDir, 0755);
            }

            // Generate filename
            $filename = $episodeId . '.mp3';
            $filepath = $podcastDir . '/' . $filename;

            // Move uploaded file (or copy if it's a downloaded file, not an upload)
            if (is_uploaded_file($file['tmp_name'])) {
                // Real uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    return [
                        'success' => false,
                        'message' => 'Failed to move uploaded file'
                    ];
                }
            } else {
                // Downloaded file (from cloning)
                if (!copy($file['tmp_name'], $filepath)) {
                    return [
                        'success' => false,
                        'message' => 'Failed to copy audio file'
                    ];
                }
                // Clean up temp file
                @unlink($file['tmp_name']);
            }

            // Set permissions
            chmod($filepath, 0644);

            // Get audio info
            $audioInfo = $this->getAudioInfo($podcastId, $episodeId);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => $this->audioUrl . '/' . $podcastId . '/' . $filename,
                'duration' => $audioInfo['duration'] ?? 0,
                'file_size' => $audioInfo['file_size'] ?? 0,
                'message' => 'Audio file uploaded successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Upload error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete audio file
     */
    public function deleteAudio($podcastId, $episodeId)
    {
        try {
            $filepath = $this->audioDir . '/' . $podcastId . '/' . $episodeId . '.mp3';
            
            if (file_exists($filepath)) {
                unlink($filepath);
                
                // Remove podcast directory if empty
                $podcastDir = $this->audioDir . '/' . $podcastId;
                if (is_dir($podcastDir) && count(scandir($podcastDir)) == 2) {
                    rmdir($podcastDir);
                }
                
                return [
                    'success' => true,
                    'message' => 'Audio file deleted successfully'
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Audio file not found'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Delete error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get audio file information
     */
    public function getAudioInfo($podcastId, $episodeId)
    {
        $filepath = $this->audioDir . '/' . $podcastId . '/' . $episodeId . '.mp3';
        
        if (!file_exists($filepath)) {
            return null;
        }

        $info = [
            'exists' => true,
            'file_size' => filesize($filepath),
            'file_size_formatted' => $this->formatFileSize(filesize($filepath)),
            'url' => $this->audioUrl . '/' . $podcastId . '/' . $episodeId . '.mp3',
            'path' => $filepath,
            'duration' => 0,
            'duration_formatted' => '00:00:00'
        ];

        // Try to get duration (basic method - works without external libraries)
        $duration = $this->getAudioDuration($filepath);
        if ($duration > 0) {
            $info['duration'] = $duration;
            $info['duration_formatted'] = $this->formatDuration($duration);
        }

        return $info;
    }

    /**
     * Get audio duration (basic MP3 parsing)
     */
    private function getAudioDuration($filepath)
    {
        try {
            // Basic MP3 duration calculation
            // This is a simplified version - for production you might want getID3 library
            $filesize = filesize($filepath);
            $handle = fopen($filepath, 'rb');
            
            if (!$handle) {
                return 0;
            }

            // Read first 4 bytes to check for ID3 tag
            $header = fread($handle, 10);
            $offset = 0;

            // Skip ID3v2 tag if present
            if (substr($header, 0, 3) == 'ID3') {
                $id3v2_flags = ord($header[5]);
                $id3v2_size = (ord($header[6]) << 21) | (ord($header[7]) << 14) | (ord($header[8]) << 7) | ord($header[9]);
                $offset = $id3v2_size + 10;
                fseek($handle, $offset);
            }

            // Read frame header
            $frame_header = fread($handle, 4);
            fclose($handle);

            if (strlen($frame_header) < 4) {
                return 0;
            }

            // Parse frame header
            $header_int = (ord($frame_header[0]) << 24) | (ord($frame_header[1]) << 16) | (ord($frame_header[2]) << 8) | ord($frame_header[3]);
            
            // Check for frame sync (11 bits set to 1)
            if (($header_int & 0xFFE00000) != 0xFFE00000) {
                return 0;
            }

            // Extract bitrate and sample rate
            $version = ($header_int >> 19) & 0x03;
            $layer = ($header_int >> 17) & 0x03;
            $bitrate_index = ($header_int >> 12) & 0x0F;
            $samplerate_index = ($header_int >> 10) & 0x03;

            // Bitrate table (MPEG1 Layer 3)
            $bitrates = [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 0];
            $samplerates = [44100, 48000, 32000, 0];

            if ($bitrate_index == 0 || $bitrate_index == 15 || $samplerate_index == 3) {
                return 0;
            }

            $bitrate = $bitrates[$bitrate_index] * 1000;
            $samplerate = $samplerates[$samplerate_index];

            if ($bitrate > 0) {
                // Calculate duration: (filesize * 8) / bitrate
                $duration = ($filesize * 8) / $bitrate;
                return round($duration);
            }

            return 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Validate audio file
     */
    private function validateAudioFile($file)
    {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in HTML form',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
            ];
            
            $message = $errorMessages[$file['error']] ?? 'Unknown upload error';
            
            return [
                'valid' => false,
                'message' => $message
            ];
        }

        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return [
                'valid' => false,
                'message' => 'File size exceeds maximum allowed size of ' . $this->formatFileSize($this->maxFileSize)
            ];
        }

        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return [
                'valid' => false,
                'message' => 'Invalid file type. Only MP3 files are allowed.'
            ];
        }

        // Check MIME type (be lenient for downloaded files)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        // Accept audio/mpeg, audio/mp3, and application/octet-stream (for downloads)
        $acceptableMimeTypes = ['audio/mpeg', 'audio/mp3', 'application/octet-stream', 'audio/x-mpeg'];
        
        if (!in_array($mimeType, $acceptableMimeTypes)) {
            error_log("AudioUploader: Rejecting file with MIME type: $mimeType");
            return [
                'valid' => false,
                'message' => 'Invalid file format. File must be an MP3 audio file. (Got: ' . $mimeType . ')'
            ];
        }

        // Check if file is actually an MP3 (magic number check)
        // Skip this for downloaded files (not real uploads) - they may have different headers
        if (is_uploaded_file($file['tmp_name'])) {
            $handle = fopen($file['tmp_name'], 'rb');
            $header = fread($handle, 3);
            fclose($handle);

            // Check for ID3 tag or MPEG frame sync
            $isMP3 = (substr($header, 0, 3) === 'ID3') || 
                     (ord($header[0]) === 0xFF && (ord($header[1]) & 0xE0) === 0xE0);

            if (!$isMP3) {
                return [
                    'valid' => false,
                    'message' => 'File does not appear to be a valid MP3 file.'
                ];
            }
        }

        return [
            'valid' => true,
            'message' => 'File is valid'
        ];
    }

    /**
     * Ensure audio directory exists with proper permissions
     */
    private function ensureDirectoryExists()
    {
        if (!file_exists($this->audioDir)) {
            mkdir($this->audioDir, 0755, true);
            chmod($this->audioDir, 0755);
            
            // Create .htaccess for security
            $htaccess = $this->audioDir . '/.htaccess';
            $htaccessContent = <<<EOT
# Allow MP3 downloads
<FilesMatch "\\.mp3$">
    Header set Content-Type "audio/mpeg"
    Header set Accept-Ranges "bytes"
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Prevent PHP execution
<FilesMatch "\\.php$">
    Deny from all
</FilesMatch>
EOT;
            file_put_contents($htaccess, $htaccessContent);
            chmod($htaccess, 0644);
        }
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
     * Format duration for display (HH:MM:SS)
     */
    private function formatDuration($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    /**
     * Get storage statistics
     */
    public function getStorageStats()
    {
        $totalSize = 0;
        $fileCount = 0;

        if (is_dir($this->audioDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->audioDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'mp3') {
                    $totalSize += $file->getSize();
                    $fileCount++;
                }
            }
        }

        return [
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'directory' => $this->audioDir
        ];
    }

    /**
     * Clean up orphaned audio files
     */
    public function cleanupOrphanedFiles($validPodcastIds)
    {
        $deletedCount = 0;

        if (!is_dir($this->audioDir)) {
            return $deletedCount;
        }

        $podcastDirs = scandir($this->audioDir);

        foreach ($podcastDirs as $dir) {
            if ($dir === '.' || $dir === '..' || $dir === '.htaccess') {
                continue;
            }

            $fullPath = $this->audioDir . '/' . $dir;

            if (is_dir($fullPath) && !in_array($dir, $validPodcastIds)) {
                // Delete entire podcast directory
                $files = scandir($fullPath);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        unlink($fullPath . '/' . $file);
                        $deletedCount++;
                    }
                }
                rmdir($fullPath);
            }
        }

        return $deletedCount;
    }
}
