# Sort Options Feature - Deep Planning Document

## Overview
This document outlines the comprehensive plan for implementing sorting functionality for the podcast directory, affecting both the admin interface and the public RSS feed. The design accounts for future automated feed health monitoring and bad feed disabling capabilities.

---

## 1. Feature Scope

### 1.1 Primary Objectives
- **Admin Interface Sorting**: Allow users to sort the podcast table by multiple criteria
- **Public Feed Sorting**: Apply sorting to the live RSS feed output (`feed.php`)
- **Persistent Preferences**: Remember user's sort preference across sessions
- **Extensible Design**: Architecture that supports future health status sorting

### 1.2 Sort Criteria (Phase 1)
1. **Date Added** (Created Date)
   - Newest First (default)
   - Oldest First
2. **Title** (Alphabetical)
   - A-Z
   - Z-A
3. **Status**
   - Active First
   - Inactive First

### 1.3 Future Sort Criteria (Phase 2 - Health Monitoring)
4. **Health Status**
   - Healthy First
   - Problematic First
   - Failed First
5. **Last Health Check**
   - Recently Checked
   - Needs Check
6. **Episode Count**
   - Most Episodes
   - Least Episodes

---

## 2. UI/UX Design

### 2.1 Sort Button Component
**Location**: Between "Podcast Directory" title and the podcast table

**Visual Design**:
```
┌─────────────────────────────────────────┐
│  PODCAST DIRECTORY                      │
│                                         │
│  [🔽 Sort By: Newest First ▼]          │
│  ─────────────────────────────────────  │
│  [Search podcasts...]          [Clear]  │
└─────────────────────────────────────────┘
```

**Button Specifications**:
- **Icon**: Font Awesome `fa-sort` or `fa-arrow-down-wide-short`
- **Style**: Secondary button style matching existing design
- **States**: 
  - Default: Shows current sort method
  - Hover: Highlight with border color change
  - Active/Open: Dropdown visible with accent color

### 2.2 Dropdown Menu Design
**Modern Dropdown Features**:
- Smooth slide-down animation (200ms ease)
- Dark theme matching existing UI (`--bg-tertiary`)
- Grouped sections for different sort types
- Active state indicator (checkmark icon)
- Hover states for each option

**Dropdown Structure**:
```
┌─────────────────────────────────┐
│ SORT BY DATE                    │
│ ✓ Newest First                  │
│   Oldest First                  │
├─────────────────────────────────┤
│ SORT BY TITLE                   │
│   A-Z                           │
│   Z-A                           │
├─────────────────────────────────┤
│ SORT BY STATUS                  │
│   Active First                  │
│   Inactive First                │
└─────────────────────────────────┘
```

### 2.3 Responsive Behavior
- **Desktop**: Dropdown aligned to button, max-width 280px
- **Tablet**: Full-width dropdown below button
- **Mobile**: Bottom sheet modal for better touch interaction

---

## 3. Technical Architecture

### 3.1 Frontend Implementation

#### 3.1.1 HTML Structure
```html
<!-- Sort Controls Container -->
<div class="sort-controls">
  <button id="sortButton" class="btn btn-secondary sort-button">
    <i class="fa-solid fa-arrow-down-wide-short"></i>
    <span id="currentSortLabel">Newest First</span>
    <i class="fa-solid fa-chevron-down sort-chevron"></i>
  </button>
  
  <div id="sortDropdown" class="sort-dropdown">
    <!-- Dynamically populated -->
  </div>
</div>
```

#### 3.1.2 CSS Styling
**New CSS File**: `assets/css/sort-controls.css`
- Sort button styles
- Dropdown container with positioning
- Animation keyframes
- Responsive breakpoints
- Dark theme integration

**Key CSS Classes**:
- `.sort-controls` - Container wrapper
- `.sort-button` - Button styling
- `.sort-dropdown` - Dropdown container
- `.sort-dropdown.show` - Active state
- `.sort-option` - Individual option
- `.sort-option.active` - Selected option
- `.sort-section` - Grouped sections

#### 3.1.3 JavaScript Implementation
**New JS File**: `assets/js/sort-manager.js`

