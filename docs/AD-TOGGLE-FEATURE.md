# Individual Ad Toggle Feature

**Date:** October 23, 2025  
**Feature:** Per-Ad Enable/Disable Toggle Switches

---

## Overview

Added individual enable/disable toggle switches for each banner ad in the ads management system. This provides granular control over which ads appear in the preview, on the front page, and in the RSS feed.

---

## What Was Added

### **UI Components**
- Individual toggle switches on each ad card (bottom right, same row as date)
- Material design styling matching existing toggle switches
- Smaller toggle design (44x22px) to fit on date row
- Visual feedback: disabled ads show at 50% opacity

### **Functionality**
- Toggle controls whether ad appears in:
  - Live preview (web ads only)
  - Front page display
  - Mobile RSS feed
- Real-time preview updates when toggling web ads
- Instant visual feedback with no page reload required
- Backward compatible: existing ads default to enabled

---

## Technical Implementation

### **1. Backend (XML & PHP)**

#### **AdsXMLHandler.php**
- Added `enabled` field to XML structure (defaults to '1')
- Updated `addWebAd()` to include enabled field (line 111)
- Updated `addMobileAd()` to include enabled field (line 139)
- Updated `getWebAds()` to retrieve enabled state (lines 178-185)
- Updated `getMobileAds()` to retrieve enabled state (lines 216-225)
- Added new method `toggleAdEnabled($adId, $adType)` (lines 403-430)

```php
public function toggleAdEnabled($adId, $adType)
{
    $root = $this->xml->documentElement;
    $adsSection = $adType === 'web' ? 
        $root->getElementsByTagName('webads')->item(0) : 
        $root->getElementsByTagName('mobileads')->item(0);
    
    $ads = $adsSection->getElementsByTagName('ad');
    
    foreach ($ads as $ad) {
        $id = $ad->getElementsByTagName('id')->item(0)->nodeValue;
        if ($id === $adId) {
            $enabledElement = $ad->getElementsByTagName('enabled')->item(0);
            if ($enabledElement) {
                // Toggle the value
                $currentValue = $enabledElement->nodeValue;
                $enabledElement->nodeValue = $currentValue === '1' ? '0' : '1';
            } else {
                // Add enabled element if it doesn't exist (backward compatibility)
                $this->addElement($ad, 'enabled', '1');
            }
            $this->saveXML();
            return true;
        }
    }
    
    return false;
}
```

#### **AdsManager.php**
- Added `getEnabledWebAds()` method (lines 262-268)
- Added `getEnabledMobileAds()` method (lines 273-279)

```php
public function getEnabledWebAds(): array
{
    $ads = $this->getWebAds();
    return array_filter($ads, function($ad) {
        return $ad['enabled'] === true;
    });
}

public function getEnabledMobileAds(): array
{
    $ads = $this->getMobileAds();
    return array_filter($ads, function($ad) {
        return $ad['enabled'] === true;
    });
}
```

#### **api/toggle-ad-enabled.php** (NEW FILE)
- Created new API endpoint to handle toggle requests
- Validates input (ad_id, ad_type)
- Calls `toggleAdEnabled()` method
- Returns JSON response with new enabled state

### **2. Frontend (UI)**

#### **ads-manager.php**
- Added toggle switches to all ad cards (web, phone, tablet)
- Wrapped date in `ad-date-row` div with toggle container
- Added `data-ad-id` and `data-ad-type` attributes for JavaScript
- Set initial opacity based on enabled state
- Updated preview to only show enabled ads initially

```php
<div class="ad-date-row">
    <div class="ad-date">
        <i class="fas fa-calendar"></i>
        <?php echo date('M j, Y', strtotime($ad['created_at'])); ?>
    </div>
    <div class="ad-toggle-container">
        <label class="ad-toggle-switch">
            <input type="checkbox" 
                   class="ad-enabled-toggle" 
                   data-ad-id="<?php echo $ad['id']; ?>"
                   data-ad-type="web"
                   <?php echo $ad['enabled'] ? 'checked' : ''; ?>>
            <span class="ad-toggle-slider"></span>
        </label>
    </div>
</div>
```

