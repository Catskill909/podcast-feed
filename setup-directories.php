<?php
/**
 * Setup Script - Create Required Directories
 * Run this once after deployment to set up directory structure
 */

$directories = [
    'uploads/audio',
    'uploads/covers',
    'data/backup',
    'logs'
];

echo "<h2>Directory Setup</h2>";
echo "<pre>";

foreach ($directories as $dir) {
    echo "\nChecking: $dir\n";
    
    if (!is_dir($dir)) {
        echo "  → Creating directory...\n";
        if (mkdir($dir, 0755, true)) {
            echo "  ✓ Created successfully\n";
        } else {
            echo "  ✗ FAILED to create\n";
            echo "  Error: " . error_get_last()['message'] . "\n";
        }
    } else {
        echo "  ✓ Already exists\n";
    }
    
    // Check if writable
    if (is_writable($dir)) {
        echo "  ✓ Writable\n";
    } else {
        echo "  ✗ NOT writable\n";
        echo "  Attempting to fix permissions...\n";
        if (chmod($dir, 0755)) {
            echo "  ✓ Permissions fixed\n";
        } else {
            echo "  ✗ Could not fix permissions\n";
        }
    }
}

// Create .gitkeep files
$gitkeeps = [
    'uploads/audio/.gitkeep',
    'uploads/covers/.gitkeep',
    'data/backup/.gitkeep',
    'logs/.gitkeep'
];

echo "\n\nCreating .gitkeep files...\n";
foreach ($gitkeeps as $file) {
    if (!file_exists($file)) {
        if (touch($file)) {
            echo "✓ Created $file\n";
        } else {
            echo "✗ Failed to create $file\n";
        }
    } else {
        echo "✓ $file already exists\n";
    }
}

echo "\n✅ Setup complete!\n";
echo "</pre>";
