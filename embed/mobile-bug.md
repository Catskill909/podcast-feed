# MOBILE LAYOUT BUG - DEEP AUDIT

**Date:** November 12, 2025  
**Issue:** Mobile layout changes not applying despite 4 attempts  
**Status:** 🔴 ROOT CAUSE FOUND

---

## THE PROBLEM

**User wants:** Episode description to take full width on mobile (stacked layout)

**Current layout:** Side-by-side (image + content + buttons all in one row)

**Attempts made:** 4 CSS changes, none worked, even in private browser

---

## ROOT CAUSE DISCOVERED

**THE CSS CASCADE IS WRONG!**

### Media Query Order in styles.css:

1. **Line 912-922:** Base `.episode-item` - `display: flex`
2. **Line 2085-2105:** TABLET query `@media (min-width: 481px) and (max-width: 769px)`
   - Only changes padding, NOT display
   - Still uses `display: flex` from base
3. **Line 2110+:** MOBILE query `@media (max-width: 480px)`
   - Line 2245-2250: Changes to `display: grid` ← MY FIX
   - But this is AFTER tablet query

### The Cascade Problem:

**At 375px viewport (mobile):**
- Base rule applies: `display: flex` ✓
- Tablet query does NOT match (375 < 481) ✗
- Mobile query SHOULD match (375 < 480) ✓
- Mobile `display: grid` SHOULD override base `display: flex` ✓

**So why isn't it working?**

Let me check the EXACT line numbers of my mobile changes...

### Line 2245-2281: Mobile Media Query

```css
@media (max-width: 480px) {
    /* ... other rules ... */
    
    .episode-item {
        padding: var(--space-2);
        gap: var(--space-2);
        display: grid;  /* ← MY CHANGE */
        grid-template-columns: 50px 1fr auto;
        grid-template-rows: auto auto;
    }
}
```

**This SHOULD work!** The mobile query comes after the base, so it should override!

---

## HYPOTHESIS: THE CSS FILE ISN'T LOADING

**Evidence:**
1. Cache buster added: `?v=20251112-GRID`
2. Changed to JavaScript timestamp: `?v=${Date.now()}`
3. Tested in private browser
4. Tested in entirely new browser
5. **STILL NO CHANGE**

**Possible causes:**

### 1. The CSS file path is wrong
- Check: Is `styles.css` in `/embed/` directory?
- Check: Is the iframe loading from the right URL?

### 2. There's ANOTHER stylesheet loading AFTER
- Check: Are there multiple CSS files?
- Check: Is there inline CSS overriding?

### 3. The mobile media query isn't matching
- Check: Is the viewport actually 375px?
- Check: Is there a viewport meta tag issue?

### 4. CSS syntax error preventing the rule from parsing
- Check: Is there a syntax error in the mobile media query?
- Check: Are all braces closed?

---

## VERIFICATION NEEDED

### Step 1: Check if CSS is loading at all

Open browser DevTools on the iframe:
1. Network tab
2. Filter to CSS
3. Check if `styles.css?v=...` loads
4. Check the response - does it have my grid changes?

### Step 2: Check computed styles

Select `.episode-item` in mobile view:
1. Elements tab
2. Find an episode item
3. Check Computed styles
4. What is `display`? (should be `grid`, probably shows `flex`)

### Step 3: Check which rule is winning

In Styles panel:
1. Look for `.episode-item` rules
2. Are there strikethroughs on my `display: grid`?
3. Which rule is winning?

### Step 4: Check media query matching

In DevTools:
1. Check viewport width (should be 375px)
2. Check if mobile media query shows as active
3. Try manually adding `display: grid !important` in DevTools

---

## CURRENT CSS STRUCTURE

### Base (Line 912-922)
```css
.episode-item {
    background: rgba(30, 30, 30, 0.6);
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: var(--space-4);
    border-radius: var(--radius-lg);
    cursor: pointer;
    transition: all var(--transition);
    display: flex;  /* ← BASE DISPLAY */
    gap: var(--space-4);
    align-items: center;
}
```

### Tablet (Line 2096-2098)
```css
@media (min-width: 481px) and (max-width: 769px) {
    .episode-item {
        padding: var(--space-3);
        /* NO display change - still flex */
    }
}
```

### Mobile (Line 2245-2250)
```css
@media (max-width: 480px) {
    .episode-item {
        padding: var(--space-2);
        gap: var(--space-2);
        display: grid;  /* ← SHOULD OVERRIDE BASE */
        grid-template-columns: 50px 1fr auto;
        grid-template-rows: auto auto;
    }
}
```

**This structure is CORRECT! Mobile should override base!**

---

## POSSIBLE ISSUES

### Issue 1: Specificity
- All rules use `.episode-item` (same specificity)
- Mobile comes last, so it should win
- **This is NOT the issue**

### Issue 2: !important flag needed
- Base doesn't have `!important`
- Mobile doesn't have `!important`
- **Try adding:** `display: grid !important;`

### Issue 3: Syntax error
- Check for missing semicolons
- Check for unclosed braces
- Check for invalid CSS

### Issue 4: File not saving
- **CRITICAL:** Did the file actually save?
- Check file modification time
- Check file contents on disk

### Issue 5: Wrong file being edited
- **CRITICAL:** Is there another `styles.css` somewhere?
- Check if there are multiple embed directories
- Check if the iframe loads from a different path

---

## NEXT STEPS

1. **Verify the file saved:**
   ```bash
   ls -la /Users/paulhenshaw/Desktop/podcast-feed/embed/styles.css
   cat /Users/paulhenshaw/Desktop/podcast-feed/embed/styles.css | grep "display: grid"
   ```

2. **Check what the browser is loading:**
   - Open DevTools Network tab
   - Reload iframe
   - Check the CSS file response
   - Search for "display: grid" in the response

3. **Add !important flag:**
   ```css
   display: grid !important;
   ```

4. **Add a visual test:**
   ```css
   .episode-item {
       background: red !important; /* If this shows, CSS is loading */
       display: grid !important;
   }
   ```

5. **Check for other CSS files:**
   ```bash
   find /Users/paulhenshaw/Desktop/podcast-feed -name "styles.css"
   ```

---

## FAILED ATTEMPTS LOG

### Attempt 1: flex-wrap
- Added `flex-wrap: wrap` to `.episode-item`
- Added `flex-basis: 100%` to `.episode-description-wrapper`
- **Result:** No change

### Attempt 2: CSS Grid
- Changed `.episode-item` to `display: grid`
- Set grid template columns and rows
- **Result:** No change

### Attempt 3: Aggressive cache busting
- Changed cache buster to `Date.now()`
- **Result:** No change

### Attempt 4: New browser + private mode
- Tested in completely new browser
- Tested in private mode
- **Result:** No change

**Conclusion:** The CSS changes are NOT being applied, which means either:
1. The file isn't saving
2. The wrong file is being edited
3. A different CSS file is loading
4. There's a syntax error preventing parsing
5. Something else is overriding (inline styles, JavaScript)

---

## STATUS: NEED TO VERIFY FILE IS ACTUALLY LOADING

Before making any more CSS changes, we need to:
1. Confirm the file saved
2. Confirm the browser is loading it
3. Confirm there are no syntax errors
4. Confirm no other CSS is overriding
