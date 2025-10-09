<?php

/**
 * Utility Functions
 * Common helper functions used throughout the application
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize input data
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    return $data;
}

/**
 * Validate URL format
 */
function isValidUrl($url)
{
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate RSS feed URL (basic check)
 */
function isValidRSSUrl($url)
{
    if (!isValidUrl($url)) {
        return false;
    }

    // Optional: Add more specific RSS validation
    // This could include checking content-type, XML structure, etc.
    return true;
}

/**
 * Format file size for display
 */
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Generate unique ID
 */
function generateUniqueId($prefix = '')
{
    return $prefix . time() . '_' . uniqid();
}

/**
 * Log message to file
 */
function logMessage($message, $level = 'INFO', $logFile = null)
{
    if ($logFile === null) {
        $logFile = LOGS_DIR . '/app.log';
    }

    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Create directory if it doesn't exist
 */
function ensureDirectoryExists($path, $permissions = 0755)
{
    if (!is_dir($path)) {
        return mkdir($path, $permissions, true);
    }
    return true;
}

/**
 * Get client IP address
 */
function getClientIP()
{
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            )) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

/**
 * Rate limiting check
 */
function checkRateLimit($key, $maxRequests = 10, $timeWindow = 60)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $currentTime = time();
    $rateLimitKey = "rate_limit_$key";

    if (!isset($_SESSION[$rateLimitKey])) {
        $_SESSION[$rateLimitKey] = [];
    }

    // Clean old requests outside time window
    $_SESSION[$rateLimitKey] = array_filter(
        $_SESSION[$rateLimitKey],
        function ($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        }
    );

    // Check if limit exceeded
    if (count($_SESSION[$rateLimitKey]) >= $maxRequests) {
        return false;
    }

    // Record current request
    $_SESSION[$rateLimitKey][] = $currentTime;

    return true;
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Redirect with message
 */
function redirectWithMessage($url, $message, $type = 'info')
{
    $params = [
        'message' => urlencode($message),
        'type' => $type
    ];

    $separator = (strpos($url, '?') !== false) ? '&' : '?';
    $redirectUrl = $url . $separator . http_build_query($params);

    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * Get message from URL parameters
 */
function getUrlMessage()
{
    if (isset($_GET['message']) && isset($_GET['type'])) {
        return [
            'message' => urldecode($_GET['message']),
            'type' => $_GET['type']
        ];
    }
    return null;
}

/**
 * Escape data for HTML output
 */
function e($data)
{
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if running in development mode
 */
function isDevelopment()
{
    return (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ||
        (isset($_SERVER['SERVER_NAME']) &&
            (strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
                strpos($_SERVER['SERVER_NAME'], '127.0.0.1') !== false));
}

/**
 * Get current URL
 */
function getCurrentUrl()
{
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';

    return $protocol . '://' . $host . $uri;
}

/**
 * Generate slug from string
 */
function generateSlug($string)
{
    $slug = strtolower(trim($string));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    return $slug;
}

/**
 * Validate image dimensions from file
 */
function validateImageDimensions(
    $filePath,
    $minWidth = MIN_IMAGE_WIDTH,
    $minHeight = MIN_IMAGE_HEIGHT,
    $maxWidth = MAX_IMAGE_WIDTH,
    $maxHeight = MAX_IMAGE_HEIGHT
) {
    $imageInfo = getimagesize($filePath);

    if (!$imageInfo) {
        return [
            'valid' => false,
            'message' => 'Invalid image file'
        ];
    }

    $width = $imageInfo[0];
    $height = $imageInfo[1];

    if ($width < $minWidth || $height < $minHeight) {
        return [
            'valid' => false,
            'message' => "Image too small. Minimum size required: {$minWidth}x{$minHeight} pixels",
            'actual' => "{$width}x{$height}"
        ];
    }

    if ($width > $maxWidth || $height > $maxHeight) {
        return [
            'valid' => false,
            'message' => "Image too large. Maximum size allowed: {$maxWidth}x{$maxHeight} pixels",
            'actual' => "{$width}x{$height}"
        ];
    }

    return [
        'valid' => true,
        'dimensions' => [
            'width' => $width,
            'height' => $height
        ]
    ];
}

/**
 * Clean old backup files
 */
function cleanupOldBackups($directory, $maxFiles = 10)
{
    if (!is_dir($directory)) {
        return 0;
    }

    $files = glob($directory . '/*.xml');
    if (count($files) <= $maxFiles) {
        return 0;
    }

    // Sort by modification time, oldest first
    usort($files, function ($a, $b) {
        return filemtime($a) - filemtime($b);
    });

    $filesToDelete = array_slice($files, 0, count($files) - $maxFiles);
    $deletedCount = 0;

    foreach ($filesToDelete as $file) {
        if (unlink($file)) {
            $deletedCount++;
        }
    }

    return $deletedCount;
}

/**
 * Get system information
 */
function getSystemInfo()
{
    return [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'memory_limit' => ini_get('memory_limit'),
        'disk_free_space' => disk_free_space('.'),
        'disk_total_space' => disk_total_space('.'),
    ];
}

/**
 * Check system requirements
 */
function checkSystemRequirements()
{
    $requirements = [];

    // PHP Version
    $requirements['php_version'] = [
        'name' => 'PHP Version',
        'required' => '7.4.0',
        'current' => PHP_VERSION,
        'status' => version_compare(PHP_VERSION, '7.4.0', '>=')
    ];

    // Required Extensions
    $extensions = ['xml', 'dom', 'gd', 'fileinfo'];
    foreach ($extensions as $ext) {
        $requirements["ext_$ext"] = [
            'name' => "PHP Extension: $ext",
            'required' => 'Enabled',
            'current' => extension_loaded($ext) ? 'Enabled' : 'Disabled',
            'status' => extension_loaded($ext)
        ];
    }

    // Directory Permissions
    $directories = [DATA_DIR, UPLOADS_DIR, LOGS_DIR];
    foreach ($directories as $dir) {
        $dirName = basename($dir);
        $requirements["dir_$dirName"] = [
            'name' => "Directory Writable: $dirName",
            'required' => 'Writable',
            'current' => is_writable($dir) ? 'Writable' : 'Not Writable',
            'status' => is_writable($dir)
        ];
    }

    return $requirements;
}

/**
 * Emergency cleanup function
 */
function emergencyCleanup()
{
    $cleaned = [];

    // Clean temporary files
    $tempFiles = glob(sys_get_temp_dir() . '/podcast_*');
    foreach ($tempFiles as $file) {
        if (is_file($file) && unlink($file)) {
            $cleaned[] = $file;
        }
    }

    // Clean old session files (if using file sessions)
    if (ini_get('session.save_handler') === 'files') {
        $sessionPath = session_save_path() ?: sys_get_temp_dir();
        $sessionFiles = glob($sessionPath . '/sess_*');
        $oneHourAgo = time() - 3600;

        foreach ($sessionFiles as $file) {
            if (is_file($file) && filemtime($file) < $oneHourAgo && unlink($file)) {
                $cleaned[] = $file;
            }
        }
    }

    return $cleaned;
}
