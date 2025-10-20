<?php
/**
 * Clone Feed API Endpoint
 * Handles podcast feed cloning requests and progress tracking
 * 
 * Actions:
 * - validate: Validate feed before cloning
 * - start: Start cloning process
 * - progress: Get current progress
 * - cancel: Cancel cloning (future)
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/PodcastFeedCloner.php';

// Disable output buffering for long-running processes
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);

header('Content-Type: application/json');

// Only allow POST for start/validate, GET for progress
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'validate':
            handleValidate();
            break;
            
        case 'start':
            handleStart();
            break;
            
        case 'progress':
            handleProgress();
            break;
            
        case 'cancel':
            handleCancel();
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log('Clone Feed API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Validate feed before cloning
 */
function handleValidate()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $feedUrl = $_POST['feed_url'] ?? '';

    if (empty($feedUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Feed URL is required']);
        return;
    }

    $cloner = new PodcastFeedCloner();
    $result = $cloner->validateFeedForCloning($feedUrl);

    echo json_encode($result);
}

/**
 * Start cloning process
 */
function handleStart()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $feedUrl = $_POST['feed_url'] ?? '';

    if (empty($feedUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Feed URL is required']);
        return;
    }

    // Parse options
    $options = [
        'download_episode_images' => !empty($_POST['download_episode_images']),
        'import_to_directory' => !empty($_POST['import_to_directory']),
        'limit_episodes' => !empty($_POST['limit_episodes']) ? intval($_POST['limit_episodes']) : 0
    ];

    // Increase time limit for long-running process
    set_time_limit(3600); // 1 hour

    // Create cloner and start
    $cloner = new PodcastFeedCloner();
    $jobId = $cloner->getJobId();

    // Store job ID in session for progress tracking
    $_SESSION['clone_job_id'] = $jobId;

    // Start cloning (this will take a while)
    $result = $cloner->cloneFeed($feedUrl, $options);

    // Return result with job ID
    echo json_encode(array_merge($result, [
        'job_id' => $jobId
    ]));
}

/**
 * Get cloning progress
 */
function handleProgress()
{
    $jobId = $_GET['job_id'] ?? $_SESSION['clone_job_id'] ?? '';

    if (empty($jobId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Job ID is required']);
        return;
    }

    $cloner = new PodcastFeedCloner($jobId);
    $progress = $cloner->getProgress();

    if (empty($progress)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Job not found or expired'
        ]);
        return;
    }

    // Calculate elapsed time
    $elapsed = time() - ($progress['started_at'] ?? time());
    $progress['elapsed_seconds'] = $elapsed;
    $progress['elapsed_formatted'] = formatDuration($elapsed);

    // Estimate remaining time
    if (!empty($progress['percent']) && $progress['percent'] > 0) {
        $totalEstimated = ($elapsed / $progress['percent']) * 100;
        $remaining = $totalEstimated - $elapsed;
        $progress['remaining_seconds'] = max(0, $remaining);
        $progress['remaining_formatted'] = formatDuration(max(0, $remaining));
    }

    echo json_encode([
        'success' => true,
        'progress' => $progress
    ]);
}

/**
 * Cancel cloning (future enhancement)
 */
function handleCancel()
{
    $jobId = $_POST['job_id'] ?? $_SESSION['clone_job_id'] ?? '';

    if (empty($jobId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Job ID is required']);
        return;
    }

    // Future: Implement cancellation logic
    // For now, just clean up progress file
    $cloner = new PodcastFeedCloner($jobId);
    $cloner->cleanupProgress();

    echo json_encode([
        'success' => true,
        'message' => 'Cloning cancelled'
    ]);
}

/**
 * Format duration in seconds to human-readable format
 */
function formatDuration($seconds)
{
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . 'm ' . $secs . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}
