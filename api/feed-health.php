<?php
/**
 * Feed Health API
 * Get health status, reactivate feeds, reset errors
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/FeedHealthMonitor.php';
require_once __DIR__ . '/../includes/PodcastManager.php';

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? 'status';
    $podcastId = $_GET['id'] ?? $_POST['id'] ?? '';
    
    $healthMonitor = new FeedHealthMonitor();
    
    switch ($action) {
        case 'status':
            // Get health status for a specific podcast
            if (empty($podcastId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast ID is required'
                ]);
                exit;
            }
            
            $podcastManager = new PodcastManager();
            $podcast = $podcastManager->getPodcast($podcastId);
            
            if (!$podcast) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast not found'
                ]);
                exit;
            }
            
            $errorHistory = $healthMonitor->getErrorHistory($podcastId, 10);
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $podcast['id'],
                    'title' => $podcast['title'],
                    'health_status' => $podcast['health_status'] ?? 'healthy',
                    'last_check_date' => $podcast['last_check_date'] ?? '',
                    'last_success_date' => $podcast['last_success_date'] ?? '',
                    'consecutive_failures' => (int)($podcast['consecutive_failures'] ?? 0),
                    'total_failures' => (int)($podcast['total_failures'] ?? 0),
                    'total_checks' => (int)($podcast['total_checks'] ?? 0),
                    'success_rate' => (float)($podcast['success_rate'] ?? 100),
                    'avg_response_time' => (float)($podcast['avg_response_time'] ?? 0),
                    'last_error' => $podcast['last_error'] ?? '',
                    'last_error_date' => $podcast['last_error_date'] ?? '',
                    'auto_disabled' => ($podcast['auto_disabled'] ?? 'false') === 'true',
                    'auto_disabled_date' => $podcast['auto_disabled_date'] ?? '',
                    'error_history' => $errorHistory
                ]
            ]);
            break;
            
        case 'summary':
            // Get health summary for all feeds
            $summary = $healthMonitor->getHealthSummary();
            
            echo json_encode([
                'success' => true,
                'data' => $summary
            ]);
            break;
            
        case 'reactivate':
            // Manually reactivate a feed
            if (empty($podcastId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast ID is required'
                ]);
                exit;
            }
            
            $result = $healthMonitor->reactivateFeed($podcastId);
            
            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(500);
                echo json_encode($result);
            }
            break;
            
        case 'reset_errors':
            // Reset error counters
            if (empty($podcastId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast ID is required'
                ]);
                exit;
            }
            
            $result = $healthMonitor->resetErrors($podcastId);
            
            if ($result['success']) {
                echo json_encode($result);
            } else {
                http_response_code(500);
                echo json_encode($result);
            }
            break;
            
        case 'force_check':
            // Force a health check on a specific feed
            if (empty($podcastId)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast ID is required'
                ]);
                exit;
            }
            
            require_once __DIR__ . '/../includes/RssFeedParser.php';
            
            $podcastManager = new PodcastManager();
            $podcast = $podcastManager->getPodcast($podcastId);
            
            if (!$podcast) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Podcast not found'
                ]);
                exit;
            }
            
            $parser = new RssFeedParser();
            $startTime = microtime(true);
            $result = $parser->fetchFeedMetadata($podcast['feed_url']);
            $responseTime = microtime(true) - $startTime;
            
            if ($result['success']) {
                $healthMonitor->recordSuccess($podcast['id'], $responseTime);
                
                // Update episode data if changed
                if (isset($result['latest_episode_date']) || isset($result['episode_count'])) {
                    $updateData = [];
                    if (isset($result['latest_episode_date'])) {
                        $updateData['latest_episode_date'] = $result['latest_episode_date'];
                    }
                    if (isset($result['episode_count'])) {
                        $updateData['episode_count'] = $result['episode_count'];
                    }
                    $podcastManager->updatePodcastMetadata($podcast['id'], $updateData);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Feed check successful',
                    'data' => [
                        'response_time' => round($responseTime, 2),
                        'episode_count' => $result['episode_count'] ?? 0,
                        'latest_episode_date' => $result['latest_episode_date'] ?? ''
                    ]
                ]);
            } else {
                $error = $result['error'] ?? 'Unknown error';
                $healthMonitor->recordFailure($podcast['id'], $error, 'manual_check', 0, $responseTime);
                
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $error,
                    'response_time' => round($responseTime, 2)
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action. Valid actions: status, summary, reactivate, reset_errors, force_check'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
