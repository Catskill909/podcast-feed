# Modal Height Bug - Investigation & Fix

## Problem
Help modal (`#helpModal`) not using available vertical space despite multiple CSS attempts.

## Root Cause
**INLINE STYLE OVERRIDE** in `index.php` line 773:
```html
<div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
```

This inline style has **highest specificity** and overrides ALL CSS changes.

## Failed Attempts (Why They Failed)
1. **Attempt 1**: Changed `.modal-lg` max-height to `95vh` - Failed because inline style on `.modal-body` limited content to 70vh
2. **Attempt 2**: Changed `.modal-lg` max-height to `92vh` - Failed for same reason
3. **Attempt 3**: Changed to `calc(100vh - 3rem)` with explicit margins - Failed for same reason
4. **Attempts 4-5**: Repeated similar CSS changes - All failed because inline style takes precedence

## CSS Specificity Hierarchy
1. **Inline styles** (highest) ← The problem
2. IDs
3. Classes
4. Elements

## Solution
Remove the inline `style="max-height: 70vh; overflow-y: auto;"` from the help modal's `.modal-body` div in `index.php` and let CSS handle it properly.

## Files Involved
- `/Users/paulhenshaw/Desktop/podcast-feed/index.php` (line 773) - Contains inline style
- `/Users/paulhenshaw/Desktop/podcast-feed/assets/css/components.css` - CSS rules being overridden

## Correct Fix
1. Remove inline style from `index.php` line 773
2. Ensure `.modal-body` in CSS has proper overflow handling
3. Ensure `.modal-lg` has appropriate max-height
