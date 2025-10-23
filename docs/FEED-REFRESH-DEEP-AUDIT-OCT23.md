# DEEP ARCHITECTURAL AUDIT - Feed Refresh System
## Date: October 23, 2025 4:40pm

---

## THE CORE PROBLEM

**You have fought this battle 78+ times.** The docs show repeated attempts to fix "latest episode date" issues with different approaches:
- Cache busting
- Auto-refresh systems
- Manual refresh buttons
- Cron jobs
- Browser-based refresh
- "Feed is truth" architecture
- Multiple API endpoints

**Yet the problem persists.** Why?

---

## ROOT CAUSE ANALYSIS

### The Fundamental Architectural Flaw

Your system has **TOO MANY CACHING LAYERS** fighting each other:

```
RSS Feed (Podbean/External)
    ↓
[LAYER 1] Podbean's CDN cache (you can't control)
    ↓
[LAYER 2] RssFeedParser file cache (1 hour, /tmp/feed_cache_*)
    ↓
[LAYER 3] cURL response (can be cached by intermediate proxies)
    ↓
[LAYER 4] podcasts.xml database (updated by cron/manual refresh)
    ↓
[LAYER 5] Browser JavaScript cache (ASSETS_VERSION)
    ↓
[LAYER 6] HTML data attributes (row.dataset.latestEpisode)
    ↓
Display
```

**SEVEN LAYERS OF CACHING!** Any ONE of these can serve stale data.

---

## THE COMPETING SYSTEMS

### System 1: Auto-Refresh (Browser-Based)
- **File:** `api/auto-refresh.php`
- **Trigger:** Page load (every 5 minutes)
- **Cache:** USES RssFeedParser cache (1 hour)
- **Problem:** Serves stale data from cache

### System 2: Manual Refresh Button
- **File:** `api/refresh-feed-metadata.php`
- **Trigger:** User clicks 🔄 button
- **Cache:** Calls `clearCache()` then fetches
- **Problem:** Cache can be recreated between clear and fetch

### System 3: Cron Job
- **File:** `cron/auto-scan-feeds.php`
- **Trigger:** Scheduled (if running)
- **Cache:** USES RssFeedParser cache (1 hour)
- **Problem:** May not be running in production

### System 4: "View Feed" Proxy
- **File:** `api/fetch-feed.php`
- **Trigger:** User clicks "View Feed"
- **Cache:** BYPASSES RssFeedParser, uses file_get_contents()
- **Problem:** Shows fresh data but doesn't update database

**FOUR DIFFERENT SYSTEMS** trying to solve the same problem!

---

## WHY "VIEW FEED" SHOWS FRESH DATA

The `api/fetch-feed.php` proxy uses `file_get_contents()` which:
- ✅ Bypasses your RssFeedParser cache
- ✅ Sends fresh HTTP request
- ✅ Shows current feed content

But it **DOESN'T UPDATE** the database, so:
- ❌ Main page still shows old date
- ❌ Admin page still shows old date
- ❌ Sorting still uses old date

**This is why you see the feed is correct but the app shows wrong dates!**

---

## THE CACHE-BUSTING PARADOX

You've added cache-busting in layers:
1. `clearCache()` method - deletes /tmp file
2. HTTP headers (Cache-Control, Pragma, Expires)
3. Timestamp query parameter (?_t=timestamp)

**But there's a race condition:**

```php
// api/refresh-feed-metadata.php line 38
$parser->clearCache($url);  // Delete cache file
$result = $parser->fetchFeedMetadata($url, true);  // Fetch fresh

// Inside fetchFeedMetadata()
$xmlContent = $this->fetchFeedContent($url, $bustCache);

// Inside fetchFeedContent() - LINE 96-99
if (!$bustCache) {
    $cached = $this->getCachedFeed($url);  // ← CHECKS CACHE AGAIN!
    if ($cached !== false) {
        return $cached;  // ← RETURNS STALE DATA
    }
}
```

**The $bustCache parameter is FALSE by default!** So even after clearing cache, it checks again.

---

## THE REAL ISSUE: PODBEAN'S CACHE

Even with all your cache-busting, **Podbean itself** might be caching the feed:
- CDN layer (Cloudflare, Fastly, etc.)
- Origin server cache
- RSS generator cache

When you fetch `https://feed.podbean.com/yourrightsatwork/feed.xml`:
- Your cache-busting headers are sent
- But Podbean's CDN might ignore them
- Returns cached version from 1 hour ago

**This is why the feed shows Oct 16 even though there might be a newer episode.**

---

## WHAT YOU ACTUALLY NEED

### Option A: Simplify to ONE System (RECOMMENDED)

**Kill everything except manual refresh:**

