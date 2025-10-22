# Ads Management System - Planning Document

## Overview
Create a comprehensive ads management system with two distinct sections:
1. **Web Banner Ads** - Display rotating banner ads on the podcast app front page
2. **Mobile/Tablet Banner Feed** - RSS feed of banner ads for mobile/tablet apps

## Design Reference
- Copy styling and drag-and-drop functionality from `self-hosted-podcasts.php` "Create New Podcast" section
- Maintain consistent dark theme and UI patterns
- Use existing image upload code patterns

---

## Section 1: Web Banner Ads

### Purpose
Display rotating banner ads on the front page of the web podcast app.

### Image Requirements
- **Leaderboard Size**: 728x90px (strict)
- **Validation**: Reject uploads that don't match exact dimensions
- **Error Handling**: Show styled modal with clear message about incorrect size

### Features

#### Upload Interface
- Beautiful drag-and-drop zone (copy from self-hosted-podcasts.php)
- Multiple image upload support
- Real-time image preview after upload
- Display uploaded images in a grid/list

#### Image Management
- Each uploaded image shows with:
  - Preview thumbnail
  - Styled X icon for deletion
  - Delete confirmation modal
- Ability to reorder images (for rotation sequence)

#### Display Logic
- **Single Image**: Display static banner
- **Multiple Images**: Rotate through images with fade transition
- **Rotation Timing**: User-defined duration (e.g., 5 seconds, 10 seconds, 30 seconds)
- **Transition**: Smooth fade effect between images

#### On/Off Toggle
- Master switch to enable/disable web banner ads
- When OFF: No ads display on front page
- When ON: Ads display according to rotation settings

#### Preview Section
- Live preview at top of interface showing how ads will appear
- Shows actual rotation behavior with timing
- Helps user visualize before publishing

---

## Section 2: Mobile/Tablet Banner Feed

### Purpose
Generate an RSS feed of banner ads for consumption by mobile/tablet apps.

### Image Requirements
- **Mobile Leaderboard Size**: 320x50px (strict)
- **Validation**: Reject uploads that don't match exact dimensions
- **Error Handling**: Show styled modal with clear message about incorrect size

### Features

#### Upload Interface
- Separate drag-and-drop zone (matching web ads section styling)
- Multiple image upload support
- Real-time image preview after upload
- Display uploaded images in a grid/list

#### Image Management
- Each uploaded image shows with:
  - Preview thumbnail
  - Styled X icon for deletion
  - Delete confirmation modal
- Ability to reorder images (for feed order)

#### RSS Feed Generation
- Create simple RSS feed listing all uploaded banner images
- Feed structure:
  ```xml
  <rss version="2.0">
    <channel>
      <title>Mobile Banner Ads</title>
      <item>
        <title>Banner Ad 1</title>
        <enclosure url="[image-url]" type="image/png" />
      </item>
      <!-- More items -->
    </channel>
  </rss>
  ```
- Feed URL: `/mobile-ads-feed.php` or similar
- Reference existing feed creation code from self-hosted-feed.php

#### On/Off Toggle
- Master switch to enable/disable mobile banner feed
- When OFF: Feed returns empty or disabled status
- When ON: Feed returns full list of banner images
- Mobile app checks this status and shows/hides ads accordingly

---

## Technical Implementation Plan

### Files to Create

1. **ads-manager.php** (Main interface page)
   - Two-section layout (Web Ads + Mobile Ads)
   - Drag-and-drop interfaces for both sections
   - Image preview grids
   - On/off toggles
   - Settings for rotation timing (web ads)
   - Live preview section (web ads)

2. **includes/AdsManager.php** (Business logic)
   - Image upload handling with strict dimension validation
   - Image storage management
   - Image deletion with file cleanup
   - Settings management (on/off, timing, order)
   - CRUD operations for both ad types

3. **includes/AdsImageUploader.php** (Image handling)
   - Strict dimension validation (728x90 and 320x50)
   - Image upload to storage
   - Image deletion from storage
   - Generate unique filenames
   - Storage path: `uploads/ads/web/` and `uploads/ads/mobile/`

4. **mobile-ads-feed.php** (RSS feed generator)
   - Generate RSS feed for mobile banner ads
   - Check on/off status
   - Return empty/disabled feed when OFF
   - List all active mobile banner images

