# Automated Feed Scanning Setup Guide

## 🎯 Overview

Your podcast directory now has **automated feed scanning** that runs periodically to keep all podcast episode dates up-to-date. When new episodes are published, your feed automatically reflects the changes - **no manual refresh required!**

---

## 🔄 How It Works

```
Every 30 minutes (configurable):
1. Scanner fetches all podcasts from database
2. Checks each podcast's RSS feed for latest episode
3. Compares with stored data
4. Updates if changes detected
5. Logs results
6. Your feed.php automatically shows updated sort order
```

**Result**: Your podcast app always shows the freshest content first!

---

## 📋 Setup Options

### Option 1: Cron Job (Recommended for Production)

#### For Linux/Mac Servers:

1. **Open crontab editor:**
   ```bash
   crontab -e
   ```

2. **Add one of these lines:**

   **Every 30 minutes:**
   ```bash
   */30 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

   **Every hour:**
   ```bash
   0 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

   **Every 15 minutes (aggressive):**
   ```bash
   */15 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

   **Twice per day (conservative):**
   ```bash
   0 6,18 * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

3. **Replace `/path/to/podcast-feed`** with your actual path:
   ```bash
   */30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
   ```

4. **Save and exit** (in vi: press `Esc`, type `:wq`, press Enter)

5. **Verify cron is set:**
   ```bash
   crontab -l
   ```

#### For Coolify/Docker Deployments:

Add to your deployment configuration:

```yaml
# In your docker-compose.yml or Coolify settings
services:
  cron:
    image: your-app-image
    command: |
      sh -c "
        while true; do
          php /app/cron/auto-scan-feeds.php
          sleep 1800
        done
      "
```

Or use Coolify's built-in cron feature:
1. Go to your app settings
2. Add scheduled task
3. Command: `php /app/cron/auto-scan-feeds.php`
4. Schedule: `*/30 * * * *`

---

### Option 2: Manual Testing (Development)

Run the scanner manually to test:

```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
php cron/auto-scan-feeds.php
```

**Output:**
```
========================================
Auto-Scan Started: 2025-01-13 14:30:00
========================================
Found 3 podcasts to scan
[1/3] Processing: Labor Radio-Podcast Weekly
  ✓ Updated - Latest episode: 2025-01-10 12:00:00, Episodes: 156
[2/3] Processing: Tech Talk Daily
  → No changes detected
[3/3] Processing: News Hour
  ✓ Updated - Latest episode: 2025-01-13 08:00:00, Episodes: 423
========================================
Auto-Scan Completed
========================================
Total Podcasts: 3
Updated: 2
No Changes: 1
Failed: 0
Execution Time: 8.45s
========================================
```

---

### Option 3: System Service (Advanced)

Create a systemd service for continuous monitoring:

**File: `/etc/systemd/system/podcast-scanner.service`**
```ini
[Unit]
Description=Podcast Feed Auto Scanner
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/podcast-feed
ExecStart=/usr/bin/php /path/to/podcast-feed/cron/auto-scan-feeds.php
Restart=always
RestartSec=1800

[Install]
WantedBy=multi-user.target
```

Enable and start:
```bash
sudo systemctl enable podcast-scanner
sudo systemctl start podcast-scanner
sudo systemctl status podcast-scanner
```

---

## 📊 Monitoring & Logs

### View Logs:
```bash
tail -f logs/auto-scan.log
```

### Check Last Scan Time:
```bash
cat data/last-scan.txt
```

### Log Location:
- **Main log**: `logs/auto-scan.log`
- **Last scan timestamp**: `data/last-scan.txt`

### Log Rotation (Recommended):

Create `/etc/logrotate.d/podcast-feed`:
```
/path/to/podcast-feed/logs/auto-scan.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
}
```

---

## ⚙️ Configuration

Edit `cron/auto-scan-feeds.php` to customize:

```php
$config = [
    'log_file' => __DIR__ . '/../logs/auto-scan.log',
    'max_execution_time' => 300, // 5 minutes
    'delay_between_feeds' => 2, // seconds between each feed fetch
];
```

**Settings:**
- `max_execution_time`: Maximum script runtime (seconds)
- `delay_between_feeds`: Delay between fetching each feed (be nice to servers!)

---

## 🎯 Recommended Scan Intervals

| Interval | Use Case | Pros | Cons |
|----------|----------|------|------|
| **15 min** | High-traffic news podcasts | Very fresh data | Higher server load |
| **30 min** | **Recommended** | Good balance | Balanced |
| **1 hour** | Standard podcasts | Lower load | Slight delay |
| **6 hours** | Low-priority feeds | Minimal load | Less fresh |

**Our Recommendation**: **30 minutes** - Perfect balance for most podcast apps

---

## 🔍 Verification

