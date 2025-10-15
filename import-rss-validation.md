# RSS Import Validation System - Design Document

## 🎯 Problem Statement

A user imported a podcast feed that:
- ❌ Had a missing/invalid cover image
- ❌ Had RSS validation errors
- ❌ Caused issues in the system

**Goal:** Prevent bad feeds from being imported by validating them thoroughly before adding to the database.

---

## 📋 Validation Requirements

### **Critical (Must Pass - Block Import):**
1. ✅ Valid RSS 2.0 or Atom XML structure
2. ✅ Feed URL is accessible (HTTP 200)
3. ✅ Required fields present: `<title>`, `<link>`, `<description>`
4. ✅ At least one `<item>` (episode) exists
5. ✅ Cover image exists and is accessible
6. ✅ Cover image meets size requirements (1400-3000px recommended)
7. ✅ Cover image is valid format (JPG, PNG)

### **Warnings (Can Import with Notice):**
1. ⚠️ Missing iTunes namespace (`xmlns:itunes`)
2. ⚠️ Missing iTunes-specific tags (`itunes:author`, `itunes:category`)
3. ⚠️ Cover image smaller than 1400px (works but not ideal)
4. ⚠️ Missing `<pubDate>` on items
5. ⚠️ Slow response time (>5 seconds)

### **Info (Nice to Have):**
1. ℹ️ iTunes explicit tag present
2. ℹ️ Feed has `<language>` tag
3. ℹ️ Feed has `<copyright>` tag
4. ℹ️ Episodes have `<enclosure>` tags (audio files)

---

## 🎨 User Interface Design

### **Current Flow (Before):**
```
1. User enters RSS URL
2. Click "Import from RSS"
3. Loading spinner
4. ✅ Success OR ❌ Generic error
```

### **New Flow (After):**
```
1. User enters RSS URL
2. Click "Import from RSS"
3. Loading spinner with "Validating feed..."
4. Validation Results Modal:
   
   ┌─────────────────────────────────────────┐
   │  RSS Feed Validation Results            │
   ├─────────────────────────────────────────┤
   │                                         │
   │  Feed: "My Podcast"                     │
   │  URL: https://example.com/feed.xml      │
   │                                         │
   │  ✅ Critical Checks (3/3 passed)        │
   │    ✓ Valid RSS 2.0 structure           │
   │    ✓ Feed accessible (HTTP 200)        │
   │    ✓ Cover image found and valid       │
   │                                         │
   │  ⚠️ Warnings (2 issues)                 │
   │    ⚠ Missing iTunes namespace          │
   │    ⚠ Cover image is 1200px (< 1400px)  │
   │                                         │
   │  ℹ️ Info                                │
   │    • 25 episodes found                 │
   │    • Latest episode: Today             │
   │    • Feed type: RSS 2.0                │
   │                                         │
   │  [Cancel]  [Import Anyway] [✓ Import]  │
   └─────────────────────────────────────────┘
```

### **Error State (Blocking):**
```
   ┌─────────────────────────────────────────┐
   │  ❌ Cannot Import Feed                  │
   ├─────────────────────────────────────────┤
   │                                         │
   │  Feed: "Bad Podcast"                    │
   │  URL: https://example.com/bad.xml       │
   │                                         │
   │  ❌ Critical Issues (2 found)           │
   │    ✗ Cover image not found             │
   │    ✗ Invalid XML structure             │
   │                                         │
   │  Details:                               │
   │  • <image> tag missing or empty        │
   │  • XML parse error at line 45          │
   │                                         │
   │  Suggestions:                           │
   │  • Validate feed at feedvalidator.org  │
   │  • Check that cover image URL works    │
   │  • Contact podcast host for support    │
   │                                         │
   │  [Close]  [Try Different URL]          │
   └─────────────────────────────────────────┘
```

---

## 🔧 Technical Implementation

### **1. New Validation Class** (`includes/RssFeedValidator.php`)