5. **api/upload-ad.php** (AJAX endpoint)
   - Handle image upload requests
   - Validate dimensions
   - Return success/error responses
   - Support both web and mobile ad types

6. **api/delete-ad.php** (AJAX endpoint)
   - Handle image deletion requests
   - Remove from database and filesystem
   - Return success/error responses

7. **api/update-ad-settings.php** (AJAX endpoint)
   - Update on/off toggles
   - Update rotation timing
   - Update image order
   - Return success/error responses

8. **assets/js/ads-manager.js** (Frontend logic)
   - Drag-and-drop functionality
   - AJAX upload handling
   - Image preview rendering
   - Delete confirmation modals
   - Settings updates
   - Live preview rotation (web ads)

9. **assets/css/ads-manager.css** (Styling)
   - Match existing dark theme
   - Drag-and-drop zone styling
   - Image grid/list styling
   - Toggle switch styling
   - Modal styling

### Database Schema

#### Table: `web_banner_ads`
```sql
CREATE TABLE web_banner_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Table: `mobile_banner_ads`
```sql
CREATE TABLE mobile_banner_ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    filepath VARCHAR(255) NOT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### Table: `ads_settings`
```sql
CREATE TABLE ads_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Settings Keys:**
- `web_ads_enabled` (1 or 0)
- `mobile_ads_enabled` (1 or 0)
- `web_ads_rotation_duration` (seconds, e.g., 5, 10, 30)

---

## Frontend Display Implementation

### Web Banner Ads (Front Page)
- Create `includes/WebBannerDisplay.php`
- Check if web ads are enabled
- Fetch all web banner images
- Generate HTML/JS for rotation display
- Include in front page template

### Mobile App Integration
- Mobile app fetches `/mobile-ads-feed.php`
- Parses RSS feed for banner image URLs
- Checks enabled/disabled status
- Displays banners according to app logic

---

## User Flow

### Web Ads Management
1. Admin navigates to Ads Manager page
2. Sees "Web Banner Ads" section at top
3. Drags/drops 728x90 image or clicks to upload
4. If wrong size → Modal error with exact requirements
5. If correct size → Image uploads and appears in preview grid
6. Can upload multiple images
7. Can delete images (with confirmation)
8. Can reorder images (drag to reorder)
9. Sets rotation duration (dropdown: 5s, 10s, 30s, 60s)
10. Toggles "Enable Web Ads" switch ON
11. Views live preview showing rotation behavior
12. Ads now display on front page

### Mobile Ads Management
1. Admin scrolls to "Mobile Banner Feed" section
2. Drags/drops 320x50 image or clicks to upload
3. If wrong size → Modal error with exact requirements
4. If correct size → Image uploads and appears in preview grid
5. Can upload multiple images
6. Can delete images (with confirmation)
7. Can reorder images (affects feed order)
8. Toggles "Enable Mobile Ads" switch ON
9. RSS feed is now active and accessible
10. Mobile app fetches feed and displays banners

---

## Code References to Copy From

### Drag-and-Drop Upload
- **File**: `self-hosted-podcasts.php`
- **Section**: "Create New Podcast" modal
- **Lines**: Cover image upload zone with drag-and-drop

### Image Upload Handling
- **File**: `includes/ImageUploader.php`
- **Methods**: Image validation, upload, storage

### RSS Feed Generation
- **File**: `self-hosted-feed.php`
- **Methods**: XML generation, feed structure

### Modal Styling
- **File**: `self-hosted-podcasts.php`
- **Section**: Modals for create/edit/delete

### AJAX Patterns
- **File**: `assets/js/feed-cloner.js`
- **Methods**: AJAX upload, progress tracking, error handling

---

## Image Dimension Validation Logic

```php
function validateAdImageDimensions($tmpFile, $adType) {
    $imageInfo = getimagesize($tmpFile);
    
    if ($imageInfo === false) {
        return ['valid' => false, 'error' => 'Invalid image file'];
    }
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    
    $requirements = [
        'web' => ['width' => 728, 'height' => 90],
        'mobile' => ['width' => 320, 'height' => 50]
    ];
    
    $required = $requirements[$adType];
    
    if ($width !== $required['width'] || $height !== $required['height']) {
        return [
            'valid' => false,
            'error' => "Image must be exactly {$required['width']}x{$required['height']}px. Your image is {$width}x{$height}px."
        ];
    }
    
    return ['valid' => true];
}
```

---

## Frontend Rotation Logic (Web Ads)

```javascript
function initWebAdRotation(images, duration) {
    if (images.length === 0) return;
    if (images.length === 1) {
        // Display single static image
        displayStaticAd(images[0]);
        return;
    }
    
    let currentIndex = 0;
    const container = document.getElementById('web-ad-container');
    
    function showNextAd() {
        // Fade out current
        container.style.opacity = 0;
        
        setTimeout(() => {
            // Change image
            container.style.backgroundImage = `url(${images[currentIndex]})`;
            // Fade in
            container.style.opacity = 1;
            
            currentIndex = (currentIndex + 1) % images.length;
        }, 500); // Fade duration
    }
    
    // Initial display
    container.style.backgroundImage = `url(${images[0]})`;
    container.style.opacity = 1;
    
    // Start rotation
    setInterval(showNextAd, duration * 1000);
}
```

---

## Mobile Feed RSS Structure

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:ads="http://podcast-app.com/ads">
    <channel>
        <title>Mobile Banner Ads</title>
        <description>Banner advertisements for mobile app</description>
        <ads:enabled>true</ads:enabled>
        
        <item>
            <title>Banner Ad 1</title>
            <guid>ad-12345</guid>
            <enclosure url="https://your-domain.com/uploads/ads/mobile/banner1.png" 
                       length="12345" 
                       type="image/png" />
            <ads:dimensions>320x50</ads:dimensions>
        </item>
        
        <item>
            <title>Banner Ad 2</title>
            <guid>ad-12346</guid>
            <enclosure url="https://your-domain.com/uploads/ads/mobile/banner2.png" 
                       length="12346" 
                       type="image/png" />
            <ads:dimensions>320x50</ads:dimensions>
        </item>
    </channel>
</rss>
```

