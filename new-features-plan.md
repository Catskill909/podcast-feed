# New Features Implementation Plan

**Project:** PodFeed Builder  
**Date:** 2025-10-10  
**Features:** RSS Auto-Import, Podcast Validation & Health Check, Preview Cards

---

## 🎯 Overview

This document outlines the implementation plan for three key features that will significantly enhance the PodFeed Builder platform:

1. **RSS Feed Auto-Import** - Quickly populate the directory from existing RSS feeds
2. **Podcast Validation & Health Check** - Ensure feed quality and uptime
3. **Podcast Preview Cards** - Enhanced hover interactions for better UX

---

## 📋 Feature 1: RSS Feed Auto-Import

### **Goal**
Allow users to paste any podcast RSS feed URL and automatically extract all relevant information, with a preview before importing.

### **User Flow**
1. User clicks "Import from RSS" button (new button next to "Add New Podcast")
2. Modal opens with URL input field
3. User pastes RSS feed URL (e.g., `https://feed.podbean.com/laborradiopodcastweekly/feed.xml`)
4. System fetches and parses the feed
5. Preview shows extracted data: title, description, cover image, episode count
6. User confirms or edits data
7. Podcast is added to directory

### **Technical Implementation**

#### **Frontend (New Modal)**
- **File:** `index.php`
- Add new modal: `#importRssModal`
- Form fields:
  - RSS Feed URL (required, with validation)
  - Preview section (hidden until feed is fetched)
  - Edit fields for extracted data
  - Import button

#### **Backend (RSS Parser)**
- **New File:** `includes/RssFeedParser.php`
- **Methods:**
  ```php
  class RssFeedParser {
      public function fetchFeed($url)
      public function parseChannel($xml)
      public function extractTitle($xml)
      public function extractDescription($xml)
      public function extractImage($xml)
      public function extractEpisodeCount($xml)
      public function downloadCoverImage($imageUrl, $podcastId)
      public function validate($url)
  }
  ```

#### **New Endpoint**
- **File:** `api/import-rss.php`
- **Method:** POST
- **Parameters:** `feed_url`
- **Response:**
  ```json
  {
    "success": true,
    "data": {
      "title": "Podcast Name",
      "description": "Description...",
      "image_url": "https://...",
      "episode_count": 150,
      "feed_url": "https://..."
    }
  }
  ```

#### **JavaScript**
- **File:** `assets/js/app.js`
- New methods:
  - `showImportRssModal()`
  - `hideImportRssModal()`
  - `fetchRssFeed(url)`
  - `previewRssFeed(data)`
  - `importRssFeed(data)`

### **Error Handling**
- Invalid URL format
- Feed not accessible (404, timeout)
- Invalid RSS/XML structure
- Missing required fields (title)
- Image download failures
- Duplicate feed detection

### **UI/UX Considerations**
- Loading spinner while fetching feed
- Clear error messages
- Preview shows actual cover image
- Ability to edit extracted data before import
- Cancel option at any stage

### **Database Changes**
- No schema changes needed
- Uses existing `podcasts.xml` structure

### **Dependencies**
- PHP SimpleXML or DOMDocument for parsing
- cURL for fetching remote feeds
- Image validation and download utilities

---

## 📋 Feature 2: Podcast Validation & Health Check

### **Goal**
Automatically verify that podcast feeds are active, images are loading, and RSS feeds are valid. Alert users to broken feeds.

### **User Flow**
1. System runs automated checks (daily cron job)
2. Dashboard shows health status for each podcast
3. User can manually trigger health check
4. Broken feeds are highlighted with warning badges
5. User receives notifications for issues
6. Health check results stored in history

### **Technical Implementation**

#### **Backend (Health Checker)**
- **New File:** `includes/PodcastHealthChecker.php`
- **Methods:**
  ```php
  class PodcastHealthChecker {
      public function checkFeedUrl($url)          // HTTP status, response time
      public function checkImageUrl($url)         // Image accessible, valid format
      public function validateRssFeed($url)       // Valid XML, required fields
      public function checkAllPodcasts()          // Run checks on all podcasts
      public function getHealthStatus($podcastId) // Get current health status
      public function getHealthHistory($podcastId)// Get check history
      public function sendAlerts($issues)         // Email/log broken feeds
  }
  ```

#### **Health Check Data Structure**
- **New File:** `data/health-checks.xml`
- **Structure:**
  ```xml
  <health_checks>
      <check>
          <podcast_id>abc123</podcast_id>
          <timestamp>2025-10-10T10:00:00</timestamp>
          <feed_status>active</feed_status>
          <feed_response_time>250ms</feed_response_time>
          <image_status>active</image_status>
          <rss_valid>true</rss_valid>
          <issues>
              <issue>Slow response time (>2s)</issue>
          </issues>
      </check>
  </health_checks>
  ```

