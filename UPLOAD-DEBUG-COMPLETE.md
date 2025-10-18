# Complete Upload Debug Session - October 18, 2025

## THE CORE PROBLEM
**Episode upload with audio files works PERFECTLY in local development but FAILS in Coolify production.**

---

## WHAT WORKS ✅
1. **Local development** - Everything works perfectly
2. **Production podcast creation** - Works fine
3. **Production episode creation WITHOUT file** - Not tested but should work
4. **File metadata extraction** - Works (console shows duration, file size)
5. **JavaScript validation** - Removed, no longer blocking
6. **PHP configuration** - Set to 500M via `.user.ini`
7. **Directory permissions** - All correct (`uploads/audio` writable)
8. **URLs** - Correctly detected as `https://podcast.supersoul.top`

---

## WHAT FAILS ❌
**AJAX file upload gets stuck at "Uploading... 0%"**

### Console Output (Misleading):
```
Duration set: 6585 seconds
File size set: 263437582 bytes
Audio uploaded successfully: {duration: 6585, fileSize: 263437582, ...}
```

**This is FAKE success!** It's just from reading the file locally, NOT from server upload.

### Network Tab:
- Request to `/api/upload-audio-chunk.php` appears
- **Status: 413 Request Entity Too Large**
- Response: Nginx error page

### Error Logs:
```
2025/10/18 14:10:36 [error] 37#37: client intended to send too large body: 264313290 bytes
POST /self-hosted-episodes.php HTTP/1.1" 413
```

---

## ROOT CAUSE IDENTIFIED

### The Infrastructure Stack:
```
Browser → Traefik (Coolify Proxy) → Nginx (Inside Container) → PHP-FPM
```

**BOTH Traefik AND Nginx have upload limits that block large files!**

