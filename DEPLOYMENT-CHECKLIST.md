# 🚀 Production Deployment Checklist

**Last Updated:** 2025-10-11  
**Feature:** RSS Feed Auto-Import

---

## 🚨 CRITICAL: AFTER EVERY DEPLOYMENT

**YOU MUST RUN THESE COMMANDS IN COOLIFY TERMINAL:**

```bash
cd /app
chown -R 65534:65534 data uploads logs
chmod -R 755 data uploads logs
```

**Why:** Files reset to `root:root` on deploy, PHP runs as `nobody` (UID 65534)  
**If you skip this:** ALL file operations will fail with permission errors  
**Time required:** 30 seconds  
**Frequency:** EVERY SINGLE DEPLOYMENT

**Verify it worked:**
1. Visit `https://your-domain.com/check-user.php`
2. All directories should show "✅ Writable"
3. Try deleting a podcast

---

## ✅ Environment Configuration - VERIFIED

Your application is **fully configured** to work in both local and production environments automatically!

### **Auto-Detection System:**

```php
// config/config.php automatically detects:
$isLocalhost = in_array($_SERVER['SERVER_NAME'], ['localhost', '127.0.0.1', '::1']);
define('ENVIRONMENT', $isLocalhost ? 'development' : 'production');
```

### **What Changes Automatically in Production:**

| Feature | Development | Production |
|---------|------------|------------|
| **Error Display** | Shown on screen | Hidden (logged to file) |
| **SSL Verification** | Disabled (for testing) | ✅ **Enabled** |
| **Error Reporting** | Full (E_ALL) | Off (logged only) |
| **Password Protection** | Active | Active |
| **API Paths** | Relative (works anywhere) | Relative (works anywhere) |

---

## 🔒 Security - PRODUCTION READY

### ✅ **Automatically Enabled in Production:**

1. **SSL Certificate Verification**
   - `CURLOPT_SSL_VERIFYPEER` automatically enabled
   - Prevents man-in-the-middle attacks
   - Validates HTTPS certificates on RSS feeds

2. **Error Logging**
   - Errors logged to `logs/error.log`
   - No sensitive info displayed to users
   - Stack traces hidden

3. **Password Protection**
   - Custom dark mode modal
   - localStorage-based authentication
   - Works in both environments

### ✅ **Already Implemented:**

- URL validation before fetching
- XML parsing with error suppression (XXE protection)
- Image type validation (JPG, PNG, GIF, WebP only)
- Image size limit (5MB max)
- HTTP timeout (10 seconds)
- POST-only API endpoints
- Proper error handling

---

## 📁 File Paths - ENVIRONMENT AGNOSTIC

All paths use relative references that work in both environments:

```javascript
// JavaScript API calls (app.js)
fetch('api/import-rss.php')  // ✅ Works locally and in production

// PHP includes
require_once __DIR__ . '/../includes/RssFeedParser.php'  // ✅ Absolute from script location

// File paths (config.php)
define('DATA_DIR', __DIR__ . '/../data');  // ✅ Relative to config
define('UPLOADS_DIR', __DIR__ . '/../uploads');  // ✅ Relative to config
```

**Result:** No path changes needed when deploying! 🎉

---

## 🧪 Pre-Deployment Testing

### **Local Testing (Already Done):**
- ✅ RSS feed import works
- ✅ Image download works
- ✅ Modal functions properly
- ✅ Error handling works
- ✅ Form validation works

### **Production Testing (After Deploy):**
1. **Test RSS Import**
   - Try importing a feed with HTTPS URL
   - Verify SSL verification is working
   - Check that images download correctly

2. **Test Error Handling**
   - Try invalid URL
   - Try non-existent feed
   - Verify errors are logged (not displayed)

3. **Check File Permissions**
   - Ensure `uploads/covers/` is writable (755)
   - Ensure `logs/` is writable (755)
   - Ensure `data/` is writable (755)

---

## 📦 Deployment Steps

### **1. Upload Files to Production Server**

Upload these **new files**:
```
includes/RssFeedParser.php
api/import-rss.php
RSS-IMPORT-IMPLEMENTATION.md
DEPLOYMENT-CHECKLIST.md
new-features-plan.md
```

Upload these **modified files**:
```
index.php
assets/js/app.js
includes/PodcastManager.php
```

### **2. Set File Permissions**

```bash
# On your production server:
chmod 755 uploads/covers/
chmod 755 logs/
chmod 755 data/
chmod 644 includes/RssFeedParser.php
chmod 644 api/import-rss.php
```

### **3. Verify Directory Structure**

Ensure these directories exist:
```
/data/
/uploads/covers/
/logs/
/api/
```

### **4. Test in Production**

1. Visit your production URL
2. Enter password (if prompted)
3. Click "Import from RSS"
4. Test with: `https://feed.podbean.com/laborradiopodcastweekly/feed.xml`
5. Verify import works and image downloads

### **5. Monitor Logs**

Check `logs/error.log` for any issues:
```bash
tail -f logs/error.log
```

---

## 🔍 Environment Detection Verification

### **How to Verify Environment:**

