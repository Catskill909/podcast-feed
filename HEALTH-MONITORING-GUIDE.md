# Health Monitoring System - User Guide

## 🎯 **Where Is It? How Does It Work?**

The health monitoring system is **working automatically in the background**. Here's where you can see it:

---

## 📊 **1. Cron Job Output** (Primary View)

Every time the cron job runs (every 30 minutes), you'll see:

```bash
php cron/auto-scan-feeds.php
```

**Output:**
```
[2025-10-15 20:28:21] Feed Health Status:
[2025-10-15 20:28:21]   🟢 Healthy: 4
[2025-10-15 20:28:21]   🟡 Warning: 0
[2025-10-15 20:28:21]   🟠 Degraded: 0
[2025-10-15 20:28:21]   🔴 Critical: 0
[2025-10-15 20:28:21]   ⚫ Inactive: 0
[2025-10-15 20:28:21]   Avg Success Rate: 100%
[2025-10-15 20:28:21]   Avg Response Time: 0s
```

**This tells you:**
- How many feeds are healthy vs. having issues
- Overall success rate
- Average response time

---

## 🔌 **2. Health API** (Programmatic Access)

### Get Overall Health Summary:
```bash
curl "http://localhost:8000/api/feed-health.php?action=summary"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "total": 4,
    "healthy": 4,
    "warning": 0,
    "degraded": 0,
    "critical": 0,
    "inactive": 0,
    "avg_success_rate": 100,
    "avg_response_time": 0
  }
}
```

### Get Specific Podcast Health:
```bash
curl "http://localhost:8000/api/feed-health.php?action=status&id=pod_1760559709_68f0025d8dbdf"
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": "pod_1760559709_68f0025d8dbdf",
    "title": "WJFF - Radio Chatskill",
    "health_status": "healthy",
    "consecutive_failures": 0,
    "total_failures": 0,
    "total_checks": 0,
    "success_rate": 100,
    "avg_response_time": 0,
    "last_error": "",
    "error_history": []
  }
}
```

---

## 🤖 **3. Automatic Behavior** (What Happens Behind the Scenes)

### When Cron Runs Every 30 Minutes:

```
For Each Podcast:
  1. Fetch RSS feed
  2. Record success OR failure
  3. Update health metrics
  4. Calculate health status:
     - 0-1 failures = 🟢 Healthy
     - 2 failures = 🟡 Warning
     - 3-4 failures = 🟠 Degraded
     - 5+ failures = 🔴 Critical
  5. Auto-disable if 5 consecutive failures
```

### Example Scenario:

**Day 1:**
```
WJFF Feed: 🟢 Healthy (0 failures)
```

**Day 2 - Feed has issues:**
```
Check 1: ❌ Timeout → 🟡 Warning (1 failure)
Check 2: ❌ Timeout → 🟡 Warning (2 failures)
Check 3: ❌ Timeout → 🟠 Degraded (3 failures)
Check 4: ❌ Timeout → 🔴 Critical (4 failures)
Check 5: ❌ Timeout → ⚫ Auto-Disabled (5 failures)
```

**Result:**
- Feed status changed to "inactive"
- Feed hidden from public RSS feed
- Error logged with details
- Admin can manually reactivate

**Day 3 - Feed fixed:**
```
Admin runs: curl -X POST "http://localhost:8000/api/feed-health.php?action=reactivate&id=pod_123"
Next cron check: ✅ Success → 🟢 Healthy again
```

---

## 📁 **4. Data Storage** (Where It Lives)

### Main Database (`data/podcasts.xml`):
```xml
<podcast id="pod_1760559709_68f0025d8dbdf">
  <title>WJFF - Radio Chatskill</title>
  
  <!-- Health Monitoring Fields -->
  <health_status>healthy</health_status>
  <last_success_date>2025-10-15 20:21:49</last_success_date>
  <consecutive_failures>0</consecutive_failures>
  <total_failures>0</total_failures>
  <total_checks>0</total_checks>
  <success_rate>100</success_rate>
  <avg_response_time>0</avg_response_time>
  <last_error></last_error>
  <auto_disabled>false</auto_disabled>
</podcast>
```

### Error History (`data/feed-errors.xml`):
```xml
<feed_errors>
  <feed id="pod_123">
    <error>
      <timestamp>2025-10-15 16:00:00</timestamp>
      <type>timeout</type>
      <message>Connection timed out after 3000ms</message>
      <http_code>0</http_code>
      <response_time>3.1</response_time>
    </error>
  </feed>
</feed_errors>
```

---

## 🎨 **5. UI Integration** (Coming in Phase 2)

**Currently:** Health data exists but no visual indicators in the web UI yet.

