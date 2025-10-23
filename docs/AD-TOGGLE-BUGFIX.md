# Ad Toggle Bug Fixes

**Date:** October 23, 2025  
**Issue:** Enable/Disable toggle not working correctly in preview and front page

---

## Bugs Identified

### **Bug #1: Preview Rotation Stops After Sequence**
**Problem:** Preview rotation would run through the sequence once, then stop when it hit a disabled ad.

**Root Cause:** `startWebAdRotation()` was using `querySelectorAll('.preview-ad')` which selected ALL ads (including hidden disabled ones). When rotation reached a disabled ad with `display: none`, it would try to show it but fail.

**Fix:** Filter to only include visible enabled ads:
```javascript
// BEFORE (BROKEN)
const previewAds = previewContainer.querySelectorAll('.preview-ad');

// AFTER (FIXED)
const allPreviewAds = previewContainer.querySelectorAll('.preview-ad');
const previewAds = Array.from(allPreviewAds).filter(ad => ad.style.display !== 'none');
```

**File:** `assets/js/ads-manager.js` (line 372)

---

### **Bug #2: Front Page Shows All Ads Despite Enable/Disable**
**Problem:** The public front page (index.php) was displaying ALL ads in rotation, ignoring the enabled/disabled state.

**Root Cause:** `index.php` was calling `getWebAds()` which returns all ads, instead of `getEnabledWebAds()` which filters to only enabled ads.

**Fix:** Use the correct method:
```php
// BEFORE (BROKEN)
$webAds = $adsManager->getWebAds();

// AFTER (FIXED)
$webAds = $adsManager->getEnabledWebAds(); // Only get enabled ads
```

**File:** `index.php` (line 69)

---

### **Bug #3: Blank Ad Shows on First Visit to Front Page**
**Problem:** When first visiting `index.php`, no ad would show for the rotation duration, then ads would start rotating correctly.

**Root Cause:** `array_filter()` preserves original array keys. If the first ad (index 0) in the original array was disabled, the filtered array would start at index 1 or 2. The PHP code `$index === 0 ? 'active' : ''` would never match, leaving no ad with the `active` class initially.

**Example:**
```php
// Original array with first ad disabled:
[0 => disabled, 1 => enabled, 2 => enabled]

// After array_filter (keys preserved):
[1 => enabled, 2 => enabled]  // No index 0!

// PHP loop: $index === 0 ? 'active' : ''
// Index 1: not 0, no active class
// Index 2: not 0, no active class
// Result: NO AD SHOWN initially
```

**Fix:** Reset array keys using `array_values()` so the first enabled ad is always at index 0:
```php
public function getEnabledWebAds(): array
{
    $ads = $this->getWebAds();
    $filtered = array_filter($ads, function($ad) {
        return $ad['enabled'] === true;
    });
    // Reset array keys so first enabled ad is at index 0
    return array_values($filtered);
}
```

**File:** `includes/AdsManager.php` (lines 262-270, 275-283)

---

## Testing Checklist

- [x] Preview rotation only shows enabled ads
- [x] Preview rotation continues indefinitely (doesn't stop)
- [x] Front page only shows enabled ads
- [x] Front page rotation only includes enabled ads
- [x] First ad shows immediately on page load (no blank period)
- [x] Disabling an ad removes it from both preview and front page
- [x] Enabling an ad adds it back to rotation
- [x] Works correctly when first ad in XML is disabled

---

## Files Modified

1. **index.php** (line 69) - Changed `getWebAds()` to `getEnabledWebAds()`
2. **assets/js/ads-manager.js** (line 372) - Filter to only visible ads before rotation
3. **includes/AdsManager.php** (lines 262-283) - Reset array keys with `array_values()`

---

## Impact

All three bugs are now fixed:
- ✅ Preview rotation works continuously with only enabled ads
- ✅ Front page respects enable/disable toggle
- ✅ Rotation never stops or hits disabled ads
- ✅ First ad shows immediately on page load (no blank period)
- ✅ Toggle changes are immediately reflected on front page (after refresh)
- ✅ Works correctly regardless of which ads are enabled/disabled
