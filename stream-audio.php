<?php
/**
 * Audio Streaming with Range Request Support
 * Required for audio seeking to work properly
 */

$file = $_GET['file'] ?? '';

// Security: Validate file path
$file = basename($file); // Remove any directory traversal
$filePath = __DIR__ . '/uploads/audio/' . $file;

// Check if file exists
if (!file_exists($filePath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}

// Get file info
$fileSize = filesize($filePath);
$mimeType = 'audio/mpeg';

// Get range request
$range = $_SERVER['HTTP_RANGE'] ?? '';

if (empty($range)) {
    // No range request - send entire file
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    readfile($filePath);
    exit;
}

// Parse range request
preg_match('/bytes=(\d+)-(\d*)/', $range, $matches);
$start = intval($matches[1]);
$end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

// Validate range
if ($start > $end || $start >= $fileSize || $end >= $fileSize) {
    header('HTTP/1.1 416 Range Not Satisfiable');
    header('Content-Range: bytes */' . $fileSize);
    exit;
}

// Calculate content length
$length = $end - $start + 1;

// Send headers
header('HTTP/1.1 206 Partial Content');
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $length);
header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
header('Accept-Ranges: bytes');

// Open file and seek to start position
$fp = fopen($filePath, 'rb');
fseek($fp, $start);

// Send the requested range
$buffer = 8192;
$bytesRemaining = $length;

while ($bytesRemaining > 0 && !feof($fp)) {
    $bytesToRead = min($buffer, $bytesRemaining);
    echo fread($fp, $bytesToRead);
    $bytesRemaining -= $bytesToRead;
    flush();
}

fclose($fp);
exit;
?>
