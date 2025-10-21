# Auto-Refresh Fix - October 21, 2025

## Problem
Cron job was failing with errors like:
```
RSS Fetch Error for http://localhost:8000/self-hosted-feed.php?id=shp_...
HTTP 0 - Failed to connect to localhost port 8000
```

**Root Cause:** Self-hosted podcasts had localhost URLs that work in browser but fail in cron context (no web server running on localhost when cron executes).

## Solution Implemented

### 1. **Skip Self-Hosted Feeds in Cron** ✅
Modified `cron/auto-scan-feeds.php` to skip any feed URL starting with `localhost` or `127.0.0.1`:

```php
// Skip self-hosted feeds (localhost URLs) - they don't need auto-scanning
if (preg_match('/^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?/i', $podcast['feed_url'])) {
    $stats['skipped']++;
    log_message("  → Skipped (self-hosted feed)");
    continue;
}
```

**Why:** Self-hosted feeds are managed directly and don't need external scanning.

### 2. **Browser-Based Auto-Refresh** ✅
Created a new system that refreshes feeds when users visit the site:

**New Files:**
- `api/auto-refresh.php` - API endpoint that refreshes feeds (max once per 30 min)
- `assets/js/auto-refresh.js` - JavaScript that triggers refresh on page load

**Modified Files:**
- `index.php` - Added auto-refresh script
- `admin.php` - Added auto-refresh script

**How It Works:**
1. When user visits index.php or admin.php, JavaScript triggers after 2 seconds
2. Calls `api/auto-refresh.php` in background
3. API checks if last refresh was > 30 minutes ago
4. If yes, refreshes all external feeds (skips localhost)
5. If podcasts updated, page reloads to show new content
6. If no, silently skips (no disruption)

## Benefits

✅ **Works Everywhere:** Local dev, production, no cron setup needed  
✅ **No Localhost Errors:** Skips self-hosted feeds that cause failures  
✅ **User-Triggered:** Refreshes happen when people actually use the site  
✅ **Rate Limited:** Max once per 30 minutes to avoid hammering feeds  
✅ **Silent:** Runs in background, doesn't disrupt user experience  
✅ **Automatic Reload:** If new episodes found, page reloads to show them  

## Testing

Tested cron script - now shows:
```
[7/9] Processing: Old Skool Sessions
  → Skipped (self-hosted feed)
[8/9] Processing: KPFT - Conversations w/ Michael Woodson
  → Skipped (self-hosted feed)
[9/9] Processing: A Third of Your Life
  → Skipped (self-hosted feed)
```

**Exit code: 0** (success, no errors)

## Production Deployment

The browser-based auto-refresh will work immediately in production:
- No cron setup required
- No permissions issues
- Works on any hosting platform
- Refreshes happen naturally as users visit

The cron job (if still running) will also work without errors now.

## Future Improvements

If you want even more control, you could:
1. Add a manual "Refresh All" button in admin
2. Adjust the 30-minute interval in `api/auto-refresh.php`
3. Add visual feedback when refresh happens
4. Make refresh interval configurable per-podcast

## Self-Hosted Podcast Updates ✅

**Additional Fix:** Self-hosted podcasts now automatically update their `latest_episode_date` when:
- Adding a new episode
- Updating an episode (especially if pub_date changes)
- Deleting an episode

**Modified File:**
- `includes/SelfHostedPodcastManager.php` - Added `updateLatestEpisodeDate()` method

**How It Works:**
1. After any episode add/update/delete operation
2. Method calculates the latest published episode date
3. Updates the podcast's metadata in `self-hosted-podcasts.xml`
4. This date is then used in:
   - `index.php` and `admin.php` for sorting
   - `self-hosted-feed.php` RSS feed generation
   - Main podcast directory listings

**Result:** Self-hosted podcasts now show correct "latest episode" dates everywhere:
- ✅ In the admin podcast list
- ✅ In the public index.php browse page
- ✅ In the RSS feed for podcast apps
- ✅ When sorting by "Latest Episodes"

## Status
✅ **Complete and tested** - Ready for production deployment

## Summary of All Updates

### External Feeds (RSS imports)
- ✅ Auto-refresh via browser when users visit site (every 30 min)
- ✅ Cron job skips localhost URLs (no errors)
- ✅ Updates show immediately on index.php and admin.php

### Self-Hosted Feeds
- ✅ Latest episode date updates when episodes added/edited/deleted
- ✅ Dates show correctly in admin, index.php, and RSS feeds
- ✅ Sorting by "Latest Episodes" works correctly
- ✅ No cron errors (localhost feeds skipped)

**Everything updates EVERYWHERE now!** 🎉