**Phase 2 will add:**
- Health status badges next to each podcast in the table
- Click badge to see error details modal
- Health dashboard page with charts
- Bulk reactivation controls

**Example (Future):**
```
Podcast Table:
┌─────────────────────────────────┬──────────┬─────────────────┐
│ Title                           │ Status   │ Latest Episode  │
├─────────────────────────────────┼──────────┼─────────────────┤
│ WJFF 🟢 Healthy                 │ Active   │ Today           │
│ Labor Radio 🟡 Warning          │ Active   │ 2 days ago      │
│ Bad Feed 🔴 Critical            │ Active   │ Unknown         │
│ Broken Feed ⚫ Auto-Disabled    │ Inactive │ 7 days ago      │
└─────────────────────────────────┴──────────┴─────────────────┘
```

---

## 🧪 **How to Test It**

### Test Auto-Flagging (Simulate Bad Feed):

1. **Add a fake bad feed:**
   ```bash
   # In your browser, add a podcast with this URL:
   https://this-does-not-exist-12345.com/feed.xml
   ```

2. **Run cron job 5 times:**
   ```bash
   php cron/auto-scan-feeds.php  # Run 1 - 🟡 Warning
   php cron/auto-scan-feeds.php  # Run 2 - 🟡 Warning
   php cron/auto-scan-feeds.php  # Run 3 - 🟠 Degraded
   php cron/auto-scan-feeds.php  # Run 4 - 🔴 Critical
   php cron/auto-scan-feeds.php  # Run 5 - ⚫ Auto-Disabled
   ```

3. **Check the output:**
   ```
   [4/4] Processing: Bad Feed
     ✗ Failed to fetch feed: Connection timed out
   
   Feed Health Status:
     🟢 Healthy: 3
     ⚫ Inactive: 1  ← Your bad feed!
   ```

4. **Check the feed status:**
   ```bash
   curl "http://localhost:8000/api/feed-health.php?action=status&id=pod_xxx"
   ```

5. **Reactivate it:**
   ```bash
   curl -X POST "http://localhost:8000/api/feed-health.php?action=reactivate&id=pod_xxx"
   ```

---

## 📋 **API Endpoints Reference**

### Get Health Summary:
```bash
GET /api/feed-health.php?action=summary
```

### Get Podcast Health:
```bash
GET /api/feed-health.php?action=status&id=pod_123
```

### Force Health Check:
```bash
POST /api/feed-health.php?action=force_check&id=pod_123
```

### Reactivate Feed:
```bash
POST /api/feed-health.php?action=reactivate&id=pod_123
```

### Reset Error Counters:
```bash
POST /api/feed-health.php?action=reset_errors&id=pod_123
```

---

## 🔍 **Monitoring in Production**

### Check Cron Logs:
```bash
tail -f logs/auto-scan.log
```

### Check Overall Health:
```bash
curl "https://your-domain.com/api/feed-health.php?action=summary"
```

### Check for Auto-Disabled Feeds:
```bash
curl "https://your-domain.com/api/feed-health.php?action=summary" | grep inactive
```

### View Error History:
```bash
cat data/feed-errors.xml
```

---

## ❓ **FAQ**

### Q: Why don't I see health badges in the UI?
**A:** Phase 1 implemented the backend system. Phase 2 will add visual indicators to the web interface. The data is there and working, just not displayed yet.

### Q: How do I know if a feed is having issues?
**A:** Check the cron log output or use the API. You'll see warnings/critical status in the health summary.

### Q: What happens to auto-disabled feeds?
**A:** They're marked as "inactive" and hidden from the public RSS feed. You can reactivate them via the API.

### Q: Can I adjust the thresholds?
**A:** Yes! Edit `includes/FeedHealthMonitor.php` constants:
```php
const THRESHOLD_WARNING_FAILURES = 2;
const THRESHOLD_CRITICAL_FAILURES = 5;
const THRESHOLD_AUTO_DISABLE_FAILURES = 5;
```

### Q: Does this slow down the cron job?
**A:** No, it adds minimal overhead (just recording metrics). The cron job already fetches feeds, we're just tracking the results now.

---

## 🎯 **Summary**

**The health monitoring IS working right now!** ✅

You just don't see it in the UI yet because we haven't built the visual components (Phase 2).

**Where to see it:**
1. ✅ Cron job output (every 30 min)
2. ✅ API endpoints (programmatic access)
3. ✅ XML database (raw data)
4. ⏳ Web UI (coming in Phase 2)

**What it does automatically:**
- Tracks every feed check
- Records successes and failures
- Auto-disables bad feeds after 5 failures
- Logs detailed error history
- Provides API for management

**You don't need to do anything - it's working in the background!** 🚀
