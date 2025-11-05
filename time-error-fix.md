# Timezone Error Analysis & Fix

**Date:** November 4, 2025, 8:24 PM EST  
**Issue:** RSS feeds showing "Yesterday" for podcasts published today (Nov 4)  
**Current Time:** Tuesday, November 4, 2025, 8:24 PM EST (UTC-5)  
**Status:** ✅ **IMPLEMENTED** - See TIMEZONE-FIX-SUMMARY.md for deployment details

---

## Problem Statement

Both local and production RSS feeds are showing incorrect relative dates:

- **WJFF - Radio Chatskill**: Published Nov 4, 2025 at 3:00 PM → Shows "Yesterday" ❌
- **Democracy Now! Audio**: Published Nov 4, 2025 at 1:00 PM → Shows "Yesterday" ❌

The feed generation timestamp shows:
```xml
<lastBuildDate>Wed, 05 Nov 2025 01:23:14 +0000</lastBuildDate>
```

This is **November 5th at 1:23 AM UTC**, which is **November 4th at 8:23 PM EST**.

---

## Root Cause Analysis

### 1. **Timezone Configuration**

**config/config.php (Line 76):**
```php
// Timezone
date_default_timezone_set('UTC');
```

**config/config.production.php (Line 60):**
```php
// Timezone
date_default_timezone_set(getenv('TIMEZONE') ?: 'UTC');
```

Both configs default to **UTC timezone**.

### 2. **Date Comparison Logic**

**includes/XMLHandler.php (Lines 759-788):**
```php
private function formatRelativeDate($dateString)
{
    if (empty($dateString)) {
        return 'Unknown';
    }
    
    try {
        $date = new DateTime($dateString);  // Episode date (from RSS feed)
        $now = new DateTime();              // Current time in UTC
        
        // Reset to midnight for accurate day comparison
        $dateOnly = new DateTime($date->format('Y-m-d'));
        $nowOnly = new DateTime($now->format('Y-m-d'));
        
        $diff = $nowOnly->diff($dateOnly);
        $diffDays = (int)$diff->format('%a'); // Absolute days (unsigned)
        
        if ($diffDays === 0) return 'Today';
        if ($diffDays === 1) return 'Yesterday';
        if ($diffDays < 7) return $diffDays . ' days ago';
        // ... more logic
    }
}
```

### 3. **The Problem**

**Scenario:**
- Current time: **Nov 4, 2025, 8:24 PM EST** (Nov 5, 2025, 1:24 AM UTC)
- Episode published: **Nov 4, 2025, 3:00 PM EST** (Nov 4, 2025, 8:00 PM UTC)

**What happens:**
1. `$now = new DateTime()` creates **Nov 5, 2025, 1:24 AM UTC**
2. `$nowOnly = new DateTime($now->format('Y-m-d'))` becomes **Nov 5, 2025, 12:00 AM UTC**
3. `$date = new DateTime('2025-11-04')` is **Nov 4, 2025, 12:00 AM UTC**
4. `$dateOnly = new DateTime($date->format('Y-m-d'))` becomes **Nov 4, 2025, 12:00 AM UTC**
5. Difference: **1 day** → Shows "Yesterday" ❌

**The bug:** The server is in UTC, so after 7:00 PM EST (midnight UTC), it thinks we're already in the next day.

---

## Why This Happens

### Timeline Visualization

```
EST (UTC-5):  Nov 4, 2025 7:00 PM  |  Nov 4, 2025 8:00 PM  |  Nov 4, 2025 11:59 PM
UTC:          Nov 5, 2025 12:00 AM |  Nov 5, 2025 1:00 AM  |  Nov 5, 2025 4:59 AM
              ▲                     ▲
              |                     |
              Day boundary in UTC   Current time (8:24 PM EST = 1:24 AM UTC)
```

**The Issue:**
- After 7:00 PM EST, the UTC date rolls over to the next day
- Episode published at 3:00 PM EST on Nov 4 (8:00 PM UTC Nov 4)
- Server thinks "now" is Nov 5 (1:24 AM UTC)
- Comparison: Nov 5 - Nov 4 = 1 day = "Yesterday"

---

## Solutions Considered

### Option 1: Change Server Timezone to EST ❌
**Problem:** Breaks for users in other timezones. Not scalable.

### Option 2: Store Timezone in Config ✅ (CHOSEN)
**Solution:** Use EST (America/New_York) as the application timezone for date comparisons.

