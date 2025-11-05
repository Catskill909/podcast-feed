# JavaScript String Escaping Fix

**Date:** November 5, 2025  
**Issue:** Syntax errors when podcast titles contain apostrophes (e.g., "What's At Stake")

## Problem

When episode titles or podcast names contain special characters like apostrophes (`'`), quotes (`"`), or newlines, they break JavaScript code in `onclick` attributes, causing:

```
Uncaught SyntaxError: missing ) after argument list
```

### Root Cause

The code was using `htmlspecialchars()` to escape strings in `onclick` attributes. However, `htmlspecialchars()` is designed for HTML escaping, not JavaScript escaping. It doesn't escape single quotes by default, which breaks JavaScript strings.

**Example of broken code:**
```php
onclick="downloadEpisode('What's At Stake')"
```

This becomes invalid JavaScript because the apostrophe in "What's" terminates the string early.

## Solution

Created a dedicated `escapeJs()` function that properly escapes JavaScript strings by handling:
- Backslashes (`\` → `\\`)
- Single quotes (`'` → `\'`)
- Double quotes (`"` → `\"`)
- Newlines (`\n` → `\\n`)
- Carriage returns (`\r` → `\\r`)
- Tabs (`\t` → `\\t`)

### Implementation

**JavaScript (player-modal.js):**
```javascript
escapeJs(text) {
    if (!text) return '';
    return text
        .replace(/\\/g, '\\\\')   // Escape backslashes first
        .replace(/'/g, "\\'")     // Escape single quotes
        .replace(/"/g, '\\"')     // Escape double quotes
        .replace(/\n/g, '\\n')    // Escape newlines
        .replace(/\r/g, '\\r')    // Escape carriage returns
        .replace(/\t/g, '\\t');   // Escape tabs
}
```

**PHP (admin.php, menu-manager.php, self-hosted-episodes.php):**
```php
function escapeJs($text) {
    if (empty($text)) return '';
    return str_replace(
        ['\\', "'", '"', "\n", "\r", "\t"],
        ['\\\\', "\\'", '\\"', '\\n', '\\r', '\\t'],
        $text
    );
}
```

## Files Modified

### 1. assets/js/player-modal.js
- **Line 759-768:** Added `escapeJs()` method
- **Line 552:** Changed download button onclick from `escapeHtml()` to `escapeJs()`

### 2. admin.php
- **Line 116-123:** Added `escapeJs()` helper function
- **Line 350:** Fixed "View Feed" button onclick
- **Line 359:** Fixed "Change Status" button onclick
- **Line 399:** Fixed "Check Health" button onclick
- **Line 409:** Fixed "Delete Podcast" button onclick

### 3. menu-manager.php
- **Line 18-25:** Added `escapeJs()` helper function
- **Line 223:** Fixed "Delete Menu Item" button onclick

### 4. self-hosted-episodes.php
- **Line 153-160:** Added `escapeJs()` helper function
- **Line 1049:** Fixed "Delete Episode" button onclick (replaced `htmlspecialchars(addslashes())` hack)

## Testing

The fix resolves the issue with the "WPFW - What's At Stake" podcast and any other podcasts with special characters in their titles.

**Test cases:**
- ✅ Podcast titles with apostrophes: "What's At Stake"
- ✅ Podcast titles with quotes: `He said "Hello"`
- ✅ Podcast titles with newlines or special formatting
- ✅ Episode titles with mixed special characters

## Key Lesson

**Always use the right escaping function for the context:**
- `htmlspecialchars()` - For HTML attributes and content
- `escapeJs()` - For JavaScript strings in onclick attributes
- `json_encode()` - For embedding data in `<script>` tags
- `addslashes()` - Legacy, avoid (doesn't handle all cases)

## Impact

This fix prevents JavaScript syntax errors across the entire application when handling user-generated content or external feed data that contains special characters.
