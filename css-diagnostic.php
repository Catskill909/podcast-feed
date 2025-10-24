<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSS Diagnostic</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #0d1117;
            color: #f0f6fc;
            line-height: 1.6;
        }
        .section {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .pass { color: #2ea043; }
        .fail { color: #f85149; }
        .warning { color: #d29922; }
        pre {
            background: #0d1117;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .test-card {
            width: 200px;
            height: 200px;
            background: #238636;
            margin: 20px 0;
            transform: scale(0.88);
        }
        .test-badge {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 15px;
            display: inline-block;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <h1>🔍 CSS Diagnostic Tool</h1>
    
    <div class="section">
        <h2>1. File Timestamps</h2>
        <?php
        $cssFile = __DIR__ . '/assets/css/browse.css';
        if (file_exists($cssFile)) {
            $modTime = filemtime($cssFile);
            $modDate = date('Y-m-d H:i:s', $modTime);
            echo "<p class='pass'>✓ browse.css exists</p>";
            echo "<p><strong>Last Modified:</strong> $modDate</p>";
            echo "<p><strong>File Size:</strong> " . filesize($cssFile) . " bytes</p>";
            echo "<p><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . "</p>";
            echo "<p><strong>Cache Bust Value:</strong> " . time() . "</p>";
        } else {
            echo "<p class='fail'>✗ browse.css NOT FOUND</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>2. CSS File Content Check</h2>
        <?php
        if (file_exists($cssFile)) {
            $content = file_get_contents($cssFile);
            
            // Check for our mobile fixes
            $checks = [
                'font-size: 15px' => strpos($content, 'font-size: 15px') !== false,
                'transform: scale(0.88)' => strpos($content, 'scale(0.88)') !== false,
                'user-select: none' => strpos($content, 'user-select: none') !== false,
                'pointer: coarse' => strpos($content, 'pointer: coarse') !== false,
                'CACHE BUST: v2024-10-24-1059' => strpos($content, 'CACHE BUST: v2024-10-24-1059') !== false,
            ];
            
            foreach ($checks as $check => $found) {
                $class = $found ? 'pass' : 'fail';
                $symbol = $found ? '✓' : '✗';
                echo "<p class='$class'>$symbol $check</p>";
            }
            
            // Show first 500 chars of mobile section
            $mobilePos = strpos($content, '@media (max-width: 480px)');
            if ($mobilePos !== false) {
                $snippet = substr($content, $mobilePos, 800);
                echo "<h3>Mobile CSS Section Preview:</h3>";
                echo "<pre>" . htmlspecialchars($snippet) . "</pre>";
            }
        }
        ?>
    </div>

    <div class="section">
        <h2>3. CSS URL Test</h2>
        <?php
        $cssUrl = "assets/css/browse.css?v=" . time();
        echo "<p><strong>URL being used:</strong></p>";
        echo "<pre>$cssUrl</pre>";
        
        echo "<p><strong>Full URL:</strong></p>";
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $fullUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/' . $cssUrl;
        echo "<pre>$fullUrl</pre>";
        
        echo "<p class='warning'>⚠️ Try accessing this URL directly in your browser to verify it loads</p>";
        ?>
    </div>

    <div class="section">
        <h2>4. Visual Tests</h2>
        <p>If the CSS is loading correctly, you should see:</p>
        <ul>
            <li>Green box scaled down to 88%</li>
            <li>Badge with 15px font size</li>
        </ul>
        
        <div class="test-card">
            <div style="padding: 20px;">
                <div class="test-badge">TEST 15px</div>
            </div>
        </div>
        
        <p>Try selecting this text - if user-select works, you shouldn't be able to:</p>
        <div style="user-select: none; -webkit-user-select: none; background: #238636; padding: 10px; border-radius: 4px;">
            This text should NOT be selectable
        </div>
    </div>

    <div class="section">
        <h2>5. Server Info</h2>
        <pre><?php
        echo "PHP Version: " . phpversion() . "\n";
        echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
        echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
        echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
        ?></pre>
    </div>

    <div class="section">
        <h2>6. Recommended Actions</h2>
        <ol>
            <li>Check if the file modification time is recent (within last few minutes)</li>
            <li>Verify all checks show ✓ green checkmarks</li>
            <li>Try accessing the CSS URL directly to see if it loads</li>
            <li>Clear browser cache: Settings → Clear Browsing Data → Cached Images and Files</li>
            <li>Try incognito/private mode</li>
            <li>Check if there's a CDN or proxy caching the CSS</li>
        </ol>
    </div>

</body>
</html>
