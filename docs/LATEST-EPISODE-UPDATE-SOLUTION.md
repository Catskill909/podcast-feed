# Latest Episode Update Solution - Analysis & Recommendations

**Date:** November 20, 2025 (analysis), updated December 1, 2025 (implementation)  
**Status:** Implemented - Hybrid solution live in production  
**Priority:** High - Affects user experience on live site

---

## Executive Summary

**The Problem:** Latest podcast episodes don't appear on the live site automatically. Users must visit admin and "click around" to trigger updates.

**Root Cause:** `data/podcasts.xml` (the source of truth for episode metadata) only updates when:
1. Someone visits the site (browser auto-refresh with 5-min gate)
2. Admin manually clicks refresh button
3. Cron job runs (if configured)

**Impact:**
- ❌ App users see stale episode data
- ❌ Embed player shows old episodes
- ❌ Browse page shows outdated "Latest: X days ago" badges
- ❌ Requires manual admin intervention

**Good News:** Live site works perfectly otherwise! This was purely an update frequency issue.

### Implementation Summary (December 1, 2025)

- **Solution Chosen:** Hybrid approach (cron + lazy scan + browser auto-refresh)
- **Cron:** Coolify scheduled task running `php /app/cron/auto-scan-feeds.php` every 15 minutes (`*/15 * * * *`) in the PHP app container
- **Lazy Scan:** New `includes/FeedScanner.php` used by `feed.php` and `api/get-public-podcasts.php`
  - Triggers a background scan when data is older than 5 minutes
  - Uses `data/last-lazy-scan.txt` as its lock file
  - Writes stats to `logs/lazy-scan.log`
- **Browser Auto-Refresh:** Existing `api/auto-refresh.php` + `assets/js/auto-refresh.js` kept as an extra safety net when users hit `index.php` or `admin.php`
- **Caching Fix:** `RssFeedParser` now caches RSS responses in `data/cache/` instead of `/tmp` to avoid container permission errors inside Coolify
- **Result:** New visitors (and embeds) see the latest episodes automatically; no more need to "click around" in admin to force updates.

---

## Current Architecture (How Updates Work Now)

### Data Flow
```
External RSS Feeds
       ↓
[Update Mechanism] ← Only runs when triggered
       ↓
data/podcasts.xml (cached metadata)
       ↓
├─→ feed.php → App/Embed
├─→ api/get-public-podcasts.php → Browse page
└─→ Admin panel
```

### Existing Update Mechanisms

#### 1. Browser Auto-Refresh
**File:** `api/auto-refresh.php` + `assets/js/auto-refresh.js`

**How it works:**
- Runs once per page load on `index.php` and `admin.php`
- Gated by 5-minute cooldown (`data/last-auto-refresh.txt`)
- Fetches all RSS feeds and updates XML if changes detected

**Pros:**
- ✅ Already implemented and working
- ✅ No server configuration needed
- ✅ Works in local dev

**Cons:**
- ❌ Requires someone to visit the site
- ❌ Overnight episodes won't appear until morning visit
- ❌ 5-minute gate means not every visit triggers update
- ❌ Depends on user traffic

**Current Status:** Working, but insufficient as sole update mechanism

---

#### 2. Manual Refresh Button
**File:** `api/refresh-feed-metadata.php` + admin panel UI

**How it works:**
- Admin clicks 🔄 button on individual podcast
- Immediately fetches that feed and updates XML
- Bypasses all caching

**Pros:**
- ✅ Instant updates
- ✅ Bypasses cache completely
- ✅ Good for testing/debugging

**Cons:**
- ❌ Requires admin intervention
- ❌ Only updates one podcast at a time
- ❌ Not scalable

**Current Status:** Working perfectly, but manual

---

#### 3. Cron Job (Recommended but Not Verified in Production)
**File:** `cron/auto-scan-feeds.php`

**How it works:**
- Scheduled task runs every X minutes (recommended: 15-30)
- Scans all RSS feeds
- Updates XML with new episode data
- Logs activity to `logs/auto-scan.log`

**Pros:**
- ✅ Fully automatic
- ✅ Works 24/7 even with zero traffic
- ✅ Predictable update frequency
- ✅ Already written and tested

**Cons:**
- ⚠️ Requires server configuration (cron or scheduled task)
- ⚠️ Can silently fail if misconfigured
- ⚠️ Need to verify it's actually running in production

**Current Status:** Code exists, but unclear if running on live site (Coolify)

---

## Proposed Solutions (Ranked)

