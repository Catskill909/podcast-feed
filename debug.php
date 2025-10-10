<?php
/**
 * Debug page to check environment detection
 * Delete this file after debugging
 */

require_once __DIR__ . '/config/config.php';

?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Info</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .info { background: #2a2a2a; padding: 15px; margin: 10px 0; border-left: 3px solid #0f0; }
        .label { color: #888; }
        .value { color: #0f0; font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 Environment Debug Info</h1>
    
    <div class="info">
        <div><span class="label">Environment:</span> <span class="value"><?php echo ENVIRONMENT; ?></span></div>
        <div><span class="label">APP_URL:</span> <span class="value"><?php echo APP_URL; ?></span></div>
    </div>
    
    <div class="info">
        <div><span class="label">SERVER_NAME:</span> <span class="value"><?php echo $_SERVER['SERVER_NAME'] ?? 'not set'; ?></span></div>
        <div><span class="label">HTTP_HOST:</span> <span class="value"><?php echo $_SERVER['HTTP_HOST'] ?? 'not set'; ?></span></div>
        <div><span class="label">HTTPS:</span> <span class="value"><?php echo $_SERVER['HTTPS'] ?? 'not set'; ?></span></div>
    </div>
    
    <div class="info">
        <h3>Expected Behavior:</h3>
        <ul>
            <li>If Environment = <strong>development</strong>: No password required</li>
            <li>If Environment = <strong>production</strong>: Password required (auth.js loads)</li>
        </ul>
    </div>
    
    <div class="info">
        <h3>Check auth.js:</h3>
        <p>Open browser console (F12) and look for: <strong>🔒 Password protection active</strong></p>
        <p>If you see this message, auth.js is loading correctly.</p>
    </div>
    
    <p><a href="index.php" style="color: #0f0;">← Back to Main Page</a></p>
    
    <script>
        console.log('Debug page loaded');
        console.log('Environment:', '<?php echo ENVIRONMENT; ?>');
        console.log('Check if auth.js would load:', <?php echo ENVIRONMENT === 'production' ? 'true' : 'false'; ?>);
    </script>
</body>
</html>
