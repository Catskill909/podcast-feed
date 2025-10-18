<?php
/**
 * Production Environment Diagnostic Script
 * Check PHP configuration and directory permissions
 */

header('Content-Type: text/plain');

echo "=== PRODUCTION ENVIRONMENT DIAGNOSTICS ===\n\n";

// 1. PHP Configuration
echo "--- PHP CONFIGURATION ---\n";
echo "PHP Version: " . phpversion() . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n\n";

// 2. Directory Existence
echo "--- DIRECTORY CHECKS ---\n";
$dirs = [
    'uploads/audio',
    'uploads/covers',
    'data',
    'logs'
];

foreach ($dirs as $dir) {
    $exists = is_dir($dir) ? '✓ EXISTS' : '✗ MISSING';
    $writable = is_writable($dir) ? '✓ WRITABLE' : '✗ NOT WRITABLE';
    echo "$dir: $exists, $writable\n";
    
    if (!is_dir($dir)) {
        echo "  → Attempting to create...\n";
        if (@mkdir($dir, 0755, true)) {
            echo "  → Created successfully!\n";
        } else {
            echo "  → FAILED to create\n";
        }
    }
}
echo "\n";

// 3. File Permissions
echo "--- FILE PERMISSIONS ---\n";
$files = [
    'data/self-hosted-podcasts.xml',
    'logs/error.log'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        $writable = is_writable($file) ? '✓ WRITABLE' : '✗ NOT WRITABLE';
        echo "$file: $writable (permissions: $perms)\n";
    } else {
        echo "$file: ✗ DOES NOT EXIST\n";
    }
}
echo "\n";

// 4. Extensions
echo "--- PHP EXTENSIONS ---\n";
$required = ['xml', 'dom', 'gd', 'fileinfo', 'mbstring'];
foreach ($required as $ext) {
    $loaded = extension_loaded($ext) ? '✓ LOADED' : '✗ MISSING';
    echo "$ext: $loaded\n";
}
echo "\n";

// 5. Disk Space
echo "--- DISK SPACE ---\n";
$free = disk_free_space('.');
$total = disk_total_space('.');
echo "Free: " . round($free / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Total: " . round($total / 1024 / 1024 / 1024, 2) . " GB\n";
echo "Used: " . round(($total - $free) / 1024 / 1024 / 1024, 2) . " GB\n\n";

// 6. Error Log Check
echo "--- RECENT ERROR LOG (last 20 lines) ---\n";
if (file_exists('logs/error.log')) {
    $lines = file('logs/error.log');
    $recent = array_slice($lines, -20);
    echo implode('', $recent);
} else {
    echo "No error log found\n";
}

echo "\n=== END DIAGNOSTICS ===\n";