#### **Cron Job / Scheduled Task**
- **New File:** `cron/daily-health-check.php`
- Runs daily at 2 AM
- Checks all active podcasts
- Logs results
- Sends email if critical issues found

#### **Manual Health Check**
- **New Endpoint:** `api/health-check.php`
- **Method:** POST
- **Parameters:** `podcast_id` (optional, checks all if not provided)
- **Response:**
  ```json
  {
    "success": true,
    "results": [
      {
        "podcast_id": "abc123",
        "feed_status": "active",
        "image_status": "active",
        "rss_valid": true,
        "response_time": "250ms",
        "issues": []
      }
    ]
  }
  ```

#### **Frontend Updates**
- **File:** `index.php`
- Add health status badges to podcast list:
  - 🟢 Green: All checks passed
  - 🟡 Yellow: Warning (slow response)
  - 🔴 Red: Critical (feed down, image broken)
- Add "Run Health Check" button in header
- Show last check timestamp
- Health check modal with detailed results

#### **JavaScript**
- **File:** `assets/js/app.js`
- New methods:
  - `runHealthCheck(podcastId = null)`
  - `showHealthCheckResults(results)`
  - `displayHealthBadge(status)`

### **Health Check Criteria**

| Check | Pass | Warning | Fail |
|-------|------|---------|------|
| Feed URL | HTTP 200, <2s | HTTP 200, 2-5s | 404, 500, timeout |
| Image URL | HTTP 200, valid image | Slow load | 404, invalid format |
| RSS Valid | Valid XML, has title | Missing optional fields | Invalid XML |

### **Notifications**
- Email alerts for critical failures
- In-app notification badge
- Weekly health summary report

### **UI/UX Considerations**
- Non-intrusive health badges
- Detailed health report in modal
- Historical trend (last 7 days)
- Quick fix suggestions
- Ability to dismiss warnings

---

## 📋 Feature 3: Podcast Preview Cards

### **Goal**
Show rich preview cards on hover with full details and quick actions, eliminating the need to open edit modal for quick tasks.

### **User Flow**
1. User hovers over any podcast row
2. Preview card appears next to cursor (or anchored to row)
3. Card shows: full description, large cover image, stats, status
4. Quick action buttons: Edit, Delete, Toggle Status
5. Card disappears on mouse leave (with small delay)

### **Technical Implementation**

#### **Frontend (Preview Card Component)**
- **File:** `assets/css/components.css`
- New CSS classes:
  ```css
  .preview-card
  .preview-card-image
  .preview-card-content
  .preview-card-title
  .preview-card-description
  .preview-card-stats
  .preview-card-actions
  .preview-card-badge
  ```

#### **HTML Structure**
- **File:** `index.php`
- Add data attributes to podcast rows:
  ```html
  <tr data-podcast-id="abc123" 
      data-preview-title="Podcast Title"
      data-preview-description="Full description..."
      data-preview-image="uploads/covers/abc123.jpg"
      data-preview-status="active"
      data-preview-created="Oct 9, 2025">
  ```

#### **Preview Card Template**
```html
<div id="previewCard" class="preview-card" style="display: none;">
    <div class="preview-card-image">
        <img src="" alt="">
        <span class="preview-card-badge"></span>
    </div>
    <div class="preview-card-content">
        <h3 class="preview-card-title"></h3>
        <p class="preview-card-description"></p>
        <div class="preview-card-stats">
            <div class="stat-item">
                <i class="fa-solid fa-calendar"></i>
                <span class="stat-value"></span>
            </div>
            <div class="stat-item">
                <i class="fa-solid fa-link"></i>
                <span class="stat-label">Feed Active</span>
            </div>
        </div>
        <div class="preview-card-actions">
            <button class="btn btn-sm btn-secondary" onclick="editPodcast()">
                <i class="fa-solid fa-edit"></i> Edit
            </button>
            <button class="btn btn-sm btn-secondary" onclick="toggleStatus()">
                <i class="fa-solid fa-toggle-on"></i> Toggle
            </button>
            <button class="btn btn-sm btn-danger" onclick="deletePodcast()">
                <i class="fa-solid fa-trash"></i> Delete
            </button>
        </div>
    </div>
</div>
```

#### **JavaScript**
- **File:** `assets/js/app.js`
- New methods:
  ```javascript
  showPreviewCard(podcastData, position)
  hidePreviewCard()
  positionPreviewCard(mouseX, mouseY)
  populatePreviewCard(data)
  ```

#### **Event Listeners**
```javascript
// Attach to all podcast rows
document.querySelectorAll('.podcast-row').forEach(row => {
    let hoverTimeout;
    
    row.addEventListener('mouseenter', (e) => {
        hoverTimeout = setTimeout(() => {
            const data = {
                id: row.dataset.podcastId,
                title: row.dataset.previewTitle,
                description: row.dataset.previewDescription,
                image: row.dataset.previewImage,
                status: row.dataset.previewStatus,
                created: row.dataset.previewCreated
            };
            showPreviewCard(data, {x: e.clientX, y: e.clientY});
        }, 500); // 500ms delay before showing
    });
    
    row.addEventListener('mouseleave', () => {
        clearTimeout(hoverTimeout);
        setTimeout(hidePreviewCard, 200); // 200ms delay before hiding
    });
});
```

