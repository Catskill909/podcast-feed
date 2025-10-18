<?php
/**
 * Deep Permission Debugging
 * Find out WHY uploads/audio isn't writable
 */

header('Content-Type: text/plain');

echo "=== DEEP PERMISSION DEBUG ===\n\n";

// 1. Who is PHP running as?
echo "--- PHP Process User ---\n";
echo "Current user: " . get_current_user() . "\n";
echo "Process UID: " . getmyuid() . "\n";
echo "Process GID: " . getmygid() . "\n";

if (function_exists('posix_getpwuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    echo "Process owner: " . $processUser['name'] . "\n";
}
echo "\n";

// 2. Check uploads/audio details
echo "--- uploads/audio Details ---\n";
$dir = 'uploads/audio';

if (file_exists($dir)) {
    echo "Path: " . realpath($dir) . "\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($dir)), -4) . "\n";
    
    $stat = stat($dir);
    echo "Owner UID: " . $stat['uid'] . "\n";
    echo "Owner GID: " . $stat['gid'] . "\n";
    
    if (function_exists('posix_getpwuid')) {
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        echo "Owner name: " . $owner['name'] . "\n";
        echo "Group name: " . $group['name'] . "\n";
    }
    
    echo "Is dir: " . (is_dir($dir) ? 'Yes' : 'No') . "\n";
    echo "Is readable: " . (is_readable($dir) ? 'Yes' : 'No') . "\n";
    echo "Is writable: " . (is_writable($dir) ? 'Yes' : 'No') . "\n";
    echo "Is executable: " . (is_executable($dir) ? 'Yes' : 'No') . "\n";
} else {
    echo "Directory does not exist!\n";
}
echo "\n";

// 3. Compare with working directory (uploads/covers)
echo "--- uploads/covers Details (WORKING) ---\n";
$workingDir = 'uploads/covers';

if (file_exists($workingDir)) {
    echo "Path: " . realpath($workingDir) . "\n";
    echo "Permissions: " . substr(sprintf('%o', fileperms($workingDir)), -4) . "\n";
    
    $stat = stat($workingDir);
    echo "Owner UID: " . $stat['uid'] . "\n";
    echo "Owner GID: " . $stat['gid'] . "\n";
    
    if (function_exists('posix_getpwuid')) {
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        echo "Owner name: " . $owner['name'] . "\n";
        echo "Group name: " . $group['name'] . "\n";
    }
    
    echo "Is writable: " . (is_writable($workingDir) ? 'Yes' : 'No') . "\n";
}
echo "\n";

// 4. Test write
echo "--- Write Test ---\n";
$testFile = $dir . '/test-' . time() . '.txt';
echo "Attempting to write: $testFile\n";

if (@file_put_contents($testFile, 'test')) {
    echo "✓ SUCCESS! File written\n";
    @unlink($testFile);
} else {
    echo "✗ FAILED! Error: " . error_get_last()['message'] . "\n";
}
echo "\n";

// 5. Suggested fix
echo "--- SUGGESTED FIX ---\n";
echo "The uploads/audio directory is owned by a different user than PHP.\n";
echo "Run this command in Coolify terminal:\n\n";

if (function_exists('posix_getpwuid')) {
    $processUser = posix_getpwuid(posix_geteuid());
    $phpUser = $processUser['name'];
    echo "chown -R $phpUser:$phpUser /app/uploads/audio\n";
} else {
    echo "chown -R \$(whoami):\$(whoami) /app/uploads/audio\n";
}

echo "\nOR if that doesn't work:\n";
echo "chmod -R 777 /app/uploads/audio  (less secure but will work)\n";

echo "\n=== END DEBUG ===\n";
