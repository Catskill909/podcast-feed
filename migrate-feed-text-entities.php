<?php

/**
 * One-time repair for feed text stored before feedText() existed.
 *
 * RssFeedParser::extractText() used to run strip_tags() without decoding HTML
 * entities, so publisher double-encoding ("&amp;nbsp;" in the feed) was stored
 * as the literal text "&nbsp;" and rendered that way in the UI.
 *
 * The parser is fixed, but refreshing a feed only updates the episode date and
 * count - it does not rewrite title or description - so existing rows stay
 * damaged until repaired here.
 *
 * Usage:
 *   php migrate-feed-text-entities.php            # dry run, shows what would change
 *   php migrate-feed-text-entities.php --apply    # writes, after backing up
 */

require_once __DIR__ . '/includes/FeedText.php';

$apply = in_array('--apply', $argv, true);

$files = [
    __DIR__ . '/data/podcasts.xml',
    __DIR__ . '/data/self-hosted-podcasts.xml',
];

$fields = ['title', 'description'];
$totalChanged = 0;

foreach ($files as $file) {
    if (!is_readable($file)) {
        echo "skip (missing): " . basename($file) . "\n";
        continue;
    }

    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;

    if (!@$doc->load($file)) {
        echo "ERROR: could not parse " . basename($file) . "\n";
        continue;
    }

    $changed = 0;

    foreach ($fields as $field) {
        foreach ($doc->getElementsByTagName($field) as $node) {
            $before = $node->textContent;
            $after = feedText($before);

            if ($after === $before || $after === '') {
                continue;
            }

            $changed++;
            $totalChanged++;

            printf("  %s\n    - %s\n    + %s\n",
                basename($file),
                mb_strimwidth($before, 0, 90, '...'),
                mb_strimwidth($after, 0, 90, '...')
            );

            if ($apply) {
                // Replace children with a CDATA section, matching how the rest
                // of the app writes these fields.
                while ($node->firstChild) {
                    $node->removeChild($node->firstChild);
                }
                $node->appendChild($doc->createCDATASection($after));
            }
        }
    }

    if ($apply && $changed > 0) {
        $backup = $file . '.bak-' . date('Ymd-His');
        if (!copy($file, $backup)) {
            echo "ERROR: backup failed, refusing to write " . basename($file) . "\n";
            continue;
        }
        $doc->save($file);
        echo "wrote " . basename($file) . " ($changed field(s)); backup: " . basename($backup) . "\n";
    }
}

echo "\n";
if ($totalChanged === 0) {
    echo "Nothing to repair.\n";
} elseif ($apply) {
    echo "Repaired $totalChanged field(s).\n";
} else {
    echo "$totalChanged field(s) would change. Re-run with --apply to write.\n";
}
