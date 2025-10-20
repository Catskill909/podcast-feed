# Cache Busting Fix - Production JavaScript Not Running

## Date: October 17, 2025 10:01am

---

## Problem

**Production shows absolute dates** ("Oct 16, 2025")  
**Localhost shows relative dates** ("Yesterday", "2 days ago")  
**Modals work on both** (they use inline JavaScript, not cached files)

**Root Cause:** Production server is serving OLD cached version of app.js that doesn't have the `updateAllLatestEpisodeDates()` function.

---

## Solution: Cache Busting

### Changes Made

#### 1. Added ASSETS_VERSION constant (config/config.php line 30)
```php
define('ASSETS_VERSION', '20251017_1001'); // Update this when JS/CSS changes
```

#### 2. Updated JavaScript includes (index.php lines 1428-1432)
```php
<script src="assets/js/app.js?v=<?php echo ASSETS_VERSION; ?>"></script>
```

Now the URL will be: `assets/js/app.js?v=20251017_1001`

When you update JavaScript, change the version number and browsers will fetch the new file.

---

## How To Deploy

### Step 1: Push to Production
```bash
git add .
git commit -m "Add cache busting for JavaScript files"
git push
```

### Step 2: Hard Refresh on Production
After deployment, do a **hard refresh**:
- **Mac:** Cmd + Shift + R
- **Windows:** Ctrl + Shift + R  
- **Or:** Clear browser cache

### Step 3: Verify
1. Open browser console
2. Should see logs:
   ```
   Updating 6 date cells
   Processing date: 2025-10-16 14:00:00
   ...
   Date update complete
   ```
3. Main page should show "Yesterday", "2 days ago", etc.

---

## Future Updates

**When you modify JavaScript or CSS files:**

1. Edit `config/config.php`
2. Update `ASSETS_VERSION` to current timestamp:
   ```php
   define('ASSETS_VERSION', '20251017_1430'); // New timestamp
   ```
3. Deploy
4. Users will automatically get the new files

---

## Why This Works

### Without Version Parameter
```
Browser: "Give me app.js"
Server: "Here's the cached version from yesterday"
Browser: "Thanks!" (uses old code)
```

### With Version Parameter
```
Browser: "Give me app.js?v=20251017_1001"
Server: "I don't have that version cached, here's the new file"
Browser: "Thanks!" (uses new code)
```

The `?v=20251017_1001` makes the browser think it's a different file.

---

## Files Changed

1. **config/config.php** - Added ASSETS_VERSION constant
2. **index.php** - Added version parameter to all script tags

---

## Status

✅ Cache busting implemented  
✅ Version constant added  
⏳ Waiting for production deployment  
⏳ Waiting for verification  

---

## Verification Checklist

After deploying to production:

- [ ] Hard refresh the page (Cmd+Shift+R)
- [ ] Check browser console for logs
- [ ] Verify main page shows relative dates ("Yesterday", etc.)
- [ ] Verify modals still work
- [ ] Test on different browsers
- [ ] Verify dates update when clicking refresh button

---

## Why Modals Always Worked

The modals use JavaScript that's defined in `player-modal.js` as a CLASS METHOD:

```javascript
class PlayerModal {
    formatDate(dateString) {
        // ... calculation
    }
}
```

This code was ALWAYS loaded because the modal JavaScript file was cached correctly.

The main page uses a STANDALONE FUNCTION added recently:

```javascript
function formatLatestEpisodeDate(dateString) {
    // ... calculation  
}
```

This NEW function wasn't in the cached version on production!

---

## Next Time

When adding new JavaScript functions, always:
1. Update ASSETS_VERSION in config.php
2. Test on production with hard refresh
3. Verify in browser console

This ensures users get the latest code immediately.
