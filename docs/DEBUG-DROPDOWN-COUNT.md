# DROPDOWN COUNT DEBUG - SYSTEMATIC INVESTIGATION

**Issue:** Dropdown shows 9 podcasts, should show 14
**Date:** November 3, 2025

---

## STEP 1: Verify Source Data

We need to check what `$podcasts` actually contains in admin.php line 103.

### Action: Add Debug Output

Add this BEFORE the closing `</body>` tag in admin.php:

```php
<!-- DEBUG: Check podcast count -->
<script>
console.log('=== PODCAST COUNT DEBUG ===');
console.log('Total podcasts from PHP:', window.ALL_PODCASTS_FOR_FILTER ? window.ALL_PODCASTS_FOR_FILTER.length : 'undefined');
console.log('All podcasts:', window.ALL_PODCASTS_FOR_FILTER);
</script>
```

---

## STEP 2: Check Browser Console

1. Hard refresh (Cmd+Shift+R)
2. Open browser console (Cmd+Option+J)
3. Look for "PODCAST COUNT DEBUG"
4. Check the count and list

---

## STEP 3: Possible Issues

### Issue A: $podcasts Variable Scope
- `$podcasts` is defined on line 103
- Script is at line 1968
- Variable should be in scope

### Issue B: Array Filtering
- `array_map()` might be filtering out some podcasts
- Check if all podcasts have 'id' and 'title' fields

### Issue C: JSON Encoding Issue
- Special characters in titles might break JSON
- Check for quotes, apostrophes, etc.

### Issue D: getAllPodcasts() Return Value
- Might not be returning all podcasts
- Could be filtering by status

---

## STEP 4: Verification Query

Run this in admin.php to see exact count:

```php
<!-- DEBUG: Raw podcast count -->
<?php
echo "<!-- PODCAST COUNT: " . count($podcasts) . " -->";
foreach ($podcasts as $p) {
    echo "<!-- PODCAST: " . htmlspecialchars($p['id']) . " - " . htmlspecialchars($p['title']) . " -->";
}
?>
```

---

## NEXT STEPS

1. Add debug output
2. Check console
3. Report findings
4. Identify exact cause
5. Apply targeted fix
