# Production Issues - Troubleshooting Checklist

## 🔍 Issues Reported

1. ✅ **Edit button error** - "Error loading podcast data"
2. ✅ **Latest Episode shows "Unknown"** - No episode dates
3. ✅ **Refresh button does nothing** - No response

**All work locally!** = Environment/deployment issue

---

## 🎯 Root Causes & Fixes

### 1. Edit Button Error ✅ FIXED

**Cause:** Code change (Feed URL → Button) broke the selector

**Fix Applied:**
```javascript
// OLD (broken):
const feedUrl = row.querySelector('td:nth-child(3) a').textContent.trim();

// NEW (fixed):
const feedButton = row.querySelector('td:nth-child(3) button');
const onclickAttr = feedButton.getAttribute('onclick');
const feedUrl = onclickAttr.match(/showPodcastFeedModal\('([^']+)'/)[1];
```

**Action:** Push to Git → Coolify deploys

---

### 2. Latest Episode = "Unknown" ⚠️ CRON NOT RUNNING

**Cause:** Cron job not set up in production

**Why it works locally:**
- You manually ran: `php cron/auto-scan-feeds.php`
- Data was populated in local database

**Why it fails in production:**
- Cron job not configured in Coolify
- Database has no episode dates
- Shows "Unknown" for all podcasts

**Fix:** Set up cron in Coolify

#### Option A: Coolify Scheduled Tasks (Easiest)
1. Go to your app in Coolify
2. Click "Scheduled Tasks"
3. Add new task:
   - **Command**: `php /app/cron/auto-scan-feeds.php`
   - **Schedule**: `*/30 * * * *` (every 30 minutes)
   - **Enabled**: Yes
4. Save

#### Option B: Manual Run (Temporary)
SSH into container and run:
```bash
php /app/cron/auto-scan-feeds.php
```

---

### 3. Refresh Button Does Nothing ⚠️ NEEDS TESTING

**Possible causes:**
1. API endpoint not accessible
2. CORS issue
3. Path issue in production

**Test in production:**
1. Open browser console (F12)
2. Click refresh button
3. Check for errors

**Expected behavior:**
- Button shows spinner
- Calls `/api/refresh-feed-metadata.php`
- Updates episode date
- Re-sorts table

**If it fails:**
- Check console for 404 or 500 errors
- Verify `/api/refresh-feed-metadata.php` exists in production
- Check file permissions

---

## 🚀 Deployment Steps

### Step 1: Push Code Fix (Edit Button)
```bash
git add .
git commit -m "Fix edit button selector for new Feed URL button"
git push origin main
```

### Step 2: Wait for Coolify Deploy
- Coolify auto-deploys (2-3 minutes)
- Verify edit button works

### Step 3: Set Up Cron Job
**In Coolify Dashboard:**
1. Your App → Scheduled Tasks
2. Add: `php /app/cron/auto-scan-feeds.php`
3. Schedule: `*/30 * * * *`
4. Enable

### Step 4: Run Initial Scan
**Option A: Wait 30 minutes** for first cron run

**Option B: Manual trigger** (faster):
1. SSH into container
2. Run: `php /app/cron/auto-scan-feeds.php`
3. Refresh browser
4. Should see episode dates

---

## 🧪 Verification

### After Deployment:

1. **Edit Button:**
   - Click edit (✏️) on any podcast
   - Should open edit modal with data
   - No error message

2. **Latest Episode:**
   - After cron runs (or manual run)
   - Should show dates instead of "Unknown"
   - Recent episodes show green

3. **Refresh Button:**
   - Click 🔄 on any podcast
   - Should show spinner
   - Should update episode date
   - Should show success message

---

## 📊 Current Status

| Issue | Status | Action Needed |
|-------|--------|---------------|
| Edit Button | ✅ Fixed in code | Push to Git |
| Latest Episode | ⚠️ Cron not running | Set up in Coolify |
| Refresh Button | ❓ Needs testing | Test after deploy |

---

## 🔧 Quick Fix Commands

### Test API Endpoint (from production):
```bash
curl -X POST https://podcast.supersoul.top/api/refresh-feed-metadata.php \
  -d "podcast_id=YOUR_PODCAST_ID"
```

### Manual Cron Run (SSH into container):
```bash
php /app/cron/auto-scan-feeds.php
```

### Check Logs:
```bash
tail -f /app/logs/auto-scan.log
```

---

## 💡 Why This Happened

### Local vs Production Differences:

| Aspect | Local | Production |
|--------|-------|------------|
| Cron | Manual run | Not configured |
| Data | Populated | Empty |
| Code | Latest | Old version |
| Testing | Easy | Requires deploy |

**Key Lesson:** Always set up automation in production after deployment!

---

## ✅ Next Steps

1. **Push code fix** for edit button
2. **Set up cron** in Coolify (5 minutes)
3. **Run initial scan** manually or wait 30 min
4. **Test all features** in production
5. **Verify** episode dates appear

**Estimated time:** 10-15 minutes total

---

**Status**: Ready to fix  
**Priority**: High (edit button), Medium (episode dates)  
**Complexity**: Low (configuration, not code)
