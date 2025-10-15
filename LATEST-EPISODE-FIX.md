# Latest Episode Display Fix - October 15, 2025

## 🐛 Problem

The "Latest Episode" column in the main podcast table shows "Unknown" for existing podcasts, even though:
- The data exists in the RSS feed
- The podcast preview modal shows the correct date ("Today")
- Newly imported podcasts display correctly

## 🔍 Root Cause

When we implemented the health check timeout fix, we removed RSS fetching from `getAllPodcasts()` to prevent blocking page loads. This was correct for performance, but it revealed a data issue:

**Existing podcasts in the production XML database don't have the `latest_episode_date` field populated.**

### Why This Happened:

1. **Old podcasts** were created before the `latest_episode_date` field existed
2. **Previous code** fetched RSS data on every page load and filled in missing data dynamically
3. **New code** (correctly) reads only from XML database for performance
4. **Result**: Old podcasts show "Unknown" because the XML field is empty

### Why Preview Modal Works:

The preview modal (`api/get-podcast-preview.php`) fetches fresh RSS data on-demand when you click a podcast, so it always shows current information.

## ✅ Solution

### Two-Part Fix:

#### 1. **Migration Script** (One-Time)
Run `migrate-missing-fields.php` to add missing fields to existing podcasts in the XML database.

```bash
php migrate-missing-fields.php
```

This script:
- ✅ Checks all existing podcasts
- ✅ Adds missing `latest_episode_date` and health monitoring fields
- ✅ Creates automatic backup before changes
- ✅ Safe to run multiple times (idempotent)

#### 2. **Populate Episode Data** (Automatic)
Run the cron job to fetch actual episode dates from RSS feeds:

```bash
php cron/auto-scan-feeds.php
```

This will:
- ✅ Fetch RSS data for all podcasts
- ✅ Update `latest_episode_date` with real data
- ✅ Update `episode_count`
- ✅ Record health metrics

## 📋 Step-by-Step Fix

### For Local Development:

```bash
# 1. Navigate to project directory
cd /Users/paulhenshaw/Desktop/podcast-feed

# 2. Run migration script
php migrate-missing-fields.php

# 3. Populate episode data
php cron/auto-scan-feeds.php

# 4. Refresh browser
# Latest Episode column should now show dates!
```

### For Production (Coolify):

```bash
# 1. Commit and push the fixes
git add .
git commit -m "Fix: Add migration script for missing podcast fields"
git push origin main

# 2. SSH into production server or use Coolify terminal

# 3. Navigate to app directory
cd /var/www/html  # or wherever Coolify deploys

# 4. Run migration
php migrate-missing-fields.php

# 5. Run cron job manually (or wait for next automatic run)
php cron/auto-scan-feeds.php

# 6. Verify in browser
```

## 🎯 What Gets Fixed

### Before:
```
Latest Episode Column:
- WJFF - Radio Chatskill: Unknown ❌
- Labor Radio: 2 days ago ✓
- 3rd & Fairfax: 6 days ago ✓
```

### After Migration:
```
Latest Episode Column:
- WJFF - Radio Chatskill: (empty field added, ready for data)
- Labor Radio: 2 days ago ✓
- 3rd & Fairfax: 6 days ago ✓
```

### After Cron Run:
```
Latest Episode Column:
- WJFF - Radio Chatskill: Today ✅
- Labor Radio: 2 days ago ✓
- 3rd & Fairfax: 6 days ago ✓
```

## 🔧 Technical Details

### Fields Added by Migration:

```xml
<latest_episode_date></latest_episode_date>
<episode_count>0</episode_count>
<health_status>healthy</health_status>
<last_check_date></last_check_date>
<last_success_date>2025-10-15 16:30:00</last_success_date>
<consecutive_failures>0</consecutive_failures>
<total_failures>0</total_failures>
<total_checks>0</total_checks>
<avg_response_time>0</avg_response_time>
<success_rate>100</success_rate>
<last_error></last_error>
<last_error_date></last_error_date>
<auto_disabled>false</auto_disabled>
<auto_disabled_date></auto_disabled_date>
```

### Code Changes:

**File: `includes/XMLHandler.php`**
- Updated `podcastNodeToArray()` to return all health monitoring fields
- Ensures backward compatibility with default values

## 🔮 Future Prevention

### For New Podcasts:
- ✅ All new podcasts automatically get all required fields (via `XMLHandler::addPodcast()`)
- ✅ Episode data populated immediately when imported from RSS

### For Existing Podcasts:
- ✅ Cron job runs every 30 minutes to keep episode data fresh
- ✅ Manual refresh button available for instant updates
- ✅ Health monitoring tracks any issues

## 🧪 Testing

### Verify the Fix:

1. **Check XML has fields:**
   ```bash
   grep -A 5 "latest_episode_date" data/podcasts.xml
   ```

2. **Run cron manually:**
   ```bash
   php cron/auto-scan-feeds.php
   ```

3. **Check browser:**
   - Refresh the main page
   - "Latest Episode" column should show dates
   - No more "Unknown" entries

4. **Check preview modal:**
   - Click any podcast cover/title
   - Should show same date as table

## 📚 Related Files

- `migrate-missing-fields.php` - Migration script (NEW)
- `includes/XMLHandler.php` - Updated to return health fields
- `includes/PodcastManager.php` - No longer fetches RSS on page load
- `cron/auto-scan-feeds.php` - Populates episode data automatically
- `HEALTH-CHECK-TIMEOUT-FIX.md` - Original performance fix
- `FEED-HEALTH-IMPLEMENTATION-SUMMARY.md` - Health monitoring system

## 💡 Key Learnings

1. **Database migrations matter** - When adding new fields, existing records need updating
2. **Backward compatibility** - Always provide default values for new fields
3. **Data vs. Display** - Separate data fetching (cron) from display (page load)
4. **Testing with real data** - Production data often reveals issues not seen in development

---

**Status:** ✅ Fixed with migration script  
**Impact:** Medium - Affects display of existing podcasts  
**Action Required:** Run migration script once in production  
**Date:** October 15, 2025