1. **Traefik** (Coolify's reverse proxy)
   - Default limit: ~4MB via `maxRequestBodyBytes`
   - Blocks request BEFORE it reaches your container
   
2. **Nginx** (Inside Nixpacks container)
   - Default `client_max_body_size`: 1MB
   - Blocks request BEFORE it reaches PHP

**Both must be fixed!**

---

## ATTEMPTED FIXES (ALL FAILED)

### 1. Manual Nginx Config in Terminal ❌
```bash
sed -i 's/http {/http {\n    client_max_body_size 500M;/' /nginx.conf
nginx -s reload
```
**Result:** Works temporarily, lost on next deployment

### 2. Post-Deploy Script (`coolify-post-deploy.sh`) ❌
- Tried multiple `sed` and `awk` approaches
- Script runs but Nginx config not applied
- Likely because Nixpacks regenerates `/nginx.conf`

### 3. `.platform/nginx/conf.d/uploads.conf` ❌
- Created directory structure
- Coolify/Nixpacks doesn't use this pattern

### 4. `nixpacks.toml` Configuration ❌
- Tried to override start command
- **BROKE DEPLOYMENT COMPLETELY**
- Error: `php-fpm: command not found`
- **DELETED - DO NOT USE**

### 5. AJAX Upload Implementation ⚠️
- Created `/api/upload-audio-chunk.php`
- Updated `assets/js/audio-uploader.js` to use XMLHttpRequest
- **Still hits 413 error from Traefik/Nginx**

---

## CODE BUGS FOUND & FIXED

### Bug 1: Premature Success Callback
**File:** `assets/js/audio-uploader.js` line 95

**Problem:**
```javascript
this.extractMetadata(file).then(metadata => {
    this.options.onUploadComplete(file, metadata); // Called BEFORE upload!
    this.uploadFile(file, metadata);
});
```

**Fix:** Removed premature callback, now only called after server confirms upload

### Bug 2: No Error Handling for 413
**File:** `assets/js/audio-uploader.js`

**Problem:** 413 errors weren't being caught or displayed

**Fix:** Added specific 413 handler:
```javascript
} else if (xhr.status === 413) {
    this.showError('File too large! Server limit exceeded (413).');
    this.options.onUploadError('413 - File too large');
    this.showZone();
    this.hideProgress();
}
```

### Bug 3: Form Validation Blocking Submit
**File:** `self-hosted-episodes.php` line 697

**Problem:**
```html
<form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
```

**Fix:** Removed validation:
```html
<form method="POST" enctype="multipart/form-data">
```

---

## FILES MODIFIED

### Created:
1. `/api/upload-audio-chunk.php` - AJAX upload endpoint
2. `/debug-server-vars.php` - Server config checker
3. `PRODUCTION-UPLOAD-BUG-SUMMARY.md` - Bug analysis
4. `COOLIFY-UPLOAD-FIX.md` - Fix instructions
5. `UPLOAD-DEBUG-COMPLETE.md` - This file

### Modified:
1. `assets/js/audio-uploader.js` - AJAX upload + error handling
2. `self-hosted-episodes.php` - Removed validation, updated callback
3. `coolify-post-deploy.sh` - Multiple Nginx config attempts
4. `.user.ini` - PHP upload limits (already correct)

### Deleted:
1. `nixpacks.toml` - BROKE DEPLOYMENT
2. `phpinfo-check.php` - Temporary debug file
3. `debug-permissions.php` - Temporary debug file

---

## THE ACTUAL SOLUTION (NOT YET APPLIED)

### Why It Works Locally:
- No Traefik proxy
- PHP built-in server (no Nginx)
- Direct file upload via form POST

### Why It Fails in Production:
- Traefik blocks at ~4MB
- Nginx blocks at 1MB
- AJAX upload hits these limits

### The Fix (2 Steps):

#### Step 1: Fix Traefik (Coolify UI)
**Location:** Coolify → Your App → Configuration → Labels

**Add these 2 labels:**
```
traefik.http.middlewares.podcast-upload.buffering.maxRequestBodyBytes=524288000
traefik.http.routers.YOUR_APP_NAME.middlewares=podcast-upload
```

Replace `YOUR_APP_NAME` with your actual app name from Coolify.

**What this does:** Tells Traefik to allow 500MB uploads (524288000 bytes)

#### Step 2: Fix Nginx (Choose ONE option)

**Option A: Use Dockerfile (RECOMMENDED)**

Create `Dockerfile` in repo root:
```dockerfile
FROM serversideup/php:8.3-fpm-nginx

COPY --chown=www-data:www-data . /var/www/html

# Nginx upload config
RUN echo 'client_max_body_size 500M;' > /etc/nginx/conf.d/uploads.conf && \
    echo 'client_body_timeout 600s;' >> /etc/nginx/conf.d/uploads.conf && \
    echo 'client_header_timeout 600s;' >> /etc/nginx/conf.d/uploads.conf

# PHP upload config
RUN echo 'upload_max_filesize = 500M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'post_max_size = 500M' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'max_execution_time = 600' >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo 'memory_limit = 512M' >> /usr/local/etc/php/conf.d/uploads.ini

RUN chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html

EXPOSE 80
```

Then in Coolify:
- Go to Application → General → Build Pack
- Change from "Nixpacks" to "Dockerfile"
- Redeploy

**Option B: Keep Nixpacks, Manual Fix After Each Deploy**

After every deployment, SSH into container:
```bash
docker exec -it CONTAINER_ID /bin/bash
echo 'client_max_body_size 500M;' > /etc/nginx/conf.d/uploads.conf
nginx -s reload
exit
```

**NOT RECOMMENDED** - Must do after every deploy

---

## VERIFICATION STEPS

After applying both fixes:

### 1. Check Traefik Labels
- Coolify → App → Configuration → Labels
- Should see both `buffering.maxRequestBodyBytes` and `middlewares` labels

### 2. Check Nginx Config (if using Dockerfile)
```bash
docker exec -it CONTAINER_ID nginx -T | grep client_max_body_size
# Should output: client_max_body_size 500M;
```

### 3. Test Upload
- Try uploading your 264MB file
- Check browser console for `XHR load event - Status: 200`
- Should see success message

### 4. Check Logs
```bash
tail -f /app/logs/error.log
# Should see: === AJAX AUDIO UPLOAD STARTED ===
# Should see: SUCCESS: Returning response: {...}
```

---

## CRITICAL INSIGHTS

### Why We Struggled for 3+ Hours:

1. **Didn't realize Traefik was the first blocker**
   - Focused only on Nginx/PHP
   - Traefik silently blocked before reaching container

2. **Misleading "success" message**
   - JavaScript showed success from local file reading
   - Actual AJAX upload was failing silently

3. **Post-deploy script doesn't work with Nixpacks**
   - Nixpacks regenerates configs
   - Need Dockerfile for persistent config

4. **Multiple layers of limits**
   - Traefik: 4MB default
   - Nginx: 1MB default
   - PHP: 2MB default (fixed via .user.ini)
   - ALL must be increased

### The Simple Truth:

**It works locally because there's no Traefik and no Nginx - just PHP.**

**In production, you have 2 extra layers that both block large uploads.**

---

## NEXT SESSION ACTION PLAN

1. **Add Traefik labels in Coolify UI** (2 minutes)
2. **Create Dockerfile** (5 minutes)
3. **Change to Dockerfile build pack** (1 minute)
4. **Redeploy** (5 minutes)
5. **Test upload** (1 minute)
6. **Done!**

**Total time: ~15 minutes**

---

## FILES TO KEEP

- `api/upload-audio-chunk.php` - AJAX upload endpoint
- `assets/js/audio-uploader.js` - Fixed uploader
- `self-hosted-episodes.php` - Fixed form
- `.user.ini` - PHP limits
- `coolify-post-deploy.sh` - Keep for permissions

## FILES TO DELETE AFTER FIX WORKS

- `debug-server-vars.php`
- `PRODUCTION-UPLOAD-BUG-SUMMARY.md`
- `COOLIFY-UPLOAD-FIX.md`
- `UPLOAD-DEBUG-COMPLETE.md` (this file)
- `.platform/` directory

---

## FINAL NOTES

**The fix is simple:**
1. Tell Traefik to allow 500MB
2. Tell Nginx to allow 500MB (via Dockerfile)
3. PHP already allows 500MB (via .user.ini)

**That's it. No complex solutions needed.**

The Dockerfile approach is permanent and version-controlled. Once set up, it will work on every deployment forever.

---

**Status:** Ready for next session with clear action plan.
