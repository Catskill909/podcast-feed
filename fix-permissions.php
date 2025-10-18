<?php
/**
 * Fix Directory Permissions
 * Run this once to fix upload directory permissions
 * DELETE THIS FILE AFTER RUNNING!
 */

header('Content-Type: text/plain');

echo "=== FIXING DIRECTORY PERMISSIONS ===\n\n";

$directories = [
    'uploads/audio',
    'uploads/covers',
    'data',
    'data/backup',
    'logs'
];

foreach ($directories as $dir) {
    echo "Fixing: $dir\n";
    
    // Create if doesn't exist
    if (!is_dir($dir)) {
        echo "  → Creating directory...\n";
        if (@mkdir($dir, 0755, true)) {
            echo "  ✓ Created\n";
        } else {
            echo "  ✗ Failed to create\n";
            continue;
        }
    }
    
    // Try to fix permissions
    echo "  → Setting permissions to 0755...\n";
    if (@chmod($dir, 0755)) {
        echo "  ✓ Permissions set\n";
    } else {
        echo "  ✗ Could not set permissions (might need server admin)\n";
    }
    
    // Check if writable now
    if (is_writable($dir)) {
        echo "  ✓ Directory is now writable!\n";
    } else {
        echo "  ✗ Still not writable - server configuration issue\n";
        echo "  → Try running: chown -R www-data:www-data $dir\n";
    }
    
    echo "\n";
}

echo "=== DONE ===\n";
echo "\n⚠️  DELETE THIS FILE NOW!\n";