1. **Remove auto-refresh** (api/auto-refresh.php, assets/js/auto-refresh.js)
2. **Remove cron job** (or make it optional)
3. **Keep ONLY manual refresh button**
4. **Make it REALLY work:**
   ```php
   // Force fresh fetch with aggressive cache busting
   $url = $podcast['feed_url'];
   $timestamp = time();
   $fetchUrl = $url . (strpos($url, '?') ? '&' : '?') . "_t=$timestamp&_nocache=1";
   
   // Use file_get_contents (like View Feed does)
   $context = stream_context_create([
       'http' => [
           'header' => "Cache-Control: no-cache\r\n" .
                      "Pragma: no-cache\r\n" .
                      "Expires: 0\r\n"
       ]
   ]);
   $xmlContent = file_get_contents($fetchUrl, false, $context);
   
   // Parse and update database
   // No caching layer at all
   ```

**Benefits:**
- ✅ ONE code path to maintain
- ✅ User controls when to refresh
- ✅ No competing systems
- ✅ No cache conflicts
- ✅ Works everywhere (local, production)

---

### Option B: Accept Eventual Consistency

**Embrace the cache:**

1. Keep 1-hour cache in RssFeedParser
2. Show cache age in UI: "Last updated: 23 minutes ago"
3. Manual refresh button bypasses cache
4. Auto-refresh runs hourly (matches cache duration)

**Benefits:**
- ✅ Reduces load on external servers
- ✅ Faster page loads
- ✅ Users know when data is stale
- ✅ Refresh button for immediate updates

---

### Option C: Webhook-Based Updates (IDEAL but requires external support)

**Let Podbean tell YOU when there's a new episode:**

1. Register webhook with Podbean
2. They POST to your server when new episode published
3. Your server updates database immediately
4. No polling, no caching, instant updates

**Benefits:**
- ✅ Real-time updates
- ✅ No wasted requests
- ✅ No cache issues

**Drawbacks:**
- ❌ Requires Podbean support (they may not offer this)
- ❌ More complex setup

---

## IMMEDIATE FIX FOR CURRENT ISSUE

The changes I just made should help, but let's verify the flow:

### Test the Manual Refresh Button

1. Click 🔄 on Labor Heritage Power Hour
2. Check browser Network tab:
   - Should see request to `api/refresh-feed-metadata.php`
   - Should see request to Podbean with `?_t=` timestamp
3. Check response:
   - Should show latest_episode_date
4. Check database (podcasts.xml):
   - Should be updated with new date
5. Check display:
   - Should show new date immediately

**If it still shows Oct 16:**
- The RSS feed itself hasn't updated
- Podbean's cache is stale
- Wait for Podbean to update (could be hours)

---

## RECOMMENDED ACTION PLAN

### Phase 1: Simplify (This Week)
1. ✅ Remove auto-refresh.js from index.php and admin.php
2. ✅ Remove api/auto-refresh.php
3. ✅ Keep only manual refresh button
4. ✅ Make manual refresh use file_get_contents() (like View Feed)
5. ✅ Add "Last refreshed" timestamp to UI

### Phase 2: Monitor (Next Week)
1. ✅ Add logging to refresh button
2. ✅ Track: When refreshed, what date returned, what date stored
3. ✅ Compare with "View Feed" output
4. ✅ Identify if issue is fetch or parse

### Phase 3: Optimize (Later)
1. ✅ Add cache age indicator
2. ✅ Make refresh interval configurable
3. ✅ Consider webhook integration

---

## FILES TO MODIFY NOW

### 1. Remove Auto-Refresh
```bash
# Delete these files
rm api/auto-refresh.php
rm assets/js/auto-refresh.js
```

### 2. Simplify Manual Refresh
Replace `api/refresh-feed-metadata.php` with simpler version that uses `file_get_contents()` instead of RssFeedParser.

### 3. Add Logging
Add detailed logging to see exactly what's being fetched and stored.

---

## CONCLUSION

**You don't have a code problem. You have an architecture problem.**

The system is over-engineered with:
- 4 different refresh mechanisms
- 7 layers of caching
- Race conditions between cache clear and fetch
- External caches you can't control

**The solution is SIMPLIFICATION:**
1. One refresh mechanism (manual button)
2. One code path (no cache layers)
3. One source of truth (direct fetch)
4. Clear feedback to user (show when last refreshed)

**Stop fighting the cache. Eliminate it.**

---

## NEXT STEPS

1. Test current changes (cache-busting headers + timestamp)
2. If still broken, implement Option A (simplify to one system)
3. Add logging to see exactly what's happening
4. Document the ONE way to refresh feeds
5. Delete all the other mechanisms

**The goal: ONE button, ONE code path, ALWAYS works.**
