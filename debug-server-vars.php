<?php
require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain');

echo "=== SERVER VARIABLES ===\n\n";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'NOT SET') . "\n";
echo "SERVER_NAME: " . ($_SERVER['SERVER_NAME'] ?? 'NOT SET') . "\n";
echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'NOT SET') . "\n";
echo "HTTP_X_FORWARDED_PROTO: " . ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'NOT SET') . "\n";
echo "HTTP_X_FORWARDED_SSL: " . ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? 'NOT SET') . "\n";
echo "SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'NOT SET') . "\n";

echo "\n=== DETECTED CONFIG ===\n\n";
echo "APP_URL: " . APP_URL . "\n";
echo "AUDIO_URL: " . AUDIO_URL . "\n";
echo "ENVIRONMENT: " . ENVIRONMENT . "\n";

echo "\n=== EXPECTED ===\n\n";
echo "Should be: https://podcast.supersoul.top\n";
