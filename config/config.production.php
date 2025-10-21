<?php

/**
 * Production Configuration Template
 * Copy this to config.php and update with your production values
 */

// Load environment variables if .env file exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

// Application Settings
define('APP_NAME', getenv('APP_NAME') ?: 'PodFeed Builder');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'https://yourdomain.com');
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'production');

// File Paths
define('DATA_DIR', __DIR__ . '/../data');
define('UPLOADS_DIR', __DIR__ . '/../uploads');
define('COVERS_DIR', UPLOADS_DIR . '/covers');
define('LOGS_DIR', __DIR__ . '/../logs');

// XML Files
define('PODCASTS_XML', DATA_DIR . '/podcasts.xml');
define('BACKUP_DIR', DATA_DIR . '/backup');

// Image Settings
define('MAX_FILE_SIZE', (int)(getenv('MAX_FILE_SIZE') ?: 2 * 1024 * 1024)); // 2MB
define('MIN_IMAGE_WIDTH', (int)(getenv('MIN_IMAGE_WIDTH') ?: 1400));
define('MIN_IMAGE_HEIGHT', (int)(getenv('MIN_IMAGE_HEIGHT') ?: 1400));
define('MAX_IMAGE_WIDTH', (int)(getenv('MAX_IMAGE_WIDTH') ?: 3000));
define('MAX_IMAGE_HEIGHT', (int)(getenv('MAX_IMAGE_HEIGHT') ?: 3000));
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

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

// Timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'UTC');

// Error Reporting - PRODUCTION SETTINGS
error_reporting((int)(getenv('ERROR_REPORTING') ?: 0));
ini_set('display_errors', (string)(getenv('DISPLAY_ERRORS') ?: '0'));

// Log errors to file instead of displaying
ini_set('log_errors', '1');
ini_set('error_log', LOGS_DIR . '/error.log');

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Session Security
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1'); // Enable if using HTTPS
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Strict');

// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Create .gitkeep files in empty directories
$keepFiles = [
    COVERS_DIR . '/.gitkeep',
    LOGS_DIR . '/.gitkeep',
    BACKUP_DIR . '/.gitkeep'
];
foreach ($keepFiles as $file) {
    if (!file_exists($file)) {
        touch($file);
    }
}