```php
class RssFeedValidator
{
    // Validation levels
    const LEVEL_CRITICAL = 'critical';  // Must pass
    const LEVEL_WARNING = 'warning';    // Should pass
    const LEVEL_INFO = 'info';          // Nice to have
    
    /**
     * Validate RSS feed comprehensively
     * @return array Validation results
     */
    public function validate($feedUrl) {
        return [
            'valid' => true/false,
            'can_import' => true/false,
            'feed_info' => [...],
            'critical' => [...],
            'warnings' => [...],
            'info' => [...],
            'suggestions' => [...]
        ];
    }
    
    // Individual validation methods
    private function validateXmlStructure($xml);
    private function validateRequiredFields($xml);
    private function validateCoverImage($imageUrl);
    private function validateImageDimensions($imageUrl);
    private function validateItunesNamespace($xml);
    private function validateEpisodes($xml);
    private function validateResponseTime($url);
}
```

### **2. Validation Checks**

#### **Critical Validations:**

```php
// 1. XML Structure
function validateXmlStructure($content) {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($content);
    $errors = libxml_get_errors();
    
    if ($errors) {
        return [
            'passed' => false,
            'message' => 'Invalid XML structure',
            'details' => $errors[0]->message
        ];
    }
    return ['passed' => true];
}

// 2. Required Fields
function validateRequiredFields($xml) {
    $required = ['title', 'link', 'description'];
    $missing = [];
    
    foreach ($required as $field) {
        if (!isset($xml->channel->$field)) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        return [
            'passed' => false,
            'message' => 'Missing required fields',
            'details' => implode(', ', $missing)
        ];
    }
    return ['passed' => true];
}

// 3. Cover Image Validation
function validateCoverImage($imageUrl) {
    // Check if URL is accessible
    $headers = @get_headers($imageUrl);
    if (!$headers || strpos($headers[0], '200') === false) {
        return [
            'passed' => false,
            'message' => 'Cover image not accessible',
            'details' => 'HTTP error or URL not found'
        ];
    }
    
    // Check image type
    $imageInfo = @getimagesize($imageUrl);
    if (!$imageInfo) {
        return [
            'passed' => false,
            'message' => 'Invalid image file',
            'details' => 'Not a valid image format'
        ];
    }
    
    // Check dimensions
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    if ($width < 1400 || $height < 1400) {
        return [
            'passed' => false,
            'message' => 'Cover image too small',
            'details' => "{$width}x{$height}px (minimum 1400x1400px)"
        ];
    }
    
    if ($width > 3000 || $height > 3000) {
        return [
            'passed' => false,
            'message' => 'Cover image too large',
            'details' => "{$width}x{$height}px (maximum 3000x3000px)"
        ];
    }
    
    return [
        'passed' => true,
        'dimensions' => "{$width}x{$height}px"
    ];
}

// 4. Episode Validation
function validateEpisodes($xml) {
    $items = $xml->channel->item ?? [];
    
    if (count($items) === 0) {
        return [
            'passed' => false,
            'message' => 'No episodes found',
            'details' => 'Feed must have at least one episode'
        ];
    }
    
    return [
        'passed' => true,
        'count' => count($items)
    ];
}
```

#### **Warning Validations:**

```php
// 1. iTunes Namespace
function validateItunesNamespace($xml) {
    $namespaces = $xml->getNamespaces(true);
    
    if (!isset($namespaces['itunes'])) {
        return [
            'level' => 'warning',
            'message' => 'Missing iTunes namespace',
            'details' => 'Feed may not work properly in Apple Podcasts',
            'suggestion' => 'Add xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd"'
        ];
    }
    return ['passed' => true];
}

// 2. Stale Feed Check
function validateFeedFreshness($xml) {
    $latestItem = $xml->channel->item[0] ?? null;
    if (!$latestItem || !$latestItem->pubDate) {
        return [
            'level' => 'warning',
            'message' => 'Cannot determine feed freshness',
            'details' => 'No publication date found'
        ];
    }
    
    $pubDate = strtotime((string)$latestItem->pubDate);
    $daysSince = (time() - $pubDate) / 86400;
    
    if ($daysSince > 90) {
        return [
            'level' => 'warning',
            'message' => 'Stale feed',
            'details' => "Last episode was " . round($daysSince) . " days ago"
        ];
    }
    
    return ['passed' => true];
}

// 3. Response Time
function validateResponseTime($url, $maxTime = 5) {
    $start = microtime(true);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    $time = microtime(true) - $start;
    
    if ($time > $maxTime) {
        return [
            'level' => 'warning',
            'message' => 'Slow feed response',
            'details' => round($time, 2) . "s (recommended < {$maxTime}s)"
        ];
    }
    
    return ['passed' => true];
}
```

