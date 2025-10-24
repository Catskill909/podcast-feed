<?php
// This will show you EXACTLY what HTML is being sent to the browser
header('Content-Type: text/plain');

// Capture the output of index.php
ob_start();
include 'index.php';
$html = ob_get_clean();

// Show just the <head> section which contains our inline CSS
preg_match('/<head>(.*?)<\/head>/s', $html, $matches);

echo "=== HEAD SECTION OF INDEX.PHP ===\n\n";
echo $matches[0] ?? 'Could not find <head> section';

echo "\n\n=== CHECKING FOR INLINE MOBILE CSS ===\n\n";

if (strpos($html, 'CRITICAL MOBILE CSS') !== false) {
    echo "✓ FOUND: Inline mobile CSS is present\n";
} else {
    echo "✗ MISSING: Inline mobile CSS NOT found!\n";
}

if (strpos($html, 'font-size: 15px') !== false) {
    echo "✓ FOUND: 15px badge size\n";
} else {
    echo "✗ MISSING: 15px badge size NOT found!\n";
}

if (strpos($html, 'scale(0.88)') !== false) {
    echo "✓ FOUND: scale(0.88) transform\n";
} else {
    echo "✗ MISSING: scale(0.88) NOT found!\n";
}

if (strpos($html, 'user-select: none') !== false) {
    echo "✓ FOUND: user-select: none\n";
} else {
    echo "✗ MISSING: user-select: none NOT found!\n";
}

echo "\n\n=== FIRST 2000 CHARACTERS OF HTML ===\n\n";
echo substr($html, 0, 2000);
?>
