# Feed Health Monitoring System - Design Document

## 🎯 Overview

A comprehensive system to track, monitor, and automatically flag problematic podcast feeds with detailed error reporting and recovery mechanisms.

## 🏗️ Architecture

### Core Components

1. **FeedHealthMonitor** - Tracks feed health metrics and errors
2. **Extended XML Schema** - Stores health data per podcast
3. **Auto-Flagging System** - Automatically marks feeds as inactive after failures
4. **Health Dashboard** - Visual overview of all feed statuses
5. **Error Recovery** - Auto-reactivation when feeds recover

## 📊 Health Status Levels

### Status Types:
- **🟢 Healthy** - Feed accessible, valid RSS, recent episodes
- **🟡 Warning** - Slow response, minor issues, stale content
- **🟠 Degraded** - Intermittent failures, parsing errors
- **🔴 Critical** - Consistent failures, unreachable, invalid format
- **⚫ Inactive** - Auto-disabled after repeated failures

### Health Metrics:
```php
[
    'health_status' => 'healthy|warning|degraded|critical|inactive',
    'last_check_date' => '2025-10-15 16:00:00',
    'last_success_date' => '2025-10-15 16:00:00',
    'consecutive_failures' => 0,
    'total_failures' => 0,
    'total_checks' => 100,
    'success_rate' => 98.5,
    'avg_response_time' => 1.2,
    'last_error' => 'Connection timeout',
    'error_history' => [...],
]
```

## 🔧 Auto-Flagging Rules

### Automatic Status Changes:

```
Healthy → Warning:
- Response time > 3 seconds
- No new episodes in 90 days
- Success rate < 95%

Warning → Degraded:
- 2 consecutive failures
- Response time > 5 seconds
- Success rate < 85%

Degraded → Critical:
- 3 consecutive failures
- Response time > 8 seconds
- Success rate < 70%

Critical → Inactive:
- 5 consecutive failures
- OR 10+ failures in last 20 checks
- OR Feed unreachable for 7+ days
```

### Auto-Recovery:

```
Inactive → Critical:
- 1 successful check

Critical → Degraded:
- 2 consecutive successes

Degraded → Warning:
- 5 consecutive successes

Warning → Healthy:
- 10 consecutive successes
- Response time < 2 seconds
- Success rate > 95%
```

## 📝 XML Schema Extension

### New Fields in podcasts.xml:

```xml
<podcast id="pod_123">
    <!-- Existing fields -->
    <title>Podcast Name</title>
    <feed_url>https://...</feed_url>
    <status>active</status>
    
    <!-- NEW: Health Monitoring Fields -->
    <health_status>healthy</health_status>
    <last_check_date>2025-10-15 16:00:00</last_check_date>
    <last_success_date>2025-10-15 16:00:00</last_success_date>
    <consecutive_failures>0</consecutive_failures>
    <total_failures>0</total_failures>
    <total_checks>100</total_checks>
    <avg_response_time>1.2</avg_response_time>
    <last_error></last_error>
    <last_error_date></last_error_date>
    <auto_disabled>false</auto_disabled>
    <auto_disabled_date></auto_disabled_date>
</podcast>
```

## 🎨 UI Components

### 1. Feed Health Badge
```
🟢 Healthy (98.5% uptime)
🟡 Warning (Slow response)
🟠 Degraded (2 failures)
🔴 Critical (5 failures)
⚫ Auto-Disabled (Feed unreachable)
```

### 2. Health Details Modal
- **Status Timeline** - Visual graph of health over time
- **Error Log** - Last 10 errors with timestamps
- **Performance Metrics** - Response time, success rate
- **Quick Actions** - Force check, reset errors, reactivate

### 3. Health Dashboard Page
- **Overview Cards** - Total healthy/warning/critical/inactive
- **Feed List** - Sortable by health status
- **Bulk Actions** - Recheck all, disable all critical
- **Export Report** - CSV/JSON of all feed health

## 🔄 Cron Job Integration

### Enhanced auto-scan-feeds.php:

```php
foreach ($podcasts as $podcast) {
    $startTime = microtime(true);
    
    try {
        $result = $parser->fetchAndParse($podcast['feed_url']);
        $responseTime = microtime(true) - $startTime;
        
        if ($result['success']) {
            // Record success
            $healthMonitor->recordSuccess($podcast['id'], $responseTime);
        } else {
            // Record failure with error details
            $healthMonitor->recordFailure($podcast['id'], $result['error']);
        }
        
        // Update health status based on metrics
        $healthMonitor->updateHealthStatus($podcast['id']);
        
    } catch (Exception $e) {
        $healthMonitor->recordFailure($podcast['id'], $e->getMessage());
    }
}
```

