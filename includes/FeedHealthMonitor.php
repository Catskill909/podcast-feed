<?php
require_once __DIR__ . '/../config/config.php';

/**
 * FeedHealthMonitor Class
 * Tracks feed health, errors, and automatically flags problematic feeds
 */
class FeedHealthMonitor
{
    private $xmlHandler;
    private $errorLogFile;
    
    // Health status thresholds
    const THRESHOLD_WARNING_FAILURES = 2;
    const THRESHOLD_DEGRADED_FAILURES = 3;
    const THRESHOLD_CRITICAL_FAILURES = 5;
    const THRESHOLD_AUTO_DISABLE_FAILURES = 5;
    
    const THRESHOLD_WARNING_RESPONSE_TIME = 3.0;
    const THRESHOLD_DEGRADED_RESPONSE_TIME = 5.0;
    const THRESHOLD_CRITICAL_RESPONSE_TIME = 8.0;
    
    const THRESHOLD_WARNING_SUCCESS_RATE = 95.0;
    const THRESHOLD_DEGRADED_SUCCESS_RATE = 85.0;
    const THRESHOLD_CRITICAL_SUCCESS_RATE = 70.0;
    
    // Recovery thresholds
    const RECOVERY_CRITICAL_TO_DEGRADED = 2;
    const RECOVERY_DEGRADED_TO_WARNING = 5;
    const RECOVERY_WARNING_TO_HEALTHY = 10;
    
    public function __construct()
    {
        require_once __DIR__ . '/XMLHandler.php';
        $this->xmlHandler = new XMLHandler();
        $this->errorLogFile = DATA_DIR . '/feed-errors.xml';
        $this->initializeErrorLog();
    }
    
    /**
     * Initialize error log file if it doesn't exist
     */
    private function initializeErrorLog()
    {
        if (!file_exists($this->errorLogFile)) {
            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            $root = $xml->createElement('feed_errors');
            $xml->appendChild($root);
            $xml->save($this->errorLogFile);
        }
    }
    
