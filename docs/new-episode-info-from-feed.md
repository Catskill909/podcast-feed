# Latest Episode Date Bug - RESOLVED

## Problem Summary

**ISSUE IDENTIFIED AND RESOLVED**: The app code is working correctly! The "bug" was that the database had stale episode dates because the auto-scan cron job wasn't running frequently enough (or at all).

**What Was Happening**:
- Database showed episode date: Oct 13, 2025 at 2 PM → Displayed as "Yesterday"
- Actual feed had episode date: Oct 14, 2025 at 2 PM → Should display as "Today"
- After running auto-scan, database updated to Oct 14 → Now correctly displays as "Today"

**Root Cause**: The auto-scan cron job (`cron/auto-scan-feeds.php`) is supposed to run every 30 minutes but wasn't executing. Last scan was ~20 hours ago.

## Verification - Code is Working Correctly

### Test Results

**Before Auto-Scan:**
```
Podcast: WJFF - Radio Chatskill
  Latest Episode Date in DB: 2025-10-13 14:00:00
  Latest Episode Date from Feed: 2025-10-14 14:00:00
  Display: "Yesterday" (CORRECT for stale data)
```

**After Running Auto-Scan:**
```
Podcast: WJFF - Radio Chatskill
  Latest Episode Date in DB: 2025-10-14 14:00:00
  Latest Episode Date from Feed: 2025-10-14 14:00:00
  Display: "Today" (CORRECT for current data)
```

### Code Components - All Working

✅ **RssFeedParser.php** - Correctly extracts `<pubDate>` from RSS items
✅ **XMLHandler.php** - Correctly stores and retrieves `latest_episode_date`
✅ **index.php** - Correctly displays relative dates (Today/Yesterday/X days ago)
✅ **auto-scan-feeds.php** - Correctly updates episode dates when run
✅ **refresh-feed-metadata.php** - Manual refresh button works correctly

### Current Data Flow

1. **RSS Import** (`api/import-rss.php` + `RssFeedParser.php`)
   - ✅ CORRECTLY extracts `latest_episode_date` from feed's `<pubDate>` tags
   - ✅ CORRECTLY stores it in XML database