---

## 🎨 Frontend Integration

### **1. Update Import Modal**

Add validation step before showing import form:

```javascript
async function fetchRssFeedData() {
    const feedUrl = document.getElementById('rssFeedUrlInput').value.trim();
    
    // Show validation loading
    showValidationLoading();
    
    try {
        // Call validation endpoint
        const response = await fetch('/api/validate-rss.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({feed_url: feedUrl})
        });
        
        const result = await response.json();
        
        if (result.success) {
            if (result.validation.can_import) {
                // Show validation results + import form
                showValidationResults(result.validation);
                populateImportForm(result.data);
            } else {
                // Show blocking errors
                showValidationErrors(result.validation);
            }
        }
    } catch (error) {
        showError('Validation failed: ' + error.message);
    }
}

function showValidationResults(validation) {
    // Build validation results UI
    let html = `
        <div class="validation-results">
            <h3>Feed Validation Results</h3>
            
            <!-- Critical Checks -->
            <div class="validation-section critical">
                <h4>✅ Critical Checks (${validation.critical.passed}/${validation.critical.total})</h4>
                ${validation.critical.checks.map(check => `
                    <div class="check ${check.passed ? 'passed' : 'failed'}">
                        ${check.passed ? '✓' : '✗'} ${check.message}
                    </div>
                `).join('')}
            </div>
            
            <!-- Warnings -->
            ${validation.warnings.length > 0 ? `
                <div class="validation-section warnings">
                    <h4>⚠️ Warnings (${validation.warnings.length})</h4>
                    ${validation.warnings.map(w => `
                        <div class="warning">⚠ ${w.message}</div>
                    `).join('')}
                </div>
            ` : ''}
            
            <!-- Info -->
            <div class="validation-section info">
                <h4>ℹ️ Feed Information</h4>
                <ul>
                    <li>Episodes: ${validation.info.episode_count}</li>
                    <li>Latest: ${validation.info.latest_episode}</li>
                    <li>Type: ${validation.info.feed_type}</li>
                </ul>
            </div>
        </div>
    `;
    
    document.getElementById('validationResults').innerHTML = html;
}
```

### **2. New API Endpoint** (`api/validate-rss.php`)

```php
<?php
require_once __DIR__ . '/../includes/RssFeedValidator.php';

$feedUrl = $_POST['feed_url'] ?? '';

if (empty($feedUrl)) {
    echo json_encode(['success' => false, 'error' => 'Feed URL required']);
    exit;
}

$validator = new RssFeedValidator();
$validation = $validator->validate($feedUrl);

echo json_encode([
    'success' => true,
    'validation' => $validation,
    'data' => $validation['feed_info']
]);
```

---

## 📊 Validation Result Structure

```json
{
  "success": true,
  "validation": {
    "valid": true,
    "can_import": true,
    "feed_info": {
      "title": "My Podcast",
      "description": "...",
      "image_url": "https://...",
      "episode_count": 25,
      "latest_episode": "2025-10-15",
      "feed_type": "RSS 2.0"
    },
    "critical": {
      "passed": 4,
      "total": 4,
      "checks": [
        {"name": "xml_structure", "passed": true, "message": "Valid RSS 2.0 structure"},
        {"name": "required_fields", "passed": true, "message": "All required fields present"},
        {"name": "cover_image", "passed": true, "message": "Cover image valid (1600x1600px)"},
        {"name": "episodes", "passed": true, "message": "25 episodes found"}
      ]
    },
    "warnings": [
      {
        "level": "warning",
        "message": "Missing iTunes namespace",
        "details": "Feed may not work properly in Apple Podcasts",
        "suggestion": "Add xmlns:itunes attribute to RSS tag"
      }
    ],
    "info": [
      {"message": "Feed has language tag (en-US)"},
      {"message": "Episodes have audio enclosures"},
      {"message": "Average response time: 1.2s"}
    ],
    "suggestions": [
      "Consider adding iTunes-specific tags for better Apple Podcasts support",
      "Cover image could be larger (1600px vs recommended 3000px)"
    ]
  }
}
```

---

## 🚀 Implementation Plan

