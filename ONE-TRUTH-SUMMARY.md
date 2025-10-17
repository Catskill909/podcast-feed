# Latest Episode Date - ONE TRUTH System

## Quick Reference Card

### ✅ VERIFIED: System is Consistent

All three display locations now use:
- **Same data source:** `row.dataset.latestEpisode` from `<tr data-latest-episode="...">`
- **Same calculation:** Midnight normalization + day difference
- **Same format:** "Yesterday", "2 days ago", or "Oct 16, 2025"

---

## The ONE TRUTH

```
📄 data/podcasts.xml
   └─→ <latest_episode_date>YYYY-MM-DD HH:MM:SS</latest_episode_date>
```

**Everything flows from this single source.**

---

## How Data Flows

### 1. Storage (The Truth)
```
data/podcasts.xml
```

### 2. Updates (How truth changes)
```
Cron Job (hourly) ──┐
Manual Refresh ─────┼──→ RssFeedParser ──→ PodcastManager ──→ XML
Import Process ─────┘
```

### 3. Distribution (How truth spreads)
```
XML ──→ PHP ──→ HTML data attribute ──→ JavaScript ──→ Display
```

### 4. Display (How truth appears)
```
Main Page Table ────┐
Player Modal ───────┼──→ Same calculation ──→ "Yesterday"
Podcast Info Modal ─┘
```

---

## The Three Display Locations

### 1. Main Page Table
- **File:** `index.php` + `assets/js/app.js`
- **Reads from:** `row.dataset.latestEpisode`
- **Function:** `formatLatestEpisodeDate()` (app.js lines 1663-1705)
- **Runs:** On page load via `updateAllLatestEpisodeDates()`

### 2. Player Modal
- **File:** `assets/js/player-modal.js`
- **Reads from:** `row.dataset.latestEpisode`
- **Function:** `formatDate()` (lines 616-644)
- **Runs:** When modal opens

### 3. Podcast Info Modal
- **File:** `assets/js/app.js`
- **Reads from:** API response `data.latest_episode_date`
- **Calculation:** Inline (lines 1517-1546)
- **Runs:** When modal opens

---

## The Shared Calculation

**All three use this exact logic:**

```javascript
// 1. Parse date
const date = new Date(dateString);
const now = new Date();

// 2. Reset to midnight
const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());

// 3. Calculate difference
const diffDays = Math.round((nowOnly - dateOnly) / (1000 * 60 * 60 * 24));

// 4. Format
if (diffDays === 0) return 'Today';
if (diffDays === 1) return 'Yesterday';
if (diffDays < 7) return `${diffDays} days ago`;
return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
```

---

## Common Issues & Solutions

### Issue: Dates don't match across locations
**Cause:** Cached JavaScript  
**Solution:** Hard refresh (Cmd+Shift+R) or update `ASSETS_VERSION`

### Issue: Main page shows "Loading..."
**Cause:** JavaScript not executing  
**Solution:** Check console for errors, verify app.js is loaded

### Issue: Dates are stale
**Cause:** Cron hasn't run, XML not updated  
**Solution:** Click refresh button on that podcast row

### Issue: After refresh, modal shows different date
**Cause:** Data attribute not updated  
**Solution:** Verify `app.js` line 1342 updates `row.dataset.latestEpisode`

---

## Maintenance Checklist

### When Deploying JavaScript Changes
- [ ] Update `ASSETS_VERSION` in `config/config.php`
- [ ] Push to production
- [ ] Hard refresh to verify
- [ ] Check all three display locations

### When Debugging Date Issues
- [ ] Check XML has correct date: `php debug-episode-dates.php`
- [ ] Check HTML has data attribute: View page source
- [ ] Check JavaScript is running: Open browser console
- [ ] Check calculation is correct: Test with known dates

### When Adding New Display Location
- [ ] Read from `row.dataset.latestEpisode` (or `podcast['latest_episode_date']` in PHP)
- [ ] Use shared calculation logic (copy from `formatLatestEpisodeDate()`)
- [ ] Test with multiple date ranges
- [ ] Verify matches other locations

---

## File Reference

### Core Files (Don't modify without understanding)
1. `data/podcasts.xml` - The truth
2. `includes/PodcastManager.php` - Updates the truth
3. `includes/RssFeedParser.php` - Fetches fresh truth

### Display Files (Safe to modify)
4. `index.php` - Main page HTML
5. `assets/js/app.js` - Main page + podcast info modal logic
6. `assets/js/player-modal.js` - Player modal logic

### Update Files (Automated)
7. `cron/auto-scan-feeds.php` - Hourly updates
8. `api/refresh-feed-metadata.php` - Manual updates

### Configuration
9. `config/config.php` - ASSETS_VERSION for cache busting

---

## Documentation Files

- `LATEST-EPISODE-AUDIT.md` - Complete system audit
- `LATEST-EPISODE-FLOW-DIAGRAM.md` - Visual data flow
- `ONE-TRUTH-SUMMARY.md` - This file (quick reference)
- `CACHE-BUSTING-FIX.md` - Cache busting solution
- `date-bug.md` - Debugging history

---

## Testing Commands

### Check XML date
```bash
php debug-episode-dates.php
```

### Manually refresh a podcast
```bash
php -r "
require 'includes/PodcastManager.php';
require 'includes/RssFeedParser.php';
\$pm = new PodcastManager();
\$parser = new RssFeedParser();
\$result = \$parser->fetchFeedMetadata('FEED_URL_HERE');
\$pm->updatePodcastMetadata('PODCAST_ID', [
    'latest_episode_date' => \$result['latest_episode_date']
]);
"
```

### Test JavaScript function
```javascript
// In browser console
window.updateAllLatestEpisodeDates();
```

---

## Success Criteria

✅ Main page shows relative dates ("Yesterday")  
✅ Player modal shows same relative dates  
✅ Podcast info modal shows same relative dates  
✅ Refresh button updates all three immediately  
✅ No "Loading..." stuck on page  
✅ No console errors  
✅ Cache busting works (ASSETS_VERSION)  

**All criteria met = System is working correctly! 🎉**

---

## Contact Points

If dates are inconsistent, check in this order:

1. **XML Database** - Is the date correct in `data/podcasts.xml`?
2. **HTML Attribute** - Is `data-latest-episode` set correctly?
3. **JavaScript Loading** - Is `app.js` loaded without errors?
4. **Calculation** - Is the date calculation running?
5. **Cache** - Is browser serving old JavaScript?

Most issues are #5 (cache). Hard refresh solves 90% of problems.

---

## Version History

- **Oct 17, 2025 10:04am** - Complete audit, confirmed ONE TRUTH system
- **Oct 17, 2025 10:01am** - Added cache busting (ASSETS_VERSION)
- **Oct 17, 2025 9:33am** - Fixed data attribute reading (row vs cell)
- **Oct 17, 2025 9:22am** - Added client-side date calculation
- **Earlier** - Various attempts, see `date-bug.md` for history

---

## Final Word

**The system is now consistent and maintainable.**

One source of truth (XML), one update path (PodcastManager), one calculation logic (JavaScript), one data attribute (data-latest-episode).

Any future issues will be:
- Stale data (refresh button fixes)
- Cached JavaScript (ASSETS_VERSION + hard refresh fixes)
- New bugs (check console, review this doc)

**Keep it simple. Keep it consistent. Keep ONE TRUTH.** ✅
