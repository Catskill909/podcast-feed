<?php
/**
 * Health Check Endpoint for Coolify
 * Returns 200 OK if application is running
 */

// Disable any authentication for health checks
// Simple health check - just return OK
http_response_code(200);
header('Content-Type: text/plain');
header('Cache-Control: no-cache');
echo 'OK';
exit;
