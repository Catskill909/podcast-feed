# Episode Date Fixes - Applied Summary

## Date: October 14, 2025

## Issues Identified & Fixed

### Issue 1: Display Logic Used Elapsed Time Instead of Calendar Days ✅ FIXED

**Problem**: Episodes published yesterday showed as "Today" if less than 24 hours had passed.

**Example**:
- Episode published: Oct 13 at 4:00 PM
- Current time: Oct 14 at 3:39 PM  
- Elapsed time: 23 hours 39 minutes
- **OLD**: Showed "Today" (< 24 hours)
- **NEW**: Shows "Yesterday" (different calendar day)

**Root Cause**: Code compared elapsed seconds instead of calendar dates.

**Files Fixed**:
1. `index.php` (lines 303-335) - Main table display
2. `assets/js/app.js` (lines 1347-1383) - Modal preview display

**Solution**: Changed logic to compare calendar dates by stripping time component:
```php
// OLD METHOD
$diff = $now - $epDate;
if ($diff < 86400) { echo 'Today'; }

// NEW METHOD
$epDay = strtotime(date('Y-m-d', $epDate));
$today = strtotime(date('Y-m-d', $now));
$daysDiff = (int)floor(($today - $epDay) / 86400);
if ($daysDiff == 0) { echo 'Today'; }
elseif ($daysDiff == 1) { echo 'Yesterday'; }
```

---

### Issue 2: RSS Feed Used Wrong Date Field ✅ FIXED

**Problem**: Generated RSS feed (`feed.php`) used `created_date` (when podcast was added to our app) instead of `latest_episode_date` (actual episode publication date).

**Example**:
```xml
<!-- OLD -->
<pubDate>Wed, 09 Oct 2025 18:11:11 +0000</pubDate>  <!-- When added to app -->

<!-- NEW -->
<pubDate>Mon, 13 Oct 2025 16:00:00 +0000</pubDate>  <!-- Actual episode date -->
```

**File Fixed**:
- `includes/XMLHandler.php` (lines 460-467)

**Solution**: Changed RSS generation to use `latest_episode_date` with fallback:
```php
// Use latest_episode_date if available, fallback to created_date
if (!empty($podcast['latest_episode_date'])) {
    $pubDate = date('r', strtotime($podcast['latest_episode_date']));
} else {
    $pubDate = isset($podcast['created_date']) ? 
        date('r', strtotime($podcast['created_date'])) : date('r');
}
```

---

### Issue 3: Auto-Scan Not Running ✅ IDENTIFIED (Requires Cron Setup)

**Problem**: Auto-scan cron job wasn't running automatically, causing stale episode dates.

**Status**: 
- ✅ Code works correctly
- ❌ Cron job not scheduled
- ✅ Manual execution works

**Solution Required**: Set up cron job to run every 30 minutes:
```bash
crontab -e
# Add this line:
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
```

---

## Test Results

### Before Fixes
```
Labor Radio:
  Episode Date: Oct 13, 4:00 PM (23h ago)
  Display: "Today" ❌
  RSS Feed: Wed, 09 Oct 2025 (created date) ❌

3rd & Fairfax:
  Episode Date: Oct 9, 10:31 PM (113h ago)
  Display: "4 days ago" ❌ (should be 5)
  RSS Feed: Wed, 09 Oct 2025 (created date) ❌
```

### After Fixes
```
Labor Radio:
  Episode Date: Oct 13, 4:00 PM (23h ago)
  Display: "Yesterday" ✅
  RSS Feed: Mon, 13 Oct 2025 16:00:00 ✅

3rd & Fairfax:
  Episode Date: Oct 9, 10:31 PM (113h ago)
  Display: "5 days ago" ✅
  RSS Feed: Thu, 09 Oct 2025 22:31:00 ✅

WJFF Radio:
  Episode Date: Oct 14, 2:00 PM (1h ago)
  Display: "Today" ✅
  RSS Feed: Tue, 14 Oct 2025 14:00:00 ✅
```

---

## Files Modified

### 1. index.php
**Lines**: 303-335
**Change**: Calendar-day comparison for "Latest Episode" display
**Impact**: Main table now shows accurate relative dates
**Risk**: Low - isolated change, well-tested

### 2. assets/js/app.js
**Lines**: 1347-1383
**Change**: Calendar-day comparison for modal preview
**Impact**: Modal preview matches table display
**Risk**: Low - isolated function, consistent with PHP logic

