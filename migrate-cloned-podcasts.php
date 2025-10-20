<?php
/**
 * Migration Script: Remove (Cloned) from titles and add is_cloned field
 * Run this once after deploying the cloning feature updates
 */

require_once __DIR__ . '/config/config.php';

$xmlFile = __DIR__ . '/data/self-hosted-podcasts.xml';

if (!file_exists($xmlFile)) {
    die("No self-hosted podcasts file found. Nothing to migrate.\n");
}

$xml = new DOMDocument('1.0', 'UTF-8');
$xml->formatOutput = true;
$xml->preserveWhiteSpace = false;
$xml->load($xmlFile);

$podcasts = $xml->getElementsByTagName('podcast');
$updated = 0;

foreach ($podcasts as $podcast) {
    $titleNode = $podcast->getElementsByTagName('title')->item(0);
    $isClonedNode = $podcast->getElementsByTagName('is_cloned')->item(0);
    
    if ($titleNode) {
        $title = $titleNode->nodeValue;
        
        // Check if title ends with (Cloned)
        if (preg_match('/\s*\(Cloned\)\s*$/i', $title)) {
            // Remove (Cloned) from title
            $newTitle = preg_replace('/\s*\(Cloned\)\s*$/i', '', $title);
            $titleNode->nodeValue = '';
            $cdata = $xml->createCDATASection($newTitle);
            $titleNode->appendChild($cdata);
            
            // Add is_cloned field if it doesn't exist
            if (!$isClonedNode) {
                $isClonedNode = $xml->createElement('is_cloned', 'yes');
                $podcast->appendChild($isClonedNode);
            } else {
                $isClonedNode->nodeValue = 'yes';
            }
            
            $updated++;
            echo "Updated: {$title} -> {$newTitle}\n";
        } else {
            // Not cloned, ensure is_cloned is set to 'no'
            if (!$isClonedNode) {
                $isClonedNode = $xml->createElement('is_cloned', 'no');
                $podcast->appendChild($isClonedNode);
            }
        }
    }
}

if ($updated > 0) {
    $xml->save($xmlFile);
    echo "\n✅ Migration complete! Updated {$updated} podcast(s).\n";
} else {
    echo "\n✅ No podcasts needed updating.\n";
}

echo "\nYou can now delete this migration script.\n";
