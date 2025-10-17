# Chevron Dropdown Bug - Player Modal

## Problem
The chevron icon in the sort dropdown (`#playerEpisodeSort`) is touching the right edge of the select box.

## Root Cause Analysis

### CSS Cascade Investigation
1. **Base Form Control Styling** (`style.css` line 433-446):
   ```css
   .form-control {
     padding: var(--spacing-md); /* 1rem on ALL sides */
   }
   ```

2. **Player Modal Override Attempt** (`player-modal.css` line 272-275):
   ```css
   .player-episodes-controls select {
     padding-right: 2.5rem !important;
   }
   ```

3. **HTML Element** (`index.php` line 1260):
   ```html
   <select id="playerEpisodeSort" class="form-control form-control-sm">
   ```

### Why Previous Fixes Failed
- The `.form-control` class applies `padding: var(--spacing-md)` (1rem) to all sides
- Browser default select styling may be interfering
- The `!important` flag should work, but the selector specificity might not be high enough
- Need to target the specific ID or use a more specific selector

## Solution Applied

### The Fix
Remove the browser's default select appearance and add a custom SVG chevron with controlled positioning:

```css
#playerEpisodeSort,
.player-episodes-controls select,
select#playerEpisodeSort.form-control {
  min-width: 150px;
  padding-right: 2.5rem !important;
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  background-image: url("data:image/svg+xml...");  /* Custom chevron SVG */
  background-repeat: no-repeat;
  background-position: right 0.75rem center;  /* 0.75rem from right edge */
  background-size: 16px;
}
```

### Why This Works
1. **Remove native appearance**: `appearance: none` removes the browser's default chevron
2. **Custom chevron**: SVG data URI provides a consistent chevron across browsers
3. **Controlled positioning**: `background-position: right 0.75rem center` places chevron 0.75rem from the right edge
4. **Adequate padding**: `padding-right: 2.5rem` ensures text doesn't overlap with chevron
5. **High specificity**: Multiple selectors with `!important` ensure the styles override base `.form-control` styles

## Status
✅ **FIXED** - Chevron now has proper spacing from the right edge of the dropdown.
