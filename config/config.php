<?php

/**
 * Podcast Directory Management System
 * Configuration File
 */

// Application Settings
define('APP_NAME', 'Podcast Directory Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost:8000');
define('ENVIRONMENT', 'development'); // 'development' or 'production'

// File Paths
define('DATA_DIR', __DIR__ . '/../data');
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('COVERS_DIR', UPLOADS_DIR . '/covers');
define('LOGS_DIR', __DIR__ . '/../logs');

// XML Files
define('PODCASTS_XML', DATA_DIR . '/podcasts.xml');
define('BACKUP_DIR', DATA_DIR . '/backup');

// Image Settings
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('MIN_IMAGE_WIDTH', 1400);
define('MIN_IMAGE_HEIGHT', 1400);
define('MAX_IMAGE_WIDTH', 2400);
define('MAX_IMAGE_HEIGHT', 2400);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Error Messages
define('ERROR_MESSAGES', [
    'image_too_small' => 'Image too small. Minimum size required: 1400x1400 pixels',
    'image_too_large' => 'Image too large. Maximum size allowed: 2400x2400 pixels',
    'invalid_format' => 'Please upload a JPG, PNG, or GIF image',
    'file_too_large' => 'File size too large. Maximum 2MB allowed',
    'upload_failed' => 'Failed to upload image. Please try again.',
    'invalid_url' => 'Please enter a valid RSS feed URL',
    'title_required' => 'Podcast title is required',
    'title_too_long' => 'Title must be less than 200 characters',
    'duplicate_entry' => 'A podcast with this title or feed URL already exists'
]);

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
