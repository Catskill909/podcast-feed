# Production Audio Upload Bug - Complete Analysis
**Date:** October 18, 2025  
**Time Spent:** 3+ hours  
**Status:** ❌ NOT RESOLVED

---

## 🔴 THE PROBLEM

**Episode creation with audio file upload works perfectly in LOCAL development but FAILS in PRODUCTION (Coolify).**

### Symptoms:
- Form submission shows spinner indefinitely
- No error messages displayed to user
- No network request appears in browser Network tab
- No errors in browser Console
- No errors in server logs (`/app/logs/error.log`)
- File appears to upload but episode is never created

---

## ✅ WHAT WORKS

1. **Local Development:** Everything works perfectly
2. **Production - Podcast Creation:** Works fine
3. **Production - Episode Creation with URL:** NOT TESTED (should work)
4. **Directory Permissions:** Fixed - `uploads/audio` is writable by `nobody:nogroup`
5. **PHP Upload Limits:** Set to 500M in `.user.ini`
6. **URLs Detection:** Correct - `https://podcast.supersoul.top`

---

## ❌ WHAT'S BROKEN

### Primary Issue: Nginx 413 Error
```
2025/10/18 14:10:36 [error] client intended to send too large body: 264313290 bytes
POST /self-hosted-episodes.php HTTP/1.1" 413
```

**Root Cause:** Nginx `client_max_body_size` is NOT set to 500M in production.

### Why Nginx Config Keeps Getting Lost:
1. Manual `sed` commands in terminal work temporarily
2. Post-deploy script (`coolify-post-deploy.sh`) attempts to add config but FAILS
3. Every redeploy resets Nginx config back to default
4. `.platform/nginx/conf.d/` approach doesn't work with Coolify/Nixpacks

---

## 🔧 ATTEMPTED FIXES (ALL FAILED)

### 1. Manual Nginx Config (TEMPORARY)
```bash
sed -i 's/http {/http {\n    client_max_body_size 500M;/' /nginx.conf
nginx -s reload
```
**Result:** Works until next deployment, then lost

### 2. Post-Deploy Script with `sed`
**File:** `coolify-post-deploy.sh`  
**Result:** `sed` command fails silently, config not added

### 3. Post-Deploy Script with `awk`
**Result:** Not tested yet, likely same issue

### 4. `.platform/nginx/conf.d/uploads.conf`
**Result:** Coolify/Nixpacks doesn't use this directory structure

### 5. AJAX Upload Implementation
**Files Created:**
- `/api/upload-audio-chunk.php` - Server endpoint
- Updated `assets/js/audio-uploader.js` - AJAX upload
- Updated `self-hosted-episodes.php` - Callback handling

**Current Status:** 
- File uploads via AJAX
- Console shows: "Audio uploaded successfully"
- BUT: Form still doesn't submit
- No URL being set in form field

---

## 📊 ENVIRONMENT DIFFERENCES

### Local (Works):
- Server: PHP built-in server
- Environment: `development`
- Nginx: Not used
- Upload method: Direct form POST

### Production (Broken):
- Server: Nginx + PHP-FPM
- Environment: Detected as `development` (wrong, but URLs are correct)
- Nginx: `client_max_body_size` defaults to ~1MB
- Upload method: Attempted AJAX, but incomplete

---

## 🔍 DEBUGGING ATTEMPTS

### What We Checked:
1. ✅ Browser Console - No errors
2. ✅ Network Tab - No requests appearing (form not submitting)
3. ✅ Server Error Logs - No new entries
4. ✅ Nginx Error Logs - Shows 413 errors
5. ✅ Directory Permissions - All correct
6. ✅ PHP Configuration - Correct via `phpinfo`
7. ✅ File Upload Limits - Set correctly in `.user.ini`

### What We Didn't Check:
1. ❌ Actual server response from `/api/upload-audio-chunk.php`
2. ❌ Whether AJAX upload actually completes
3. ❌ Whether `audio_url` field gets populated after upload
4. ❌ Form validation blocking submission

---

## 🎯 THE REAL ISSUE

**The AJAX upload appears to work (console says "Audio uploaded successfully") but:**
1. The server response might not include the `url` field
2. The JavaScript callback might not be setting the form field
3. Form validation might still be blocking submission

**We need to verify:**
1. Does `/api/upload-audio-chunk.php` actually receive the file?
2. Does it return the correct JSON with `url` field?
3. Does the JavaScript set `document.getElementById('audio_url').value`?
4. Is there still form validation blocking submission?

---

## 💡 NEXT STEPS (PRIORITY ORDER)

### 1. Verify AJAX Upload Works
```javascript
// In browser console after upload attempt:
console.log(document.getElementById('audio_url').value);
// Should show: https://podcast.supersoul.top/uploads/audio/...
```

### 2. Check Server Response
- Open Network tab
- Look for POST to `/api/upload-audio-chunk.php`
- Check Response tab - should be JSON with `url` field

### 3. Test Form Submission
- If `audio_url` is populated, manually click submit
- Check if episode is created

### 4. If Still Failing - Add More Logging
```php
// In /api/upload-audio-chunk.php - add at top:
error_log("=== AJAX UPLOAD STARTED ===");
error_log("FILES: " . print_r($_FILES, true));
error_log("POST: " . print_r($_POST, true));
```

---

## 🚨 CRITICAL QUESTIONS TO ANSWER

1. **Does the AJAX upload actually complete?**
   - Check Network tab for `/api/upload-audio-chunk.php` request
   - Status should be 200
   - Response should be JSON

2. **Is the `audio_url` field being populated?**
   - Check form field value after upload
   - Should contain full URL to uploaded file

3. **Is form validation still blocking?**
   - We removed `onsubmit="return validateForm()"` 
   - But is there other validation?

4. **Why is Nginx config not persisting?**
   - Coolify might regenerate `/nginx.conf` on every deploy
   - Need to find correct way to inject config

---

## 📝 FILES MODIFIED TODAY

1. `self-hosted-episodes.php` - Removed form validation
2. `assets/js/audio-uploader.js` - Added AJAX upload
3. `api/upload-audio-chunk.php` - NEW - AJAX endpoint
4. `coolify-post-deploy.sh` - Attempted Nginx config
5. `.user.ini` - Increased PHP limits
6. `.platform/nginx/conf.d/uploads.conf` - NEW - Doesn't work
7. `nginx-custom.conf` - Created but not used
8. Multiple debug scripts created and deleted

---

## 🎬 RECOMMENDED ACTION PLAN

### Option A: Fix AJAX Upload (Current Approach)
1. Add extensive logging to `/api/upload-audio-chunk.php`
2. Verify file actually uploads and URL is returned
3. Verify JavaScript sets form field
4. Test form submission

### Option B: Fix Nginx Config (Permanent Solution)
1. Find Coolify's actual Nginx config location
2. Use Coolify environment variables to set limits
3. Or contact Coolify support for proper way to set `client_max_body_size`

### Option C: Hybrid Approach
1. Keep AJAX upload for better UX
2. Also fix Nginx config for robustness
3. Both should work together

---

## 💭 LESSONS LEARNED

1. **Don't fight the infrastructure** - If Nginx keeps resetting, find the RIGHT way to configure it
2. **Test incrementally** - Should have tested URL-only episodes first
3. **Log everything** - Need more server-side logging to see what's actually happening
4. **Check Network tab first** - Would have seen 413 errors immediately

---

**Status:** Stuck in loop, need to step back and verify basics before proceeding.
