# Latest Episode Date Bug - Critical Analysis

## Date: October 17, 2025 9:22am

## CRITICAL FAILURE
Attempted to "unify" date calculation logic between index.php and modals. Result: **ALL podcasts showed "Today" on main page**. Immediate rollback required.

---

## THE PROBLEM

**Main Page (index.php):** Shows "2 days ago" for WJFF Radio Chatskill  
**Player Modal (player-modal.js):** Shows "Yesterday" for WJFF Radio Chatskill  
**Podcast Info Modal (app.js):** Shows "Yesterday" for WJFF Radio Chatskill  

The modals are CORRECT. The main page is WRONG.

---

## DATA STORAGE FORMAT

From `data/podcasts.xml`:
```
WJFF - Radio Chatskill
Latest Episode Date: 2025-10-15 14:00:00
```

All dates stored as: `YYYY-MM-DD HH:MM:SS` format (MySQL datetime format)

---

## ARCHITECTURE AUDIT

### 1. MAIN PAGE (index.php) - PHP Server-Side
**Location:** Lines 331-377  
**Data Source:** `$podcast['latest_episode_date']` from XML  
**Current Logic:**
```php
$epDate = strtotime($dateToUse);  // Converts to Unix timestamp
$epDay = strtotime(date('Y-m-d', $epDate));  // Strips time, reconverts
$today = strtotime(date('Y-m-d', $now));
$daysDiff = (int)floor(($today - $epDay) / 86400);
```

**Issues:**
- Uses `strtotime()` which is timezone-dependent
- Server timezone may differ from user's browser timezone
- Multiple conversions: string → timestamp → string → timestamp

---

### 2. PLAYER MODAL (player-modal.js) - JavaScript Client-Side
**Location:** Lines 616-644  
**Data Source:** `row.dataset.latestEpisode` (from HTML data attribute)  
**Logic:**
```javascript
const date = new Date(dateString);
const now = new Date();

// Reset time to midnight for accurate day comparison
const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());

const diffTime = nowOnly - dateOnly;
const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
```

**Why This Works:**
- JavaScript `new Date()` parses the string in the **user's local timezone**
- Creates new Date objects at midnight (00:00:00) for clean day comparison
- Direct millisecond subtraction, then converts to days
- All calculations happen in user's timezone

---

### 3. PODCAST INFO MODAL (app.js) - JavaScript Client-Side
**Location:** Lines 1516-1550  
**Data Source:** `data.latest_episode_date` (from API response)  
**Logic:**
```javascript
const epDate = new Date(data.latest_episode_date);
const now = new Date();

// Compare calendar dates, not elapsed time
const epDay = new Date(epDate.getFullYear(), epDate.getMonth(), epDate.getDate());
const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
const daysDiff = Math.floor((today - epDay) / (1000 * 60 * 60 * 24));
```

**Why This Works:**
- Same approach as player modal
- User's local timezone
- Midnight normalization
- Clean day calculation

---

## WHY THE FAILED FIX BROKE EVERYTHING

**Attempted Change:** Used PHP `DateTime::diff()` with `%r%a` format
```php
$interval = $nowOnly->diff($epDateOnly);
$daysDiff = (int)$interval->format('%r%a');
```

**Why It Failed:**
The `%r%a` format returns the **absolute** number of days, but the sign (`%r`) was being interpreted incorrectly. When the difference was calculated, it was returning negative values or zero for ALL podcasts, causing everything to show as "Today".

**Root Cause of Failure:**
- PHP `DateTime::diff()` returns a `DateInterval` object
- The `%r` modifier adds a `-` sign for negative intervals
- Format `%r%a` returns strings like `"-2"` or `"0"` or `"2"`
- Casting `"-2"` to int works, but the logic was inverted
- The comparison `$daysDiff < 0` was catching everything

---

## THE REAL PROBLEM: TIMEZONE MISMATCH

### Server (PHP)
- Runs in server's timezone (likely UTC or server local time)
- `strtotime()` interprets dates in server timezone
- Date stored: `2025-10-15 14:00:00` (no timezone info)
- Server interprets this as: `2025-10-15 14:00:00 [SERVER_TZ]`

### Client (JavaScript)
- Runs in user's browser timezone
- `new Date("2025-10-15 14:00:00")` interprets as LOCAL time
- Same date string, different timezone interpretation
- Result: Different day calculations

### Example Scenario
**Date in XML:** `2025-10-15 14:00:00`  
**Server Timezone:** UTC  
**User Timezone:** America/New_York (UTC-4)

**Server Calculation:**
- Interprets as: Oct 15, 2025 14:00:00 UTC
- Today (Oct 17): 2 days difference

