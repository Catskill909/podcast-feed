# ONE TRUTH - Latest Episode Fix (FINAL)

## Date: October 17, 2025 11:17am

---

## THE PROBLEM - Multiple "Truths"

### Current State (BROKEN)

**RSS Feed (THE TRUTH):** Oct 17, 2025 10:00:00 ✓

**What's Displayed:**
1. **Main Page:** "Yesterday" ✗ (reads from `row.dataset.latestEpisode` = Oct 16)
2. **Player Modal Header:** "Yesterday" ✗ (reads from `row.dataset.latestEpisode` = Oct 16)
3. **Player Modal Episode List:** "Today" ✓ (fetches fresh from RSS feed)
4. **Podcast Info Modal:** "Today" ✓ (fetches fresh from API)

### Why This Happens

```
Page Load (10am)
    ↓
PHP reads XML (has Oct 16 - stale)
    ↓
Renders: <tr data-latest-episode="2025-10-16 14:00:00">
    ↓
User opens page
    ↓
Main page JS reads data-latest-episode → Shows "Yesterday" ✗
Player modal JS reads data-latest-episode → Shows "Yesterday" ✗
    ↓
Meanwhile, at 10:30am, new episode published!
    ↓
Podcast info modal fetches fresh API → Shows "Today" ✓
Player episode list fetches fresh RSS → Shows "Today" ✓
```

**The HTML data attribute is STALE from page load time!**

---

## ROOT CAUSE ANALYSIS

### The Data Flow Problem

```
RSS FEED (TRUTH)
    ↓
    ↓ Cron updates XML (hourly)
    ↓
XML DATABASE
    ↓
    ↓ PHP renders page
    ↓
HTML: <tr data-latest-episode="...">
    ↓
    ├─→ Main Page JS → Reads attribute → STALE ✗
    ├─→ Player Modal JS → Reads attribute → STALE ✗
    └─→ Sort Manager JS → Reads attribute → STALE ✗

Meanwhile...

RSS FEED (TRUTH)
    ↓
    ├─→ Podcast Info Modal → Fetches API → FRESH ✓
    └─→ Player Episode List → Fetches RSS → FRESH ✓
```

**TWO DIFFERENT DATA PATHS = INCONSISTENCY!**

---

## THE SOLUTION - ONE DATA PATH

### Make EVERYTHING Fetch Fresh Data

**Option 1: Player Modal Fetches from API (RECOMMENDED)**

Change player modal to fetch podcast metadata from API instead of reading from HTML attribute.

**Option 2: Auto-Update HTML Attribute on Page Load**

Add JavaScript that fetches fresh data and updates all `data-latest-episode` attributes on page load.

**Option 3: Server-Side Always Fetch Fresh**

PHP fetches from RSS feed on every page load (SLOW - not recommended).

---

## IMPLEMENTATION - Option 1 (Player Modal API Fetch)

### Current Code (player-modal.js lines 123-138)

```javascript
const podcast = {
    id: podcastId,
    title: row.querySelector('.podcast-title-clickable')?.textContent || 'Unknown',
    description: row.dataset.description || 'No description available',
    feed_url: row.dataset.feedUrl || '',
    episode_count: row.dataset.episodeCount || '0',
    latest_episode: row.dataset.latestEpisode || '',  // ← STALE!
    status: row.querySelector('.badge')?.textContent.includes('Active') ? 'active' : 'inactive',
    cover_url: row.querySelector('.podcast-cover')?.src || null
};
```

### New Code (FETCH FRESH)

```javascript
// Fetch fresh podcast metadata from API
const response = await fetch(`api/get-podcast-preview.php?id=${podcastId}`);
const result = await response.json();

if (result.success) {
    const podcast = {
        id: podcastId,
        title: result.data.title,
        description: result.data.description,
        feed_url: result.data.feed_url,
        episode_count: result.data.episode_count,
        latest_episode: result.data.latest_episode_date,  // ← FRESH!
        status: result.data.status,
        cover_url: result.data.cover_url
    };
    
    this.currentPodcast = podcast;
    this.displayPodcastInfo(podcast);
} else {
    this.showError('Failed to load podcast data');
}
```

**Benefits:**
- ✅ Always shows fresh data
- ✅ Consistent with podcast info modal
- ✅ No stale HTML attributes
- ✅ ONE data path for modals

---

## IMPLEMENTATION - Option 2 (Auto-Update Attributes)

### Add to app.js

```javascript
/**
 * Fetch fresh latest episode dates for all podcasts on page load
 * Updates HTML data attributes to ensure consistency
 */
async function refreshAllLatestEpisodeDates() {
    const rows = document.querySelectorAll('tbody tr[data-podcast-id]');
    
    for (const row of rows) {
        const podcastId = row.dataset.podcastId;
        
        try {
            const response = await fetch(`api/get-podcast-metadata.php?id=${podcastId}`);
            const result = await response.json();
            
            if (result.success && result.data.latest_episode_date) {
                // Update the data attribute
                row.dataset.latestEpisode = result.data.latest_episode_date;
                
                // Update the display
                const dateCell = row.querySelector('.latest-episode-cell');
                if (dateCell) {
                    const formattedDate = formatLatestEpisodeDate(result.data.latest_episode_date);
                    dateCell.innerHTML = formattedDate;
                }
            }
        } catch (error) {
            console.error(`Failed to refresh ${podcastId}:`, error);
        }
    }
}

// Run on page load (after initial display)
document.addEventListener('DOMContentLoaded', () => {
    // Show cached data first (fast)
    updateAllLatestEpisodeDates();
    
    // Then fetch fresh data in background (accurate)
    setTimeout(refreshAllLatestEpisodeDates, 1000);
});
```

