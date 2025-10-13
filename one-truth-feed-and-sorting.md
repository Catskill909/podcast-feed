# One Truth Feed and Sorting - Architecture Analysis & Fix

**Date:** 2025-10-13  
**Issue:** Sort order changes in local browser don't persist to production feed on different machines  
**Root Cause:** Client-side only sorting with no server-side persistence

---

## 🔍 CRITICAL FINDINGS

### The Problem
When you change the sort order in your local browser:
1. ✅ The **UI table** reorders immediately (you see the change)
2. ✅ The **localStorage** saves your preference (persists in YOUR browser)
3. ❌ The **feed.php** URL parameters are built from localStorage
4. ❌ **NO SERVER-SIDE PERSISTENCE** - other users/browsers see default order

### Current Architecture (BROKEN for Multi-User)

```
┌─────────────────────────────────────────────────────────────┐
│                    BROWSER A (Your Local)                    │
│                                                               │
│  1. User clicks sort → "Newest Episodes"                     │
│  2. SortManager saves to localStorage                        │
│     └─> podcast_sort_preference: "date-newest"               │
│  3. Table rows reorder (DOM manipulation)                    │
│  4. Feed modal builds URL with params:                       │
│     └─> /feed.php?sort=episodes&order=desc                   │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ localStorage is LOCAL ONLY
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  BROWSER B (Production/Other)                │
│                                                               │
│  1. Opens app - NO localStorage data                         │
│  2. SortManager loads default: "date-newest"                 │
│  3. Feed modal builds URL:                                   │
│     └─> /feed.php (no params = default)                      │
│  4. Sees DIFFERENT order than Browser A                      │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

---

## 📁 FILE ANALYSIS

### 1. **sort-manager.js** (Lines 71-97)
**Problem:** Uses `localStorage` which is browser-specific

```javascript
loadSortPreference() {
    const stored = localStorage.getItem('podcast_sort_preference'); // ❌ LOCAL ONLY
    if (stored) {
        const data = JSON.parse(stored);
        return data.sortKey || 'date-newest';
    }
    return 'date-newest';
}

saveSortPreference(sortKey) {
    const data = { sortKey: sortKey, timestamp: Date.now() };
    localStorage.setItem('podcast_sort_preference', JSON.stringify(data)); // ❌ NOT SHARED
}
```

**Impact:** Each browser has its own preference. No shared state.

---

### 2. **feed.php** (Lines 17-30)
**Current:** Accepts URL parameters but has NO default persistence

```php
$sortBy = $_GET['sort'] ?? 'episodes';    // ✅ Can accept params
$sortOrder = $_GET['order'] ?? 'desc';    // ✅ Can accept params

// ❌ NO PERSISTENCE - always defaults if no params
```

**Impact:** Feed URL without parameters always returns default order.

---

### 3. **XMLHandler.php** (Lines 410-559)
**Current:** Generates feed with runtime sorting only

```php
public function generateRSSFeed($sortBy = 'episodes', $sortOrder = 'desc')
{
    $podcasts = $this->getAllPodcasts();
    $podcasts = $this->sortPodcasts($podcasts, $sortBy, $sortOrder); // ✅ Sorts
    // ... generates XML
}
```

**Impact:** Sorting works correctly when parameters are provided, but there's no "saved default" order.

---

### 4. **podcasts.xml** (Data File)
**Current:** Stores podcasts in insertion order

```xml
<podcasts>
    <podcast id="pod_1">...</podcast>
    <podcast id="pod_2">...</podcast>
    <podcast id="pod_3">...</podcast>
</podcasts>
```

**Missing:** No `display_order` or `sort_preference` metadata.

---

## 🎯 THE REAL ISSUE

### What You're Experiencing:
1. **Local browser:** You change sort → localStorage saves it → feed modal uses it → you see sorted feed
2. **Production browser:** Opens app → no localStorage → feed modal uses default → sees different order
3. **The feed.php itself** has no "memory" of what order you want

### Why This Happens:
- `localStorage` is **per-browser, per-domain**
- Different machines = different localStorage
- No server-side "default sort preference" stored anywhere

---

## 💡 SOLUTION ARCHITECTURE

### Option 1: Server-Side Default Sort Preference (RECOMMENDED)
**Store a global default sort preference that ALL users see**

```
┌─────────────────────────────────────────────────────────────┐
│                    SERVER (Single Truth)                     │
│                                                               │
│  data/sort-preference.json                                   │
│  {                                                            │
│    "default_sort": "episodes",                               │
│    "default_order": "desc",                                  │
│    "last_updated": "2025-10-13T17:30:00Z"                   │
│  }                                                            │
│                                                               │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ ALL browsers read this
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Browser A, B, C, D... (All Users)               │
│                                                               │
│  1. Load page → fetch default from server                    │
│  2. User changes sort → save to server                       │
│  3. feed.php reads server default                            │
│  4. ALL users see same order                                 │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

**Pros:**
- ✅ Single source of truth
- ✅ All users see same order
- ✅ Persists across browsers/machines
- ✅ Simple to implement

**Cons:**
- ⚠️ All users share same preference (not per-user)

---

### Option 2: Per-User Preferences (Advanced)
**Store preferences per authenticated user**

Would require:
- User authentication system
- Database or user-specific config files
- More complex implementation

**Not recommended** for current use case (simple auth, likely single admin).

---

## 🔧 IMPLEMENTATION PLAN

### Phase 1: Add Server-Side Sort Preference Storage