### **Positioning Logic**
- Card appears to the right of cursor if space available
- Falls back to left if near right edge
- Adjusts vertically to stay in viewport
- Smooth fade-in animation

### **UI/UX Considerations**
- 500ms delay before showing (prevents accidental triggers)
- 200ms delay before hiding (allows moving to card)
- Card stays visible when hovering over it
- Smooth animations (fade in/out, slide)
- Responsive design (disable on mobile, use tap instead)
- Max width: 400px
- Max description length: 200 chars with "Read more..."
- Large, clear cover image (200x200px)

### **Accessibility**
- Keyboard navigation support
- Focus trap when card is open
- ARIA labels for screen readers
- Escape key to close
- Skip to actions with Tab

---

## 🔄 Implementation Order

### **Phase 1: Foundation (Week 1)**
1. ✅ Create `new-features-plan.md`
2. Create `includes/RssFeedParser.php`
3. Create `includes/PodcastHealthChecker.php`
4. Set up `data/health-checks.xml` structure

### **Phase 2: RSS Auto-Import (Week 2)**
1. Build import RSS modal UI
2. Implement RSS parser backend
3. Create `api/import-rss.php` endpoint
4. Add JavaScript for fetch/preview/import
5. Test with various RSS feeds
6. Error handling and validation

### **Phase 3: Health Check System (Week 3)**
1. Implement health checker class
2. Create health check API endpoint
3. Build cron job for daily checks
4. Add health status badges to UI
5. Create health check results modal
6. Set up email notifications

### **Phase 4: Preview Cards (Week 4)**
1. Design and style preview card component
2. Add data attributes to podcast rows
3. Implement JavaScript hover logic
4. Add positioning and animation
5. Integrate quick actions
6. Mobile/tablet responsive handling

### **Phase 5: Testing & Polish (Week 5)**
1. Cross-browser testing
2. Mobile device testing
3. Performance optimization
4. User feedback collection
5. Bug fixes
6. Documentation updates

---

## 📊 Success Metrics

### **RSS Auto-Import**
- ✅ Successfully import 95%+ of valid RSS feeds
- ✅ Import time < 5 seconds for typical feed
- ✅ Accurate data extraction (title, description, image)
- ✅ User can edit before confirming import

### **Health Check**
- ✅ Daily automated checks run successfully
- ✅ 100% of broken feeds detected within 24 hours
- ✅ False positive rate < 5%
- ✅ Email alerts sent within 1 hour of detection

### **Preview Cards**
- ✅ Cards appear within 500ms of hover
- ✅ No performance impact on page load
- ✅ Quick actions work 100% of the time
- ✅ Positive user feedback on UX improvement

---

## 🛠️ Technical Requirements

### **Server Requirements**
- PHP 7.4+ (for RSS parsing)
- cURL enabled
- Cron job access (for daily health checks)
- Write permissions for `data/` directory

### **Browser Support**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### **Dependencies**
- Font Awesome 6.5.1 (already included)
- No new external libraries needed
- Uses existing CSS framework

---

## 🚨 Potential Challenges & Solutions

### **Challenge 1: RSS Feed Variations**
- **Problem:** Different RSS formats (RSS 2.0, Atom, iTunes)
- **Solution:** Support multiple formats, fallback to basic fields

### **Challenge 2: Large RSS Feeds**
- **Problem:** Feeds with 1000+ episodes may timeout
- **Solution:** Set timeout limits, parse only channel info (not episodes)

### **Challenge 3: Cron Job Access**
- **Problem:** Shared hosting may not allow cron jobs
- **Solution:** Fallback to manual checks, or trigger on user visits

### **Challenge 4: Preview Card Performance**
- **Problem:** Many hover events may cause lag
- **Solution:** Debounce hover events, use CSS transforms for animations

### **Challenge 5: Image Download Failures**
- **Problem:** Some podcast images may be behind auth or broken
- **Solution:** Graceful fallback to placeholder, retry logic

---

## 📝 Next Steps

1. **Review this plan** with team/stakeholders
2. **Create GitHub issues** for each feature
3. **Set up development branch** for new features
4. **Begin Phase 1** implementation
5. **Schedule weekly check-ins** to track progress

---

## 📚 Additional Notes

- All features should maintain backward compatibility
- Existing podcasts should work without modification
- Consider adding feature flags for gradual rollout
- Document all new API endpoints
- Update user guide with new features

---

**Last Updated:** 2025-10-10  
**Status:** Planning Phase  
**Next Review:** After Phase 1 completion
