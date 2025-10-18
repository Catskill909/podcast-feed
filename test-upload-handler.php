<?php
echo "<h1>Upload Test Result</h1>";

echo "<h2>\$_FILES:</h2>";
echo "<pre>";
print_r($_FILES);
echo "</pre>";

echo "<h2>\$_POST:</h2>";
echo "<pre>";
print_r($_POST);
echo "</pre>";

if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
    echo "<h2 style='color: green;'>✅ FILE UPLOADED SUCCESSFULLY!</h2>";
    echo "Name: " . $_FILES['audio_file']['name'] . "<br>";
    echo "Size: " . number_format($_FILES['audio_file']['size']) . " bytes<br>";
    echo "Type: " . $_FILES['audio_file']['type'] . "<br>";
} else {
    echo "<h2 style='color: red;'>❌ NO FILE UPLOADED</h2>";
    if (isset($_FILES['audio_file'])) {
        echo "Error code: " . $_FILES['audio_file']['error'] . "<br>";
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (php.ini)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form)',
            UPLOAD_ERR_PARTIAL => 'Partial upload',
            UPLOAD_ERR_NO_FILE => 'NO FILE SELECTED',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Extension blocked'
        ];
        echo "Error: " . ($errors[$_FILES['audio_file']['error']] ?? 'Unknown');
    }
}
?>