    /**
     * Record a successful feed check
     */
    public function recordSuccess($podcastId, $responseTime = 0)
    {
        try {
            $podcast = $this->xmlHandler->getPodcast($podcastId);
            if (!$podcast) {
                return false;
            }
            
            // Get current health metrics
            $consecutiveFailures = (int)($podcast['consecutive_failures'] ?? 0);
            $totalChecks = (int)($podcast['total_checks'] ?? 0) + 1;
            $totalFailures = (int)($podcast['total_failures'] ?? 0);
            $avgResponseTime = (float)($podcast['avg_response_time'] ?? 0);
            
            // Calculate new average response time
            $newAvgResponseTime = $avgResponseTime > 0 
                ? (($avgResponseTime * ($totalChecks - 1)) + $responseTime) / $totalChecks
                : $responseTime;
            
            // Update health metrics
            $updateData = [
                'last_check_date' => date('Y-m-d H:i:s'),
                'last_success_date' => date('Y-m-d H:i:s'),
                'consecutive_failures' => 0, // Reset on success
                'total_checks' => $totalChecks,
                'total_failures' => $totalFailures,
                'avg_response_time' => round($newAvgResponseTime, 2),
                'last_error' => '', // Clear last error
                'last_error_date' => ''
            ];
            
            $this->xmlHandler->updatePodcast($podcastId, $updateData);
            
            // Update health status based on new metrics
            $this->updateHealthStatus($podcastId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::recordSuccess Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record a failed feed check
     */
    public function recordFailure($podcastId, $errorMessage, $errorType = 'unknown', $httpCode = 0, $responseTime = 0)
    {
        try {
            $podcast = $this->xmlHandler->getPodcast($podcastId);
            if (!$podcast) {
                return false;
            }
            
            // Get current health metrics
            $consecutiveFailures = (int)($podcast['consecutive_failures'] ?? 0) + 1;
            $totalChecks = (int)($podcast['total_checks'] ?? 0) + 1;
            $totalFailures = (int)($podcast['total_failures'] ?? 0) + 1;
            $avgResponseTime = (float)($podcast['avg_response_time'] ?? 0);
            
            // Calculate new average response time
            $newAvgResponseTime = $avgResponseTime > 0 
                ? (($avgResponseTime * ($totalChecks - 1)) + $responseTime) / $totalChecks
                : $responseTime;
            
            // Update health metrics
            $updateData = [
                'last_check_date' => date('Y-m-d H:i:s'),
                'consecutive_failures' => $consecutiveFailures,
                'total_checks' => $totalChecks,
                'total_failures' => $totalFailures,
                'avg_response_time' => round($newAvgResponseTime, 2),
                'last_error' => substr($errorMessage, 0, 255), // Limit error message length
                'last_error_date' => date('Y-m-d H:i:s')
            ];
            
            // Check if should auto-disable
            if ($consecutiveFailures >= self::THRESHOLD_AUTO_DISABLE_FAILURES) {
                $updateData['status'] = 'inactive';
                $updateData['auto_disabled'] = 'true';
                $updateData['auto_disabled_date'] = date('Y-m-d H:i:s');
            }
            
            $this->xmlHandler->updatePodcast($podcastId, $updateData);
            
            // Log error to error history
            $this->logError($podcastId, $errorMessage, $errorType, $httpCode, $responseTime);
            
            // Update health status based on new metrics
            $this->updateHealthStatus($podcastId);
            
            return true;
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::recordFailure Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update health status based on current metrics
     */
    public function updateHealthStatus($podcastId)
    {
        try {
            $podcast = $this->xmlHandler->getPodcast($podcastId);
            if (!$podcast) {
                return false;
            }
            
            $consecutiveFailures = (int)($podcast['consecutive_failures'] ?? 0);
            $totalChecks = (int)($podcast['total_checks'] ?? 0);
            $totalFailures = (int)($podcast['total_failures'] ?? 0);
            $avgResponseTime = (float)($podcast['avg_response_time'] ?? 0);
            $status = $podcast['status'] ?? 'active';
            
            // Calculate success rate
            $successRate = $totalChecks > 0 
                ? (($totalChecks - $totalFailures) / $totalChecks) * 100 
                : 100;
            
            // Determine health status
            $healthStatus = 'healthy';
            
            // Check if auto-disabled
            if ($status === 'inactive' && ($podcast['auto_disabled'] ?? 'false') === 'true') {
                $healthStatus = 'inactive';
            }
            // Critical: 5+ consecutive failures OR very low success rate
            elseif ($consecutiveFailures >= self::THRESHOLD_CRITICAL_FAILURES || 
                    $successRate < self::THRESHOLD_CRITICAL_SUCCESS_RATE ||
                    $avgResponseTime > self::THRESHOLD_CRITICAL_RESPONSE_TIME) {
                $healthStatus = 'critical';
            }
            // Degraded: 3+ consecutive failures OR low success rate
            elseif ($consecutiveFailures >= self::THRESHOLD_DEGRADED_FAILURES || 
                    $successRate < self::THRESHOLD_DEGRADED_SUCCESS_RATE ||
                    $avgResponseTime > self::THRESHOLD_DEGRADED_RESPONSE_TIME) {
                $healthStatus = 'degraded';
            }
            // Warning: 2+ consecutive failures OR moderate success rate
            elseif ($consecutiveFailures >= self::THRESHOLD_WARNING_FAILURES || 
                    $successRate < self::THRESHOLD_WARNING_SUCCESS_RATE ||
                    $avgResponseTime > self::THRESHOLD_WARNING_RESPONSE_TIME) {
                $healthStatus = 'warning';
            }
            
            // Update health status
            $this->xmlHandler->updatePodcast($podcastId, [
                'health_status' => $healthStatus,
                'success_rate' => round($successRate, 2)
            ]);
            
            return $healthStatus;
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::updateHealthStatus Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log error to error history file
     */
    private function logError($podcastId, $errorMessage, $errorType, $httpCode, $responseTime)
    {
        try {
            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;
            $xml->load($this->errorLogFile);
            
            $root = $xml->documentElement;
            
            // Find or create feed element
            $feedElement = null;
            foreach ($root->getElementsByTagName('feed') as $feed) {
                if ($feed->getAttribute('id') === $podcastId) {
                    $feedElement = $feed;
                    break;
                }
            }
            
            if (!$feedElement) {
                $feedElement = $xml->createElement('feed');
                $feedElement->setAttribute('id', $podcastId);
                $root->appendChild($feedElement);
            }
            
            // Create error element
            $errorElement = $xml->createElement('error');
            
            $timestamp = $xml->createElement('timestamp', date('Y-m-d H:i:s'));
            $errorElement->appendChild($timestamp);
            
            $type = $xml->createElement('type', htmlspecialchars($errorType));
            $errorElement->appendChild($type);
            
            $message = $xml->createElement('message', htmlspecialchars(substr($errorMessage, 0, 500)));
            $errorElement->appendChild($message);
            
            $code = $xml->createElement('http_code', (string)$httpCode);
            $errorElement->appendChild($code);
            
            $time = $xml->createElement('response_time', (string)round($responseTime, 2));
            $errorElement->appendChild($time);
            
            // Add to beginning (most recent first)
            if ($feedElement->firstChild) {
                $feedElement->insertBefore($errorElement, $feedElement->firstChild);
            } else {
                $feedElement->appendChild($errorElement);
            }
            
            // Keep only last 50 errors per feed
            $errors = $feedElement->getElementsByTagName('error');
            while ($errors->length > 50) {
                $feedElement->removeChild($errors->item($errors->length - 1));
            }
            
            $xml->save($this->errorLogFile);
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::logError Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get error history for a podcast
     */
    public function getErrorHistory($podcastId, $limit = 10)
    {
        try {
            if (!file_exists($this->errorLogFile)) {
                return [];
            }
            
            $xml = new DOMDocument();
            $xml->load($this->errorLogFile);
            
            $errors = [];
            foreach ($xml->getElementsByTagName('feed') as $feed) {
                if ($feed->getAttribute('id') === $podcastId) {
                    $errorElements = $feed->getElementsByTagName('error');
                    $count = 0;
                    
                    foreach ($errorElements as $error) {
                        if ($count >= $limit) break;
                        
                        $errors[] = [
                            'timestamp' => $error->getElementsByTagName('timestamp')->item(0)->nodeValue ?? '',
                            'type' => $error->getElementsByTagName('type')->item(0)->nodeValue ?? '',
                            'message' => $error->getElementsByTagName('message')->item(0)->nodeValue ?? '',
                            'http_code' => $error->getElementsByTagName('http_code')->item(0)->nodeValue ?? '',
                            'response_time' => $error->getElementsByTagName('response_time')->item(0)->nodeValue ?? ''
                        ];
                        
                        $count++;
                    }
                    break;
                }
            }
            
            return $errors;
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::getErrorHistory Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Manually reactivate a feed (clears auto-disabled flag)
     */
    public function reactivateFeed($podcastId)
    {
        try {
            $updateData = [
                'status' => 'active',
                'auto_disabled' => 'false',
                'auto_disabled_date' => '',
                'consecutive_failures' => 0,
                'last_error' => ''
            ];
            
            $this->xmlHandler->updatePodcast($podcastId, $updateData);
            $this->updateHealthStatus($podcastId);
            
            return [
                'success' => true,
                'message' => 'Feed reactivated successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset error counters for a feed
     */
    public function resetErrors($podcastId)
    {
        try {
            $updateData = [
                'consecutive_failures' => 0,
                'total_failures' => 0,
                'last_error' => '',
                'last_error_date' => ''
            ];
            
            $this->xmlHandler->updatePodcast($podcastId, $updateData);
            $this->updateHealthStatus($podcastId);
            
            return [
                'success' => true,
                'message' => 'Error counters reset successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get health summary for all feeds
     */
    public function getHealthSummary()
    {
        try {
            $podcasts = $this->xmlHandler->getAllPodcasts();
            
            $summary = [
                'total' => count($podcasts),
                'healthy' => 0,
                'warning' => 0,
                'degraded' => 0,
                'critical' => 0,
                'inactive' => 0,
                'avg_success_rate' => 0,
                'avg_response_time' => 0
            ];
            
            $totalSuccessRate = 0;
            $totalResponseTime = 0;
            $count = 0;
            
            foreach ($podcasts as $podcast) {
                $healthStatus = $podcast['health_status'] ?? 'healthy';
                $summary[$healthStatus]++;
                
                if (isset($podcast['success_rate'])) {
                    $totalSuccessRate += (float)$podcast['success_rate'];
                    $count++;
                }
                
                if (isset($podcast['avg_response_time'])) {
                    $totalResponseTime += (float)$podcast['avg_response_time'];
                }
            }
            
            $summary['avg_success_rate'] = $count > 0 ? round($totalSuccessRate / $count, 2) : 0;
            $summary['avg_response_time'] = $count > 0 ? round($totalResponseTime / $count, 2) : 0;
            
            return $summary;
            
        } catch (Exception $e) {
            error_log("FeedHealthMonitor::getHealthSummary Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get health badge HTML for display
     */
    public function getHealthBadge($podcast)
    {
        $healthStatus = $podcast['health_status'] ?? 'healthy';
        $consecutiveFailures = (int)($podcast['consecutive_failures'] ?? 0);
        $successRate = (float)($podcast['success_rate'] ?? 100);
        $autoDisabled = ($podcast['auto_disabled'] ?? 'false') === 'true';
        
        $badges = [
            'healthy' => [
                'icon' => '🟢',
                'text' => 'Healthy',
                'class' => 'badge-success',
                'tooltip' => sprintf('Success rate: %.1f%%', $successRate)
            ],
            'warning' => [
                'icon' => '🟡',
                'text' => 'Warning',
                'class' => 'badge-warning',
                'tooltip' => sprintf('%d failures, %.1f%% success', $consecutiveFailures, $successRate)
            ],
            'degraded' => [
                'icon' => '🟠',
                'text' => 'Degraded',
                'class' => 'badge-warning',
                'tooltip' => sprintf('%d consecutive failures', $consecutiveFailures)
            ],
            'critical' => [
                'icon' => '🔴',
                'text' => 'Critical',
                'class' => 'badge-danger',
                'tooltip' => sprintf('%d consecutive failures', $consecutiveFailures)
            ],
            'inactive' => [
                'icon' => '⚫',
                'text' => $autoDisabled ? 'Auto-Disabled' : 'Inactive',
                'class' => 'badge-secondary',
                'tooltip' => $autoDisabled ? 'Automatically disabled due to repeated failures' : 'Manually disabled'
            ]
        ];
        
        $badge = $badges[$healthStatus] ?? $badges['healthy'];
        
        return sprintf(
            '<span class="badge %s" title="%s">%s %s</span>',
            $badge['class'],
            htmlspecialchars($badge['tooltip']),
            $badge['icon'],
            $badge['text']
        );
    }
}
