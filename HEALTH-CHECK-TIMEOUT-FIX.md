# Health Check Timeout Fix - October 15, 2025

## 🚨 Problem Summary

**Symptoms:**
- Server health checks timing out (5 second timeout exceeded)
- Deployment failing with "Health check exceeded timeout" errors
- Server appearing down/unresponsive
- Error logs showing: `RSS Fetch Error: HTTP 0 - Connection timed out after 10002 milliseconds`

**Root Cause:**
A user entered a podcast feed with a bad/slow URL. The application was fetching RSS feeds **synchronously on every page load** in `PodcastManager::getAllPodcasts()`, which blocked the entire page load including health checks.

## 🔍 Technical Analysis

### The Blocking Code (BEFORE):
```php
// includes/PodcastManager.php - Line 310-334
public function getAllPodcasts($includeImageInfo = false): array
{
    $podcasts = $this->xmlHandler->getAllPodcasts();
    
    // ❌ THIS WAS BLOCKING EVERY PAGE LOAD
    foreach ($podcasts as &$podcast) {
        if (!empty($podcast['feed_url'])) {
            $parser = new RssFeedParser();
            $feedData = $parser->fetchAndParse($podcast['feed_url']); // 10 second timeout PER FEED!
            
            if ($feedData['success']) {
                $podcast['latest_episode_date'] = $feedData['data']['latest_episode_date'];
                $podcast['episode_count'] = $feedData['data']['episode_count'];
            }
        }
    }
    
    return $podcasts;
}
```

**Why This Failed:**
1. `index.php` calls `getAllPodcasts()` on line 81 (every page load)
2. Health check endpoint (`/health.php`) loads → triggers `index.php` → calls `getAllPodcasts()`
3. If ANY feed is slow/broken, it blocks for 10+ seconds
4. Health check has 5 second timeout → FAILS
5. Coolify can't deploy because health check never passes

## ✅ Solution Implemented

### 1. Removed RSS Fetching from Page Load
**File:** `includes/PodcastManager.php`

```php
/**
 * Get all podcasts
 * NOTE: Does NOT fetch RSS feeds on page load (performance/health check fix)
 * Use refreshPodcastFeed() or cron job to update episode data
 */
public function getAllPodcasts($includeImageInfo = false): array
{
    $podcasts = $this->xmlHandler->getAllPodcasts();
    
    // ✅ Only include image info - NO RSS fetching
    foreach ($podcasts as &$podcast) {
        if ($includeImageInfo && !empty($podcast['cover_image'])) {
            $imageInfo = $this->imageUploader->getImageInfo($podcast['cover_image']);
            $podcast['image_info'] = $imageInfo;
        }
    }
    
    return $podcasts;
}
```

### 2. Reduced RSS Timeouts
**File:** `includes/RssFeedParser.php`

- **Timeout:** 10s → 3s (faster failures)
- **Connection Timeout:** Added 2s limit
- **Added Caching:** 1 hour cache for successful fetches

```php
private $timeout = 3; // seconds (reduced for faster failures)
private $cacheTime = 3600; // Cache results for 1 hour

private function fetchFeedContent($url)
{
    // Try cache first
    $cached = $this->getCachedFeed($url);
    if ($cached !== false) {
        return $cached;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_TIMEOUT => $this->timeout,           // 3 seconds
        CURLOPT_CONNECTTIMEOUT => 2,                 // 2 seconds
        // ... other options
    ]);
    
    // Cache successful responses
    if ($content) {
        $this->cacheFeed($url, $content);
    }
}
```

## 📊 How Episode Data Updates Now

### Before (Synchronous - BLOCKING):
```
User loads page → getAllPodcasts() → Fetch ALL RSS feeds → Wait 10s+ → Display page
                                    ↑ BLOCKS HEALTH CHECK
```

### After (Async - NON-BLOCKING):
```
User loads page → getAllPodcasts() → Read from XML → Display page (FAST!)
                                                      ↑ HEALTH CHECK PASSES

Episode updates happen via:
1. Cron job (every 30 min) → Updates all feeds in background
2. Manual refresh button → Updates single feed on-demand
3. Podcast preview modal → Fetches fresh data when clicked
```

## 🎯 What This Fixes

✅ **Health checks pass** - Page loads instantly without RSS fetching  
✅ **Deployment works** - No more timeout errors  
✅ **Server stays responsive** - Bad feeds don't block the entire app  
✅ **Better performance** - Caching reduces redundant fetches  
✅ **Graceful degradation** - Shows stored data if feeds are down  

## 🔄 Episode Data Update Methods

### 1. Automated Cron Job (Recommended)
```bash
# Runs every 30 minutes
*/30 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
```

### 2. Manual Refresh Button
- Click 🔄 button on any podcast
- Fetches fresh data for that single podcast
- Updates episode count and latest date

### 3. Podcast Preview Modal
- Click on podcast cover/title
- Modal fetches fresh RSS data
- Falls back to stored data if fetch fails

## 📝 Deployment Steps

1. **Commit the fixes:**
   ```bash
   git add includes/PodcastManager.php includes/RssFeedParser.php
   git commit -m "Fix: Remove blocking RSS fetches from page load - health check fix"
   git push origin main
   ```

2. **Coolify auto-deploys** - Health check should now pass!

3. **Verify deployment:**
   - Check health endpoint: `curl https://your-domain.com/health.php`
   - Should return `OK` instantly
   - Main page should load quickly

4. **Set up cron job** (if not already configured):
   ```bash
   # In Coolify or server terminal
   crontab -e
   # Add:
   */30 * * * * cd /var/www/html && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

## 🧪 Testing

### Test Health Check:
```bash
# Should return "OK" in under 1 second
time curl http://localhost:3000/health.php
```

### Test Main Page:
```bash
# Should load quickly without RSS timeouts
time curl http://localhost:3000/
```

### Test Manual Refresh:
1. Open app in browser
2. Click 🔄 on any podcast
3. Should update episode data without blocking other podcasts

## 🔮 Future Improvements

- [ ] Add async JavaScript fetching for episode data
- [ ] Implement WebSocket for real-time updates
- [ ] Add feed health monitoring dashboard
- [ ] Queue system for RSS fetches (Redis/RabbitMQ)
- [ ] Rate limiting per feed domain

## 📚 Related Files

- `includes/PodcastManager.php` - Main CRUD operations
- `includes/RssFeedParser.php` - RSS feed fetching/parsing
- `cron/auto-scan-feeds.php` - Automated feed updates
- `api/get-podcast-preview.php` - On-demand feed fetching
- `health.php` - Health check endpoint

## 🎓 Lessons Learned

1. **Never block page load with external requests** - Always async
2. **Health checks must be instant** - No external dependencies
3. **Fail fast** - Short timeouts prevent cascading failures
4. **Cache aggressively** - Reduce redundant external calls
5. **Graceful degradation** - Show stale data rather than nothing

---

**Status:** ✅ FIXED  
**Date:** October 15, 2025  
**Impact:** Critical - Deployment blocker resolved  
**Testing:** Ready for production deployment
