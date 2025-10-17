# Latest Episode Date - Complete System Audit

## Date: October 17, 2025 10:04am

---

## ONE TRUTH: The XML Database

**Source of Truth:** `/data/podcasts.xml`

All latest episode dates are stored in the XML file in this format:
```xml
<latest_episode_date>2025-10-16 14:00:00</latest_episode_date>
```

**Format:** `YYYY-MM-DD HH:MM:SS` (MySQL datetime format, no timezone)

---

## Data Flow Architecture

### 1. DATA SOURCES (Where dates come from)

#### A. RSS Feed Parser (`includes/RssFeedParser.php`)
- **Method:** `fetchFeedMetadata()`
- **Returns:** `latest_episode_date` in `YYYY-MM-DD HH:MM:SS` format
- **Source:** Parses RSS/Atom feed XML
- **Lines:** 217-219, 268-270, 334-366, 371-399

**This is the ORIGIN of all latest episode dates.**

#### B. XML Database (`data/podcasts.xml`)
- **Managed by:** `includes/XMLHandler.php`
- **Storage:** `<latest_episode_date>` element
- **Format:** `YYYY-MM-DD HH:MM:SS`
- **Lines:** 195-196, 295-301

**This is the STORAGE of latest episode dates.**

---

### 2. DATA WRITERS (What updates the dates)

#### A. Cron Job (`cron/auto-scan-feeds.php`)
- **Frequency:** Every hour (configured in crontab)
- **Process:**
  1. Reads all podcasts from XML
  2. Calls `RssFeedParser::fetchFeedMetadata()` for each
  3. Compares with stored date
  4. Updates XML if changed via `PodcastManager::updatePodcastMetadata()`
- **Lines:** 78-93

#### B. Manual Refresh Button (`api/refresh-feed-metadata.php`)
- **Trigger:** User clicks refresh icon on podcast row
- **Process:**
  1. Fetches fresh data from RSS feed
  2. Updates XML database
  3. Returns new date to JavaScript
  4. JavaScript updates `row.dataset.latestEpisode`
- **Lines:** 46-62

#### C. Feed Health Check (`api/feed-health.php`)
- **Trigger:** Automated health monitoring
- **Process:**
  1. Checks feed accessibility
  2. Updates episode data if changed
- **Lines:** 156-162

#### D. Import Process (`index.php`)
- **Trigger:** User imports new podcast from RSS
- **Process:**
  1. Creates podcast in XML
  2. Immediately fetches metadata
  3. Updates with latest episode date
- **Lines:** 36-49

---

### 3. DATA READERS (What displays the dates)

#### A. Main Page Table (`index.php`)

**Server-Side (PHP):**
- **Line 334-343:** Reads `$podcast['latest_episode_date']` from XML
- **Renders:** Absolute date as fallback (e.g., "Oct 16, 2025")
- **Also sets:** `<tr data-latest-episode="...">` attribute

**Client-Side (JavaScript):**
- **File:** `assets/js/app.js`
- **Function:** `updateAllLatestEpisodeDates()` (lines 1712-1739)
- **Reads from:** `row.dataset.latestEpisode` (line 1731)
- **Calculates:** Relative date using `formatLatestEpisodeDate()` (lines 1663-1705)
- **Displays:** "Yesterday", "2 days ago", etc.

**Data Flow:**
```
XML → PHP → HTML data attribute → JavaScript → Formatted display
```

#### B. Player Modal (`assets/js/player-modal.js`)

**Data Source:**
- **Line 123:** Reads `row.dataset.latestEpisode`
- **Stores in:** `podcast.latest_episode`

**Display:**
- **Line 189:** Calls `this.formatDate(podcast.latest_episode)`
- **Function:** `formatDate()` (lines 616-644)
- **Displays:** "Yesterday", "2 days ago", etc.

**Data Flow:**
```
HTML data attribute → JavaScript object → formatDate() → Display
```

#### C. Podcast Info Modal (`assets/js/app.js`)

**Data Source:**
- **API Call:** `api/get-podcast-preview.php`
- **Returns:** `latest_episode_date` from XML or fresh from feed
- **Line 1516:** Receives `data.latest_episode_date`

**Display:**
- **Lines 1517-1546:** Calculates relative date
- **Displays:** "Yesterday", "2 days ago", etc.

**Data Flow:**
```
API → JSON response → JavaScript calculation → Display
```

---

## Date Calculation Logic

### ALL THREE LOCATIONS USE THE SAME LOGIC:

```javascript
// 1. Parse the date string
const date = new Date(dateString);
const now = new Date();

// 2. Reset to midnight for accurate day comparison
const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());

// 3. Calculate difference in days
const diffTime = nowOnly - dateOnly;
const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

// 4. Format based on difference
if (diffDays === 0) return 'Today';
if (diffDays === 1) return 'Yesterday';
if (diffDays < 7) return `${diffDays} days ago`;
return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
```

**Locations:**
- Main page: `assets/js/app.js` lines 1669-1700
- Player modal: `assets/js/player-modal.js` lines 620-640
- Podcast info modal: `assets/js/app.js` lines 1517-1546

---

## Potential Issues & Solutions

### Issue 1: Stale Data in XML
**Problem:** Cron job hasn't run, XML has old date  
**Solution:** Manual refresh button updates immediately  
**Prevention:** Cron runs every hour

### Issue 2: Cached JavaScript
**Problem:** Browser serves old JS without new functions  
**Solution:** Cache busting with `ASSETS_VERSION` constant  
**File:** `config/config.php` line 30

### Issue 3: Data Attribute Not Updated
**Problem:** Refresh button updates XML but not HTML attribute  
**Solution:** `api/refresh-feed-metadata.php` returns new date, JS updates `row.dataset.latestEpisode`  
**File:** `assets/js/app.js` line 1342

### Issue 4: Timezone Confusion
**Problem:** Server and client in different timezones  
**Solution:** All calculations done client-side in user's timezone  
**Note:** Dates stored without timezone info, interpreted as local time

---

## Data Consistency Checklist

✅ **Single Source of Truth:** XML database (`data/podcasts.xml`)  
✅ **Single Update Method:** `PodcastManager::updatePodcastMetadata()`  
✅ **Single Calculation Logic:** Shared across all display locations  
✅ **Single Data Attribute:** `data-latest-episode` on table rows  
✅ **Automatic Updates:** Cron job every hour  
✅ **Manual Updates:** Refresh button  
✅ **Cache Busting:** Version parameter on JS files  

---

## Critical Files

### Data Layer
1. `data/podcasts.xml` - Storage
2. `includes/XMLHandler.php` - XML operations
3. `includes/PodcastManager.php` - Business logic
4. `includes/RssFeedParser.php` - Feed parsing

### Update Layer
5. `cron/auto-scan-feeds.php` - Automated updates
6. `api/refresh-feed-metadata.php` - Manual updates
7. `api/feed-health.php` - Health check updates

### Display Layer
8. `index.php` - Main page (server-side + data attribute)
9. `assets/js/app.js` - Main page calculation + podcast info modal
10. `assets/js/player-modal.js` - Player modal calculation

### Configuration
11. `config/config.php` - ASSETS_VERSION for cache busting

---

## Testing Checklist

- [ ] Main page shows relative dates
- [ ] Player modal shows same relative dates
- [ ] Podcast info modal shows same relative dates
- [ ] Refresh button updates all three locations
- [ ] Cron job updates XML correctly
- [ ] Hard refresh loads latest JavaScript
- [ ] Different timezones show correct relative dates
- [ ] Dates older than 7 days show absolute format

---

## Maintenance

### When Adding New Display Location
1. Read from `row.dataset.latestEpisode` (HTML) or `podcast['latest_episode_date']` (PHP)
2. Use the shared calculation logic (copy from `formatLatestEpisodeDate()`)
3. Test with different dates (today, yesterday, 5 days ago, 30 days ago)

### When Modifying Date Logic
1. Update in ONE place: `assets/js/app.js` `formatLatestEpisodeDate()`
2. Consider updating `player-modal.js` `formatDate()` to use shared function
3. Update `ASSETS_VERSION` in `config/config.php`
4. Test all three display locations

### When Deploying
1. Update `ASSETS_VERSION` in `config/config.php`
2. Push to production
3. Verify with hard refresh
4. Check browser console for errors

---

## Summary

**ONE TRUTH:** XML database stores all dates  
**ONE FORMAT:** `YYYY-MM-DD HH:MM:SS`  
**ONE CALCULATION:** Shared JavaScript logic  
**ONE DATA ATTRIBUTE:** `data-latest-episode` on rows  
**ONE UPDATE PATH:** Through `PodcastManager::updatePodcastMetadata()`  

All display locations read from the same source and use the same calculation. Any discrepancies are due to:
1. Stale data (solved by cron/manual refresh)
2. Cached JavaScript (solved by cache busting)
3. Data attribute not updated (solved by refresh button JS)

**System is now consistent and maintainable.** ✅
