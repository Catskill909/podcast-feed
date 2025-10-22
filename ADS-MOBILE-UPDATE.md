# Ads Manager - Mobile Feed Update

## Changes Made (Oct 22, 2025)

### 1. ✅ Added "Ads Manager" to Admin Menu
**Location:** `admin.php` navigation bar

**Change:**
```html
<li><a href="ads-manager.php"><i class="fa-solid fa-ad"></i> Ads Manager</a></li>
```

Now accessible from the top menu alongside "Public Site", "My Podcasts", etc.

---

### 2. ✅ Mobile Feed Now Accepts Two Sizes

**Before:** Only 320x50px (phone)
**After:** Both 320x50px (phone) AND 728x90px (tablet)

#### Backend Changes

**AdsImageUploader.php:**
- Updated validation to accept both dimensions
- Error message shows both accepted sizes
- Detects which size was uploaded

**AdsManager.php:**
- Detects image dimensions after upload
- Stores dimensions with each ad

**AdsXMLHandler.php:**
- Added `dimensions` field to mobile ads
- Stores actual dimensions (320x50 or 728x90)

**mobile-ads-feed.php:**
- RSS feed includes actual dimensions in `<ads:dimensions>` tag
- Mobile app can read dimensions and display accordingly

---

### 3. ✅ Separate Phone & Tablet Sections in UI

**New Interface Structure:**

```
Mobile Banner Feed
├── Upload Zone (accepts both sizes)
├── Phone Banners (320x50)
│   └── Grid of phone ads
├── Tablet Banners (728x90)
│   └── Grid of tablet ads
└── RSS Feed URL
```

#### Visual Features

**Phone Section:**
- Icon: 📱 (mobile-screen)
- Header: "Phone Banners (320x50)"
- Shows only 320x50 ads
- Separate sortable grid

**Tablet Section:**
- Icon: 📱 (tablet-screen-button)
- Header: "Tablet Banners (728x90)"
- Shows only 728x90 ads
- Separate sortable grid

**Each Ad Card Shows:**
- Device icon (phone or tablet)
- Dimensions badge (320x50 or 728x90)
- Image preview
- Ad ID
- Upload date
- Delete button
- Drag handle

---

### 4. ✅ Updated Upload Requirements

**Upload Zone Text:**
```
Phone: 320x50px or Tablet: 728x90px • Max 2MB • JPG, PNG, or GIF
```

**Validation:**
- Accepts 320x50 → Adds to phone section
- Accepts 728x90 → Adds to tablet section
- Rejects anything else with clear error

**Error Message Example:**
```
Mobile Banner must be either 320x50px (Phone) or 728x90px (Tablet). 
Your image is 400x60px.
```

---

### 5. ✅ RSS Feed Enhancement

**Feed Structure:**
```xml
<item>
    <title>Banner Ad mad_1234567890_abc123</title>
    <guid>mad_1234567890_abc123</guid>
    <enclosure url="..." length="..." type="..." />
    <ads:dimensions>320x50</ads:dimensions>  <!-- or 728x90 -->
    <ads:displayOrder>0</ads:displayOrder>
</item>
```

**Mobile App Integration:**
- Reads `<ads:dimensions>` tag
- Displays phone ads (320x50) on phones
- Displays tablet ads (728x90) on tablets
- Can filter by dimension if needed

---

## Technical Implementation

### Files Modified (8 files)

1. **admin.php**
   - Added "Ads Manager" link to navigation

2. **includes/AdsImageUploader.php**
   - Updated `$mobileAdsDimensions` to array with phone/tablet
   - Modified `validateDimensions()` to accept both sizes

3. **includes/AdsManager.php**
   - Added dimension detection in `uploadMobileAd()`
   - Passes dimensions to XML handler

4. **includes/AdsXMLHandler.php**
   - Added `$dimensions` parameter to `addMobileAd()`
   - Stores dimensions in XML
   - Returns dimensions in `getMobileAds()`

5. **mobile-ads-feed.php**
   - Outputs actual dimensions in feed

