# Permission Issue - October 11, 2025

## 🚨 Problem
Same permission errors as before when trying to delete podcasts in production:

```
Warning: copy(/app/config/../data/backup/podcasts_2025-10-11_01-04-43.xml): Failed to open stream: Permission denied
Warning: DOMDocument::save(/app/config/../data/podcasts.xml): Failed to open stream: Permission denied
Warning: file_put_contents(/app/config/../logs/error.log): Failed to open stream: Permission denied
```

## 🔍 Root Cause
The items added locally have no images and are causing issues in production. The underlying problem is:

1. **Directories in production don't have correct permissions**
2. **PHP (running as www-data) cannot write to directories owned by root**
3. **This happens after every deployment/redeploy in Coolify**

## ✅ IMMEDIATE FIX (5 minutes)

### Step 1: Fix Permissions in Coolify Terminal

```bash
# Connect to Coolify terminal for your app
cd /app

# Verify current state
ls -la data uploads logs

# Fix ownership and permissions
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs

# Verify the fix
ls -la data uploads logs
# Should show: drwxr-xr-x www-data www-data
```

### Step 2: Test Immediately
- Try deleting a podcast
- Try adding a new podcast
- Check for permission errors

## 🔧 PERMANENT FIX (Already in place)

We already have a `Dockerfile` that should handle this, but Coolify may not be using it.

### Verify Dockerfile is Being Used

1. Check if `Dockerfile` exists in repo root ✅
2. Check Coolify build settings:
   - Go to Coolify Dashboard
   - Select your app
   - Go to "Build" or "Settings"
   - Verify "Build Pack" is set to "Dockerfile"
   - If not, change it and redeploy

## 📋 The Dockerfile Solution

Our `Dockerfile` already sets permissions correctly:

```dockerfile
FROM php:8.1-apache

# Install required extensions
RUN apt-get update && apt-get install -y \
    libxml2-dev \
    && docker-php-ext-install \
    dom \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Create and set permissions for required directories
RUN mkdir -p /var/www/html/data/backup \
    /var/www/html/uploads/covers \
    /var/www/html/logs && \
    chown -R www-data:www-data /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs && \
    chmod -R 755 /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs

WORKDIR /var/www/html
EXPOSE 80
```

**This sets permissions at BUILD TIME** so they persist across container restarts.

## 🎯 Why This Keeps Happening

### The Cycle:
1. Deploy/redeploy app in Coolify
2. New container created
3. Files owned by `root:root` with `755` permissions
4. PHP runs as `www-data` user
5. `www-data` cannot write to `root`-owned directories
6. All write operations fail

### The Solution:
- **Use Dockerfile** to set ownership/permissions at build time
- **OR** Run permission fix command after every deploy
- **OR** Use persistent volumes with correct permissions

## 🔄 Post-Deploy Checklist

After EVERY deployment, verify:

```bash
# In Coolify terminal
cd /app
ls -la data uploads logs

# If ownership is wrong (shows root:root), fix it:
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs
```

## 🎓 About Local Items Without Images

The items added locally without images are fine - they just don't have cover images. The real issue is the permission problem preventing ANY writes in production.

Once permissions are fixed:
- Deletes will work
- Adds will work
- Uploads will work
- RSS imports will work

## ⚡ Quick Command Reference

```bash
# Check current permissions
ls -la /app/data /app/uploads /app/logs

# Fix permissions (run this after every deploy if needed)
cd /app && chown -R www-data:www-data data uploads logs && chmod -R 755 data uploads logs

# Verify PHP user
ps aux | grep php

# Check if directories are writable
touch /app/data/test.txt && rm /app/data/test.txt && echo "✅ Writable" || echo "❌ Not writable"
```

## 📞 Status

- **Issue:** Permission denied on write operations
- **Cause:** Directories owned by root, PHP runs as www-data
- **Fix:** Run chown/chmod commands in Coolify terminal
- **Prevention:** Ensure Dockerfile is being used for builds
- **Time to Fix:** 2-5 minutes

---

**Next Action:** Run the permission fix commands in Coolify terminal NOW.