**Class Structure**:
```javascript
class SortManager {
  constructor() {
    this.currentSort = this.loadSortPreference();
    this.sortOptions = this.defineSortOptions();
    this.init();
  }
  
  // Core Methods
  init()
  defineSortOptions()
  loadSortPreference()
  saveSortPreference(sortKey)
  applySortToTable(sortKey)
  applySortToFeed(sortKey)
  renderDropdown()
  toggleDropdown()
  selectSort(sortKey)
  
  // Sorting Logic
  sortByDateNewest(a, b)
  sortByDateOldest(a, b)
  sortByTitleAZ(a, b)
  sortByTitleZA(a, b)
  sortByStatusActive(a, b)
  sortByStatusInactive(a, b)
  
  // Future: Health Status Sorting
  sortByHealthStatus(a, b)
  sortByLastCheck(a, b)
}
```

**Sort Options Data Structure**:
```javascript
{
  'date-newest': {
    label: 'Newest First',
    icon: 'fa-calendar-plus',
    section: 'date',
    sortFn: 'sortByDateNewest',
    feedParam: 'sort=date&order=desc'
  },
  'date-oldest': {
    label: 'Oldest First',
    icon: 'fa-calendar-minus',
    section: 'date',
    sortFn: 'sortByDateOldest',
    feedParam: 'sort=date&order=asc'
  },
  // ... more options
}
```

### 3.2 Backend Implementation

#### 3.2.1 PodcastManager Modifications
**File**: `includes/PodcastManager.php`

**New Method**: `getAllPodcasts($activeOnly = false, $sortBy = 'date', $order = 'desc')`

```php
public function getAllPodcasts($activeOnly = false, $sortBy = 'date', $order = 'desc') {
    $podcasts = $this->xmlHandler->getAllPodcasts($activeOnly);
    
    // Apply sorting
    $podcasts = $this->sortPodcasts($podcasts, $sortBy, $order);
    
    return $podcasts;
}

private function sortPodcasts($podcasts, $sortBy, $order) {
    usort($podcasts, function($a, $b) use ($sortBy, $order) {
        $result = 0;
        
        switch($sortBy) {
            case 'date':
                $result = strtotime($a['created_date']) - strtotime($b['created_date']);
                break;
            case 'title':
                $result = strcasecmp($a['title'], $b['title']);
                break;
            case 'status':
                $statusOrder = ['active' => 1, 'inactive' => 0];
                $result = $statusOrder[$a['status']] - $statusOrder[$b['status']];
                break;
            // Future: health status sorting
            case 'health':
                $result = $this->compareHealthStatus($a, $b);
                break;
        }
        
        return ($order === 'desc') ? -$result : $result;
    });
    
    return $podcasts;
}
```

#### 3.2.2 Feed.php Modifications
**File**: `feed.php`

```php
// Accept sort parameters from URL
$sortBy = $_GET['sort'] ?? 'date';
$order = $_GET['order'] ?? 'desc';

// Validate parameters
$allowedSorts = ['date', 'title', 'status'];
$allowedOrders = ['asc', 'desc'];

if (!in_array($sortBy, $allowedSorts)) $sortBy = 'date';
if (!in_array($order, $allowedOrders)) $order = 'desc';

// Get sorted podcasts
$podcastManager = new PodcastManager();
$rssXml = $podcastManager->getRSSFeed($sortBy, $order);
```

#### 3.2.3 XMLHandler Modifications
**File**: `includes/XMLHandler.php`

- Ensure `getAllPodcasts()` returns data suitable for sorting
- Add metadata fields for future health status
- Optimize XML parsing for large datasets

---

## 4. Data Persistence

### 4.1 Client-Side Storage
**localStorage Key**: `podcast_sort_preference`

**Stored Data**:
```json
{
  "sortKey": "date-newest",
  "timestamp": 1697234567890
}
```

**Benefits**:
- Instant load on page refresh
- No server round-trip needed
- User-specific preferences

### 4.2 URL Parameters (Feed)
**Feed URL Structure**:
```
https://podcast.supersoul.top/feed.php?sort=date&order=desc
```

**Benefits**:
- Shareable sorted feeds
- RSS reader compatibility
- Cacheable by CDN

---

## 5. Future Integration: Health Monitoring

### 5.1 Database Schema Extension
**New XML Fields** (to be added to podcast entries):
```xml
<podcast id="...">
  <!-- Existing fields -->
  <title>...</title>
  <feed_url>...</feed_url>
  
  <!-- New health monitoring fields -->
  <health_status>healthy|warning|error|unknown</health_status>
  <last_health_check>2025-01-13T14:30:00Z</last_health_check>
  <health_check_count>42</health_check_count>
  <consecutive_failures>0</consecutive_failures>
  <episode_count>156</episode_count>
  <last_episode_date>2025-01-10T12:00:00Z</last_episode_date>
  <feed_errors>
    <error date="2025-01-12">Connection timeout</error>
  </feed_errors>
</podcast>
```

