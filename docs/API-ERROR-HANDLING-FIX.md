# API Error Handling Fix - Oct 16, 2024

## Problem
Podcast preview info icon was failing with JSON parsing errors:
- Error: `SyntaxError: Unexpected token '<', "<br />..." is not valid JSON`
- Console showed: `api/validate-rss-import.php:1 Failed to load resource: the server responded with a status of 422`

## Root Cause
PHP error suppression was being set **BEFORE** including files that load `config.php`. Since `config.php` re-enables error display in development mode (`error_reporting(E_ALL)` and `display_errors = 1`), any PHP warnings or errors after that point would output HTML tags (`<br />`) before the JSON response, breaking JSON parsing.

### Execution Order (WRONG):
```php
<?php
error_reporting(0);              // ← Set error suppression
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once 'PodcastManager.php';  // ← This loads config.php
                                     // ← config.php RE-ENABLES errors!
// Any errors here output HTML...
```

### Execution Order (CORRECT):
```php
<?php
require_once 'PodcastManager.php';  // ← Load config.php first

// CRITICAL: Set AFTER includes to override config.php
error_reporting(0);                 // ← Override config.php settings
ini_set('display_errors', 0);
header('Content-Type: application/json');

// Now errors are properly suppressed
```

## Files Fixed
All JSON API endpoints were updated to:
1. Load includes FIRST
2. Set error suppression AFTER includes
3. Set JSON header AFTER error suppression

### Fixed Files:
- ✅ `api/get-podcast-preview.php` (main issue)
- ✅ `api/validate-rss-import.php`
- ✅ `api/get-podcast-episodes.php`
- ✅ `api/feed-health.php`
- ✅ `api/import-rss.php`
- ✅ `api/health-check.php`
- ✅ `api/refresh-feed-metadata.php`
- ✅ `api/refresh-all-feeds.php`
- ✅ `api/sort-preference.php`

## Best Practice for New API Endpoints

When creating new JSON API endpoints, always use this pattern:

```php
<?php
/**
 * Your API Endpoint
 */

// 1. Load includes FIRST
require_once __DIR__ . '/../includes/YourClass.php';

// 2. CRITICAL: Set error handling AFTER includes
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);  // Optional: log errors to file

// 3. Set JSON header
header('Content-Type: application/json');

// 4. Your API logic
try {
    // ...
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

## Why This Matters
- **Development Mode**: `config.php` enables error display for debugging
- **JSON APIs**: Any HTML output (including error messages) breaks JSON parsing
- **User Experience**: Broken JSON causes "Failed to load" errors in the UI

## Testing
After the fix, test by:
1. Click the info icon (ℹ️) on any podcast
2. Verify the preview modal loads correctly
3. Check browser console for no JSON parsing errors
4. Test with podcasts that might have warnings (missing data, slow feeds, etc.)