#### **assets/css/ads-manager.css**
- Added `.ad-date-row` styling (flexbox layout)
- Added `.ad-toggle-container` styling
- Added `.ad-toggle-switch` styling (44x22px)
- Added `.ad-toggle-slider` styling with transitions
- Green color (#4CAF50) for enabled state
- Gray color (#404040) for disabled state

### **3. JavaScript**

#### **assets/js/ads-manager.js**
- Added `initializeAdToggles()` function
- Added `updateWebPreview()` function
- Event listeners for toggle changes
- AJAX call to toggle API endpoint
- Error handling with revert on failure
- Visual feedback (opacity changes)
- Real-time preview updates

```javascript
function initializeAdToggles() {
    const toggles = document.querySelectorAll('.ad-enabled-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('change', async function() {
            const adId = this.dataset.adId;
            const adType = this.dataset.adType;
            const isEnabled = this.checked;
            
            // Disable toggle during request
            this.disabled = true;
            
            try {
                const response = await fetch('api/toggle-ad-enabled.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ad_id: adId,
                        ad_type: adType
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update toggle state to match server response
                    this.checked = result.enabled;
                    
                    // Update preview visibility for web ads
                    if (adType === 'web') {
                        updateWebPreview();
                    }
                    
                    // Show visual feedback
                    const adItem = this.closest('.ad-item');
                    if (adItem) {
                        if (result.enabled) {
                            adItem.style.opacity = '1';
                        } else {
                            adItem.style.opacity = '0.5';
                        }
                    }
                } else {
                    // Revert toggle on error
                    this.checked = !isEnabled;
                    showErrorModal(result.message || 'Failed to toggle ad state');
                }
            } catch (error) {
                // Revert toggle on error
                this.checked = !isEnabled;
                showErrorModal('Failed to toggle ad state. Please try again.');
                console.error('Toggle error:', error);
            } finally {
                // Re-enable toggle
                this.disabled = false;
            }
        });
    });
}
```

### **4. RSS Feed**

#### **mobile-ads-feed.php**
- Changed from `getMobileAds()` to `getEnabledMobileAds()`
- Only enabled ads appear in the RSS feed

---

## Files Modified

1. **includes/AdsXMLHandler.php** - Added enabled field and toggle method
2. **includes/AdsManager.php** - Added getEnabled methods
3. **api/toggle-ad-enabled.php** - New API endpoint (65 lines)
4. **ads-manager.php** - Added toggle UI to all ad cards
5. **assets/css/ads-manager.css** - Added toggle styling
6. **assets/js/ads-manager.js** - Added toggle functionality
7. **mobile-ads-feed.php** - Filter by enabled state

---

## User Experience

### **How It Works**
1. Each ad card has a toggle switch on the bottom right (same row as date)
2. Toggle ON (green) = Ad is active and appears in preview/feed
3. Toggle OFF (gray) = Ad is disabled, hidden from preview/feed, shown at 50% opacity in manager
4. Changes are instant with no page reload required
5. Preview updates in real-time for web ads

### **Visual Feedback**
- Enabled ads: Full opacity (1.0), green toggle
- Disabled ads: 50% opacity (0.5), gray toggle
- During toggle: Button disabled to prevent double-clicks
- On error: Toggle reverts to previous state with error message

---

## Backward Compatibility

- Existing ads without `enabled` field default to enabled (true)
- XML structure gracefully handles missing `enabled` elements
- No migration required - works with existing data

---

## Future Enhancements (Not Implemented)

### **Drag-to-Reorder**
- Drag banner cards to change rotation sequence
- Visual feedback during drag operation
- Save order automatically
- Sortable.js integration (already included in project)
- **Status:** Planned - Added to FUTURE-DEV.md

---

## Testing Checklist

- [x] Toggle web ad on/off - preview updates
- [x] Toggle mobile ad on/off - RSS feed updates
- [x] Multiple ads - only enabled ones show
- [x] Disabled ad visual feedback (50% opacity)
- [x] Error handling - toggle reverts on failure
- [x] Backward compatibility - existing ads work
- [x] Page reload - toggle state persists
- [x] Mobile RSS feed - only enabled ads

---

## Related Documentation

- [ADS-SYSTEM-COMPLETE.md](../ADS-SYSTEM-COMPLETE.md) - Original ads system
- [ADS-PRODUCTION-AUDIT.md](../ADS-PRODUCTION-AUDIT.md) - Production audit
- [FUTURE-DEV.md](../FUTURE-DEV.md) - Future enhancements

---

## Summary

This feature provides granular control over individual ads, allowing users to temporarily disable ads without deleting them. The implementation is clean, follows existing patterns, and provides excellent user experience with real-time feedback and no page reloads required.
