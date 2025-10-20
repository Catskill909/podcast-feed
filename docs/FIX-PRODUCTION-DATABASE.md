# 🔧 Fix Production Database - Episode Dates Missing

## 🐛 The Problem

**Production shows:**
- Latest Episode = "Unknown" (all podcasts)
- Sort order wrong (falls back to created date)
- Feed shows: Air Line → AFT → AFGE → Labor Radio

**Local shows:**
- Latest Episode = "Today", "3 days ago", etc.
- Sort order correct
- Feed shows: Labor Radio → WJFF → 3rd & Fairfax → AFGE

**Root Cause:** Production database (`podcasts.xml`) doesn't have `latest_episode_date` fields populated!

---

## 🎯 The Solution

You have **3 options** to fix this:

### Option 1: Run Migration Script (Recommended - 1 minute)

**What it does:**
- Scans all podcast feeds
- Populates episode dates
- Updates database
- One-time run

**How to run:**

1. **Push the migration script:**
   ```bash
   git add migrate-episode-dates.php
   git commit -m "Add episode date migration script"
   git push origin main
   ```

2. **Wait for Coolify deploy** (2-3 min)

3. **Run in production:**
   - Coolify → Your App → Terminal
   - Run: `php /app/migrate-episode-dates.php`
   - Wait ~10-15 seconds
   - Done!

**Expected output:**
```
=== Episode Date Migration Script ===
Starting at: 2025-10-13 20:00:00

Found 4 podcasts to process

[1/4] Processing: Labor Radio-Podcast Weekly
  ✓ Updated: Latest episode = 2025-10-13 16:00:00
  ✓ Episode count = 156

[2/4] Processing: AFGE Y.O.U.N.G. Podcast
  ✓ Updated: Latest episode = 2024-10-28 16:49:00
  ✓ Episode count = 42

...

=== Migration Complete ===
Total: 4
Updated: 4
Failed: 0
```

---

### Option 2: Run Auto-Scan Script (Same thing, different name)

**In Coolify Terminal:**
```bash
php /app/cron/auto-scan-feeds.php
```

This does the same thing as the migration script!

---

### Option 3: Set Up Cron and Wait (30 minutes)

**In Coolify:**
1. Scheduled Tasks → Add
2. Command: `php /app/cron/auto-scan-feeds.php`
3. Schedule: `*/30 * * * *`
4. Wait 30 minutes for first run

---

## ✅ Verification

After running the migration:

### 1. Check Feed Order:
```bash
curl -s "https://podcast.supersoul.top/feed.php?sort=episodes&order=desc" | grep "<title>" | head -5
```

**Should show:**
```
<title>Available Podcasts Directory</title>
<title>Labor Radio-Podcast Weekly</title>
<title>WJFF - Radio Chatskill</title>
<title>3rd & Fairfax: The WGAW Podcast</title>
<title>AFGE Y.O.U.N.G. Podcast</title>
```

### 2. Check Admin Panel:
- Refresh browser (Cmd+Shift+R)
- "Latest Episode" column should show dates
- Recent episodes should be green
- Sort should work correctly

### 3. Test Sorting:
- Select "Newest Episodes" → Labor Radio first
- Select "Oldest Episodes" → AFGE first
- Click "View Feed" → Should match selection

---

## 🔍 Why This Happened

### Local vs Production:

| Aspect | Local | Production |
|--------|-------|------------|
| Database | Has episode dates | Missing episode dates |
| How populated | You ran scan manually | Never ran |
| Sort behavior | Uses episode dates | Falls back to created date |
| Result | Works correctly | Wrong order |

**The Issue:**
- `podcasts.xml` is in `.gitignore` (correct!)
- Data doesn't transfer between environments
- Production needs its own scan

---

## 📋 Complete Fix Steps

### Step 1: Push Migration Script
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
git add migrate-episode-dates.php
git commit -m "Add episode date migration for production"
git push origin main
```

### Step 2: Wait for Deploy
- Coolify auto-deploys (2-3 min)
- Check deployment status in Coolify

### Step 3: Run Migration
**In Coolify Terminal:**
```bash
php /app/migrate-episode-dates.php
```

### Step 4: Verify
1. Refresh browser
2. Check "Latest Episode" column
3. Test sorting
4. Click "View Feed"

### Step 5: Set Up Cron (Future Updates)
**In Coolify Scheduled Tasks:**
- Command: `php /app/cron/auto-scan-feeds.php`
- Schedule: `*/30 * * * *`
- Enabled: Yes

---

## 🎯 Expected Results

### Before Migration:
```
Latest Episode: Unknown, Unknown, Unknown, Unknown
Sort: Air Line → AFT → AFGE → Labor Radio (WRONG)
```

### After Migration:
```
Latest Episode: Today, Today, 3 days ago, Oct 28, 2024
Sort: Labor Radio → WJFF → 3rd & Fairfax → AFGE (CORRECT)
```

---

## 🐛 If Still Not Working

### Episode Dates Still "Unknown":
1. Check migration output for errors
2. Verify feeds are accessible from production
3. Check logs: `tail -f /app/logs/auto-scan.log`
4. Try manual refresh button on one podcast

### Sort Still Wrong:
1. Hard refresh browser (Cmd+Shift+R)
2. Check feed comment: `curl https://podcast.supersoul.top/feed.php | head -5`
3. Verify sort fix is deployed
4. Check browser console for errors

### Refresh Button Not Working:
1. Open browser console (F12)
2. Click refresh button
3. Look for API errors
4. Verify `/api/refresh-feed-metadata.php` exists

---

## 💡 Key Takeaway

**Database files don't deploy!**
- `podcasts.xml` is gitignored (correct)
- Each environment needs its own data
- Use migration scripts or cron to populate
- Set up automation for ongoing updates

---

## ✅ Final Checklist

- [ ] Push migration script to Git
- [ ] Wait for Coolify deploy
- [ ] Run migration in production terminal
- [ ] Verify episode dates appear
- [ ] Test sort order (newest/oldest)
- [ ] Set up cron for future updates
- [ ] Delete migration script (optional)

**Estimated time:** 5-10 minutes total

---

**Status**: Ready to fix  
**Priority**: High  
**Complexity**: Low (just run one command!)
