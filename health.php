<?php
/**
 * Health Check Endpoint for Coolify
 * Returns 200 OK if application is running
 */

// Simple health check - just return OK
http_response_code(200);
header('Content-Type: text/plain');
echo 'OK';
exit;