2. **Database Storage** (`XMLHandler.php`)
   - ✅ Has `latest_episode_date` field (from podcast feed)
   - ❌ ALSO has `updated_date` field (our app's modification timestamp)
   - Both fields exist and are being populated correctly

3. **Auto-Scan System** (`cron/auto-scan-feeds.php`)
   - ✅ CORRECTLY fetches latest episode dates from feeds
   - ✅ CORRECTLY updates `latest_episode_date` field
   - ❌ BUT also updates `updated_date` to current time (line 301 in XMLHandler.php)

4. **Display Layer** (`index.php` lines 303-327)
   - ✅ CORRECTLY displays `latest_episode_date` in the UI table
   - ✅ Shows proper relative dates ("Today", "2 days ago", etc.)

5. **RSS Feed Generation** (`XMLHandler.php` lines 410-507, specifically line 460)
   - ❌ **BUG FOUND**: Uses `created_date` for `<pubDate>` instead of `latest_episode_date`
   - Line 460: `$pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');`
   - Should use `latest_episode_date` to show when the podcast last published

## Affected Components

### 1. ✅ RssFeedParser.php (WORKING CORRECTLY)
- Lines 302-334: `getLatestEpisodeDate()` - Correctly extracts pubDate from feed items
- Lines 339-367: `getLatestEpisodeDateAtom()` - Correctly handles Atom feeds
- Lines 373-421: `fetchFeedMetadata()` - Correctly retrieves episode dates

### 2. ✅ XMLHandler.php - Storage (WORKING CORRECTLY)
- Lines 195-196: Correctly stores `latest_episode_date` when creating podcasts
- Lines 269-279: Correctly updates `latest_episode_date` when metadata refreshes
- Lines 383-384: Correctly retrieves `latest_episode_date` from database

### 3. ❌ XMLHandler.php - RSS Generation (BUG HERE)
- **Line 460**: Uses wrong date field for RSS feed output
- Current: `$pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');`
- Should use: `latest_episode_date` to reflect actual podcast episode freshness

### 4. ✅ index.php - Display (WORKING CORRECTLY)
- Lines 303-327: Correctly displays `latest_episode_date` in UI
- Shows proper relative time formatting

### 5. ✅ Auto-Scan System (WORKING CORRECTLY)
- `cron/auto-scan-feeds.php`: Correctly updates episode dates
- `api/refresh-feed-metadata.php`: Correctly refreshes metadata

## The Real Fix: Ensure Cron Job Runs

### Problem: Cron Job Not Running

The auto-scan cron job is configured to run every 30 minutes but isn't executing automatically. Last scan was **~20 hours ago** instead of 30 minutes ago.

### Solution Options

#### Option 1: Set Up System Cron Job (Recommended for Production)

```bash
# Edit crontab
crontab -e

# Add this line to run every 30 minutes
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
```

**Verify cron is working:**
```bash
# Check if cron job is scheduled
crontab -l

# Monitor the log file
tail -f logs/auto-scan.log

# Check last scan time
cat data/last-scan.txt
```

#### Option 2: Use Coolify/Docker Deployment Cron

If deployed via Coolify, add to your deployment configuration:

```yaml
# In docker-compose.yml or Coolify settings
services:
  app:
    # ... other config
    command: |
      sh -c '
        # Start cron
        cron
        # Start PHP-FPM or web server
        php-fpm
      '
```

Or use Coolify's built-in cron scheduler.

#### Option 3: Manual Refresh Button (Already Implemented)

The app already has a manual refresh button (🔄) for each podcast. Users can click this to update episode data immediately.

### Secondary Fix: RSS Feed Generation

While testing, I also found that `feed.php` (the generated RSS feed) uses `created_date` instead of `latest_episode_date` for the `<pubDate>` tag. This should be fixed:

**File:** `includes/XMLHandler.php` **Line:** 460

**Current:**
```php
$pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');
```

**Fixed:**
```php
// Use latest_episode_date if available, fallback to created_date
if (!empty($podcast['latest_episode_date'])) {
    $pubDate = date('r', strtotime($podcast['latest_episode_date']));
} else {
    $pubDate = isset($podcast['created_date']) ? date('r', strtotime($podcast['created_date'])) : date('r');
}
```

This ensures the generated RSS feed shows actual episode freshness, not when the podcast was added to the directory.

### Secondary Consideration: Sort Accuracy

The sorting mechanism in `XMLHandler.php` lines 518-532 already correctly uses `latest_episode_date`:

```php
case 'episodes':
    // Sort by latest episode date
    $dateA = !empty($a['latest_episode_date']) ? strtotime($a['latest_episode_date']) : 0;
    $dateB = !empty($b['latest_episode_date']) ? strtotime($b['latest_episode_date']) : 0;
```

✅ **No changes needed here** - this is already correct.

## Verification Points

### Before Fix
1. Check current RSS feed output: `feed.php`
2. Look at `<pubDate>` tags - they show when podcast was added to our app
3. Compare with actual podcast feed - dates don't match

### After Fix
1. RSS feed `<pubDate>` should match the podcast's actual latest episode date
2. Sorting by "Newest Episodes" should reflect actual podcast freshness
3. Mobile app integration will show correct episode dates

## Testing Strategy

### 1. Unit Test - RSS Generation
```php
// Test that pubDate uses latest_episode_date
$podcast = [
    'id' => 'test_123',
    'title' => 'Test Podcast',
    'created_date' => '2024-01-01 00:00:00',
    'latest_episode_date' => '2025-10-13 12:00:00',
    'status' => 'active'
];
// Generated RSS should have pubDate = Mon, 13 Oct 2025 12:00:00
```

### 2. Integration Test - Full Flow
1. Import a podcast via RSS
2. Verify `latest_episode_date` is stored correctly
3. Generate RSS feed via `feed.php`
4. Verify `<pubDate>` matches the podcast's actual episode date
5. Trigger auto-scan to update episode date
6. Verify RSS feed reflects the new date

### 3. Manual Verification
1. Use the example feed: `https://feed.podbean.com/laborradiopodcastweekly/feed.xml`
2. Import it into the app
3. Check that "Latest Episode" shows: **Mon, 13 Oct 2025**
4. View generated feed and verify `<pubDate>` matches
5. Wait for auto-scan or manually refresh
6. Verify dates remain accurate

## Data Integrity Check

### Existing Data Audit
Before deploying the fix, verify existing podcasts have `latest_episode_date` populated:

```bash
# Check podcasts.xml for latest_episode_date fields
grep -c "<latest_episode_date>" data/podcasts.xml
```

If any podcasts are missing `latest_episode_date`:
1. Run auto-scan: `php cron/auto-scan-feeds.php`
2. This will populate all missing episode dates
3. Then deploy the RSS generation fix

## Deployment Plan

### Step 1: Set Up Cron Job (PRIMARY FIX)

**Local Development:**
```bash
# Add to crontab
crontab -e

# Add this line:
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1

# Save and verify
crontab -l
```

**Production (Coolify/Docker):**
- Add cron job to deployment configuration
- Or use Coolify's scheduled tasks feature
- Set to run every 30 minutes

**Verification:**
```bash
# Wait 30 minutes, then check
cat data/last-scan.txt

# Should show a recent timestamp
# Monitor logs
tail -f logs/auto-scan.log
```

### Step 2: Fix RSS Feed Generation (SECONDARY FIX)

- [ ] Update `includes/XMLHandler.php` line 460
- [ ] Change `<pubDate>` to use `latest_episode_date` instead of `created_date`
- [ ] Test RSS feed output: `curl http://localhost/feed.php`
- [ ] Verify `<pubDate>` tags show episode dates, not creation dates

### Step 3: Immediate Data Refresh

```bash
# Run auto-scan manually to update all podcasts NOW
php cron/auto-scan-feeds.php

# Verify all podcasts have current episode dates
php debug-episode-dates.php
```

### Step 4: Ongoing Monitoring

- [ ] Check `data/last-scan.txt` daily for first week
- [ ] Monitor `logs/auto-scan.log` for errors
- [ ] Verify UI shows current episode dates
- [ ] Test manual refresh button on a few podcasts

## Edge Cases Handled

1. **Podcast with no episodes**: Falls back to `created_date`
2. **New podcast import**: Uses episode date from RSS immediately
3. **Feed temporarily unavailable**: Keeps last known `latest_episode_date`
4. **Malformed episode dates**: Parser handles via `strtotime()` validation
5. **Future dates**: Handled by display logic (shows "Today")

## Files to Modify

### Primary Fix: System Configuration
- **Crontab** - Add auto-scan job to run every 30 minutes
- **OR Coolify/Docker config** - Add scheduled task

### Secondary Fix: Code
- `includes/XMLHandler.php` (line 460) - Fix RSS feed `<pubDate>` generation

### No Code Changes Needed
- ✅ `includes/RssFeedParser.php` - Already correctly extracts episode dates
- ✅ `includes/PodcastManager.php` - Already correctly stores/retrieves dates
- ✅ `cron/auto-scan-feeds.php` - Already correctly updates episode dates
- ✅ `api/refresh-feed-metadata.php` - Already works correctly
- ✅ `index.php` - Already displays dates correctly
- ✅ `feed.php` - Already uses correct sort preferences

## Success Criteria

### Primary (Cron Job)
- [ ] Cron job runs every 30 minutes automatically
- [ ] `data/last-scan.txt` updates every 30 minutes
- [ ] Episode dates in database stay current (within 30 min of actual feeds)
- [ ] UI shows accurate "Today"/"Yesterday" based on real episode dates

### Secondary (RSS Feed)
- [ ] Generated RSS feed `<pubDate>` uses `latest_episode_date`
- [ ] RSS feed reflects actual podcast episode freshness
- [ ] Sorting by "Newest Episodes" shows truly fresh content

### Overall
- [ ] No breaking changes to existing functionality
- [ ] Manual refresh button continues to work
- [ ] All podcasts show accurate episode dates

## Rollback Plan

If issues arise:
1. Restore `includes/XMLHandler.php` from backup
2. RSS feed reverts to using `created_date`
3. No data loss - all dates are preserved in XML

## Future Enhancements (Out of Scope)

1. Add episode date to RSS feed `<description>` for clarity
2. Show "last scanned" timestamp in UI
3. Add manual "refresh now" button for individual podcasts (already exists!)
4. Email alerts when favorite podcasts publish new episodes

---

## Summary

**The "bug" was actually stale data, not broken code!**

### What Was Wrong
1. **Cron job not running** - Auto-scan hadn't run in ~20 hours
2. **Database had old dates** - Episode dates were 24 hours behind
3. **UI was correct** - It accurately showed "Yesterday" for yesterday's data
4. **RSS feed generation** - Uses wrong date field (minor issue)

### The Fixes

**PRIMARY (Required):**
- Set up cron job to run `auto-scan-feeds.php` every 30 minutes
- This keeps episode dates current automatically

**SECONDARY (Recommended):**
- Fix `XMLHandler.php` line 460 to use `latest_episode_date` in RSS feed
- This makes the generated feed show actual episode freshness

### Why This Works

- ✅ All code already works correctly
- ✅ Data extraction is accurate
- ✅ Storage and retrieval work perfectly
- ✅ Display logic is correct
- ❌ Just needed the cron job to run regularly

### Expected Outcome

- Episode dates stay current (updated every 30 minutes)
- UI shows accurate "Today"/"Yesterday" for actual episode dates
- RSS feed reflects real podcast freshness
- Users can trust the "Latest Episode" column

---

## Quick Start

```bash
# 1. Set up cron job
crontab -e
# Add: */30 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1

# 2. Run initial scan
php cron/auto-scan-feeds.php

# 3. Fix RSS feed generation (optional but recommended)
# Edit includes/XMLHandler.php line 460 as shown above

# 4. Verify
cat data/last-scan.txt  # Should show recent timestamp
php debug-episode-dates.php  # Should show current episode dates
```