### Test the Scanner:
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
php cron/auto-scan-feeds.php
```

### Check if Cron is Running:
```bash
# View cron jobs
crontab -l

# Check cron logs (Mac)
log show --predicate 'process == "cron"' --last 1h

# Check cron logs (Linux)
grep CRON /var/log/syslog
```

### Verify Feed Updates:
1. Note current episode dates in your admin panel
2. Wait for cron to run (or run manually)
3. Refresh admin panel
4. Check if dates updated
5. Check `feed.php` - should show new sort order

---

## 🚀 Production Deployment

### Coolify Deployment:

1. **Add cron to your Dockerfile:**
   ```dockerfile
   FROM php:8.2-apache
   
   # Install cron
   RUN apt-get update && apt-get install -y cron
   
   # Copy app files
   COPY . /var/www/html
   
   # Setup cron
   RUN echo "*/30 * * * * cd /var/www/html && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1" | crontab -
   
   # Start cron in background
   CMD cron && apache2-foreground
   ```

2. **Or use Coolify's scheduled tasks:**
   - Navigate to your app in Coolify
   - Go to "Scheduled Tasks"
   - Add new task:
     - **Command**: `php /app/cron/auto-scan-feeds.php`
     - **Schedule**: `*/30 * * * *`
     - **Enabled**: Yes

### Environment Variables:

Add to your `.env` or Coolify settings:
```bash
SCAN_INTERVAL=30  # minutes
SCAN_DELAY=2      # seconds between feeds
```

---

## 📈 Performance Considerations

### For 10 Podcasts:
- Scan time: ~30 seconds
- Server load: Minimal
- Recommended interval: 30 minutes

### For 100 Podcasts:
- Scan time: ~5 minutes
- Server load: Moderate
- Recommended interval: 1 hour

### For 1000+ Podcasts:
- Scan time: ~30-60 minutes
- Server load: High
- Recommended: Batch processing, queue system
- Consider: Separate worker server

### Optimization Tips:
1. Increase `delay_between_feeds` for large catalogs
2. Run during off-peak hours
3. Use queue system for 500+ podcasts
4. Cache feed responses
5. Implement exponential backoff for failed feeds

---

## 🐛 Troubleshooting

### Cron Not Running?

**Check cron service:**
```bash
# Mac
sudo launchctl list | grep cron

# Linux
sudo systemctl status cron
```

**Check permissions:**
```bash
ls -la cron/auto-scan-feeds.php
# Should be executable: -rwxr-xr-x
```

**Test manually:**
```bash
php cron/auto-scan-feeds.php
# Should output scan results
```

### No Updates Happening?

**Check logs:**
```bash
tail -50 logs/auto-scan.log
```

**Common issues:**
- PHP path incorrect in cron
- Working directory not set
- Permissions issues
- Feed URLs unreachable

**Debug mode:**
```bash
# Run with verbose output
php cron/auto-scan-feeds.php 2>&1 | tee debug.log
```

### High Server Load?

**Increase delay:**
```php
'delay_between_feeds' => 5, // Increase from 2 to 5 seconds
```

**Reduce frequency:**
```bash
# Change from every 30 min to every hour
0 * * * * cd /path/to/podcast-feed && php cron/auto-scan-feeds.php
```

---

## 🎉 Benefits

✅ **Automatic Updates**: No manual refresh needed  
✅ **Always Fresh**: Feed shows latest episodes  
✅ **Scalable**: Handles hundreds of podcasts  
✅ **Reliable**: Logs all activity  
✅ **Efficient**: Only updates when changes detected  
✅ **Configurable**: Adjust scan frequency  
✅ **Production-Ready**: Designed for 24/7 operation  

---

## 📝 Quick Start Checklist

- [ ] Script is executable: `chmod +x cron/auto-scan-feeds.php`
- [ ] Test manually: `php cron/auto-scan-feeds.php`
- [ ] Check output and logs
- [ ] Set up cron job with desired interval
- [ ] Verify cron is running: `crontab -l`
- [ ] Monitor logs: `tail -f logs/auto-scan.log`
- [ ] Check `data/last-scan.txt` for last run time
- [ ] Verify feed updates in admin panel
- [ ] Test `feed.php` shows updated sort order

---

## 🔗 Integration with Sort Feature

**How it works together:**

1. **Scanner runs** (every 30 min)
2. **Updates episode dates** in database
3. **User visits feed.php** with `?sort=episodes&order=desc`
4. **Backend sorts** by latest_episode_date
5. **RSS feed** shows podcasts with newest episodes first
6. **Podcast app** receives updated feed
7. **No manual refresh** required!

**Perfect for:**
- Podcast aggregator apps
- News podcast feeds
- Daily show feeds
- Any feed where freshness matters

---

**Status**: ✅ Ready to Deploy  
**Recommended Interval**: 30 minutes  
**Next Step**: Set up your cron job!