---

## Questions to Clarify

1. **Storage Location**: Should ads be stored in `uploads/ads/` or a different location?

2. **Access Control**: Should ads management be admin-only, or available to other user roles?

3. **Click Tracking**: Do we need to track clicks on banner ads, or just display them?

4. **Ad Links**: Should banner ads be clickable links to external URLs? If yes, need to store URL with each image.

5. **Rotation Order**: Should images rotate in upload order, or should user be able to manually reorder?

6. **Feed Authentication**: Should mobile ads feed be public or require authentication?

7. **Image Formats**: Accept only PNG, or also JPG/GIF/WebP?

8. **Max Images**: Any limit on number of banner ads per section?

9. **Preview Placement**: Where exactly on front page should web banners display? (Top, bottom, sidebar?)

10. **Transition Effects**: Just fade, or also support slide/other effects?

---

## Next Steps

1. Review this plan and clarify any questions
2. Create database tables
3. Build backend classes (AdsManager, AdsImageUploader)
4. Build AJAX endpoints
5. Build main interface (ads-manager.php)
6. Build frontend JavaScript
7. Build mobile RSS feed generator
8. Integrate web banner display into front page
9. Test all functionality
10. Document usage

---

## Estimated Complexity

- **Backend**: ~600-800 lines of PHP
- **Frontend**: ~400-500 lines of JavaScript
- **HTML/CSS**: ~300-400 lines
- **Total**: ~1,300-1,700 lines of new code

**Time Estimate**: 4-6 hours of development + testing

---

## Success Criteria

- ✅ Upload web banners (728x90) with strict validation
- ✅ Upload mobile banners (320x50) with strict validation
- ✅ Reject wrong dimensions with clear error modal
- ✅ Display uploaded images with delete functionality
- ✅ Delete confirmation modals work correctly
- ✅ Web ads rotate with smooth fade transitions
- ✅ Rotation duration is configurable
- ✅ On/off toggles work for both sections
- ✅ Live preview shows actual rotation behavior
- ✅ Mobile RSS feed generates correctly
- ✅ Mobile feed respects on/off toggle
- ✅ Web banners display on front page when enabled
- ✅ All styling matches existing dark theme
- ✅ Drag-and-drop works smoothly
- ✅ AJAX operations are fast and reliable