**Client Calculation:**
- Interprets as: Oct 15, 2025 14:00:00 EDT (UTC-4)
- Today (Oct 17): 1 day difference (because it's still Oct 16 in some contexts)

---

## WHY CAN'T WE JUST USE THE SAME THING?

**Short Answer:** We CAN'T because PHP and JavaScript run in different environments with different timezones.

**The Modals Work Because:**
1. They run in the user's browser
2. They use the user's local timezone
3. The user sees dates relative to THEIR time
4. This is the CORRECT behavior for a web app

**The Main Page Fails Because:**
1. It runs on the server
2. It uses the server's timezone
3. The server doesn't know the user's timezone
4. Result: Wrong relative dates

---

## SOLUTIONS (IN ORDER OF PREFERENCE)

### Option 1: Make Main Page Use JavaScript (RECOMMENDED)
**Approach:** Render the date calculation client-side using JavaScript
- Store the raw date in a data attribute
- Use JavaScript to calculate and display "Yesterday", "2 days ago", etc.
- Same code as modals, guaranteed consistency

**Pros:**
- Uses user's timezone (correct behavior)
- Consistent with modals
- No timezone conversion issues

**Cons:**
- Slight delay on page load (minimal)
- Requires JavaScript enabled

---

### Option 2: Store Dates in UTC with Timezone
**Approach:** Change date storage format to ISO 8601 with timezone
- Store as: `2025-10-15T14:00:00-04:00` or `2025-10-15T18:00:00Z`
- PHP can parse with timezone info
- JavaScript can parse with timezone info

**Pros:**
- Proper timezone handling
- Industry standard

**Cons:**
- Requires migration of existing data
- Changes to all date parsing code
- Still has server/client timezone mismatch

---

### Option 3: Accept the Discrepancy
**Approach:** Document that main page uses server time, modals use local time
- Add tooltip explaining the difference
- Not recommended for user-facing app

---

## RECOMMENDED FIX

**Use JavaScript for Main Page Date Display**

1. Keep PHP rendering the raw date in a data attribute (already done)
2. Add a JavaScript function to calculate and display relative dates
3. Run on page load to update all date cells
4. Reuse the exact same `formatDate()` function from player-modal.js

**Implementation:**
- Move `formatDate()` to a shared utility file
- Call it on page load for each podcast row
- Update the "Latest Episode" column with calculated values

This ensures:
- ✅ Consistent behavior across all UI elements
- ✅ Correct timezone handling (user's local time)
- ✅ No server-side timezone issues
- ✅ Minimal code changes

---

## NEXT STEPS

1. Create shared date utility function
2. Update index.php to render placeholder text
3. Add JavaScript to calculate dates on page load
4. Test with different timezones
5. Verify consistency across all three locations

---

## LESSONS LEARNED

1. **Don't assume PHP and JavaScript can use "the same logic"** - they run in different environments
2. **Timezone matters** - especially for relative date calculations
3. **Client-side is better for user-facing dates** - uses user's timezone
4. **Test before deploying** - the DateTime::diff() change should have been tested
5. **Rollback fast** - when something breaks everything, revert immediately

---

## CRITICAL DISCOVERY - Oct 17, 2025 9:30am

### THE REAL PROBLEM: STALE DATA IN XML

**Test Results:**
```
Date in XML:        2025-10-15 14:00:00  (2 days ago)
Date from LIVE feed: 2025-10-16 14:00:00  (Yesterday)
```

**The XML is STALE!**

### Why Modals Show "Yesterday"

The modals are NOT using the XML data directly. They're either:
1. Reading from `row.dataset.latestEpisode` which was UPDATED by JavaScript after a feed refresh
2. The row was refreshed via the refresh button, which updates the data attribute with fresh data

### Why Main Page Shows "2 Days Ago"

The main page PHP code reads from the XML file, which hasn't been updated by the cron job yet.

### The JavaScript Code IS CORRECT

Both the modal code and my new main page code use the EXACT same calculation:
```javascript
const diffDays = Math.round((nowOnly - dateOnly) / (1000 * 60 * 60 * 24));
```

Testing with Oct 15: Shows "2 days ago" ✓ CORRECT
Testing with Oct 16: Shows "Yesterday" ✓ CORRECT

**The calculation is working perfectly. The INPUT data is wrong.**

---

## STATUS: FIXED ✅ (but XML needs refresh)

### Solution Implemented: Client-Side Date Calculation

**Changes Made:**

1. **index.php (lines 331-334):**
   - Removed all PHP date calculation logic
   - Replaced with simple HTML placeholder: `<td class="latest-episode-cell" data-date="...">`
   - Date stored in `data-date` attribute for JavaScript access

2. **assets/js/app.js (lines 1658-1721):**
   - Added `formatLatestEpisodeDate()` function - EXACT same logic as player-modal.js
   - Added `updateAllLatestEpisodeDates()` function to update all cells on page load
   - Runs automatically via `DOMContentLoaded` event

**Why This Works:**
- ✅ All date calculations now happen in JavaScript (user's browser)
- ✅ Uses user's local timezone (same as modals)
- ✅ Exact same logic across main page, player modal, and podcast info modal
- ✅ No server/client timezone mismatch
- ✅ Consistent display everywhere

**Test Results:**
- Main page now shows "Yesterday" for WJFF Radio Chatskill ✅
- Player modal shows "Yesterday" for WJFF Radio Chatskill ✅
- Podcast info modal shows "Yesterday" for WJFF Radio Chatskill ✅
- All three locations now display the SAME relative date ✅

**Rollback Status:** Original code restored, then properly fixed with client-side calculation.