### 5.2 Health Status Definitions
1. **Healthy** (Green)
   - Feed accessible
   - Valid XML/RSS format
   - Episodes present
   - Last check < 24 hours ago

2. **Warning** (Yellow)
   - Feed accessible but slow (>5s)
   - No new episodes in 30+ days
   - Minor XML validation issues
   - Last check 24-48 hours ago

3. **Error** (Red)
   - Feed inaccessible (404, 500, timeout)
   - Invalid XML format
   - 3+ consecutive failures
   - Last check failed

4. **Unknown** (Gray)
   - Never checked
   - Last check > 7 days ago

### 5.3 Automated Health Scanning
**New Component**: `includes/PodcastHealthScanner.php`

**Features**:
- Cron job integration (daily scans)
- Individual podcast health checks
- Batch processing for all feeds
- Auto-disable after N consecutive failures
- Email notifications for critical failures

**Sort Integration**:
- Sort by health status (healthy → warning → error)
- Sort by last successful check
- Filter by health status
- Visual indicators in table (colored badges)

### 5.4 UI Enhancements for Health
**Table Column**: Add "Health" column
```
| Cover | Title | Feed URL | Status | Health | Created | Actions |
```

**Health Badge**:
```html
<span class="badge badge-health badge-healthy">
  <i class="fa-solid fa-heart-pulse"></i> Healthy
</span>
```

**Sort Dropdown Addition**:
```
├─────────────────────────────────┤
│ SORT BY HEALTH                  │
│   Healthy First                 │
│   Problems First                │
│   Never Checked                 │
└─────────────────────────────────┘
```

---

## 6. Implementation Phases

### Phase 1: Core Sorting (Week 1)
**Tasks**:
1. ✅ Create planning document
2. Design and implement sort button UI
3. Create `sort-manager.js` with basic sorting
4. Add CSS for sort controls
5. Implement localStorage persistence
6. Update `index.php` to include sort controls
7. Test admin interface sorting

**Deliverables**:
- Functional sort button with dropdown
- Client-side table sorting (date, title, status)
- Persistent sort preferences

### Phase 2: Feed Sorting (Week 1-2)
**Tasks**:
1. Modify `PodcastManager.php` to accept sort parameters
2. Update `feed.php` to handle URL parameters
3. Implement server-side sorting logic
4. Add feed URL parameter to sort manager
5. Test RSS feed with different sort orders
6. Update documentation

**Deliverables**:
- Sorted RSS feed output
- URL parameter support
- Feed validation

### Phase 3: Health Monitoring Foundation (Week 2-3)
**Tasks**:
1. Design health status data structure
2. Extend XML schema with health fields
3. Create `PodcastHealthChecker.php` enhancements
4. Add health status to admin table
5. Implement manual health check button
6. Add health status badges

**Deliverables**:
- Health status tracking
- Manual health checks
- Visual health indicators

### Phase 4: Automated Scanning (Week 3-4)
**Tasks**:
1. Create `PodcastHealthScanner.php`
2. Implement batch health checking
3. Add cron job configuration
4. Create auto-disable logic
5. Add email notifications
6. Build health dashboard

**Deliverables**:
- Automated health scanning
- Auto-disable bad feeds
- Health monitoring dashboard

### Phase 5: Health Sorting Integration (Week 4)
**Tasks**:
1. Add health sort options to dropdown
2. Implement health-based sorting
3. Add health filters
4. Create health status report
5. Final testing and optimization

**Deliverables**:
- Complete health-based sorting
- Health filtering
- Production-ready feature

---

## 7. Technical Considerations

### 7.1 Performance
- **Client-Side**: Sort ~100 podcasts in <50ms
- **Server-Side**: Cache sorted results for 5 minutes
- **Database**: Index on created_date, title, status fields
- **Feed**: Add ETag headers for caching

### 7.2 Accessibility
- Keyboard navigation for dropdown (Arrow keys, Enter, Escape)
- ARIA labels for screen readers
- Focus management
- High contrast mode support

### 7.3 Browser Compatibility
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Fallback for localStorage (cookies)
- Graceful degradation for older browsers

### 7.4 Security
- Sanitize sort parameters
- Prevent SQL injection (using XML, but still validate)
- Rate limiting on feed endpoint
- CSRF protection for admin actions

---

## 8. Testing Strategy

### 8.1 Unit Tests
- Sort function accuracy
- Parameter validation
- Edge cases (empty lists, single item)

