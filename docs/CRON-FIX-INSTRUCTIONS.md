# Cron Job Fix Instructions

## The Problem
You have a cron job running every 30 minutes that's trying to write to `logs/auto-scan.log` but failing due to macOS Desktop folder permissions. This generates 372 error emails.

## The Cron Job
```bash
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
```

**Error:** `/bin/sh: logs/auto-scan.log: Operation not permitted`

---

## Quick Fix (2 Steps)

### Step 1: Clear the Mail
In Terminal, type these commands in the `mail` program:
```
d *
q
```
This deletes all 372 messages and quits.

### Step 2: Remove the Cron Job
Since this is your **LOCAL development environment** (not production), you should remove this cron job. It's meant for production servers, not local dev.

In Terminal, type:
```bash
crontab -e
```

This opens your crontab file. Delete the entire line:
```
*/30 * * * * cd /Users/paulhenshaw/Desktop/podcast-feed && php cron/auto-scan-feeds.php >> logs/auto-scan.log 2>&1
```

Save and exit:
- If using **nano**: Press `Ctrl+X`, then `Y`, then `Enter`
- If using **vi/vim**: Press `Esc`, type `:wq`, press `Enter`

---

## Why This Happened

**Local vs Production:**
- **Production (Coolify)**: Cron jobs run automatically to keep feeds fresh
- **Local (Your Mac)**: You manually test features, don't need auto-updates

**The Permission Error:**
macOS security (TCC) prevents background processes like cron from writing to Desktop folders without explicit permission.

---

## What About Production?

**Don't worry!** This fix only affects your local Mac. Your production environment on Coolify is separate and unaffected. The cron job there runs perfectly because:
1. It's not on the Desktop (no TCC restrictions)
2. Proper permissions are set in the container
3. It's designed to run in production

---

## Verification

After removing the cron job, you can verify it's gone:
```bash
crontab -l
```

Should show: `crontab: no crontab for paulhenshaw` (or be empty)

No more mail messages! ✅