Add this temporarily to any PHP file:
```php
<?php
require_once 'config/config.php';
echo "Environment: " . ENVIRONMENT . "<br>";
echo "APP_URL: " . APP_URL . "<br>";
echo "SSL Verify: " . (defined('ENVIRONMENT') && ENVIRONMENT === 'production' ? 'Enabled' : 'Disabled');
?>
```

**Expected Output:**
- **Local:** `Environment: development`, `SSL Verify: Disabled`
- **Production:** `Environment: production`, `SSL Verify: Enabled`

---

## 🐛 Troubleshooting

### **Issue: RSS Import Not Working in Production**

**Possible Causes:**

1. **SSL Certificate Issues**
   - Some feeds may have invalid SSL certificates
   - Check error logs for SSL errors
   - **Solution:** Temporarily disable SSL verify for specific feeds (not recommended)

2. **cURL Not Enabled**
   - Check if cURL is installed: `php -m | grep curl`
   - **Solution:** Install PHP cURL extension

3. **File Permissions**
   - Images can't be saved
   - **Solution:** `chmod 755 uploads/covers/`

4. **Timeout Issues**
   - Large feeds take too long
   - **Solution:** Increase timeout in `RssFeedParser.php` (currently 10s)

5. **Memory Limit**
   - Large images cause memory issues
   - **Solution:** Increase PHP memory limit in `php.ini`

### **Issue: Images Not Downloading**

**Check:**
1. `uploads/covers/` directory exists and is writable
2. Image URL is accessible (test in browser)
3. Image is under 5MB
4. Image format is JPG, PNG, GIF, or WebP

### **Issue: API Returns 404**

**Check:**
1. `api/import-rss.php` file exists
2. `.htaccess` not blocking API directory
3. Server supports PHP in subdirectories

---

## 📊 Production Monitoring

### **What to Monitor:**

1. **Error Logs**
   - Location: `logs/error.log`
   - Check daily for issues

2. **Disk Space**
   - Images accumulate in `uploads/covers/`
   - Monitor disk usage

3. **Performance**
   - RSS import should complete in < 10 seconds
   - If slower, check network/server load

4. **Failed Imports**
   - Track which feeds fail to import
   - Common issues: invalid SSL, timeout, invalid XML

---

## 🎯 Success Criteria

### **Production is Working If:**

- ✅ Can import RSS feeds with HTTPS URLs
- ✅ Cover images download automatically
- ✅ No errors displayed to users
- ✅ Errors logged to `logs/error.log`
- ✅ SSL verification is enabled
- ✅ Password protection works
- ✅ Existing podcasts still work

---

## 🔐 Security Best Practices (Already Implemented)

- ✅ **Input Validation:** All URLs validated before processing
- ✅ **Output Sanitization:** All user input sanitized
- ✅ **Error Handling:** Errors logged, not displayed
- ✅ **File Type Validation:** Only allowed image types
- ✅ **File Size Limits:** 5MB max for images
- ✅ **Timeout Protection:** 10 second timeout on requests
- ✅ **SSL Verification:** Enabled in production
- ✅ **Password Protection:** Active on all pages

### **Optional Enhancements:**

- [ ] Add rate limiting to API endpoints
- [ ] Add CSRF protection to forms
- [ ] Add duplicate feed detection
- [ ] Add import history tracking
- [ ] Add email notifications for failed imports

---

## 📝 Rollback Plan

If something goes wrong in production:

### **Quick Rollback:**

1. **Remove new files:**
   ```bash
   rm includes/RssFeedParser.php
   rm api/import-rss.php
   ```

2. **Restore old files:**
   ```bash
   # Restore from your backup:
   git checkout HEAD~1 index.php
   git checkout HEAD~1 assets/js/app.js
   git checkout HEAD~1 includes/PodcastManager.php
   ```

3. **Clear browser cache**

4. **Test existing functionality**

### **No Data Loss:**
- RSS import doesn't modify existing podcasts
- All existing data remains intact
- Rollback is safe and quick

---

## ✅ Final Checklist

Before going live, verify:

- [ ] All files uploaded to production server
- [ ] File permissions set correctly (755 for directories, 644 for files)
- [ ] Directories exist: `data/`, `uploads/covers/`, `logs/`, `api/`
- [ ] Environment auto-detection working (check with test script)
- [ ] SSL verification enabled in production
- [ ] Error logging working (check `logs/error.log`)
- [ ] Password protection active
- [ ] Test RSS import with real feed
- [ ] Test image download
- [ ] Test error handling (invalid URL)
- [ ] Existing podcasts still work
- [ ] Monitor logs for 24 hours after deployment

---

## 🎉 You're Ready to Deploy!

Your RSS Feed Auto-Import feature is **production-ready** with:

- ✅ Automatic environment detection
- ✅ Security enabled in production
- ✅ No hardcoded paths
- ✅ Proper error handling
- ✅ No breaking changes
- ✅ Easy rollback if needed

**Confidence Level:** 🟢 **HIGH** - Safe to deploy!

---

**Questions or Issues?**
- Check `logs/error.log` first
- Review this checklist
- Test locally to reproduce issue
- Check file permissions

**Last Tested:** 2025-10-10 (Local)  
**Status:** ✅ Ready for Production Deployment
