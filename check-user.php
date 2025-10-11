<?php
/**
 * Diagnostic script to check PHP user and permissions
 * Access at: /check-user.php
 */

echo "<h1>PHP User & Permissions Check</h1>";

// Check PHP user
echo "<h2>PHP Process User</h2>";
echo "<pre>";
echo "User: " . exec('whoami') . "\n";
echo "UID: " . posix_getuid() . "\n";
echo "GID: " . posix_getgid() . "\n";
echo "Groups: " . implode(', ', posix_getgroups()) . "\n";
echo "</pre>";

// Check directory permissions
echo "<h2>Directory Permissions</h2>";

$dirs = [
    'data' => __DIR__ . '/data',
    'uploads' => __DIR__ . '/uploads',
    'logs' => __DIR__ . '/logs',
    'covers' => __DIR__ . '/uploads/covers',
    'backup' => __DIR__ . '/data/backup'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Directory</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Owner</th><th>Permissions</th></tr>";

foreach ($dirs as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    
    if ($exists) {
        $stat = stat($path);
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        $perms = substr(sprintf('%o', fileperms($path)), -4);
    } else {
        $owner = ['name' => 'N/A'];
        $group = ['name' => 'N/A'];
        $perms = 'N/A';
    }
    
    echo "<tr>";
    echo "<td><strong>$name</strong><br><small>$path</small></td>";
    echo "<td>" . ($exists ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($readable ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($writable ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>{$owner['name']}:{$group['name']}</td>";
    echo "<td>$perms</td>";
    echo "</tr>";
}

echo "</table>";

// Check specific files
echo "<h2>File Permissions</h2>";

$files = [
    'podcasts.xml' => __DIR__ . '/data/podcasts.xml',
    'error.log' => __DIR__ . '/logs/error.log'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>File</th><th>Exists</th><th>Readable</th><th>Writable</th><th>Owner</th><th>Permissions</th></tr>";

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $readable = is_readable($path);
    $writable = is_writable($path);
    
    if ($exists) {
        $stat = stat($path);
        $owner = posix_getpwuid($stat['uid']);
        $group = posix_getgrgid($stat['gid']);
        $perms = substr(sprintf('%o', fileperms($path)), -4);
    } else {
        $owner = ['name' => 'N/A'];
        $group = ['name' => 'N/A'];
        $perms = 'N/A';
    }
    
    echo "<tr>";
    echo "<td><strong>$name</strong><br><small>$path</small></td>";
    echo "<td>" . ($exists ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($readable ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>" . ($writable ? '✅ Yes' : '❌ No') . "</td>";
    echo "<td>{$owner['name']}:{$group['name']}</td>";
    echo "<td>$perms</td>";
    echo "</tr>";
}

echo "</table>";

// Test write capability
echo "<h2>Write Test</h2>";
echo "<pre>";

$testFile = __DIR__ . '/data/test-write.txt';
$canWrite = @file_put_contents($testFile, 'test');

if ($canWrite) {
    echo "✅ Can write to data/ directory\n";
    @unlink($testFile);
} else {
    echo "❌ CANNOT write to data/ directory\n";
    echo "Error: " . error_get_last()['message'] . "\n";
}

echo "</pre>";

echo "<hr>";
echo "<p><strong>Instructions:</strong> Access this file at your-domain.com/check-user.php to see diagnostics in production.</p>";
?>
