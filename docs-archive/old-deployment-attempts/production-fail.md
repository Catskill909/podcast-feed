# Production Deployment Failure - Root Cause Analysis

**Date:** 2025-10-10  
**Severity:** CRITICAL  
**Status:** App works locally, fails in production (Coolify/Docker)

---

## 🚨 Current Errors in Production

```
Warning: unlink(/app/config/../uploads/covers/...jpg): Permission denied
Warning: copy(/app/config/../data/backup/...xml): Failed to open stream: Permission denied
Warning: DOMDocument::save(/app/config/../data/podcasts.xml): Failed to open stream: Permission denied
Warning: file_put_contents(/app/config/../logs/error.log): Failed to open stream: Permission denied
```

**Translation:** The app CANNOT write to ANY directory in production.

---

## 🔍 Root Cause Analysis

### **What Changed Recently:**

1. **Added Help Modal** - UI only, no file system changes ✅ SAFE
2. **Changed emoji icons to Font Awesome** - UI only ✅ SAFE
3. **Fixed RSS image import bug** - Added `rss_image_url` to POST data ✅ SAFE
4. **Fixed iTunes health check** - SimpleXML parsing fix ✅ SAFE
5. **Updated documentation** - Markdown files only ✅ SAFE

### **What Changed for Deployment:**

6. **Modified `config/config.php`** ❌ THIS IS THE PROBLEM
   - Changed `mkdir()` permissions from `0755` to `0777`
   - Added `chmod()` calls to existing directories
   - Enhanced HTTPS detection
   - Added `.gitkeep` files

---

## 🎯 The Real Problem

### **Before (Working):**
```php
// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
```

**This worked because:**
- Directories already existed in Git repo
- They had correct permissions from Git checkout
- No chmod() calls interfering

### **After (Broken):**
```php
// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            if (!is_dir($dir)) {
                error_log("Failed to create directory: $dir");
            }
        } else {
            @chmod($dir, 0777);
        }
    } else {
        // ❌ THIS IS THE PROBLEM
        @chmod($dir, 0777);  // Tries to chmod existing dirs, fails silently
    }
}
```

**This breaks because:**
- Docker user doesn't have permission to chmod existing directories
- `@chmod()` fails silently but doesn't actually change permissions
- Directories remain with wrong ownership/permissions
- App can't write to them

---

## 🐳 Docker/Coolify Environment Reality

### **How Coolify Deploys:**

1. **Git Clone:** Pulls repo from GitHub
2. **File Ownership:** All files owned by `root` or `www-data`
3. **Directory Permissions:** Set by Git checkout (usually 755)
4. **PHP Process:** Runs as `www-data` user
5. **Write Access:** Needs directories owned by `www-data` with 755+ permissions

### **The Permission Matrix:**

| Directory | Owner | Group | Permissions | PHP Can Write? |
|-----------|-------|-------|-------------|----------------|
| `data/` | root | root | 755 | ❌ NO |
| `data/` | www-data | www-data | 755 | ✅ YES |
| `data/` | root | www-data | 775 | ✅ YES (group write) |
| `data/` | root | root | 777 | ✅ YES (world write) |

**Current State in Coolify:**
- Directories owned by: `root:root`
- Permissions: `755`
- PHP runs as: `www-data`
- Result: ❌ **Cannot write**

---

## 🔧 Why It Works Locally

### **Local Environment (macOS/VSCode):**
- PHP runs as your user (paulhenshaw)
- Directories owned by your user
- Your user has full write access
- Permissions don't matter

### **Production Environment (Docker/Coolify):**
- PHP runs as `www-data` user
- Directories owned by `root` or deployment user
- Strict permission enforcement
- Permissions CRITICAL

---

## ❌ What We Did Wrong

### **Mistake 1: Assumed chmod() Would Work**
```php
@chmod($dir, 0777);  // Silently fails if not owner
```
- PHP can't chmod directories it doesn't own
- `@` suppresses the error
- We thought it worked but it didn't

