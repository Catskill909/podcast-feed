# Ads Manager - URL & Styling Update

## Changes Made (Oct 22, 2025)

### 1. ✅ Removed Cryptic ID Text
**Before:** Each ad card showed the internal ID (e.g., `wad_1761142299_68f8e61b008a0`)
**After:** ID text completely removed - cleaner interface

### 2. ✅ Added URL Input Fields
Each banner ad now has a URL input field where you can specify the click destination.

**Features:**
- Text input with placeholder: `https://example.com/landing-page`
- Auto-saves on change (no save button needed)
- Stored in XML database
- Included in RSS feed
- Applied to clickable images

**Location:** Below the image in each ad card

### 3. ✅ Clickable Banner Images
All banner images are now clickable when a URL is set.

**Behavior:**
- If URL is set → Image wrapped in `<a>` tag
- If URL is empty → Image displays normally (not clickable)
- Opens in new tab (`target="_blank"`)
- Security: `rel="noopener noreferrer"`

**Works in:**
- Ad manager preview section
- Individual ad cards
- Front-end integration (when implemented)

### 4. ✅ RSS Feed Includes URLs
Mobile banner feed now includes click URLs for each ad.

**Feed Structure:**
```xml
<item>
    <title>Banner Ad mad_1234567890_abc123</title>
    <link>https://example.com/landing-page</link>
    <ads:clickUrl>https://example.com/landing-page</ads:clickUrl>
    <ads:dimensions>320x50</ads:dimensions>
    <enclosure url="..." />
</item>
```

Mobile apps can read `<ads:clickUrl>` and make banners clickable.

### 5. ✅ Reduced Padding - Larger Images
**Before:** 20px padding around images (wasted space)
**After:** 10px padding (50% reduction)

**Result:** Images appear much larger in cards, better preview

### 6. ✅ Optimized Grid Layouts

**Web Ads (728x90):** 2 columns
- Better for wide banners
- Larger preview
- Less cramped

**Tablet Ads (728x90):** 2 columns
- Same as web ads
- Consistent layout

**Phone Ads (320x50):** 3 columns
- Perfect for narrow banners
- Efficient use of space
- More ads visible at once

**Responsive:** All grids collapse to 1 column on mobile

---

## Technical Implementation

### Backend Changes

**1. AdsXMLHandler.php**
- Added `url` field to `addWebAd()`
- Added `url` field to `addMobileAd()`
- Added `url` to `getWebAds()` return
- Added `url` to `getMobileAds()` return
- New method: `updateAdUrl($adId, $adType, $url)`

**2. New API Endpoint: api/update-ad-url.php**
```php
POST /api/update-ad-url.php
Parameters:
- ad_id: string
- ad_type: 'web' or 'mobile'
- url: string
```

**3. mobile-ads-feed.php**
- Added `<link>` tag with URL
- Added `<ads:clickUrl>` custom tag
- Fixed `<enclosure>` URL to use full path

### Frontend Changes

**1. ads-manager.php**
- Removed `.ad-id` display
- Added URL input field to each ad card
- Wrapped images in `<a>` tags when URL exists
- Updated all three sections (web, phone, tablet)
- Updated preview section with clickable images

**2. assets/css/ads-manager.css**
- Changed web grid: `repeat(2, 1fr)`
- Changed phone grid: `repeat(3, 1fr)`
- Changed tablet grid: `repeat(2, 1fr)`
- Reduced image padding: 20px → 10px
- Reduced info padding: 15px → 12px 15px
- Added `.ad-url-input` styles
- Responsive: all grids → 1 column on mobile

**3. assets/js/ads-manager.js**
- New function: `updateAdUrl(adType, adId, url)`
- AJAX call to save URL changes
- Auto-saves on input change

---

## XML Data Structure

