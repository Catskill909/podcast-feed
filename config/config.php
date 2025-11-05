<?php

/**
 * Podcast Directory Management System
 * Configuration File - Auto-detects dev vs production
 */

// Auto-detect environment
// Check for localhost
$isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? 'localhost', ['localhost', '127.0.0.1', '::1']);

// Force production mode for password protection
define('ENVIRONMENT', $isLocalhost ? 'development' : 'production');

// Auto-detect APP_URL with proper HTTPS detection
// Check multiple sources for HTTPS (handles proxies/load balancers)
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
);
$protocol = $isHttps ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
define('APP_URL', $protocol . '://' . $host);

// Application Settings
define('APP_NAME', 'PodFeed Builder');
define('APP_VERSION', '1.0.0');
define('ASSETS_VERSION', '20251017_1852'); // Update this when JS/CSS changes

// File Paths
define('DATA_DIR', __DIR__ . '/../data');
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('COVERS_DIR', UPLOADS_DIR . '/covers');
define('AUDIO_DIR', UPLOADS_DIR . '/audio');
define('LOGS_DIR', __DIR__ . '/../logs');

// URL Paths
define('UPLOADS_URL', APP_URL . '/uploads');
define('COVERS_URL', UPLOADS_URL . '/covers');
define('AUDIO_URL', UPLOADS_URL . '/audio');

// XML Files
define('PODCASTS_XML', DATA_DIR . '/podcasts.xml');
define('BACKUP_DIR', DATA_DIR . '/backup');

// Image Settings
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MIN_IMAGE_WIDTH', 1400);
define('MIN_IMAGE_HEIGHT', 1400);
define('MAX_IMAGE_WIDTH', 3000);
define('MAX_IMAGE_HEIGHT', 3000);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Audio Settings
define('MAX_AUDIO_FILE_SIZE', 500 * 1024 * 1024); // 500MB
define('ALLOWED_AUDIO_EXTENSIONS', ['mp3']);
define('ALLOWED_AUDIO_MIME_TYPES', ['audio/mpeg', 'audio/mp3']);

// Error Messages
define('ERROR_MESSAGES', [
    'image_too_small' => 'Image too small. Minimum size required: 1400x1400 pixels',
    'image_too_large' => 'Image too large. Maximum size allowed: 3000x3000 pixels',
    'invalid_format' => 'Please upload a JPG, PNG, or GIF image',
    'file_too_large' => 'File size too large. Maximum 2MB allowed',
    'upload_failed' => 'Failed to upload image. Please try again.',
    'invalid_url' => 'Please enter a valid RSS feed URL',
    'title_required' => 'Podcast title is required',
    'title_too_long' => 'Title must be less than 200 characters',
    'duplicate_entry' => 'A podcast with this title or feed URL already exists'
]);

// Timezone - EST/EDT for accurate relative date calculations
date_default_timezone_set('America/New_York');

// Error Reporting - Auto-configured based on environment
if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOGS_DIR . '/error.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, AUDIO_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