## 📧 Notification System (Future)

### Alert Triggers:
- Feed moves to Critical status
- Feed auto-disabled
- Multiple feeds failing simultaneously
- Success rate drops below threshold

### Notification Methods:
- Email alerts
- Webhook to external monitoring
- Slack/Discord integration
- In-app notifications

## 🛠️ Implementation Plan

### Phase 1: Core Health Tracking ✅ (This PR)
- [x] FeedHealthMonitor class
- [x] Extended XML schema
- [x] Basic health status tracking
- [x] Auto-flagging logic

### Phase 2: UI Integration
- [ ] Health status badges in main table
- [ ] Health details modal
- [ ] Error history display
- [ ] Manual reactivation controls

### Phase 3: Dashboard
- [ ] Dedicated health dashboard page
- [ ] Visual charts and graphs
- [ ] Bulk operations
- [ ] Export functionality

### Phase 4: Advanced Features
- [ ] Email notifications
- [ ] Webhook integrations
- [ ] Historical trend analysis
- [ ] Predictive alerts

## 📊 Database Schema

### Error History (Separate XML File)

```xml
<!-- data/feed-errors.xml -->
<feed_errors>
    <feed id="pod_123">
        <error>
            <timestamp>2025-10-15 16:00:00</timestamp>
            <type>timeout</type>
            <message>Connection timed out after 3000ms</message>
            <http_code>0</http_code>
            <response_time>3.1</response_time>
        </error>
        <error>
            <timestamp>2025-10-15 15:30:00</timestamp>
            <type>parse_error</type>
            <message>Invalid XML structure</message>
            <http_code>200</http_code>
            <response_time>1.2</response_time>
        </error>
    </feed>
</feed_errors>
```

## 🎯 User Experience Flow

### For Admins:

1. **Dashboard Overview**
   - See all feeds at a glance
   - Color-coded health indicators
   - Quick stats (98% healthy, 2% critical)

2. **Problem Detection**
   - Red badge appears on problematic feed
   - Click to see error details
   - View error history and patterns

3. **Investigation**
   - See exact error messages
   - Check response times
   - View success/failure timeline

4. **Resolution**
   - Fix feed URL if needed
   - Force recheck to verify fix
   - Manually reactivate if auto-disabled
   - Monitor recovery progress

### For End Users (Flutter App):

- Only see active, healthy feeds
- Auto-disabled feeds hidden from feed
- Transparent experience (bad feeds filtered out)

## 🔒 Safety Features

### Prevent False Positives:
- Require multiple consecutive failures
- Consider time of day (maintenance windows)
- Exponential backoff for checks
- Manual override capability

### Prevent Cascading Failures:
- Rate limiting per domain
- Stagger health checks
- Timeout limits (3s max)
- Circuit breaker pattern

### Data Integrity:
- Backup before auto-disabling
- Audit log of all status changes
- Reversible operations
- Manual review queue

## 📈 Success Metrics

### System Health:
- % of feeds in healthy status
- Average response time across all feeds
- False positive rate
- Auto-recovery success rate

### User Impact:
- Reduced support tickets
- Faster problem identification
- Improved feed reliability
- Better user experience

## 🚀 Quick Start (After Implementation)

```bash
# Check all feed health
php cron/auto-scan-feeds.php

# View health dashboard
https://your-domain.com/health-dashboard.php

# Force check single feed
curl -X POST https://your-domain.com/api/check-feed-health.php?id=pod_123

# Export health report
curl https://your-domain.com/api/export-health-report.php > health-report.json
```

## 📚 API Endpoints (New)

```
GET  /api/feed-health.php?id=pod_123        # Get health status
POST /api/check-feed-health.php?id=pod_123  # Force health check
POST /api/reactivate-feed.php?id=pod_123    # Manual reactivation
GET  /api/health-report.php                  # All feeds health
POST /api/reset-health-errors.php?id=pod_123 # Clear error history
```

---

**Status:** 📋 Design Complete - Ready for Implementation  
**Priority:** High - Prevents future deployment issues  
**Estimated Time:** 4-6 hours for Phase 1  
**Dependencies:** None - Builds on existing codebase
