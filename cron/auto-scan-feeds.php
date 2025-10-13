#!/usr/bin/env php
<?php
/**
 * Automated Feed Scanner
 * Runs periodically to update all podcast episode dates
 * Can be run via cron job or manually
 * 
 * Usage:
 *   php cron/auto-scan-feeds.php
 *   
 * Cron example (every 30 minutes):
 *   Star-slash-30 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
 */

// Ensure script is run from command line
if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

// Set working directory to project root
chdir(dirname(__DIR__));

require_once __DIR__ . '/../includes/PodcastManager.php';
require_once __DIR__ . '/../includes/RssFeedParser.php';

// Configuration
$config = [
    'log_file' => __DIR__ . '/../logs/auto-scan.log',
    'max_execution_time' => 300, // 5 minutes
    'delay_between_feeds' => 2, // seconds between each feed fetch (be nice to servers)
];

// Set execution time limit
set_time_limit($config['max_execution_time']);

// Start logging
$startTime = microtime(true);
$timestamp = date('Y-m-d H:i:s');

log_message("========================================");
log_message("Auto-Scan Started: $timestamp");
log_message("========================================");

try {
    $podcastManager = new PodcastManager();
    $podcasts = $podcastManager->getAllPodcasts();
    
    $parser = new RssFeedParser();
    
    $stats = [
        'total' => count($podcasts),
        'updated' => 0,
        'failed' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    log_message("Found {$stats['total']} podcasts to scan");
    
    foreach ($podcasts as $index => $podcast) {
        $podcastNum = $index + 1;
        log_message("[$podcastNum/{$stats['total']}] Processing: {$podcast['title']}");
        
        try {
            // Fetch feed metadata
            $result = $parser->fetchFeedMetadata($podcast['feed_url']);
            
            if ($result['success']) {
                // Check if data actually changed
                $hasChanges = false;
                
                if (isset($result['latest_episode_date']) && 
                    $result['latest_episode_date'] !== $podcast['latest_episode_date']) {
                    $hasChanges = true;
                }
                
                if (isset($result['episode_count']) && 
                    $result['episode_count'] != $podcast['episode_count']) {
                    $hasChanges = true;
                }
                
                if ($hasChanges) {
                    // Update podcast with new metadata
                    $updateData = [
                        'latest_episode_date' => $result['latest_episode_date'] ?? '',
                        'episode_count' => $result['episode_count'] ?? '0'
                    ];
                    
                    $updateResult = $podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
                    
                    if ($updateResult['success']) {
                        $stats['updated']++;
                        $episodeDate = $result['latest_episode_date'] ?? 'Unknown';
                        log_message("  ✓ Updated - Latest episode: $episodeDate, Episodes: {$result['episode_count']}");
                    } else {
                        $stats['failed']++;
                        $stats['errors'][] = [
                            'podcast' => $podcast['title'],
                            'error' => 'Update failed: ' . ($updateResult['message'] ?? 'Unknown error')
                        ];
                        log_message("  ✗ Update failed: " . ($updateResult['message'] ?? 'Unknown error'));
                    }
                } else {
                    $stats['skipped']++;
                    log_message("  → No changes detected");
                }
            } else {
                $stats['failed']++;
                $error = $result['error'] ?? 'Unknown error';
                $stats['errors'][] = [
                    'podcast' => $podcast['title'],
                    'error' => $error
                ];
                log_message("  ✗ Failed to fetch feed: $error");
            }
            
        } catch (Exception $e) {
            $stats['failed']++;
            $stats['errors'][] = [
                'podcast' => $podcast['title'],
                'error' => $e->getMessage()
            ];
            log_message("  ✗ Exception: " . $e->getMessage());
        }
        
        // Delay between feeds to avoid hammering servers
        if ($podcastNum < $stats['total']) {
            sleep($config['delay_between_feeds']);
        }
    }
    
    // Calculate execution time
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);
    
    // Summary
    log_message("========================================");
    log_message("Auto-Scan Completed");
    log_message("========================================");
    log_message("Total Podcasts: {$stats['total']}");
    log_message("Updated: {$stats['updated']}");
    log_message("No Changes: {$stats['skipped']}");
    log_message("Failed: {$stats['failed']}");
    log_message("Execution Time: {$executionTime}s");
    
    if (!empty($stats['errors'])) {
        log_message("========================================");
        log_message("Errors:");
        foreach ($stats['errors'] as $error) {
            log_message("  - {$error['podcast']}: {$error['error']}");
        }
    }
    
    log_message("========================================\n");
    
    // Update last scan timestamp
    updateLastScanTime();
    
    // Exit with appropriate code
    exit($stats['failed'] > 0 ? 1 : 0);
    
} catch (Exception $e) {
    log_message("FATAL ERROR: " . $e->getMessage());
    log_message("Stack trace: " . $e->getTraceAsString());
    log_message("========================================\n");
    exit(1);
}

/**
 * Log message to file and stdout
 */
function log_message($message) {
    global $config;
    
    $logEntry = date('[Y-m-d H:i:s] ') . $message . PHP_EOL;
    
    // Output to console
    echo $logEntry;
    
    // Write to log file
    $logDir = dirname($config['log_file']);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND);
}

/**
 * Update last scan timestamp
 */
function updateLastScanTime() {
    $timestampFile = __DIR__ . '/../data/last-scan.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($timestampFile, $timestamp);
    log_message("Last scan timestamp updated: $timestamp");
}