### 🥇 Solution 1: Hybrid Approach (RECOMMENDED)
**Reliability: ⭐⭐⭐⭐⭐ | Complexity: ⭐⭐⭐ | Safety: ⭐⭐⭐⭐⭐**

**Strategy:** Multiple layers of redundancy - if one fails, others catch it.

**Components:**
1. **Primary:** Cron job (every 15-30 minutes)
2. **Backup:** Lazy scan in `feed.php` (if data >10 min old)
3. **Fallback:** Browser auto-refresh (existing, keep as-is)
4. **Emergency:** Manual refresh button (existing)

**Implementation:**
```
Layer 1: Cron runs every 15-30 min
         ↓ (if cron fails)
Layer 2: feed.php checks staleness on request
         ↓ (if no one requests feed)
Layer 3: Browser auto-refresh on site visit
         ↓ (if all else fails)
Layer 4: Admin manual refresh
```

**Why This is Best:**
- ✅ **Redundant:** Multiple failure points needed to break
- ✅ **Self-healing:** If cron fails, lazy scan catches it
- ✅ **Predictable:** Cron ensures regular updates
- ✅ **Fast recovery:** Lazy scan fixes staleness within 10 minutes
- ✅ **Zero manual intervention:** Works automatically

**Risks:**
- ⚠️ More moving parts (but each is simple)
- ⚠️ Slightly more complex to debug

**Estimated Implementation Time:** 2-3 hours

**Files to Create/Modify:**
- `includes/FeedScanner.php` (new - shared scanner logic)
- `feed.php` (add lazy scan check)
- `api/get-public-podcasts.php` (add lazy scan check)
- Verify cron configuration in Coolify

---

### 🥈 Solution 2: Cron Only (SIMPLE & RELIABLE)
**Reliability: ⭐⭐⭐⭐ | Complexity: ⭐ | Safety: ⭐⭐⭐⭐**

**Strategy:** Just make sure cron is running properly.

**Implementation:**
1. Verify `cron/auto-scan-feeds.php` is scheduled in Coolify
2. Set interval to 15-30 minutes
3. Monitor `logs/auto-scan.log` for activity
4. Keep existing browser auto-refresh as backup

**Why This Works:**
- ✅ **Simple:** One thing to configure
- ✅ **Proven:** Cron is battle-tested technology
- ✅ **Predictable:** Updates every X minutes, guaranteed
- ✅ **Low overhead:** Runs in background

**Risks:**
- ⚠️ If cron breaks, updates stop until fixed
- ⚠️ Requires operational monitoring
- ⚠️ Overnight failures won't self-heal

**Estimated Implementation Time:** 30 minutes (just verification)

**Steps:**
1. SSH to production or use Coolify UI
2. Check if cron is configured: `crontab -l`
3. Add if missing: `*/15 * * * * php /app/cron/auto-scan-feeds.php`
4. Verify logs: `tail -f /app/logs/auto-scan.log`

---

### 🥉 Solution 3: Lazy Scan Only (NO CRON DEPENDENCY)
**Reliability: ⭐⭐⭐⭐ | Complexity: ⭐⭐ | Safety: ⭐⭐⭐⭐⭐**

**Strategy:** Make `feed.php` and public API self-updating.

**Implementation:**
Add check at top of `feed.php` and `api/get-public-podcasts.php`:
```php
// Check if data is stale (>10 minutes old)
if (time() - filemtime('data/last-scan.txt') > 600) {
    // Run scan to update XML
    // (reuse existing scanner logic)
}
// Then continue to serve data
```

**Why This Works:**
- ✅ **No cron needed:** Works on any hosting
- ✅ **Self-healing:** Every request checks freshness
- ✅ **Simple to understand:** One place to look
- ✅ **Works everywhere:** Local, staging, production

**Risks:**
- ⚠️ First request after staleness window is slower (~10-30s)
- ⚠️ Overnight episodes won't appear until first morning request
- ⚠️ Low-traffic sites may have stale data

**Estimated Implementation Time:** 1-2 hours

**Files to Modify:**
- `feed.php` (add lazy scan)
- `api/get-public-podcasts.php` (add lazy scan)
- `includes/FeedScanner.php` (new - shared logic)

---

### ❌ Solution 4: Increase Browser Auto-Refresh Frequency (NOT RECOMMENDED)
**Reliability: ⭐⭐ | Complexity: ⭐ | Safety: ⭐⭐**

**Strategy:** Reduce 5-minute gate to 1-2 minutes.

**Why NOT to do this:**
- ❌ Still requires site visits
- ❌ More aggressive RSS fetching (may anger upstream servers)
- ❌ Doesn't solve overnight problem
- ❌ Wastes resources on empty checks

