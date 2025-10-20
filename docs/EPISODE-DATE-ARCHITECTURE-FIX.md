# Episode Date Architecture - Deep Analysis & Fix Plan

## Executive Summary

**The Real Problem**: The app correctly extracts and stores episode publication dates from podcast feeds, BUT the display logic uses **elapsed time** (hours) instead of **calendar days** to show "Today" vs "Yesterday". This causes episodes published yesterday to show as "Today" if less than 24 hours have passed.

**Impact Areas**:
1. ✅ **Data Collection** - Working correctly
2. ✅ **Data Storage** - Working correctly  
3. ❌ **Display Logic** - Using wrong calculation method
4. ✅ **RSS Feed Generation** - Now fixed (uses `latest_episode_date`)
5. ✅ **Sorting** - Working correctly

---

## Current State Analysis

### Test Results (Oct 14, 2025 at 3:39 PM)

| Podcast | Actual Episode Date | Time Ago | Current Display | Should Display |
|---------|-------------------|----------|-----------------|----------------|
| Labor Radio | Oct 13, 4:00 PM | 23h 39m | **Today** ❌ | **Yesterday** |
| 3rd & Fairfax | Oct 9, 10:31 PM | 4d 17h | **4 days ago** ✅ | **4 days ago** |
| WJFF Radio | Oct 14, 2:00 PM | 1h 39m | **Today** ✅ | **Today** |
| AFGE YOUNG | Oct 28, 2024 | 350 days | **Oct 28, 2024** ✅ | **Oct 28, 2024** |

### The Problem Explained

**Current Logic** (`index.php` lines 303-327):
```php
$epDate = strtotime($podcast['latest_episode_date']);
$now = time();
$diff = $now - $epDate;

if ($diff < 86400) { // Less than 24 hours
    echo 'Today';
} elseif ($diff < 172800) { // Less than 48 hours
    echo 'Yesterday';
}
```

**Why This Fails**:
- Episode published: Oct 13 at 4:00 PM
- Current time: Oct 14 at 3:39 PM
- Difference: 23 hours 39 minutes (< 24 hours)
- Result: Shows "Today" even though it's a different calendar day

**What Users Expect**:
- If episode published on Oct 13 → Show "Yesterday" (on Oct 14)
- If episode published on Oct 14 → Show "Today" (on Oct 14)
- Calendar days matter, not elapsed hours

---

## Data Flow Architecture

### 1. RSS Feed Parsing ✅ CORRECT

**File**: `includes/RssFeedParser.php`
**Method**: `getLatestEpisodeDate()` (lines 302-334)

```php
foreach ($items as $item) {
    $pubDate = $this->extractText($item->pubDate);  // Gets ITEM pubDate
    if (!empty($pubDate)) {
        $timestamp = strtotime($pubDate);
        if ($timestamp && $timestamp > $latestTimestamp) {
            $latestTimestamp = $timestamp;
            $latestDate = date('Y-m-d H:i:s', $timestamp);
        }
    }
}
```

**What it does**:
- Iterates through ALL `<item>` tags in the feed
- Extracts each item's `<pubDate>` tag
- Finds the MOST RECENT episode date
- Returns in format: `YYYY-MM-DD HH:MM:SS`

**Test Verification**:
```
Labor Radio Feed:
  - Item 0: Mon, 13 Oct 2025 12:00:00 -0400 → 2025-10-13 16:00:00 ✅
  - Item 1: Fri, 10 Oct 2025 09:00:00 -0400 → 2025-10-10 13:00:00
  - Item 2: Mon, 06 Oct 2025 20:37:22 -0400 → 2025-10-07 00:37:22
  
Parser returns: 2025-10-13 16:00:00 ✅ CORRECT (latest episode)
```

### 2. Database Storage ✅ CORRECT

**File**: `includes/XMLHandler.php`
**Methods**: 
- `addPodcast()` (lines 161-213) - Stores `latest_episode_date`
- `updatePodcast()` (lines 218-306) - Updates `latest_episode_date`
- `podcastNodeToArray()` (lines 370-386) - Retrieves `latest_episode_date`