6. **ads-manager.php**
   - Filters ads by dimensions (phone vs tablet)
   - Separate sections with device headers
   - Shows dimensions badge on each ad

7. **assets/css/ads-manager.css**
   - Added `.device-section` styles
   - Added `.device-header` styles
   - Added `.ad-dimensions` badge styles

8. **assets/js/ads-manager.js**
   - Separate Sortable instances for phone/tablet grids
   - Combined ordering for mobile ads

---

## User Workflow

### Uploading Phone Ad (320x50)
1. User drags 320x50 image to upload zone
2. System validates dimensions
3. Ad uploads successfully
4. Appears in "Phone Banners" section
5. Added to RSS feed with `<ads:dimensions>320x50</ads:dimensions>`

### Uploading Tablet Ad (728x90)
1. User drags 728x90 image to upload zone
2. System validates dimensions
3. Ad uploads successfully
4. Appears in "Tablet Banners" section
5. Added to RSS feed with `<ads:dimensions>728x90</ads:dimensions>`

### Uploading Wrong Size
1. User drags 400x60 image
2. System rejects with error modal
3. Modal shows: "Must be 320x50 or 728x90. Your image is 400x60."
4. User can try again with correct size

---

## XML Data Structure

```xml
<adsconfig>
    <settings>
        <mobile_ads_enabled>1</mobile_ads_enabled>
    </settings>
    <mobileads>
        <ad>
            <id>mad_1729600000_abc123</id>
            <filename>mobile_ad_1729600000_abc123.png</filename>
            <filepath>/path/to/file.png</filepath>
            <dimensions>320x50</dimensions>  <!-- NEW FIELD -->
            <display_order>0</display_order>
            <created_at>2025-10-22 10:30:00</created_at>
        </ad>
        <ad>
            <id>mad_1729600100_xyz789</id>
            <filename>mobile_ad_1729600100_xyz789.png</filename>
            <filepath>/path/to/file.png</filepath>
            <dimensions>728x90</dimensions>  <!-- NEW FIELD -->
            <display_order>1</display_order>
            <created_at>2025-10-22 10:31:00</created_at>
        </ad>
    </mobileads>
</adsconfig>
```

---

## Benefits

### For Users
- ✅ Single interface for both phone and tablet ads
- ✅ Clear visual separation by device type
- ✅ Easy to see which ads are for which devices
- ✅ Drag-and-drop reordering within each section

### For Mobile Apps
- ✅ Single RSS feed for all mobile ads
- ✅ Dimensions included in feed
- ✅ Can filter/display based on device
- ✅ Flexible ad delivery

### For Maintenance
- ✅ Backward compatible (existing 320x50 ads still work)
- ✅ Clean separation of concerns
- ✅ Easy to add more sizes in future

---

## Testing Checklist

- [x] Admin menu shows "Ads Manager" link
- [x] Link navigates to ads-manager.php
- [x] Upload 320x50 image → appears in Phone section
- [x] Upload 728x90 image → appears in Tablet section
- [x] Upload wrong size → shows error modal
- [x] Phone section shows only 320x50 ads
- [x] Tablet section shows only 728x90 ads
- [x] Dimensions badge displays correctly
- [x] Device icons show correctly
- [x] Drag-and-drop works in both sections
- [x] Delete works for both types
- [x] RSS feed includes dimensions
- [x] On/off toggle works
- [x] Empty state shows when no ads

---

## Backward Compatibility

**Existing Ads:**
- Old mobile ads without dimensions default to "320x50"
- Will appear in Phone section
- RSS feed works correctly
- No data migration needed

**New Ads:**
- All new uploads include dimensions
- Properly categorized
- Full functionality

---

## Future Enhancements

### Possible Additions
1. **More Sizes:** Easy to add 300x250, 468x60, etc.
2. **Device Targeting:** Specific ads for specific devices
3. **Scheduling:** Different ads at different times
4. **Geolocation:** Different ads by region
5. **Analytics:** Track which size performs better

---

**Status:** ✅ Complete and tested
**Version:** 2.1
**Date:** October 22, 2025
**Lines Changed:** ~200 lines across 8 files