### **Phase 1: Core Validation (Priority)**
- [ ] Create `RssFeedValidator` class
- [ ] Implement critical validations:
  - [ ] XML structure
  - [ ] Required fields
  - [ ] Cover image accessibility
  - [ ] Cover image dimensions
  - [ ] Episode existence
- [ ] Create `api/validate-rss.php` endpoint
- [ ] Update frontend to call validation before import

### **Phase 2: Enhanced Validation**
- [ ] Add warning-level validations:
  - [ ] iTunes namespace
  - [ ] Feed freshness
  - [ ] Response time
- [ ] Add info-level checks:
  - [ ] Language tag
  - [ ] Copyright info
  - [ ] Enclosure tags

### **Phase 3: UI Polish**
- [ ] Design validation results modal
- [ ] Add "Import Anyway" option for warnings
- [ ] Add helpful suggestions/links
- [ ] Add "Test Feed" button (validate without importing)

### **Phase 4: Advanced Features**
- [ ] Save validation history
- [ ] Re-validate existing feeds
- [ ] Bulk validation tool
- [ ] Integration with feedvalidator.org API

---

## 🎯 User Experience Goals

### **For Good Feeds:**
```
1. Enter URL
2. Click "Import from RSS"
3. See: "✅ Feed validated successfully"
4. Import form auto-populated
5. Click "Add Podcast"
6. Done!
```

### **For Feeds with Warnings:**
```
1. Enter URL
2. Click "Import from RSS"
3. See: "⚠️ Feed has 2 warnings"
4. Review warnings
5. Choose: "Import Anyway" or "Cancel"
6. If import: Form auto-populated
7. Click "Add Podcast"
8. Done!
```

### **For Bad Feeds:**
```
1. Enter URL
2. Click "Import from RSS"
3. See: "❌ Cannot import - 3 critical issues"
4. Review issues + suggestions
5. Options:
   - Fix feed and try again
   - Try different URL
   - Contact podcast host
6. Cannot proceed with import
```

---

## 🔒 Security Considerations

1. **URL Validation:**
   - Check for valid HTTP/HTTPS URLs only
   - Prevent SSRF attacks (no localhost, internal IPs)
   - Timeout limits on requests

2. **Image Validation:**
   - Verify actual image content (not just extension)
   - Limit file size checks
   - Sanitize image URLs

3. **XML Parsing:**
   - Disable external entity loading (XXE prevention)
   - Limit XML size
   - Handle malformed XML gracefully

---

## 📚 External Resources Integration

### **Optional: FeedValidator.org API**
```php
function validateWithExternalService($feedUrl) {
    $validatorUrl = "https://validator.w3.org/feed/check.cgi?url=" . urlencode($feedUrl);
    // Parse results and integrate into validation
}
```

### **iTunes Podcast Validator**
Link users to: https://podcastsconnect.apple.com/

---

## 🧪 Testing Strategy

### **Test Cases:**

1. **Perfect Feed:**
   - Valid RSS 2.0
   - All required fields
   - Good cover image (1400-3000px)
   - iTunes namespace
   - Recent episodes

2. **Missing Cover:**
   - Valid RSS but no `<image>` tag
   - Should block import

3. **Small Cover:**
   - Valid RSS with 800x800px image
   - Should show warning but allow import

4. **No iTunes Namespace:**
   - Valid RSS 2.0 without iTunes tags
   - Should show warning but allow import

5. **Invalid XML:**
   - Malformed XML structure
   - Should block import

6. **No Episodes:**
   - Valid RSS but empty `<channel>`
   - Should block import

7. **Slow Feed:**
   - Valid but takes 8+ seconds to respond
   - Should show warning

---

## 💡 Future Enhancements

- [ ] Validate audio file accessibility
- [ ] Check for duplicate feeds (same content, different URL)
- [ ] Validate episode enclosure sizes
- [ ] Check for explicit content flags
- [ ] Validate category tags
- [ ] Integration with Spotify/Google Podcasts validators
- [ ] Automated feed health scoring (0-100)

---

**Status:** 📋 Design Complete - Ready for Implementation  
**Priority:** High - Prevents bad data from entering system  
**Estimated Time:** 6-8 hours for Phase 1  
**Dependencies:** None - Can implement immediately
