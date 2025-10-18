<?php
/**
 * Audio Streaming with Range Request Support
 * This enables seeking in audio files
 */

// Get file path from query
$path = $_GET['file'] ?? '';

// Security: Only allow files from uploads/audio
if (strpos($path, '..') !== false || strpos($path, 'uploads/audio/') !== 0) {
    http_response_code(403);
    exit('Forbidden');
}

$filePath = __DIR__ . '/' . $path;

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$fileSize = filesize($filePath);
$mimeType = 'audio/mpeg';

// Parse range header
$range = $_SERVER['HTTP_RANGE'] ?? '';

if (empty($range)) {
    // No range - send entire file
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Accept-Ranges: bytes');
    readfile($filePath);
    exit;
}

// Parse range
if (!preg_match('/bytes=(\d+)-(\d*)/', $range, $matches)) {
    http_response_code(416);
    exit('Invalid range');
}

$start = intval($matches[1]);
$end = !empty($matches[2]) ? intval($matches[2]) : $fileSize - 1;

if ($start > $end || $start >= $fileSize) {
    http_response_code(416);
    header('Content-Range: bytes */' . $fileSize);
    exit('Range not satisfiable');
}

$length = $end - $start + 1;

// Send partial content
http_response_code(206);
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $length);
header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
header('Accept-Ranges: bytes');

// Stream the range
$fp = fopen($filePath, 'rb');
fseek($fp, $start);

$buffer = 8192;
while ($length > 0 && !feof($fp)) {
    $read = min($buffer, $length);
    echo fread($fp, $read);
    $length -= $read;
    flush();
}

fclose($fp);
?>