**Why this works:**
- All date comparisons happen in EST
- RSS feed timestamps remain in UTC (standard)
- Relative dates ("Today", "Yesterday") are calculated from EST perspective
- Consistent behavior regardless of server location

### Option 3: Parse Episode Timezone from RSS ❌
**Problem:** Not all RSS feeds include timezone info. Inconsistent.

---

## Proposed Fix

### Changes Required

#### 1. **config/config.php** (Line 76)
```php
// OLD:
date_default_timezone_set('UTC');

// NEW:
date_default_timezone_set('America/New_York'); // EST/EDT
```

#### 2. **config/config.production.php** (Line 60)
```php
// OLD:
date_default_timezone_set(getenv('TIMEZONE') ?: 'UTC');

// NEW:
date_default_timezone_set(getenv('TIMEZONE') ?: 'America/New_York'); // EST/EDT
```

#### 3. **includes/XMLHandler.php** (Lines 478, 606)
Keep RSS feed timestamps in UTC for standards compliance:
```php
// Existing code is correct - RSS feeds should use UTC
$channel->appendChild($rss->createElement('lastBuildDate', date('r')));
```

**Note:** `date('r')` will output in the timezone set by `date_default_timezone_set()`, but we need to force UTC for RSS compliance.

**UPDATED FIX for XMLHandler.php:**
```php
// OLD:
$channel->appendChild($rss->createElement('lastBuildDate', date('r')));

// NEW:
$utcDate = new DateTime('now', new DateTimeZone('UTC'));
$channel->appendChild($rss->createElement('lastBuildDate', $utcDate->format('r')));
```

---

## Expected Behavior After Fix

### Current Time: Nov 4, 2025, 8:24 PM EST

**Before Fix (UTC timezone):**
- Server thinks it's Nov 5, 2025, 1:24 AM
- Episode from Nov 4 → "Yesterday" ❌

**After Fix (EST timezone):**
- Server thinks it's Nov 4, 2025, 8:24 PM
- Episode from Nov 4 → "Today" ✅

### RSS Feed Output

**lastBuildDate (should remain UTC):**
```xml
<lastBuildDate>Tue, 04 Nov 2025 20:24:00 -0500</lastBuildDate>
```
or
```xml
<lastBuildDate>Wed, 05 Nov 2025 01:24:00 +0000</lastBuildDate>
```

**Relative dates (calculated in EST):**
```xml
<podfeed:relativeDate>Today</podfeed:relativeDate>
```

---

## Testing Plan

1. **Before deployment:**
   - Current time: Nov 4, 8:24 PM EST
   - Check feed shows "Yesterday" for Nov 4 episodes ✅ (confirms bug)

2. **After deployment:**
   - Check feed shows "Today" for Nov 4 episodes ✅
   - Check feed shows "Yesterday" for Nov 3 episodes ✅
   - Verify `lastBuildDate` is in UTC format ✅

3. **Edge cases:**
   - Test at 11:59 PM EST (should still be "Today")
   - Test at 12:01 AM EST (should roll to next day)
   - Test episodes from different timezones

---

## Files to Modify

1. `/config/config.php` - Line 76
2. `/config/config.production.php` - Line 60
3. `/includes/XMLHandler.php` - Lines 478, 606 (force UTC for RSS timestamps)

---

## Impact Assessment

### ✅ Safe Changes
- Only affects date comparison logic
- RSS feeds remain standards-compliant (UTC)
- No database changes required
- No breaking changes to API

### ⚠️ Considerations
- Users in other timezones will see dates relative to EST
- If you want multi-timezone support, need more complex solution
- Consider adding timezone to admin settings in future

---

## Alternative: Multi-Timezone Support (Future Enhancement)

For true multi-timezone support:

1. Add timezone setting to admin panel
2. Store user's timezone preference
3. Calculate relative dates based on user's timezone
4. More complex, but more flexible

**For now:** EST is the simplest fix that solves the immediate problem.

---

## Conclusion

**Root Cause:** Server using UTC timezone causes date rollovers at 7:00 PM EST (midnight UTC)

**Fix:** Change application timezone to `America/New_York` (EST/EDT)

**Result:** Relative dates ("Today", "Yesterday") calculated correctly for EST users

**RSS Compliance:** Force UTC for `lastBuildDate` to maintain RSS 2.0 standards
