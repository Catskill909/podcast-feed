# Ads Manager - URL Modal Fix

## Problem
The previous implementation broke all images and destroyed the styling:
- Images showed broken because `$ad['url']` was used for image src (should be image URL, not click URL)
- Inline URL inputs cluttered the interface
- Grid layouts were changed unnecessarily
- Padding was reduced too much

## Solution
Completely reverted and reimplemented with a clean modal approach.

---

## What Was Fixed

### 1. ✅ Images Restored
**Problem:** `$ad['url']` was being used for `<img src>` which is wrong
**Fix:** Separated concerns:
- `$ad['url']` = Image URL (for display)
- `$ad['click_url']` = Destination URL (for clicks)

**Backend now generates proper image URLs:**
```php
'url' => APP_URL . '/uploads/ads/web/' . $filename,  // For <img src>
'click_url' => $clickUrlNode ? $clickUrlNode->nodeValue : '',  // For destination
```

### 2. ✅ URL Modal Instead of Inline Input
**Before:** Inline text input on every ad card (cluttered)
**After:** Clean button that opens modal

**Button:**
- Shows "Add URL" if no URL set
- Shows "Edit URL" if URL exists
- Opens modal on click
- Beautiful hover effect

### 3. ✅ Restored Original Styling
- **Grid:** Back to 3 columns (original)
- **Padding:** Back to 20px (original)
- **Layout:** All original spacing restored

### 4. ✅ Clean Modal Interface
**Features:**
- Large input field
- Clear instructions
- Save/Cancel buttons
- Auto-focus on input
- Reloads page after save to update button text

---

## How It Works

### User Flow
1. User uploads banner → appears in grid
2. Click "Add URL" button on ad card
3. Modal opens with input field
4. Type destination URL
5. Click "Save URL"
6. Page reloads, button now says "Edit URL"
7. Banner is ready (URL stored for future integration)

### Technical Flow
1. `openUrlModal(adType, adId, currentUrl)` called
2. Modal opens, input pre-filled if URL exists
3. User enters URL
4. `saveAdUrl()` sends AJAX to `api/update-ad-url.php`
5. Backend saves to XML as `<click_url>`
6. Page reloads to reflect changes

---

## XML Structure

```xml
<ad>
    <id>wad_1729600000_abc123</id>
    <filename>web_ad_1729600000_abc123.png</filename>
    <filepath>uploads/ads/web/web_ad_1729600000_abc123.png</filepath>
    <click_url>https://example.com/landing-page</click_url>
    <display_order>0</display_order>
    <created_at>2025-10-22 10:30:00</created_at>
</ad>
```

**Note:** Field is `click_url` not `url` to avoid confusion with image URL

---

## Files Modified

### 1. ads-manager.php
- Restored `$ad['url']` for image src
- Added `$ad['click_url']` for destination
- Replaced inline inputs with buttons
- Added URL modal HTML

### 2. includes/AdsXMLHandler.php
- Changed field name: `url` → `click_url`
- Generate proper image URLs in getWebAds()
- Generate proper image URLs in getMobileAds()
- Backward compatible (checks for old 'url' field)

### 3. assets/css/ads-manager.css
- Removed `.ad-url-input` styles
- Added `.btn-url` button styles
- Added `.url-modal-input` styles
- Restored original grid (3 columns)
- Restored original padding (20px)

### 4. assets/js/ads-manager.js
- Added `openUrlModal()` function
- Added `saveAdUrl()` function
- Added state variables for modal

---

## Backward Compatibility

**Old ads without click_url:**
```php
// Fallback to old 'url' field
if (!$clickUrlNode) {
    $clickUrlNode = $ad->getElementsByTagName('url')->item(0);
}
```

Existing ads will work fine, just won't have click URLs until set.

---

## UI Components

### URL Button
```html
<button class="btn-url" onclick="openUrlModal('web', 'ad_id', 'current_url')">
    <i class="fas fa-link"></i>
    Add URL / Edit URL
</button>
```

**Styling:**
- Dark background with border
- Hover: lifts up, border turns green
- Full width in ad card
- Icon + text layout

### URL Modal
```html
<div id="urlModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-link"></i>
            <h3>Set Banner URL</h3>
        </div>
        <div class="modal-body">
            <p>Instructions...</p>
            <input type="text" id="urlInput" class="url-modal-input">
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary">Cancel</button>
            <button class="btn btn-primary">Save URL</button>
        </div>
    </div>
</div>
```

---

## What's Restored

✅ **Original grid layout** - 3 columns
✅ **Original padding** - 20px around images
✅ **Original spacing** - 15px in ad-info
✅ **Clean interface** - No cluttered inputs
✅ **Working images** - Proper URLs
✅ **Beautiful design** - Modal approach

---

## What's New

✅ **URL button** - Clean, professional
✅ **URL modal** - Easy to use
✅ **Click URL storage** - Ready for integration
✅ **Backward compatible** - Old ads still work

---

## Next Steps (Future)

When ready to make banners clickable on front-end:

```php
<?php if (!empty($ad['click_url'])): ?>
    <a href="<?php echo htmlspecialchars($ad['click_url']); ?>" target="_blank">
        <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Ad">
    </a>
<?php else: ?>
    <img src="<?php echo htmlspecialchars($ad['url']); ?>" alt="Ad">
<?php endif; ?>
```

---

**Status:** ✅ Fixed and working
**Images:** ✅ Displaying correctly
**Styling:** ✅ Original beauty restored
**URL Feature:** ✅ Clean modal implementation