#### 1.1 Create Sort Preference File
**File:** `data/sort-preference.json`
```json
{
  "default_sort": "episodes",
  "default_order": "desc",
  "last_updated": "2025-10-13T17:30:00Z"
}
```

#### 1.2 Create Sort Preference Manager
**File:** `includes/SortPreferenceManager.php`
```php
class SortPreferenceManager {
    private $preferenceFile;
    
    public function __construct() {
        $this->preferenceFile = DATA_DIR . '/sort-preference.json';
        $this->ensureFileExists();
    }
    
    public function getDefaultSort() {
        // Read from file
        // Return ['sort' => 'episodes', 'order' => 'desc']
    }
    
    public function setDefaultSort($sort, $order) {
        // Validate and save to file
    }
}
```

#### 1.3 Create API Endpoint
**File:** `api/sort-preference.php`
```php
// GET: Return current default sort
// POST: Update default sort (requires auth)
```

#### 1.4 Update feed.php
```php
$sortPrefManager = new SortPreferenceManager();
$defaultPref = $sortPrefManager->getDefaultSort();

$sortBy = $_GET['sort'] ?? $defaultPref['sort'];
$sortOrder = $_GET['order'] ?? $defaultPref['order'];
```

#### 1.5 Update sort-manager.js
```javascript
// On page load: fetch default from server
async loadSortPreference() {
    try {
        const response = await fetch('api/sort-preference.php');
        const data = await response.json();
        return data.sortKey || 'date-newest';
    } catch (error) {
        // Fallback to localStorage
        return this.loadFromLocalStorage();
    }
}

// On sort change: save to server
async saveSortPreference(sortKey) {
    try {
        await fetch('api/sort-preference.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sortKey })
        });
    } catch (error) {
        console.error('Failed to save sort preference:', error);
    }
}
```

---

### Phase 2: Maintain Backward Compatibility

Keep localStorage as fallback:
1. Try to load from server first
2. If server fails, use localStorage
3. Save to both server AND localStorage

---

## 🚨 CRITICAL CONSIDERATIONS

### 1. **Race Conditions**
If multiple users change sort simultaneously:
- **Solution:** Last write wins (acceptable for admin tool)
- **Alternative:** Add locking mechanism (overkill)

### 2. **File Permissions**
`data/sort-preference.json` must be writable:
```bash
chmod 666 data/sort-preference.json
chmod 777 data/
```

### 3. **Caching**
Feed.php has cache headers set to no-cache (✅ already correct):
```php
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
```

### 4. **Default Feed URL**
When users share `/feed.php` without parameters:
- Should use server-side default
- Should be consistent for all users

---

## 📊 CURRENT vs. PROPOSED FLOW

### CURRENT (Broken)
```
User A changes sort → localStorage (A only) → feed.php (default) → ❌ Inconsistent
User B opens app → localStorage (empty) → feed.php (default) → ❌ Different view
```

### PROPOSED (Fixed)
```
User A changes sort → Server saves → feed.php reads server → ✅ Consistent
User B opens app → Server reads → feed.php reads server → ✅ Same view
```

---

## 🎯 SURGICAL FIX CHECKLIST

- [ ] Create `data/sort-preference.json` with default values
- [ ] Create `includes/SortPreferenceManager.php` class
- [ ] Create `api/sort-preference.php` endpoint (GET/POST)
- [ ] Update `feed.php` to read from SortPreferenceManager
- [ ] Update `sort-manager.js` to use API instead of localStorage
- [ ] Add error handling and fallbacks
- [ ] Test multi-browser scenario
- [ ] Verify feed.php consistency across machines

---

## 🔍 TESTING PLAN

### Test 1: Single Browser
1. Change sort to "A-Z"
2. Reload page
3. ✅ Should maintain "A-Z"

### Test 2: Multi-Browser (CRITICAL)
1. Browser A: Change sort to "Oldest Episodes"
2. Browser B: Open app
3. ✅ Should see "Oldest Episodes" (not default)

### Test 3: Feed URL Direct Access
1. Browser A: Set sort to "Active First"
2. Browser B: Open `/feed.php` directly
3. ✅ Should generate feed in "Active First" order

### Test 4: Feed URL with Parameters (Override)
1. Server default: "Newest Episodes"
2. Access: `/feed.php?sort=title&order=asc`
3. ✅ Should return A-Z order (parameter overrides default)

---

## 🚀 DEPLOYMENT NOTES

### Files to Create:
1. `data/sort-preference.json`
2. `includes/SortPreferenceManager.php`
3. `api/sort-preference.php`

### Files to Modify:
1. `feed.php` (add SortPreferenceManager)
2. `assets/js/sort-manager.js` (use API instead of localStorage)

### No Breaking Changes:
- ✅ Existing feed URLs with parameters still work
- ✅ localStorage fallback maintained
- ✅ Default behavior improved (consistent across browsers)

---

## 📝 SUMMARY

**The Problem:**
Your app uses `localStorage` for sort preferences, which is browser-specific. When you change the order on your local machine, other browsers/machines don't see the change because they have their own localStorage.

**The Solution:**
Store the default sort preference on the server in a JSON file. All browsers read from and write to this single source of truth. This ensures everyone sees the same feed order.

**The Impact:**
- ✅ Consistent feed order across all browsers/machines
- ✅ Feed URL without parameters uses saved preference
- ✅ Minimal code changes (surgical fix)
- ✅ No breaking changes to existing functionality

---

**Next Step:** Implement Phase 1 - Server-Side Sort Preference Storage