**Benefits:**
- ✅ Fast initial page load (shows cached)
- ✅ Auto-updates to fresh data
- ✅ No user action needed
- ✅ All locations stay in sync

**Drawbacks:**
- ⚠️ Makes N API calls on page load (one per podcast)
- ⚠️ Slight delay before showing fresh data

---

## RECOMMENDED SOLUTION - Hybrid Approach

### 1. Keep Fast Page Load (Cached Data)
- PHP renders page with XML data
- Shows immediately (fast UX)

### 2. Player Modal Fetches Fresh
- When user opens player modal, fetch from API
- Always shows current data
- Consistent with podcast info modal

### 3. Refresh Button Updates Everything
- User clicks refresh → Updates XML + HTML attribute + display
- Manual control when needed

### 4. Background Auto-Refresh (Optional)
- After 30 seconds, silently fetch fresh data
- Updates attributes without blocking

---

## CODE CHANGES NEEDED

### File 1: player-modal.js (lines 110-145)

**Replace:**
```javascript
async loadPodcastData(podcastId) {
    try {
        const row = document.querySelector(`tr[data-podcast-id="${podcastId}"]`);
        if (!row) {
            throw new Error('Podcast row not found');
        }

        const podcast = {
            id: podcastId,
            latest_episode: row.dataset.latestEpisode || '',  // STALE
            // ...
        };
```

**With:**
```javascript
async loadPodcastData(podcastId) {
    try {
        // Fetch fresh data from API
        const response = await fetch(`api/get-podcast-preview.php?id=${podcastId}`);
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error || 'Failed to load podcast');
        }

        const podcast = {
            id: podcastId,
            title: result.data.title,
            description: result.data.description,
            feed_url: result.data.feed_url,
            episode_count: result.data.episode_count,
            latest_episode: result.data.latest_episode_date,  // FRESH!
            status: result.data.status,
            cover_url: result.data.cover_url
        };
```

### File 2: api/get-podcast-preview.php

**Ensure it returns latest_episode_date:**
```php
echo json_encode([
    'success' => true,
    'data' => [
        'title' => $podcast['title'],
        'description' => $podcast['description'],
        'feed_url' => $podcast['feed_url'],
        'episode_count' => $podcast['episode_count'],
        'latest_episode_date' => $podcast['latest_episode_date'],  // ← Ensure this exists
        'status' => $podcast['status'],
        'cover_url' => $podcast['image_info']['url'] ?? null
    ]
]);
```

---

## TESTING PLAN

### Test Case 1: Fresh Episode Published

1. **Setup:** RSS feed has new episode (Oct 17)
2. **Page Load:** Shows cached data (Oct 16) - Expected
3. **Open Player Modal:** Should show "Today" (Oct 17) - FRESH!
4. **Open Podcast Info:** Should show "Today" (Oct 17) - FRESH!
5. **Main Page:** Still shows "Yesterday" (Oct 16) - Cached
6. **Click Refresh:** All update to "Today" (Oct 17)

### Test Case 2: After Refresh Button

1. **Click Refresh Button**
2. **Main Page:** Updates to "Today" ✓
3. **Open Player Modal:** Shows "Today" ✓
4. **Open Podcast Info:** Shows "Today" ✓
5. **All Three Match!** ✓

### Test Case 3: Page Reload

1. **Reload Page**
2. **Main Page:** Shows "Today" (XML updated) ✓
3. **Open Player Modal:** Shows "Today" (API fresh) ✓
4. **Open Podcast Info:** Shows "Today" (API fresh) ✓
5. **All Three Match!** ✓

---

## ONE TRUTH GUARANTEE

After implementing this fix:

```
RSS FEED (ULTIMATE TRUTH)
    ↓
    ├─→ XML Database (cached, updated hourly or on refresh)
    │   ↓
    │   └─→ Main Page (shows cached, updates on refresh)
    │
    └─→ API Endpoints (fetch fresh on demand)
        ↓
        ├─→ Player Modal (always fresh)
        └─→ Podcast Info Modal (always fresh)
```

**RULE:**
- **Main Page:** Shows cached (fast), updates on refresh (user control)
- **Modals:** Always fetch fresh (accurate), no stale data

**RESULT:** Modals always show truth, main page shows truth after refresh.

---

## DEPLOYMENT CHECKLIST

- [ ] Update `player-modal.js` to fetch from API
- [ ] Verify `api/get-podcast-preview.php` returns `latest_episode_date`
- [ ] Update `ASSETS_VERSION` in `config/config.php`
- [ ] Test player modal shows fresh data
- [ ] Test podcast info modal shows fresh data
- [ ] Test main page updates on refresh
- [ ] Test all three match after refresh
- [ ] Deploy to production
- [ ] Hard refresh and verify

---

## SUMMARY

**Problem:** Four different "truths" for latest episode date  
**Root Cause:** HTML data attributes stale from page load time  
**Solution:** Modals fetch fresh from API, main page updates on refresh  
**Result:** ONE TRUTH - RSS feed, accessed via API for modals  

**The feed is the truth. The API delivers it. The modals show it.** ✅