**Only consider if:** You absolutely cannot use cron or lazy scan.

---

## Detailed Comparison Matrix

| Solution | Reliability | Complexity | Cron Required | Works Overnight | Self-Healing | Implementation Time |
|----------|-------------|------------|---------------|-----------------|--------------|---------------------|
| **Hybrid** | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | Optional | ✅ Yes | ✅ Yes | 2-3 hours |
| **Cron Only** | ⭐⭐⭐⭐ | ⭐ | Required | ✅ Yes | ❌ No | 30 minutes |
| **Lazy Scan** | ⭐⭐⭐⭐ | ⭐⭐ | No | ⚠️ Partial | ✅ Yes | 1-2 hours |
| **Browser Only** | ⭐⭐ | ⭐ | No | ❌ No | ❌ No | 5 minutes |

---

## Recommended Path Forward

### Phase 1: Verify Cron (30 minutes)
**Do this first - it might already be working!**

1. Check Coolify scheduled tasks
2. Look for `cron/auto-scan-feeds.php` entry
3. Check `logs/auto-scan.log` for recent activity
4. Check `data/last-scan.txt` timestamp

**If cron is running:** Problem might be solved already! Just needs monitoring.

**If cron is NOT running:** Proceed to Phase 2.

---

### Phase 2: Implement Hybrid Solution (2-3 hours)
**Belt and suspenders approach**

#### Step 1: Create Shared Scanner (45 min)
**File:** `includes/FeedScanner.php`

```php
<?php
class FeedScanner {
    private $lockFile = 'data/last-feed-scan.txt';
    private $scanInterval = 600; // 10 minutes
    
    public function needsScan() {
        if (!file_exists($this->lockFile)) return true;
        return (time() - filemtime($this->lockFile)) >= $this->scanInterval;
    }
    
    public function scan() {
        // Update lock immediately
        touch($this->lockFile);
        
        // Reuse existing scanner logic from auto-refresh.php
        // Fetch all feeds, update XML if changed
        // Return stats
    }
}
```

#### Step 2: Add Lazy Scan to feed.php (30 min)
```php
// At top of feed.php, before generating feed
require_once 'includes/FeedScanner.php';

$scanner = new FeedScanner();
if ($scanner->needsScan()) {
    $scanner->scan(); // Updates XML if stale
}

// Continue with normal feed generation
```

#### Step 3: Add Lazy Scan to Public API (30 min)
Same pattern in `api/get-public-podcasts.php`

#### Step 4: Configure/Verify Cron (30 min)
Set up in Coolify:
- Command: `php /app/cron/auto-scan-feeds.php`
- Schedule: `*/15 * * * *` (every 15 minutes)

#### Step 5: Test & Monitor (30 min)
- Force stale data: `echo "0" > data/last-feed-scan.txt`
- Request feed.php: Should trigger scan
- Wait 15 min: Cron should run
- Check logs: Verify both mechanisms working

---

### Phase 3: Production Deployment (30 min)

#### Pre-Deploy Checklist
- [ ] All code tested locally
- [ ] Cron configured in Coolify
- [ ] Logs directory writable
- [ ] Lock files writable

#### Deploy Steps
```bash
# 1. Commit changes
git add -A
git commit -m "Add hybrid update system for latest episodes

- Created FeedScanner class for shared scan logic
- Added lazy scan to feed.php (10-min gate)
- Added lazy scan to get-public-podcasts.php
- Configured cron as primary update mechanism
- Multiple redundancy layers ensure reliability"

# 2. Push to production
git push origin main

# 3. Coolify auto-deploys

# 4. Verify cron in Coolify UI
# 5. Monitor logs for 1 hour
```

#### Post-Deploy Verification
```bash
# Check cron is running
tail -f /app/logs/auto-scan.log

# Check lazy scan activity
grep "lazy scan" /app/logs/error.log

# Verify feed freshness
curl https://podcast.supersoul.top/feed.php | grep latestEpisodeDate
```

---

## Monitoring & Maintenance

### Health Check Commands

```bash
# Check last scan time
cat data/last-feed-scan.txt | xargs -I {} date -r {}

# Check cron log
tail -50 logs/auto-scan.log

# Count scans in last hour
grep "$(date +%Y-%m-%d\ %H:)" logs/auto-scan.log | wc -l

# Manual test scan
php cron/auto-scan-feeds.php
```

### Success Metrics

