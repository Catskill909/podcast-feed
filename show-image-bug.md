# Image Display Bug Analysis

## 🔴 Problem Statement
Images are not displaying in the podcast preview modal despite:
- No console errors
- Image URL being set correctly: `uploads/covers/pod_1760054802_68e84e12d488b_68e84e12d3871_1760054802_2d4451.jpg`
- JavaScript confirming it's setting the src attribute

## 📊 Console Output
```
Preview data: Object
Image URL: uploads/covers/pod_1760054802_68e84e12d488b_68e84e12d3871_1760054802_2d4451.jpg
Setting image src to: uploads/covers/pod_1760054802_68e84e12d488b_68e84e12d3871_1760054802_2d4451.jpg
```

## 🔍 Investigation Steps

### 1. Check if Image File Actually Exists
**File path**: `uploads/covers/pod_1760054802_68e84e12d488b_68e84e12d3871_1760054802_2d4451.jpg`

**Questions**:
- Does this file exist on disk?
- What's the actual file structure?
- Are permissions correct?

### 2. Image URL Path Analysis

**Current URL**: `uploads/covers/filename.jpg` (relative)

**Where is the HTML served from?**
- Local dev: `http://localhost:8080/index.php`
- Browser will resolve relative URL to: `http://localhost:8080/uploads/covers/filename.jpg`

**Is this correct?**
- If app is in root: YES ✅
- If app is in subdirectory: NO ❌

### 3. How Images Work in Main Table

**Check index.php line 274**:
```php
<img src="<?php echo htmlspecialchars($podcast['image_info']['url']); ?>">
```

**Question**: Do images show in the main table? If YES, then the URL format is correct.

### 4. Difference Between Table and Modal

**Table**: PHP renders the img tag server-side with the URL
**Modal**: JavaScript sets img.src client-side with the URL

**Potential Issue**: 
- If table images work but modal images don't, it's a JavaScript/DOM issue
- If table images also don't work, it's a URL/path issue

## 🎯 Root Cause Hypotheses

### Hypothesis 1: Relative URL Context
**Problem**: Relative URL `uploads/covers/file.jpg` resolves differently depending on current page
- From `index.php`: Works
- From modal (JavaScript): Might resolve incorrectly

**Test**: Check Network tab - what's the actual request URL?

### Hypothesis 2: Image Element Not Visible
**Problem**: Image loads but CSS hides it
- `display: none` still set?
- `opacity: 0`?
- `visibility: hidden`?
- Parent container hidden?

**Test**: Inspect element in browser DevTools

### Hypothesis 3: CORS or Security
**Problem**: Browser blocks loading
- Mixed content (HTTP/HTTPS)?
- CORS policy?

**Test**: Check console for security warnings

### Hypothesis 4: Image Path Needs Leading Slash
**Problem**: Relative vs root-relative
- Current: `uploads/covers/file.jpg` (relative to current page)
- Should be: `/uploads/covers/file.jpg` (relative to domain root)

**Test**: Add leading slash

## 🔧 Diagnostic Commands

### Check if file exists:
```bash
ls -la uploads/covers/pod_1760054802_68e84e12d488b_68e84e12d3871_1760054802_2d4451.jpg
```

### Check file permissions:
```bash
ls -l uploads/covers/
```

### Check what URL browser actually requests:
1. Open DevTools → Network tab
2. Click podcast to open modal
3. Look for image request
4. Check: Status code, URL, Response

## 🎯 Most Likely Fix

Based on web engineering best practices:

**Use root-relative URLs with leading slash**: `/uploads/covers/file.jpg`

This ensures the URL always resolves from the domain root, regardless of:
- Current page location
- JavaScript context
- Subdirectories

## 📝 Implementation Plan

1. **First**: Check if images work in main table
   - If NO: URL format is wrong everywhere
   - If YES: Issue is specific to modal

2. **Second**: Check browser Network tab
   - What URL is actually being requested?
   - What's the response (404, 200, etc.)?

3. **Third**: Fix based on findings
   - If 404: Path is wrong
   - If 200 but no display: CSS/DOM issue
   - If no request: JavaScript not executing

## 🚨 ACTUAL ROOT CAUSE FOUND

**Problem**: ImageUploader uses `$_SERVER['SCRIPT_NAME']` to build URL

**When called from**:
- `index.php`: `SCRIPT_NAME` = `/index.php` → `dirname()` = `/` → URL = `/uploads/covers/file.jpg` ✅
- `api/get-podcast-preview.php`: `SCRIPT_NAME` = `/api/get-podcast-preview.php` → `dirname()` = `/api` → URL = `/api/uploads/covers/file.jpg` ❌

**The Issue**: ImageUploader builds URLs based on WHERE IT'S CALLED FROM, not where the app root is!

**Solution**: Don't use `$_SERVER['SCRIPT_NAME']` at all. Use a fixed path or detect app root properly.

## 🎯 Correct Fix

Images are stored at: `uploads/covers/` (relative to app root)

**Option 1**: Always use root-relative path `/uploads/covers/` (assumes app is at domain root)
**Option 2**: Store base path in config once, use everywhere
**Option 3**: Just use relative path `uploads/covers/` and let browser resolve it

**Best Solution**: Store just the filename in database, build URL in the frontend where we know the context.