**Storage Format**:
```xml
<podcast id="pod_1760033471_68e7fabf9e131">
    <title><![CDATA[Labor Radio-Podcast Weekly]]></title>
    <feed_url><![CDATA[https://feed.podbean.com/laborradiopodcastweekly/feed.xml]]></feed_url>
    <created_date>2025-10-09T18:11:11+00:00</created_date>
    <updated_date>2025-10-13T18:27:04+00:00</updated_date>
    <latest_episode_date>2025-10-13 16:00:00</latest_episode_date>
    <episode_count>100</episode_count>
</podcast>
```

**Verification**: ✅ Data is stored correctly

### 3. Auto-Scan System ✅ CORRECT

**File**: `cron/auto-scan-feeds.php`
**Process**:
1. Fetches all podcasts from database
2. For each podcast, calls `RssFeedParser::fetchFeedMetadata()`
3. Compares new `latest_episode_date` with stored value
4. Updates database if changed

**Test Results**:
```
[2025-10-14 15:32:44] [4/4] Processing: WJFF - Radio Chatskill
[2025-10-14 15:32:44]   ✓ Updated - Latest episode: 2025-10-14 14:00:00
```

**Verification**: ✅ Auto-scan correctly updates episode dates

### 4. Display Logic ❌ NEEDS FIX

**File**: `index.php` (lines 303-327)
**Current Implementation**:

```php
if (!empty($podcast['latest_episode_date'])) {
    $epDate = strtotime($podcast['latest_episode_date']);
    $now = time();
    $diff = $now - $epDate;
    
    if ($diff < 0) {
        echo 'Today';  // Future date
    } elseif ($diff < 86400) {  // ❌ WRONG: Uses elapsed hours
        echo 'Today';
    } elseif ($diff < 172800) {  // ❌ WRONG: Uses elapsed hours
        echo 'Yesterday';
    } elseif ($diff < 604800) {
        echo floor($diff / 86400) . ' days ago';
    } else {
        echo date('M j, Y', $epDate);
    }
}
```

**Problem**: Uses elapsed time (seconds) instead of calendar days

**Solution**: Compare calendar dates, not timestamps

```php
if (!empty($podcast['latest_episode_date'])) {
    $epDate = strtotime($podcast['latest_episode_date']);
    $now = time();
    
    // Get calendar dates (strip time component)
    $epDay = strtotime(date('Y-m-d', $epDate));
    $today = strtotime(date('Y-m-d', $now));
    
    // Calculate day difference
    $daysDiff = floor(($today - $epDay) / 86400);
    
    if ($daysDiff < 0) {
        // Future date
        echo '<span style="color: var(--accent-primary); font-weight: 500;">Today</span>';
    } elseif ($daysDiff === 0) {
        // Same calendar day = Today
        echo '<span style="color: var(--accent-primary); font-weight: 500;">Today</span>';
    } elseif ($daysDiff === 1) {
        // One calendar day ago = Yesterday
        echo '<span style="color: var(--accent-primary);">Yesterday</span>';
    } elseif ($daysDiff < 7) {
        // 2-6 days ago
        echo '<span style="color: var(--accent-primary);">' . $daysDiff . ' days ago</span>';
    } else {
        // 7+ days ago - show date
        echo '<span class="text-muted">' . date('M j, Y', $epDate) . '</span>';
    }
}
```

### 5. RSS Feed Generation ✅ NOW FIXED

**File**: `includes/XMLHandler.php` (lines 460-467)
**Previous Code**:
```php
$pubDate = isset($podcast['created_date']) ? 
    date('r', strtotime($podcast['created_date'])) : date('r');
```

**Fixed Code** (already applied):
```php
if (!empty($podcast['latest_episode_date'])) {
    $pubDate = date('r', strtotime($podcast['latest_episode_date']));
} else {
    $pubDate = isset($podcast['created_date']) ? 
        date('r', strtotime($podcast['created_date'])) : date('r');
}
```

**Verification**: ✅ Generated feed now shows actual episode dates

### 6. Modal Preview Display ❌ NEEDS FIX

**File**: `assets/js/app.js` (search for "previewLatestEpisode")

The modal also displays episode dates and likely has the same issue.

**Location to check**: JavaScript function that populates the preview modal

---

## Comprehensive Fix Plan

### Fix 1: Main Table Display Logic (PRIMARY)

**File**: `index.php`
**Lines**: 303-327
**Change**: Use calendar day comparison instead of elapsed time

