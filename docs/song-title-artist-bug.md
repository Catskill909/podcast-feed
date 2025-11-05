# Song Title/Artist Mobile Layout Bug - Deep Audit

## Problem Statement
On mobile (iPhone XR, 414px width), the "SONG TITLE:" and "SONG ARTIST:" section is squashed between the green play button and volume icon, making the text unreadable. 

**Goal:** Move this entire section BELOW the play button and volume icon on mobile ONLY, without affecting desktop/tablet.

---

## HTML Structure Analysis

```html
<div class="player-controls">
    <div class="player-play-group">
        <button class="player-control__button--primary">
            <!-- Green play button -->
        </button>
    </div>

    <div class="player-nowplaying" id="nowPlayingSummary">
        <div class="player-nowplaying__row">
            <span class="player-nowplaying__label">Song Title:</span>
            <span class="player-nowplaying__value">Together</span>
        </div>
        <div class="player-nowplaying__row">
            <span class="player-nowplaying__label">Song Artist:</span>
            <span class="player-nowplaying__value">Smith</span>
        </div>
    </div>

    <button class="player-volume__mute">
        <!-- Volume icon -->
    </button>
</div>
```

**Structure:** All three elements (play button, nowplaying, volume) are siblings inside `.player-controls`

---

## Current CSS Analysis

### Base Styles (All Screens)

```css
/* Line 267-272 */
.player-controls {
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);
  width: 100%;
}

/* Line 274-279 */
.player-play-group {
  display: flex;
  align-items: center;
  gap: var(--spacing-lg);
  flex: 0 0 auto;  /* Don't grow or shrink */
}

/* Line 281-294 */
.player-nowplaying {
  display: inline-flex;  /* ⚠️ PROBLEM: inline-flex keeps it inline */
  flex-direction: column;
  gap: 0.25rem;
  flex: 1 1 auto;  /* Grow to fill space, shrink if needed */
  min-width: 0;  /* ⚠️ PROBLEM: Allows it to shrink below content size */
  padding: 0.4rem 0.75rem;
  border-radius: 999px;
  background: rgba(28, 34, 42, 0.7);
  border: 1px solid rgba(255, 255, 255, 0.06);
  color: var(--text-secondary);
  font-size: 0.85rem;
  line-height: 1.2;
}

/* Line 328-330 */
.player-volume__mute {
  margin-left: auto;  /* Pushes to the right */
}
```

### Tablet Breakpoint (max-width: 768px)

```css
/* Line 649-652 */
.player-controls {
  grid-template-columns: 1fr;  /* ⚠️ CONFLICT: Changes to grid but base is flex */
  gap: var(--spacing-md);
}
```

**ISSUE:** Line 650 sets `grid-template-columns` but `.player-controls` is `display: flex`, not grid. This rule does nothing.

### Mobile Breakpoint (max-width: 480px)

```css
/* Line 696-699 */
.player-controls {
  flex-wrap: wrap;  /* ✅ Allows wrapping */
  gap: var(--spacing-md);
}

/* Line 701-703 */
.player-play-group {
  flex: 0 0 auto;  /* Same as base */
}

/* Line 705-711 */
.player-nowplaying {
  display: flex !important;  /* ✅ Override inline-flex */
  order: 2;  /* ✅ Move to end */
  flex: 1 1 100%;  /* ✅ Take full width */
  width: 100%;  /* ✅ Force 100% */
  min-width: 100%;  /* ✅ Force 100% */
}

/* Line 713-716 */
.player-volume__mute {
  order: 1;  /* ✅ Before nowplaying */
  margin-left: auto;  /* ✅ Stay on right */
}
```

---

## Why It's NOT Working

### Root Cause Analysis

1. **Flex Basis Issue**: Even with `flex: 1 1 100%` and `width: 100%`, the element is NOT wrapping to a new line
2. **Order Conflict**: The play button has no explicit order (defaults to 0), volume is order 1, nowplaying is order 2
   - **Expected order:** play (0), volume (1), nowplaying (2)
   - **But they're still on same line!**

3. **The Real Problem**: `flex-wrap: wrap` only wraps when items **exceed container width**
   - Play button: ~68px
   - Volume button: ~40px  
   - Nowplaying: `flex: 1 1 100%` means "grow to fill available space"
   - **Total:** 68 + 40 + (remaining space) = fits in one line!

4. **Why 100% width doesn't force wrap**: In flexbox, `width: 100%` on a flex item is a suggestion, not absolute. The flex algorithm can shrink it if `flex-shrink` allows (which it does with `flex: 1 1 100%`)

---

## The Solution

### Strategy
Force `.player-nowplaying` to take MORE than 100% of available space, OR give it a flex-basis that guarantees wrapping.

### Fix Options

**Option A: Force flex-basis to 100%**
```css
.player-nowplaying {
  flex: 0 0 100%;  /* Don't grow, don't shrink, basis 100% */
}
```

**Option B: Set explicit order for all items**
```css
.player-play-group {
  order: 0;
}
.player-volume__mute {
  order: 1;  
}
.player-nowplaying {
  order: 2;
  flex: 0 0 100%;  /* Force new line */
}
```

**Option C: Use flex-basis with calc**
```css
.player-nowplaying {
  flex: 0 0 calc(100% + 1px);  /* Force overflow */
}
```

---

## Recommended Fix

Use **Option B** with `flex: 0 0 100%` because:
- Most explicit and predictable
- Prevents any flex shrinking
- Clear order for all elements
- No magic numbers or hacks

---

## Implementation Plan

1. Remove `flex: 1 1 100%` from `.player-nowplaying` mobile styles
2. Change to `flex: 0 0 100%` (no grow, no shrink, 100% basis)
3. Keep explicit `order` values for all three elements
4. Remove redundant `width: 100%` and `min-width: 100%` (flex-basis handles it)
5. Test on iPhone XR (414px) and other mobile sizes

---

## Desktop/Tablet Safety

✅ Changes are inside `@media (max-width: 480px)` only
✅ Desktop/tablet use base styles (inline-flex, flex: 1 1 auto)
✅ No breaking changes to larger screens

---

## FINAL FIX APPLIED

```css
@media (max-width: 480px) {
  .player-controls {
    flex-wrap: wrap;
    gap: var(--spacing-md);
  }

  .player-play-group {
    order: 0;  /* Play button first */
    flex: 0 0 auto;
  }

  .player-nowplaying {
    display: flex !important;  /* Override inline-flex */
    order: 2;  /* Last, wraps to new line */
    flex: 0 0 100%;  /* KEY FIX: Forces 100% width, no shrinking */
  }

  .player-volume__mute {
    order: 1;  /* Volume button second (same line as play) */
    margin-left: auto;  /* Stays on right */
  }
}
```

### What Changed
- **Before:** `flex: 1 1 100%` (grow, shrink, 100% basis) - allowed shrinking
- **After:** `flex: 0 0 100%` (no grow, no shrink, 100% basis) - forces wrap

### Result
- **Line 1:** Play button (left) + Volume button (right)
- **Line 2:** Song Title/Artist info (full width)

✅ **Status:** FIXED - Applied to `/assets/css/streaming-audio-player.css`