After implementation, you should see:
- ✅ Episodes appear within 15-30 minutes of publication
- ✅ No manual admin intervention needed
- ✅ Logs show regular scan activity
- ✅ App/embed/browse all show latest episodes
- ✅ "Latest: Today" badges appear for new episodes

### Troubleshooting

**Problem:** Episodes still not updating

**Check:**
1. Is cron running? `tail logs/auto-scan.log`
2. Is lazy scan triggering? `grep "lazy scan" logs/error.log`
3. Are feeds reachable? Test manually with curl
4. Check lock file timestamp: `ls -la data/last-feed-scan.txt`

**Problem:** Scans running too frequently

**Fix:** Increase interval in FeedScanner.php:
```php
private $scanInterval = 900; // 15 minutes instead of 10
```

**Problem:** Cron not running

**Fix:** Check Coolify scheduled tasks, verify path and permissions

---

## Risk Assessment

### Low Risk ✅
- Adding lazy scan to feed.php (gated, safe)
- Creating FeedScanner class (isolated, reusable)
- Verifying cron configuration (read-only check)

### Medium Risk ⚠️
- Modifying cron interval (could cause more/less frequent updates)
- Changing scan timeout (could cause more failures)

### High Risk ❌
- None! All changes are additive and gated

---

## Rollback Plan

If something breaks:

```bash
# Quick rollback
git revert HEAD
git push origin main

# Or restore specific files
git restore feed.php api/get-public-podcasts.php
git restore includes/FeedScanner.php

# Disable cron temporarily
# (via Coolify UI - pause scheduled task)
```

**Fallback:** Existing browser auto-refresh still works, so worst case is back to current behavior.

---

## Alternative: Quick Win (If Time-Constrained)

**Just verify and fix cron - 30 minutes total**

If you don't have time for full hybrid implementation:

1. ✅ Check if cron is configured in Coolify
2. ✅ Set to run every 15 minutes
3. ✅ Monitor logs for 1 hour
4. ✅ Done!

This alone might solve 90% of the problem.

---

## Lessons Learned Today

### What Went Wrong
- WJFF feeds were down, causing 502 errors
- Confused local testing with production issues
- Assumed code was broken when it was external dependency

### What Went Right
- Identified root cause of update issue
- Mapped all existing update mechanisms
- Designed comprehensive solution with redundancy
- Learned that live site actually works fine!

### Key Takeaways
- ✅ Always test with multiple feeds (not just one)
- ✅ Check external dependencies first (upstream feeds)
- ✅ Local vs production can have different issues
- ✅ Redundancy is good for critical systems

---

## Next Session Checklist

When you're ready to implement:

- [ ] Read this document fully
- [ ] Choose solution (recommend Hybrid)
- [ ] Set aside 2-3 hours uninterrupted time
- [ ] Have production access ready (Coolify)
- [ ] Test with working feeds (not WJFF!)
- [ ] Monitor for 1 hour after deploy

---

## Questions to Answer Before Implementation

1. **Do you have access to Coolify scheduled tasks?**
   - Yes → Can configure cron easily
   - No → Use lazy scan only

2. **What's your preferred update frequency?**
   - 15 minutes → More current, more overhead
   - 30 minutes → Good balance (recommended)
   - 60 minutes → Less overhead, less current

3. **How critical is overnight updating?**
   - Very → Must use cron
   - Somewhat → Lazy scan is fine
   - Not critical → Browser auto-refresh sufficient

4. **Risk tolerance?**
   - Conservative → Cron only (simple)
   - Balanced → Hybrid (recommended)
   - Aggressive → Lazy scan only (no cron)

---

## Final Recommendation

**Go with Hybrid Approach (Solution 1)**

**Why:**
- Maximum reliability with redundancy
- Self-healing if any component fails
- Works 24/7 automatically
- Minimal risk (all changes are additive)
- Future-proof (handles growth and edge cases)

**Timeline:**
- Phase 1 (Verify cron): 30 minutes
- Phase 2 (Implement hybrid): 2-3 hours
- Phase 3 (Deploy & monitor): 30 minutes
- **Total: 3-4 hours**

**Expected Result:**
- ✅ Episodes appear within 15-30 minutes automatically
- ✅ No manual intervention ever needed
- ✅ Works even if cron fails (lazy scan backup)
- ✅ Works overnight (cron handles it)
- ✅ "Set it and forget it" reliability

---

**Status:** Implemented December 1, 2025 - Hybrid solution live and verified via cron + lazy-scan logs. 🎯

**Next Step:** Periodically monitor `logs/auto-scan.log` and `logs/lazy-scan.log` to ensure background updates continue running as expected.