### **Mistake 2: Changed Working Code**
- Original code worked fine
- Directories existed in Git
- We "fixed" a problem that didn't exist

### **Mistake 3: Didn't Test in Production-Like Environment**
- Tested locally (works)
- Pushed to production (breaks)
- No staging environment to catch this

---

## ✅ The Solution

### **Option 1: Revert config.php Changes (RECOMMENDED)**

**Restore original working code:**
```php
// Create required directories if they don't exist
$dirs = [DATA_DIR, UPLOADS_DIR, COVERS_DIR, LOGS_DIR, BACKUP_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}
```

**Then fix permissions in Coolify ONE TIME:**
```bash
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs
```

### **Option 2: Use Dockerfile (PROPER SOLUTION)**

Create `Dockerfile`:
```dockerfile
FROM php:8.1-apache

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache modules
RUN a2enmod rewrite

# Copy application
COPY . /var/www/html/

# Set correct ownership and permissions
RUN chown -R www-data:www-data /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs && \
    chmod -R 755 /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs

WORKDIR /var/www/html
EXPOSE 80
```

### **Option 3: Use .htaccess or Startup Script**

Create `startup.sh`:
```bash
#!/bin/bash
chown -R www-data:www-data /app/data /app/uploads /app/logs
chmod -R 755 /app/data /app/uploads /app/logs
exec apache2-foreground
```

---

## 📋 Ground Plan to Restore Order

### **Phase 1: Immediate Rollback** ⏱️ 5 minutes

1. **Revert config.php to working version**
   ```bash
   git log --oneline config/config.php
   git show <commit-hash>:config/config.php > config/config.php
   ```