### 3. includes/XMLHandler.php
**Lines**: 460-467
**Change**: Use `latest_episode_date` for RSS `<pubDate>`
**Impact**: Generated feed shows actual episode freshness
**Risk**: Low - fallback logic prevents breaking changes

---

## Verification Steps

### 1. Test Display Logic
```bash
php test-display-fix.php
```

**Expected Output**: All tests pass ✅

### 2. Test RSS Feed
```bash
curl http://localhost:8080/feed.php | grep -A 2 "<title>Labor Radio"
```

**Expected Output**:
```xml
<title>Labor Radio-Podcast Weekly</title>
<description>...</description>
<link>...</link>
<guid>...</guid>
<pubDate>Mon, 13 Oct 2025 16:00:00 +0000</pubDate>  ✅ Episode date, not created date
```

### 3. Test UI Display
1. Open `http://localhost:8080` in browser
2. Check "Latest Episode" column
3. Verify:
   - Labor Radio shows "Yesterday" (not "Today")
   - WJFF Radio shows "Today"
   - 3rd & Fairfax shows "5 days ago" (not "4 days ago")

### 4. Test Modal Preview
1. Click on any podcast title or cover image
2. Check "Latest Episode" field in modal
3. Verify it matches the table display

---

## What's Working Now

### Data Collection ✅
- `RssFeedParser.php` correctly extracts episode dates from feeds
- Parses `<item><pubDate>` tags from RSS feeds
- Finds the most recent episode date
- Handles both RSS and Atom formats

### Data Storage ✅
- `XMLHandler.php` stores `latest_episode_date` in database
- Updates when auto-scan runs
- Retrieves correctly for display

### Auto-Scan ✅
- `cron/auto-scan-feeds.php` updates all podcasts
- Fetches latest episode dates from feeds
- Only updates if data changed
- Logs all operations

### Display ✅
- Main table shows accurate relative dates
- Modal preview matches table
- Uses calendar days, not elapsed time
- Consistent across all views

### RSS Feed ✅
- Generated feed uses actual episode dates
- `<pubDate>` reflects podcast freshness
- Sorting by episode date works correctly
- Falls back to created date if no episodes

---

## Next Steps

### Required: Set Up Cron Job
```bash
# Edit crontab
crontab -e

# Add this line (adjust path as needed):
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1

# Verify it's scheduled
crontab -l

# Monitor logs
tail -f logs/auto-scan.log

# Check last scan time
cat data/last-scan.txt
```

### Optional: Production Deployment
If deploying to Coolify/Docker:
1. Add cron job to deployment configuration
2. Or use Coolify's scheduled tasks feature
3. Set to run every 30 minutes
4. Monitor `data/last-scan.txt` for updates

---

## Success Criteria

- [x] "Today" shows only for episodes published today (same calendar day)
- [x] "Yesterday" shows for episodes published yesterday (1 calendar day ago)
- [x] "X days ago" calculates based on calendar days, not elapsed hours
- [x] Modal preview matches table display
- [x] RSS feed uses `latest_episode_date` for `<pubDate>`
- [x] Generated feed reflects actual podcast freshness
- [ ] Cron job runs every 30 minutes (requires setup)
- [ ] Episode dates stay current automatically (requires cron)

---

## Rollback Instructions

If issues arise:

### Rollback Display Logic
```bash
git checkout HEAD -- index.php assets/js/app.js
```

### Rollback RSS Feed
```bash
git checkout HEAD -- includes/XMLHandler.php
```

### No Data Loss
- All episode dates remain in database
- No structural changes to data
- Safe to rollback at any time

---

## Summary

**What Was Wrong**:
1. Display logic used elapsed time (hours) instead of calendar days
2. RSS feed used `created_date` instead of `latest_episode_date`
3. Cron job not running automatically

**What Was Fixed**:
1. ✅ Display now uses calendar-day comparison
2. ✅ RSS feed now uses actual episode dates
3. ⚠️  Cron job requires manual setup

**Impact**:
- Users now see accurate "Today"/"Yesterday" labels
- Episodes published yesterday no longer show as "Today"
- Generated RSS feed reflects actual podcast freshness
- All views (table, modal, feed) are consistent

**No Breaking Changes**:
- All existing functionality preserved
- Fallback logic prevents errors
- Data collection unchanged
- Auto-scan still works when run manually