**Implementation**:
```php
<td class="text-muted">
    <?php 
    if (!empty($podcast['latest_episode_date'])) {
        $epDate = strtotime($podcast['latest_episode_date']);
        $now = time();
        
        // Compare calendar dates, not timestamps
        $epDay = strtotime(date('Y-m-d', $epDate));
        $today = strtotime(date('Y-m-d', $now));
        $daysDiff = floor(($today - $epDay) / 86400);
        
        if ($daysDiff < 0) {
            // Future date (shouldn't happen, but handle it)
            echo '<span style="color: var(--accent-primary); font-weight: 500;">Today</span>';
        } elseif ($daysDiff === 0) {
            // Same calendar day = Today
            echo '<span style="color: var(--accent-primary); font-weight: 500;">Today</span>';
        } elseif ($daysDiff === 1) {
            // One calendar day ago = Yesterday
            echo '<span style="color: var(--accent-primary);">Yesterday</span>';
        } elseif ($daysDiff < 7) {
            // 2-6 days ago
            echo '<span style="color: var(--accent-primary);">' . $daysDiff . ' days ago</span>';
        } else {
            // 7+ days ago - show actual date
            echo '<span class="text-muted">' . date('M j, Y', $epDate) . '</span>';
        }
    } else {
        echo '<span style="color: var(--text-muted); font-style: italic;">Unknown</span>';
    }
    ?>
</td>
```

### Fix 2: Modal Preview Display (SECONDARY)

**File**: `assets/js/app.js`
**Search for**: `previewLatestEpisode` or similar

**Current code** (likely):
```javascript
// Find the code that sets the latest episode in the modal
```

**Need to apply same calendar-day logic in JavaScript**:
```javascript
function formatEpisodeDate(dateString) {
    if (!dateString) return 'Unknown';
    
    const epDate = new Date(dateString);
    const now = new Date();
    
    // Strip time component - compare calendar days
    const epDay = new Date(epDate.getFullYear(), epDate.getMonth(), epDate.getDate());
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    const daysDiff = Math.floor((today - epDay) / (1000 * 60 * 60 * 24));
    
    if (daysDiff < 0) {
        return 'Today';
    } else if (daysDiff === 0) {
        return 'Today';
    } else if (daysDiff === 1) {
        return 'Yesterday';
    } else if (daysDiff < 7) {
        return daysDiff + ' days ago';
    } else {
        return epDate.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
}
```

### Fix 3: Verify Feed Generation (ALREADY DONE ✅)

**File**: `includes/XMLHandler.php` (line 460-467)
**Status**: Already fixed to use `latest_episode_date`

---

## Testing Strategy

### Test Case 1: Labor Radio
**Setup**:
- Episode published: Oct 13, 2025 at 4:00 PM
- Current date: Oct 14, 2025 at 3:39 PM
- Elapsed time: 23 hours 39 minutes

**Expected Results**:
- ❌ OLD: Shows "Today" (< 24 hours)
- ✅ NEW: Shows "Yesterday" (different calendar day)

### Test Case 2: WJFF Radio
**Setup**:
- Episode published: Oct 14, 2025 at 2:00 PM
- Current date: Oct 14, 2025 at 3:39 PM
- Elapsed time: 1 hour 39 minutes

**Expected Results**:
- ✅ OLD: Shows "Today"
- ✅ NEW: Shows "Today" (same calendar day)

### Test Case 3: Edge Case - Just After Midnight
**Setup**:
- Episode published: Oct 13, 2025 at 11:30 PM
- Current date: Oct 14, 2025 at 12:30 AM
- Elapsed time: 1 hour

**Expected Results**:
- ❌ OLD: Shows "Today" (< 24 hours)
- ✅ NEW: Shows "Yesterday" (different calendar day)

### Test Case 4: 3rd & Fairfax
**Setup**:
- Episode published: Oct 9, 2025 at 10:31 PM
- Current date: Oct 14, 2025 at 3:39 PM
- Days difference: 4 days

**Expected Results**:
- ✅ OLD: Shows "4 days ago"
- ✅ NEW: Shows "4 days ago" (no change)

---

## Implementation Checklist

### Phase 1: Core Display Fix
- [ ] Update `index.php` lines 303-327 with calendar-day logic
- [ ] Test with all podcasts in database
- [ ] Verify "Today" shows only for same calendar day
- [ ] Verify "Yesterday" shows for previous calendar day
- [ ] Verify "X days ago" calculates correctly

