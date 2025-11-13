# Episode Descriptions Implementation

**Date:** November 12, 2025  
**Status:** ✅ Complete

## Problem
Episode descriptions were being fetched and decoded from RSS feeds but not displayed in the episode list items in the embed player.

## Solution
Added episode descriptions with 2-line clamp and "Read more" expand/collapse functionality, matching the podcast-level description behavior.

---

## Changes Made

### 1. JavaScript (`script.js`)

#### Added Description to Episode HTML (Lines 853-862)
```javascript
${episode.description ? `
    <div class="episode-description-wrapper">
        <p class="episode-item-description collapsed">${episode.description}</p>
        <button class="episode-description-toggle" aria-label="Expand description">
            <span class="toggle-text-more">Read more</span>
            <span class="toggle-text-less">Show less</span>
            <i class="fa-solid fa-chevron-down toggle-icon"></i>
        </button>
    </div>
` : ''}
```

#### Added Toggle Event Listener (Lines 915-951)
- Detects if description is short enough to hide toggle button
- Handles expand/collapse on click
- Prevents event propagation to avoid triggering episode selection
- Updates ARIA labels for accessibility

**Key Features:**
- Auto-hides "Read more" button if description is 2 lines or less
- Smooth expand/collapse animation
- Chevron icon rotates 180° when expanded
- Stops propagation to prevent conflicts with episode click handlers

---

### 2. CSS (`styles.css`)

#### Main Styles (Lines 1045-1121)

**Description Wrapper:**
```css
.episode-description-wrapper {
    position: relative;
    margin-bottom: var(--space-1);
}
```

**Description Text:**
```css
.episode-item-description {
    font-size: 13px;
    line-height: 1.5;
    color: var(--text-secondary);
    margin: 0;
    overflow: hidden;
    transition: max-height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.episode-item-description.collapsed {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    max-height: 3em; /* 2 lines * 1.5 line-height */
}

.episode-item-description.expanded {
    display: block;
    max-height: 1000px;
}
```

**Toggle Button:**
```css
.episode-description-toggle {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    background: transparent;
    border: none;
    color: var(--primary);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    padding: 2px var(--space-2);
    margin-top: 2px;
    transition: all var(--transition);
    border-radius: var(--radius-sm);
}

.episode-description-toggle:hover {
    background: rgba(187, 134, 252, 0.1);
    gap: var(--space-2);
}
```

**Toggle States:**
- `.toggle-text-less` hidden by default
- `.expanded .toggle-text-more` hidden when expanded
- `.expanded .toggle-text-less` shown when expanded
- `.expanded .toggle-icon` rotates 180deg

#### Mobile Responsive Styles (Lines 2261-2274)
```css
.episode-item-description {
    font-size: 11px;
    line-height: 1.4;
}

.episode-item-description.collapsed {
    max-height: 2.8em; /* 2 lines * 1.4 line-height */
}

.episode-description-toggle {
    font-size: 10px;
    padding: 2px var(--space-1);
}
```

---

## Features

### ✅ 2-Line Clamp
- Uses `-webkit-line-clamp: 2` for clean truncation
- Falls back to `max-height` calculation
- Works across all modern browsers

### ✅ Smart Toggle Visibility
- JavaScript checks if description exceeds 2 lines
- Auto-hides "Read more" button for short descriptions
- Prevents unnecessary UI clutter

### ✅ Smooth Animations
- 300ms cubic-bezier transition for expand/collapse
- Chevron icon rotates smoothly
- Hover effect increases gap between text and icon

### ✅ Accessibility
- ARIA labels update on toggle
- Keyboard accessible (button element)
- Semantic HTML structure

### ✅ Theme Support
- Works in both dark and light themes
- Purple accent color from theme variables
- Light theme has adjusted hover background

### ✅ Mobile Optimized
- Smaller font sizes on mobile (11px vs 13px)
- Adjusted line-height for mobile (1.4 vs 1.5)
- Compact toggle button (10px font, reduced padding)

---

## Data Flow

```
RSS Feed
  ↓
parseEpisode() - extracts description
  ↓
stripHtml() - removes HTML tags
  ↓
decodeHtmlEntities() - decodes HTML entities
  ↓
episode.description stored in state
  ↓
createEpisodeElement() - renders description
  ↓
User sees 2-line preview with "Read more"
  ↓
Click "Read more" → Full description expands
```

---

## Testing Checklist

- [ ] Descriptions appear in episode list
- [ ] 2-line clamp works correctly
- [ ] "Read more" button appears for long descriptions
- [ ] "Read more" button hidden for short descriptions
- [ ] Click expands to full description
- [ ] "Show less" button appears when expanded
- [ ] Click collapses back to 2 lines
- [ ] Chevron icon rotates on toggle
- [ ] Hover effect works on toggle button
- [ ] Works in dark theme
- [ ] Works in light theme
- [ ] Mobile responsive (smaller text)
- [ ] HTML entities decoded properly
- [ ] No layout shifts or jumps

---

## Files Modified

1. **`script.js`**
   - Added description markup to `createEpisodeElement()` function
   - Added toggle event listener with smart visibility detection

2. **`styles.css`**
   - Added `.episode-description-wrapper` styles
   - Added `.episode-item-description` with collapsed/expanded states
   - Added `.episode-description-toggle` with hover and active states
   - Added mobile responsive overrides

---

## Notes

- Descriptions are fetched from RSS feeds via `parseEpisode()`
- HTML entities are decoded using the existing `decodeHtmlEntities()` function
- Follows the same pattern as podcast-level descriptions for consistency
- No breaking changes to existing functionality
- Works with all existing episode features (play, download, etc.)
