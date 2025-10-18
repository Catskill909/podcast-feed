<?php
/**
 * Quick PHP Configuration Check
 * Delete this file after checking!
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PHP Upload Limits Check</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #e0e0e0; }
        .good { color: #4CAF50; }
        .bad { color: #f44336; }
        .warning { color: #ff9800; }
        table { border-collapse: collapse; margin: 20px 0; }
        td { padding: 10px; border: 1px solid #444; }
        .label { font-weight: bold; }
    </style>
</head>
<body>
    <h1>🔍 PHP Upload Configuration</h1>
    
    <table>
        <tr>
            <td class="label">upload_max_filesize</td>
            <td class="<?php echo (int)ini_get('upload_max_filesize') >= 500 ? 'good' : 'bad'; ?>">
                <?php echo ini_get('upload_max_filesize'); ?>
                <?php if ((int)ini_get('upload_max_filesize') < 500) echo ' ⚠️ TOO LOW! Need 500M+'; ?>
            </td>
        </tr>
        <tr>
            <td class="label">post_max_size</td>
            <td class="<?php echo (int)ini_get('post_max_size') >= 500 ? 'good' : 'bad'; ?>">
                <?php echo ini_get('post_max_size'); ?>
                <?php if ((int)ini_get('post_max_size') < 500) echo ' ⚠️ TOO LOW! Need 500M+'; ?>
            </td>
        </tr>
        <tr>
            <td class="label">max_execution_time</td>
            <td class="<?php echo ini_get('max_execution_time') >= 300 ? 'good' : 'warning'; ?>">
                <?php echo ini_get('max_execution_time'); ?> seconds
                <?php if (ini_get('max_execution_time') < 300) echo ' ⚠️ Might timeout on large uploads'; ?>
            </td>
        </tr>
        <tr>
            <td class="label">max_input_time</td>
            <td class="<?php echo ini_get('max_input_time') >= 300 ? 'good' : 'warning'; ?>">
                <?php echo ini_get('max_input_time'); ?> seconds
            </td>
        </tr>
        <tr>
            <td class="label">memory_limit</td>
            <td class="<?php echo (int)ini_get('memory_limit') >= 256 ? 'good' : 'warning'; ?>">
                <?php echo ini_get('memory_limit'); ?>
            </td>
        </tr>
    </table>
    
    <h2>📁 Directory Checks</h2>
    <table>
        <?php
        $dirs = ['uploads/audio', 'uploads/covers', 'data', 'logs'];
        foreach ($dirs as $dir) {
            $exists = is_dir($dir);
            $writable = $exists && is_writable($dir);
            echo "<tr>";
            echo "<td class='label'>$dir</td>";
            echo "<td class='" . ($exists && $writable ? 'good' : 'bad') . "'>";
            echo $exists ? '✓ Exists' : '✗ Missing';
            echo ' | ';
            echo $writable ? '✓ Writable' : '✗ Not Writable';
            echo "</td>";
            echo "</tr>";
        }
        ?>
    </table>
    
    <h2>🔧 Recommended .user.ini Settings</h2>
    <pre style="background: #2d2d2d; padding: 15px; border-radius: 5px;">
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 600
max_input_time = 600
memory_limit = 512M
    </pre>
    
    <p style="color: #ff9800;">⚠️ <strong>Delete this file after checking!</strong></p>
</body>
</html>