2. **Remove problematic changes**
   - Remove chmod() calls
   - Keep HTTPS detection fix (that's good)
   - Keep .gitkeep files (they're harmless)

3. **Commit and push**
   ```bash
   git add config/config.php
   git commit -m "Revert: Remove chmod() calls that break production"
   git push origin main
   ```

### **Phase 2: Fix Permissions in Coolify** ⏱️ 2 minutes

1. **Open Coolify Terminal**
2. **Run these commands:**
   ```bash
   cd /app
   chown -R www-data:www-data data uploads logs
   chmod -R 755 data uploads logs
   ```

3. **Verify:**
   ```bash
   ls -la data uploads logs
   # Should show: drwxr-xr-x www-data www-data
   ```

### **Phase 3: Test Functionality** ⏱️ 10 minutes

1. **Test Delete:**
   - Delete a podcast
   - Should work without errors

2. **Test Add:**
   - Add new podcast manually
   - Upload image
   - Should save successfully

3. **Test RSS Import:**
   - Import from RSS feed
   - Image should download
   - Should save successfully

4. **Test Health Check:**
   - Run health check
   - Should complete without errors

5. **Check Logs:**
   - Verify logs/error.log is being written
   - No permission errors

### **Phase 4: Permanent Solution** ⏱️ 30 minutes

1. **Create Dockerfile** (see Option 2 above)
2. **Add to Git:**
   ```bash
   git add Dockerfile
   git commit -m "Add Dockerfile with proper permissions"
   git push origin main
   ```

3. **Configure Coolify to use Dockerfile**
   - Update build settings
   - Redeploy

4. **Test again** (all functions)

### **Phase 5: Documentation** ⏱️ 15 minutes

1. **Update DEPLOYMENT-CHECKLIST.md**
   - Add permission requirements
   - Add Dockerfile instructions
   - Add troubleshooting steps

2. **Update COOLIFY-DEPLOYMENT.md**
   - Mark chmod() approach as WRONG
   - Recommend Dockerfile approach
   - Add permission verification steps

3. **Create PRODUCTION-TESTING.md**
   - Checklist for testing before deploy
   - Local vs Production differences
   - Common pitfalls

---

## 🎓 Lessons Learned

### **1. Don't Fix What Isn't Broken**
- Original code worked fine
- We "improved" it and broke it
- If it works, leave it alone

### **2. Understand the Environment**
- Local ≠ Production
- Docker has different permission model
- Test in production-like environment

### **3. chmod() in PHP is Dangerous**
- Only works if you own the file
- Fails silently with `@`
- Don't use in production code

### **4. Use Proper Deployment Tools**
- Dockerfile sets permissions correctly
- One-time setup, works forever
- No runtime permission changes needed

### **5. Test Before Deploy**
- Create staging environment
- Test all CRUD operations
- Check file permissions
- Verify logs

---

## 🔒 Best Practices Going Forward

### **1. Never Modify Permissions at Runtime**
```php
// ❌ BAD - Don't do this
@chmod($dir, 0777);

// ✅ GOOD - Let deployment handle it
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}
```

### **2. Use Dockerfile for Production**
- Set ownership: `chown -R www-data:www-data`
- Set permissions: `chmod -R 755`
- Done once, works forever

### **3. Keep Config Simple**
- Auto-detect environment ✅
- Auto-detect HTTPS ✅
- Don't try to fix permissions ❌

### **4. Test in Production-Like Environment**
- Use Docker locally
- Match production setup
- Catch issues before deploy

### **5. Have Rollback Plan**
- Keep Git history clean
- Tag working versions
- Can revert quickly

---

## 📊 File Changes Audit

### **Files Modified (Recent Session):**

| File | Change | Impact | Safe? |
|------|--------|--------|-------|
| `index.php` | Help button, FA icons | UI only | ✅ YES |
| `config/config.php` | chmod() calls | BREAKS PRODUCTION | ❌ NO |
| `config/config.php` | HTTPS detection | Fixes mixed content | ✅ YES |
| `assets/css/components.css` | Help modal styles | UI only | ✅ YES |
| `assets/js/app.js` | Help modal functions | UI only | ✅ YES |
| `includes/PodcastHealthChecker.php` | iTunes image fix | Logic fix | ✅ YES |
| `.gitkeep` files | Directory structure | Harmless | ✅ YES |

### **What to Keep:**
- ✅ HTTPS detection improvements
- ✅ Help modal (all UI changes)
- ✅ Font Awesome icons
- ✅ iTunes health check fix
- ✅ RSS image import fix
- ✅ .gitkeep files

### **What to Revert:**
- ❌ chmod() calls in config.php
- ❌ 0777 permissions (use 0755)
- ❌ Runtime permission changes

---

## 🚀 Immediate Action Items

### **RIGHT NOW (Do This First):**

1. **Revert config.php chmod() changes**
2. **Push to GitHub**
3. **Fix permissions in Coolify terminal**
4. **Test all functions**

### **THEN (Within 1 Hour):**

5. **Create Dockerfile**
6. **Update documentation**
7. **Test deployment with Dockerfile**

### **FINALLY (Before Next Feature):**

8. **Set up staging environment**
9. **Create production testing checklist**
10. **Document deployment process**

---

## ✅ Success Criteria

Deployment is successful when:

- ✅ Can add podcasts manually
- ✅ Can import from RSS
- ✅ Can upload images
- ✅ Can delete podcasts (removes files)
- ✅ Can edit podcasts
- ✅ Health checks work
- ✅ Logs are written
- ✅ Backups are created
- ✅ No permission errors in logs
- ✅ All images load via HTTPS

---

## 📞 Emergency Rollback

If deployment fails completely:

```bash
# Find last working commit
git log --oneline

# Revert to it
git reset --hard <commit-hash>

# Force push (ONLY in emergency)
git push origin main --force

# Fix permissions in Coolify
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs
```

---

**Status:** READY TO FIX  
**Priority:** CRITICAL  
**Estimated Fix Time:** 15 minutes (revert + permissions)  
**Estimated Full Solution:** 1 hour (with Dockerfile)

---

**Next Steps:**
1. Read this document
2. Approve rollback plan
3. Execute Phase 1 (revert)
4. Execute Phase 2 (fix permissions)
5. Test thoroughly
6. Implement Phase 4 (Dockerfile)
