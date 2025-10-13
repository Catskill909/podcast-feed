# 🚀 Deploy Now - Quick Action Plan

## 3 Simple Steps to Fix Everything

---

## Step 1: Push Code Changes (2 minutes)

We fixed 2 bugs today:
1. ✅ **Edit button selector** - Now works with new Feed URL button
2. ✅ **Sort order reversed** - Newest/Oldest now correct

**Run these commands:**
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed

git add .
git commit -m "Fix edit button selector and sort order for production"
git push origin main
```

**What happens:**
- Coolify detects the push
- Auto-deploys in 2-3 minutes
- Edit button will work
- Sort order will be correct

---

## Step 2: Set Up Cron Job in Coolify (3 minutes)

This will populate the "Latest Episode" dates automatically.

### In Coolify Dashboard:

1. **Go to your app**: `podcast.supersoul.top`
2. **Click "Scheduled Tasks"** (in left sidebar)
3. **Click "Add Scheduled Task"**
4. **Fill in:**
   - **Name**: Auto-scan podcast feeds
   - **Command**: `php /app/cron/auto-scan-feeds.php`
   - **Schedule**: `*/30 * * * *`
   - **Enabled**: ✅ Yes
5. **Click "Save"**

**What happens:**
- Cron runs every 30 minutes
- Fetches latest episode dates from all feeds
- Updates database
- "Latest Episode" column shows dates instead of "Unknown"

---

## Step 3: Run Initial Scan (2 options)

### Option A: Wait 30 Minutes (Easy)
- Cron will run automatically
- Episode dates will appear
- No action needed

### Option B: Manual Run (Faster - 2 minutes)

**In Coolify:**
1. Go to your app
2. Click "Terminal" or "Console"
3. Run:
   ```bash
   php /app/cron/auto-scan-feeds.php
   ```
4. Wait ~10 seconds (scans all feeds)
5. Refresh browser
6. Episode dates should appear!

**Or via SSH:**
```bash
# If you have SSH access
ssh your-server
cd /path/to/app
php cron/auto-scan-feeds.php
```

---

## ✅ Verification Checklist

After completing all steps, verify:

### 1. Edit Button Works:
- [ ] Click ✏️ edit button on any podcast
- [ ] Modal opens with podcast data
- [ ] No "Error loading podcast data" message

### 2. Sort Order Correct:
- [ ] Select "Newest Episodes" → Labor Radio first
- [ ] Select "Oldest Episodes" → AFGE first
- [ ] Click "View Feed" → Feed matches selection

### 3. Latest Episode Shows Dates:
- [ ] "Latest Episode" column shows dates (not "Unknown")
- [ ] Recent episodes show green text
- [ ] Old episodes show gray text

### 4. Refresh Button Works:
- [ ] Click 🔄 on any podcast
- [ ] Button shows spinner
- [ ] Success message appears
- [ ] Episode date updates

---

## 🎯 Expected Timeline

| Step | Time | Status |
|------|------|--------|
| Push code | 1 min | ⏳ Ready |
| Coolify deploy | 2-3 min | ⏳ Auto |
| Set up cron | 3 min | ⏳ Manual |
| Run initial scan | 2 min | ⏳ Manual |
| **Total** | **~8 minutes** | |

---

## 🐛 If Something Goes Wrong

### Edit Button Still Broken:
1. Check browser console (F12) for errors
2. Hard refresh (Cmd+Shift+R)
3. Verify deployment completed in Coolify

### Latest Episode Still "Unknown":
1. Check if cron ran: Look for "Scheduled Tasks" logs in Coolify
2. Manually run: `php /app/cron/auto-scan-feeds.php`
3. Check logs: `tail -f /app/logs/auto-scan.log`

### Refresh Button Does Nothing:
1. Open browser console (F12)
2. Click refresh button
3. Look for errors (404, 500, CORS)
4. Verify `/api/refresh-feed-metadata.php` exists

---

## 📝 Commands Summary

### Deploy:
```bash
git add .
git commit -m "Fix edit button and sort order"
git push origin main
```

### Manual Scan (in Coolify terminal):
```bash
php /app/cron/auto-scan-feeds.php
```

### Check Logs:
```bash
tail -f /app/logs/auto-scan.log
```

### Test API:
```bash
curl -X POST https://podcast.supersoul.top/api/refresh-feed-metadata.php \
  -d "podcast_id=YOUR_ID"
```

---

## 🎉 After Everything Works

You'll have:
- ✅ Edit button working perfectly
- ✅ Sort order correct (newest/oldest)
- ✅ Episode dates auto-updating every 30 min
- ✅ Refresh button manually updating any podcast
- ✅ Green highlights for recent episodes
- ✅ Fully automated system

**No more manual work needed!**

---

## 🚀 Ready?

**Start with Step 1:** Push the code changes!

```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
git add .
git commit -m "Fix edit button selector and sort order for production"
git push origin main
```

Then move to Step 2 (Coolify cron setup) while deployment happens!
