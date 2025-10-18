<?php
echo "<h1>Form Submission Test</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data:</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    echo "<h2>FILES Data:</h2>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if (isset($_FILES['audio_file'])) {
        echo "<h2>File Details:</h2>";
        echo "Name: " . $_FILES['audio_file']['name'] . "<br>";
        echo "Size: " . number_format($_FILES['audio_file']['size']) . " bytes<br>";
        echo "Type: " . $_FILES['audio_file']['type'] . "<br>";
        echo "Error: " . $_FILES['audio_file']['error'] . "<br>";
        
        $errors = [
            UPLOAD_ERR_OK => 'OK - No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload'
        ];
        
        echo "Error meaning: " . ($errors[$_FILES['audio_file']['error']] ?? 'Unknown') . "<br>";
        
        if ($_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            echo "<h3 style='color: green;'>✅ FILE UPLOADED SUCCESSFULLY!</h3>";
        } else {
            echo "<h3 style='color: red;'>❌ FILE UPLOAD FAILED!</h3>";
        }
    } else {
        echo "<h2 style='color: red;'>❌ NO FILE IN \$_FILES!</h2>";
    }
    
    echo "<hr>";
    echo "<h2>PHP Settings:</h2>";
    echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
    echo "post_max_size: " . ini_get('post_max_size') . "<br>";
    echo "max_execution_time: " . ini_get('max_execution_time') . "<br>";
    echo "memory_limit: " . ini_get('memory_limit') . "<br>";
    
} else {
?>
    <form method="POST" enctype="multipart/form-data">
        <h2>Upload Test Form</h2>
        <label>Title:</label><br>
        <input type="text" name="title" value="Test Episode"><br><br>
        
        <label>Audio File:</label><br>
        <input type="file" name="audio_file" accept=".mp3"><br><br>
        
        <button type="submit">Submit</button>
    </form>
<?php
}
?>