### Phase 2: Modal Preview Fix
- [ ] Locate modal preview code in `assets/js/app.js`
- [ ] Implement JavaScript calendar-day comparison
- [ ] Test modal displays match table displays
- [ ] Verify consistency across all views

### Phase 3: RSS Feed Verification
- [ ] Verify `feed.php` output uses `latest_episode_date`
- [ ] Check `<pubDate>` tags in generated feed
- [ ] Confirm dates match actual podcast episodes
- [ ] Test feed sorting by episode date

### Phase 4: Integration Testing
- [ ] Run auto-scan: `php cron/auto-scan-feeds.php`
- [ ] Verify all episode dates update correctly
- [ ] Check display shows correct relative dates
- [ ] Test manual refresh button
- [ ] Verify modal preview matches table
- [ ] Check generated RSS feed

---

## Files to Modify

### Required Changes
1. **`index.php`** (lines 303-327)
   - Change: Use calendar-day comparison
   - Impact: Main table display
   - Risk: Low (isolated change)

2. **`assets/js/app.js`** (location TBD)
   - Change: Add `formatEpisodeDate()` function
   - Impact: Modal preview display
   - Risk: Low (new function)

### Already Fixed
3. **`includes/XMLHandler.php`** (lines 460-467) ✅
   - Change: Use `latest_episode_date` in RSS feed
   - Status: COMPLETED

### No Changes Needed
4. **`includes/RssFeedParser.php`** ✅ Correctly extracts episode dates
5. **`includes/PodcastManager.php`** ✅ Correctly stores/retrieves dates
6. **`cron/auto-scan-feeds.php`** ✅ Correctly updates dates
7. **`api/refresh-feed-metadata.php`** ✅ Works correctly

---

## Expected Outcomes

### Before Fix
```
Labor Radio: Latest Episode = Oct 13, 4:00 PM
  Display: "Today" ❌ (23 hours ago)
  
3rd & Fairfax: Latest Episode = Oct 9, 10:31 PM
  Display: "4 days ago" ✅ (correct)
```

### After Fix
```
Labor Radio: Latest Episode = Oct 13, 4:00 PM
  Display: "Yesterday" ✅ (1 calendar day ago)
  
3rd & Fairfax: Latest Episode = Oct 9, 10:31 PM
  Display: "4 days ago" ✅ (still correct)
```

### RSS Feed Output
```xml
<item>
    <title>Labor Radio-Podcast Weekly</title>
    <pubDate>Mon, 13 Oct 2025 16:00:00 +0000</pubDate>  ✅ Actual episode date
</item>
```

---

## Success Criteria

### Data Accuracy
- [x] Parser extracts correct episode dates from feeds
- [x] Database stores episode dates correctly
- [x] Auto-scan updates episode dates every 30 minutes
- [x] RSS feed uses `latest_episode_date` for `<pubDate>`

### Display Accuracy
- [ ] "Today" shows only for episodes published today (same calendar day)
- [ ] "Yesterday" shows for episodes published yesterday (1 calendar day ago)
- [ ] "X days ago" calculates based on calendar days, not elapsed hours
- [ ] Modal preview matches table display
- [ ] All views show consistent dates

### User Experience
- [ ] Users can trust "Latest Episode" column
- [ ] Sorting by "Newest Episodes" shows truly fresh content
- [ ] Generated RSS feed reflects actual podcast freshness
- [ ] No confusion about episode recency

---

## Rollback Plan

If issues arise:
1. Revert `index.php` to use elapsed time calculation
2. Revert `assets/js/app.js` modal changes
3. Keep `XMLHandler.php` RSS feed fix (it's correct)
4. No data loss - all dates remain in database

---

## Summary

**Root Cause**: Display logic uses elapsed time (hours) instead of calendar days

**Primary Fix**: Change `index.php` to compare calendar dates, not timestamps

**Secondary Fix**: Update modal preview JavaScript to use same logic

**Already Fixed**: RSS feed generation now uses `latest_episode_date`

**Data Collection**: ✅ Working perfectly - no changes needed

**Expected Result**: 
- Episodes published yesterday show "Yesterday" (not "Today")
- Episodes published today show "Today"
- All other displays remain accurate
- RSS feed shows actual episode publication dates