```xml
<ad>
    <id>wad_1729600000_abc123</id>
    <filename>web_ad_1729600000_abc123.png</filename>
    <filepath>uploads/ads/web/web_ad_1729600000_abc123.png</filepath>
    <url>https://example.com/landing-page</url>  <!-- NEW -->
    <display_order>0</display_order>
    <created_at>2025-10-22 10:30:00</created_at>
</ad>
```

---

## User Workflow

### Adding URL to Banner
1. Upload banner image (appears in grid)
2. Type URL in input field below image
3. URL auto-saves on blur/change
4. Image becomes clickable
5. Click image → opens URL in new tab

### Testing Clickable Banner
1. Set URL: `https://google.com`
2. Click banner image in ad manager
3. New tab opens with Google
4. Works in preview section too

### Mobile Feed Integration
1. Mobile app fetches RSS feed
2. Reads `<ads:clickUrl>` for each banner
3. Displays banner as clickable
4. User taps banner → opens URL in browser

---

## Grid Layout Comparison

### Before (All 3 columns)
```
Web:    [Ad] [Ad] [Ad]     (Too cramped for 728x90)
Phone:  [Ad] [Ad] [Ad]     (OK)
Tablet: [Ad] [Ad] [Ad]     (Too cramped for 728x90)
```

### After (Optimized)
```
Web:    [  Ad  ] [  Ad  ]     (Perfect for 728x90)
Phone:  [Ad] [Ad] [Ad]        (Perfect for 320x50)
Tablet: [  Ad  ] [  Ad  ]     (Perfect for 728x90)
```

---

## Benefits

### For Advertisers
- ✅ Add click tracking URLs
- ✅ Drive traffic to landing pages
- ✅ Measure campaign effectiveness
- ✅ Change URLs without re-uploading

### For Users
- ✅ Cleaner interface (no cryptic IDs)
- ✅ Larger image previews
- ✅ Better grid layouts
- ✅ Easy URL management

### For Mobile Apps
- ✅ Clickable banners in feed
- ✅ URL included in RSS
- ✅ Dimensions for proper display
- ✅ Complete ad metadata

---

## Security

**URL Validation:**
- Stored as-is (no validation)
- Escaped with `htmlspecialchars()` on output
- XSS protection via proper escaping

**Link Security:**
- `target="_blank"` for new tab
- `rel="noopener noreferrer"` prevents:
  - Window.opener access
  - Referrer leaking
  - Tabnabbing attacks

---

## Testing Checklist

- [x] Upload web ad → URL input appears
- [x] Type URL → auto-saves
- [x] Click image with URL → opens new tab
- [x] Click image without URL → not clickable
- [x] Preview section → clickable when URL set
- [x] Phone ads → 3 columns
- [x] Tablet ads → 2 columns
- [x] Web ads → 2 columns
- [x] Images larger (less padding)
- [x] No ID text showing
- [x] RSS feed includes URL
- [x] Mobile responsive (1 column)

---

## API Reference

### Update Ad URL
```javascript
// JavaScript
updateAdUrl('web', 'wad_123_abc', 'https://example.com');

// PHP
$xmlHandler->updateAdUrl($adId, $adType, $url);
```

### Get Ads with URLs
```php
$webAds = $manager->getWebAds();
// Returns: [['id' => '...', 'url' => 'https://...', ...], ...]

$mobileAds = $manager->getMobileAds();
// Returns: [['id' => '...', 'url' => 'https://...', 'dimensions' => '320x50', ...], ...]
```

---

## Future Enhancements

### Possible Additions
1. **URL Validation:** Check if URL is valid before saving
2. **Click Tracking:** Track how many times banner is clicked
3. **UTM Parameters:** Auto-add tracking parameters
4. **QR Codes:** Generate QR code for mobile banners
5. **A/B Testing:** Test different URLs for same banner
6. **Analytics Dashboard:** View click-through rates

---

**Status:** ✅ Complete and tested
**Version:** 2.2
**Date:** October 22, 2025
**Files Modified:** 8 files
**Lines Changed:** ~300 lines
