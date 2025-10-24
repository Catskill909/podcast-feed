<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile Test - <?php echo time(); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #0d1117;
            color: white;
            padding: 20px;
        }
        
        .test-card {
            background: #161b22;
            border: 2px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            position: relative;
            -webkit-user-select: none;
            user-select: none;
            -webkit-tap-highlight-color: transparent;
        }
        
        .test-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 600;
        }
        
        .test-badge-new {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #238636;
            color: white;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 15px;
            font-weight: 700;
        }
        
        .scaled-card {
            transform: scale(0.88);
            margin: 30px 0;
        }
        
        h1 {
            color: #238636;
            margin-bottom: 20px;
        }
        
        .info {
            background: #1f6feb;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .pass {
            background: #238636;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        .fail {
            background: #da3633;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        
        code {
            background: #0d1117;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🔍 Mobile CSS Test</h1>
    <p style="color: #8b949e;">Timestamp: <?php echo date('H:i:s'); ?></p>
    
    <div class="info">
        <strong>Instructions:</strong><br>
        1. Try to select this text (should NOT be selectable)<br>
        2. Tap the cards below (should NOT show blue highlight)<br>
        3. Check badge sizes (should be 15px, easy to read)<br>
        4. Check if scaled card is smaller
    </div>
    
    <h2 style="margin-top: 30px;">Test 1: Normal Card</h2>
    <div class="test-card">
        <div class="test-badge-new">NEW</div>
        <div class="test-badge">100 Episodes</div>
        <div style="margin-top: 50px;">
            <h3>Test Podcast Title</h3>
            <p style="color: #8b949e; margin-top: 10px;">
                This text should NOT be selectable. Try to select it - if you see a blue highlight or copy menu, the CSS is NOT working.
            </p>
        </div>
    </div>
    
    <h2 style="margin-top: 30px;">Test 2: Scaled Card (88%)</h2>
    <div class="test-card scaled-card">
        <div class="test-badge-new">NEW</div>
        <div class="test-badge">25 Episodes</div>
        <div style="margin-top: 50px;">
            <h3>Scaled Test Card</h3>
            <p style="color: #8b949e; margin-top: 10px;">
                This card should be 12% smaller than the one above. You should see space on the left and right sides.
            </p>
        </div>
    </div>
    
    <h2 style="margin-top: 30px;">Results Check:</h2>
    
    <div class="pass">
        <strong>✓ PASS if:</strong><br>
        • Badges are LARGE and easy to read (15px)<br>
        • Text is NOT selectable<br>
        • NO blue highlight when tapping<br>
        • Scaled card is visibly smaller
    </div>
    
    <div class="fail">
        <strong>✗ FAIL if:</strong><br>
        • Badges are tiny/hard to read<br>
        • You can select text and see copy menu<br>
        • Blue highlight appears on tap<br>
        • Both cards look the same size
    </div>
    
    <div class="info" style="margin-top: 30px;">
        <strong>What to do next:</strong><br><br>
        
        <strong>If this page WORKS correctly:</strong><br>
        The CSS itself is fine. The problem is with index.php loading or caching.<br><br>
        
        <strong>If this page FAILS too:</strong><br>
        There's a deeper browser or device issue.<br><br>
        
        <strong>Screenshot this page and send it to me!</strong>
    </div>
    
    <div style="margin-top: 40px; padding: 20px; background: #161b22; border-radius: 8px;">
        <h3>Technical Info:</h3>
        <p style="margin: 10px 0;"><code>User Agent:</code></p>
        <p style="color: #8b949e; font-size: 12px; word-break: break-all;">
            <?php echo $_SERVER['HTTP_USER_AGENT']; ?>
        </p>
        
        <p style="margin: 10px 0;"><code>Screen Width:</code> <span id="width"></span>px</p>
        <p style="margin: 10px 0;"><code>Viewport Width:</code> <span id="viewport"></span>px</p>
        <p style="margin: 10px 0;"><code>Device Pixel Ratio:</code> <span id="dpr"></span></p>
    </div>
    
    <script>
        document.getElementById('width').textContent = screen.width;
        document.getElementById('viewport').textContent = window.innerWidth;
        document.getElementById('dpr').textContent = window.devicePixelRatio;
    </script>
</body>
</html>