### 8.2 Integration Tests
- Admin interface sorting
- Feed URL parameters
- localStorage persistence
- Health status integration

### 8.3 User Acceptance Tests
- Sort dropdown usability
- Mobile responsiveness
- Feed reader compatibility
- Performance with large datasets

### 8.4 Test Cases
1. Sort by newest → verify order
2. Sort by title A-Z → verify alphabetical
3. Change sort → verify persistence
4. Refresh page → verify saved preference
5. Feed URL with params → verify RSS output
6. Invalid sort param → verify fallback to default
7. Mobile dropdown → verify touch interaction
8. Keyboard navigation → verify accessibility

---

## 9. Documentation Updates

### 9.1 User Documentation
- **README.md**: Add sort feature description
- **Help Modal**: Update with sort instructions
- **Screenshots**: Update with new UI

### 9.2 Developer Documentation
- **API Documentation**: Document sort parameters
- **Code Comments**: Inline documentation
- **Architecture Diagram**: Update with new components

---

## 10. Success Metrics

### 10.1 Functional Metrics
- ✅ Sort works for all criteria
- ✅ Preferences persist across sessions
- ✅ Feed sorting works correctly
- ✅ No performance degradation

### 10.2 User Experience Metrics
- Sort dropdown opens in <100ms
- Table re-sorts in <200ms
- Mobile interaction feels native
- Zero accessibility violations

### 10.3 Future Metrics (Health Monitoring)
- 95%+ feeds checked daily
- Auto-disable after 3 failures
- <1% false positives
- Health dashboard load time <1s

---

## 11. Risks and Mitigations

### 11.1 Risks
1. **Performance**: Large podcast lists may slow sorting
   - **Mitigation**: Implement virtual scrolling, pagination
   
2. **Feed Compatibility**: Some RSS readers may ignore sort params
   - **Mitigation**: Default to sensible order (newest first)
   
3. **Health Check Load**: Checking 100+ feeds may timeout
   - **Mitigation**: Batch processing, async jobs, rate limiting

4. **False Positives**: Temporary network issues flagging healthy feeds
   - **Mitigation**: Require 3 consecutive failures before auto-disable

### 11.2 Rollback Plan
- Feature flag to disable sorting
- Database backup before health schema changes
- Ability to revert to previous feed.php

---

## 12. Future Enhancements

### 12.1 Advanced Sorting
- Multi-level sorting (e.g., Status → Date)
- Custom sort order (drag-and-drop)
- Save multiple sort presets

### 12.2 Filtering
- Combine sorting with filtering
- Filter by health status
- Filter by date range
- Search + Sort + Filter

### 12.3 Analytics
- Track most popular sort methods
- Feed access patterns
- Health trend analysis
- Predictive feed failure detection

### 12.4 Export/Import
- Export sorted podcast list (CSV, JSON)
- Bulk operations on sorted results
- Share sorted feed URLs

---

## 13. Code Style Guidelines

### 13.1 JavaScript
- ES6+ syntax
- Consistent naming (camelCase)
- JSDoc comments for public methods
- Error handling with try-catch

### 13.2 PHP
- PSR-12 coding standard
- Type hints where possible
- DocBlock comments
- Exception handling

### 13.3 CSS
- BEM naming convention
- CSS variables for theming
- Mobile-first approach
- Consistent spacing units

---

## 14. Deployment Checklist

### Pre-Deployment
- [ ] All tests passing
- [ ] Code review completed
- [ ] Documentation updated
- [ ] Performance benchmarks met
- [ ] Accessibility audit passed
- [ ] Browser testing completed

### Deployment
- [ ] Database backup
- [ ] Deploy to staging
- [ ] Smoke tests on staging
- [ ] Deploy to production
- [ ] Monitor error logs
- [ ] Verify feed output

### Post-Deployment
- [ ] User feedback collection
- [ ] Performance monitoring
- [ ] Bug tracking
- [ ] Iteration planning

---

## 15. Conclusion

This sorting feature provides a solid foundation for enhanced podcast directory management. The architecture is designed to be extensible, allowing seamless integration of health monitoring capabilities in future phases. By implementing sorting in phases, we can deliver value incrementally while building toward a comprehensive feed management system.

**Next Steps**:
1. Review and approve this plan
2. Begin Phase 1 implementation
3. Set up development environment
4. Create feature branch
5. Start coding!

---

**Document Version**: 1.0  
**Created**: January 13, 2025  
**Author**: Cascade AI  
**Status**: Planning Complete - Ready for Implementation
