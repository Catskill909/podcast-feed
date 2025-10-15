<?php
/**
 * Migration Script: Add Missing Fields to Existing Podcasts
 * Run this once to populate latest_episode_date and health fields for existing podcasts
 * 
 * Usage: php migrate-missing-fields.php
 */

require_once __DIR__ . '/includes/XMLHandler.php';

echo "========================================\n";
echo "Podcast Field Migration Script\n";
echo "========================================\n\n";

try {
    $xmlHandler = new XMLHandler();
    
    // Load the XML
    $xmlFile = DATA_DIR . '/podcasts.xml';
    if (!file_exists($xmlFile)) {
        die("Error: podcasts.xml not found at $xmlFile\n");
    }
    
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;
    $dom->preserveWhiteSpace = false;
    $dom->load($xmlFile);
    
    $podcasts = $dom->getElementsByTagName('podcast');
    $updated = 0;
    $skipped = 0;
    
    echo "Found " . $podcasts->length . " podcasts\n\n";
    
    foreach ($podcasts as $podcast) {
        $id = $podcast->getAttribute('id');
        $title = $podcast->getElementsByTagName('title')->item(0)->nodeValue ?? 'Unknown';
        
        echo "Processing: $title (ID: $id)\n";
        
        $needsUpdate = false;
        $fieldsToAdd = [];
        
        // Check for missing fields
        $requiredFields = [
            'latest_episode_date' => '',
            'episode_count' => '0',
            'health_status' => 'healthy',
            'last_check_date' => '',
            'last_success_date' => date('Y-m-d H:i:s'),
            'consecutive_failures' => '0',
            'total_failures' => '0',
            'total_checks' => '0',
            'avg_response_time' => '0',
            'success_rate' => '100',
            'last_error' => '',
            'last_error_date' => '',
            'auto_disabled' => 'false',
            'auto_disabled_date' => ''
        ];
        
        foreach ($requiredFields as $fieldName => $defaultValue) {
            $existingField = $podcast->getElementsByTagName($fieldName)->item(0);
            if (!$existingField) {
                $fieldsToAdd[$fieldName] = $defaultValue;
                $needsUpdate = true;
            }
        }
        
        if ($needsUpdate) {
            // Add missing fields
            foreach ($fieldsToAdd as $fieldName => $value) {
                $newElement = $dom->createElement($fieldName, $value);
                $podcast->appendChild($newElement);
                echo "  + Added field: $fieldName\n";
            }
            $updated++;
        } else {
            echo "  ✓ All fields present\n";
            $skipped++;
        }
        
        echo "\n";
    }
    
    // Save the updated XML
    if ($updated > 0) {
        // Create backup first
        $backupFile = DATA_DIR . '/backup/podcasts_pre_migration_' . date('Y-m-d_H-i-s') . '.xml';
        if (!is_dir(DATA_DIR . '/backup')) {
            mkdir(DATA_DIR . '/backup', 0755, true);
        }
        copy($xmlFile, $backupFile);
        echo "Backup created: $backupFile\n\n";
        
        // Save updated XML
        $dom->save($xmlFile);
        chmod($xmlFile, 0666);
        
        echo "========================================\n";
        echo "Migration Complete!\n";
        echo "========================================\n";
        echo "Updated: $updated podcasts\n";
        echo "Skipped: $skipped podcasts (already had all fields)\n";
        echo "Total: " . $podcasts->length . " podcasts\n\n";
        echo "✅ XML file updated successfully\n";
        echo "📁 Backup saved to: $backupFile\n\n";
        echo "Next steps:\n";
        echo "1. Run the cron job to populate episode data:\n";
        echo "   php cron/auto-scan-feeds.php\n\n";
        echo "2. Refresh your browser to see the changes\n";
    } else {
        echo "========================================\n";
        echo "No updates needed - all podcasts already have required fields\n";
        echo "========================================\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
