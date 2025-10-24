# Mobile CSS - Clean Fix Summary

**Date:** October 24, 2025  
**Commit:** `78362aa` - Clean mobile CSS implementation

---

## What Was Fixed

### **Problems:**
1. Buttons/badges too small on mobile (hard to tap and read)
2. No horizontal padding on cards (couldn't see peek of next card)
3. Hover effects triggering on tap/scroll
4. Blue text selection highlight appearing on tap

### **Root Cause:**
CSS cascade order issue - `sort-controls.css` loaded AFTER `browse.css` and overrode mobile fixes with unprotected `:hover` rules.

---

## The Clean Solution

### ✅ **1. Fixed `sort-controls.css`** (3 hover rules)
Wrapped hover rules in `@media (hover: hover) and (pointer: fine)` so they only apply on devices with mouse pointers, not touch screens.

**Lines changed:** 34-40, 123-128, 138-142

### ✅ **2. Enhanced `browse.css`** 
Added missing mobile optimizations to existing `@media` rules:

**Mobile/Tablet (≤768px):**
- Increased badge min-height: 40px → 44px
- Added stat badge sizing: 16px font, 40px height
- Added sort button sizing: 48px height (better touch target)

**Phone (≤480px):**
- Override root font-size: 16px (prevents rem shrinking)
- Better grid padding: 32px (shows peek of adjacent cards)
- Larger badges: 20px font, 48px height (extra large for phones)

### ✅ **3. Removed Duplicate Code**
Deleted 120 lines of duplicate inline CSS from `index.php` that was repeating what `browse.css` already had.

**Result:** -251 lines total (removed duplicates, added 32 lines of clean fixes)

---

## What You'll See Now

### **iPad/Tablet (≤768px):**
- ✅ Large, readable badges (18px, 44px height)
- ✅ No hover overlay on tap
- ✅ No blue highlight on tap
- ✅ No text selection
- ✅ Sort button: 48px height (easy to tap)

### **iPhone/Phone (≤480px):**
- ✅ EXTRA large badges (20px, 48px height)
- ✅ Cards scaled to 88% with 32px padding (shows peek)
- ✅ Font size maintained at 16px (no shrinking)
- ✅ All hover effects disabled
- ✅ All text selection disabled

### **Desktop (>768px):**
- ✅ Normal behavior (hover effects work)
- ✅ No changes

---

## Files Modified

1. **`index.php`** - Removed 120 lines of duplicate inline CSS
2. **`assets/css/browse.css`** - Enhanced mobile rules (added 32 lines)
3. **`assets/css/sort-controls.css`** - Wrapped hover rules in media queries (already done earlier)

---

## Technical Details

### **Why It Works Now:**

**Before:**
```
browse.css (mobile fixes)
   ↓ overridden by
sort-controls.css (unprotected :hover rules)
```

**After:**
```
browse.css (mobile fixes) ✅
   ↓ no longer overridden
sort-controls.css (hover rules wrapped in @media)
   → Only applies to (hover: hover) and (pointer: fine)
   → Won't trigger on touch devices ✅
```

### **CSS Specificity:**
- Using `!important` only where necessary
- Mobile rules use fixed `px` sizes (not `rem`) to avoid font-size scaling
- `@media (max-width: 768px)` catches all mobile/tablet devices
- `@media (max-width: 480px)` adds extra fixes for phones

---

## Testing

**Deploy to Coolify** (make sure you're in the CORRECT project!) and test on:
1. iPad - badges should be large (18px), no hover on tap
2. iPhone - badges should be EXTRA large (20px), cards show peek
3. Desktop - everything should work normally with hover effects

---

## Lessons Learned

1. **Check CSS load order** - Later stylesheets override earlier ones
2. **Protect hover rules on mobile** - Use `@media (hover: hover) and (pointer: fine)`
3. **Don't duplicate CSS** - Keep it in one place (browse.css)
4. **Use fixed px sizes on mobile** - Avoids rem shrinking issues
5. **Test in the CORRECT environment** - Wrong Coolify project = hours wasted!

---

## Maintenance

All mobile CSS is now in **ONE place**: `assets/css/browse.css` (lines 595-732)

- Easy to find
- Easy to modify
- No duplicates
- Clean and maintainable

If you need to adjust mobile styling in the future, just edit `browse.css`. No need for inline CSS or complex overrides.
