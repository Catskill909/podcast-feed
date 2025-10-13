# 🚀 Quick Deploy Guide

## 3 Steps to Production

### 1. Push to Git
```bash
git add .
git commit -m "Add automated scanning and sorting"
git push origin main
```

### 2. Coolify Auto-Deploys
✅ Automatic - just wait 2 minutes

### 3. Add Cron Job
In Coolify → Your App → Scheduled Tasks:
- **Command**: `php /app/cron/auto-scan-feeds.php`
- **Schedule**: `*/30 * * * *`
- **Enabled**: Yes

## Done! 🎉

Your feed will now:
- ✅ Update episode dates every 30 min
- ✅ Sort by latest episodes automatically
- ✅ Work with any podcast app
- ✅ Require zero maintenance

## Feed URL
```
https://podcast.supersoul.top/feed.php
```

## Verify
```bash
curl https://podcast.supersoul.top/feed.php | head -10
```

Should see: `<!-- Sorted by: episodes, Order: desc -->`

---

**That's it!** Everything else is automated.
