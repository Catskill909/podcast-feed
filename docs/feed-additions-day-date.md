# Feed Additions: Date Elements & Episode Count

**Date:** November 3, 2025  
**Status:** Planning Phase

---

## 🎯 Objective

Add the same date/episode information that appears on the front-end podcast cards to the RSS feed output (`feed.php`). This will provide consuming applications (like the Flutter app) with rich metadata about podcast freshness and episode counts.

---

## 📊 Current State Analysis

### **Front-End Display (index.php cards)**
Based on the screenshots and `browse.js` code, each podcast card shows:

1. **"New" Badge** - Green badge if latest episode is within 7 days
2. **Episode Count Badge** - Shows "X Episodes" on the card overlay
3. **Relative Date Text** - Shows one of:
   - "Today" (0 days ago)
   - "Yesterday" (1 day ago)
   - "X days ago" (2-6 days)
   - "X week(s) ago" (7-29 days)
   - "MMM DD, YYYY" (30+ days)

### **Current RSS Feed Output (feed.php)**
Located in: `/includes/XMLHandler.php` → `generateRSSFeedFromData()` method (lines 559-642)

**Current `<item>` structure:**
```xml
<item>
    <title>Podcast Title</title>
    <description>Podcast description</description>
    <link>https://feed-url.com/rss</link>
    <guid>podcast_id</guid>
    <pubDate>RFC 2822 date</pubDate>
    <enclosure url="cover_image_url" type="image/jpeg"/>
</item>
```

**What's Missing:**
- Episode count
- "New" indicator
- Human-readable relative date text

---

## 🔍 Date Formatting Logic (from browse.js)

The front-end uses this logic in `browse.js` lines 270-300:

```javascript
formatDate(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    
    // Reset to midnight for accurate day comparison
    const dateOnly = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const nowOnly = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    const diffTime = nowOnly - dateOnly;
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays === 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;
    if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        return `${weeks} week${weeks !== 1 ? 's' : ''} ago`;
    }
    
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
    });
}
```

**"New" Badge Logic (lines 252-265):**
```javascript
isNewEpisode(dateString) {
    const episodeDate = new Date(dateString);
    const now = new Date();
    const diffTime = now - episodeDate;
    const diffDays = diffTime / (1000 * 60 * 60 * 24);
    
    return diffDays <= 7;
}
```

---

## 📋 Implementation Plan

### **Phase 1: Add Helper Method to XMLHandler.php**

Create a new private method `formatRelativeDate()` in `XMLHandler.php` that mirrors the JavaScript logic:

```php
/**
 * Format date as relative time (Today, Yesterday, X days ago, etc.)
 * Matches the logic in browse.js for consistency
 */
private function formatRelativeDate($dateString) {
    if (empty($dateString)) {
        return 'Unknown';
    }
    
    try {
        $date = new DateTime($dateString);
        $now = new DateTime();
        
        // Reset to midnight for accurate day comparison
        $dateOnly = new DateTime($date->format('Y-m-d'));
        $nowOnly = new DateTime($now->format('Y-m-d'));
        
        $diff = $nowOnly->diff($dateOnly);
        $diffDays = (int)$diff->format('%r%a'); // Signed days
        
        if ($diffDays === 0) return 'Today';
        if ($diffDays === 1) return 'Yesterday';
        if ($diffDays < 7) return $diffDays . ' days ago';
        if ($diffDays < 30) {
            $weeks = floor($diffDays / 7);
            return $weeks . ' week' . ($weeks !== 1 ? 's' : '') . ' ago';
        }
        
        return $date->format('M j, Y');
    } catch (Exception $e) {
        return $dateString;
    }
}
```

### **Phase 2: Add Helper Method for "New" Check**

```php
/**
 * Check if episode is new (within last 7 days)
 * Matches the logic in browse.js
 */
private function isNewEpisode($dateString) {
    if (empty($dateString)) {
        return false;
    }
    
    try {
        $episodeDate = new DateTime($dateString);
        $now = new DateTime();
        $diff = $now->diff($episodeDate);
        $diffDays = (int)$diff->format('%a');
        
        return $diffDays <= 7;
    } catch (Exception $e) {
        return false;
    }
}
```

### **Phase 3: Extend RSS Feed Items**

Modify the `generateRSSFeedFromData()` method (around line 592-636) to add custom elements:

**Option A: Use Custom Namespace (Recommended)**
```xml
<rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:podfeed="http://podfeed.studio/xmlns">
    <channel>
        <item>
            <title>Podcast Title</title>
            <description>Podcast description</description>
            <link>https://feed-url.com/rss</link>
            <guid>podcast_id</guid>
            <pubDate>RFC 2822 date</pubDate>
            <enclosure url="cover_image_url" type="image/jpeg"/>
            
            <!-- NEW ELEMENTS -->
            <podfeed:episodeCount>127</podfeed:episodeCount>
            <podfeed:isNew>true</podfeed:isNew>
            <podfeed:relativeDate>2 days ago</podfeed:relativeDate>
            <podfeed:latestEpisodeDate>2025-11-01</podfeed:latestEpisodeDate>
        </item>
    </channel>
</rss>
```

**Option B: Use Dublin Core Namespace (Standards-Compliant)**
```xml
<rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:dc="http://purl.org/dc/elements/1.1/">
    <channel>
        <item>
            <!-- Standard elements -->
            <title>Podcast Title</title>
            <description>Podcast description</description>
            <link>https://feed-url.com/rss</link>
            <guid>podcast_id</guid>
            <pubDate>RFC 2822 date</pubDate>
            <enclosure url="cover_image_url" type="image/jpeg"/>
            
            <!-- NEW ELEMENTS -->
            <dc:extent>127 episodes</dc:extent>  <!-- Episode count -->
            <dc:date>2025-11-01</dc:date>        <!-- ISO 8601 date -->
            <description>2 days ago | 127 Episodes | New</description>  <!-- Enhanced description -->
        </item>
    </channel>
</rss>
```

**Option C: Extend Description Field (Most Compatible)**
```xml
<item>
    <title>Podcast Title</title>
    <description>Podcast description | Latest: 2 days ago | 127 Episodes | 🆕 New</description>
    <link>https://feed-url.com/rss</link>
    <guid>podcast_id</guid>
    <pubDate>RFC 2822 date</pubDate>
    <enclosure url="cover_image_url" type="image/jpeg"/>
</item>
```

---

## 🎯 Recommended Approach

**Use Option A (Custom Namespace)** for maximum flexibility and clean separation:

### **Advantages:**
- ✅ Clean, structured data
- ✅ Easy to parse in Flutter app
- ✅ Doesn't pollute standard RSS fields
- ✅ Extensible for future additions
- ✅ Maintains backward compatibility

### **Implementation Steps:**

1. **Add namespace declaration** to RSS root element (line 567-568)
2. **Add helper methods** to XMLHandler.php
3. **Extend item generation** in generateRSSFeedFromData() (lines 592-636)
4. **Test with sample podcasts** to verify output

---

## 📝 Code Changes Required

### **File: `/includes/XMLHandler.php`**

**Location 1: Line ~567** - Add namespace to RSS root
```php
$rssRoot->setAttribute('xmlns:podfeed', 'http://podfeed.studio/xmlns');
```

**Location 2: After line ~715** - Add helper methods
```php
private function formatRelativeDate($dateString) { ... }
private function isNewEpisode($dateString) { ... }
```

**Location 3: Lines ~607-633** - Add custom elements to each item
```php
// After line 615 (pubDate)
$item->appendChild($rss->createElement('pubDate', $pubDate));

// NEW: Add episode count
if (isset($podcast['episode_count'])) {
    $item->appendChild($rss->createElement('podfeed:episodeCount', $podcast['episode_count']));
}

// NEW: Add isNew flag
if (!empty($podcast['latest_episode_date'])) {
    $isNew = $this->isNewEpisode($podcast['latest_episode_date']) ? 'true' : 'false';
    $item->appendChild($rss->createElement('podfeed:isNew', $isNew));
    
    // NEW: Add relative date
    $relativeDate = $this->formatRelativeDate($podcast['latest_episode_date']);
    $item->appendChild($rss->createElement('podfeed:relativeDate', $relativeDate));
    
    // NEW: Add ISO 8601 date for easy parsing
    $isoDate = date('Y-m-d', strtotime($podcast['latest_episode_date']));
    $item->appendChild($rss->createElement('podfeed:latestEpisodeDate', $isoDate));
}

// Continue with existing enclosure code...
```

---

## 🧪 Testing Plan

1. **Verify XML validity** - Check that feed.php outputs valid XML
2. **Test date calculations** - Verify all date ranges work correctly:
   - Today (0 days)
   - Yesterday (1 day)
   - 2-6 days ago
   - 1-4 weeks ago
   - 30+ days (formatted date)
3. **Test "New" badge** - Verify podcasts with episodes ≤7 days show isNew=true
4. **Test episode counts** - Verify counts match front-end display
5. **Test with Flutter app** - Ensure consuming app can parse new fields

---

## 📦 Expected Output Example

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- Sorted by: episodes, Order: desc, Generated: 2025-11-03 10:46:00 -->
<rss version="2.0" 
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:podfeed="http://podfeed.studio/xmlns">
    <channel>
        <title>Available Podcasts Directory</title>
        <description>Directory of available podcasts for mobile app integration</description>
        <link>http://localhost:8000</link>
        <atom:link href="http://localhost:8000/feed.php" rel="self" type="application/rss+xml"/>
        <lastBuildDate>Sun, 03 Nov 2025 10:46:00 -0500</lastBuildDate>
        <generator>PodFeed Studio v1.0</generator>
        
        <item>
            <title>DEMOCRACY NOW! AUDIO</title>
            <description>Democracy Now is an independent daily TV &amp; radio news program...</description>
            <link>https://democracynow.org/podcast.xml</link>
            <guid>podcast_12345</guid>
            <pubDate>Sun, 03 Nov 2025 06:00:00 -0500</pubDate>
            <enclosure url="http://localhost:8000/uploads/covers/podcast_12345.jpg" type="image/jpeg"/>
            
            <!-- NEW ELEMENTS -->
            <podfeed:episodeCount>11</podfeed:episodeCount>
            <podfeed:isNew>true</podfeed:isNew>
            <podfeed:relativeDate>Today</podfeed:relativeDate>
            <podfeed:latestEpisodeDate>2025-11-03</podfeed:latestEpisodeDate>
        </item>
        
        <item>
            <title>WJFF - SHELF LIFE</title>
            <description>Host Aaron Hicklin speaks to authors, artists...</description>
            <link>https://wjff.org/shelf-life/feed</link>
            <guid>podcast_67890</guid>
            <pubDate>Sat, 02 Nov 2025 12:00:00 -0500</pubDate>
            <enclosure url="http://localhost:8000/uploads/covers/podcast_67890.jpg" type="image/jpeg"/>
            
            <!-- NEW ELEMENTS -->
            <podfeed:episodeCount>10</podfeed:episodeCount>
            <podfeed:isNew>true</podfeed:isNew>
            <podfeed:relativeDate>Yesterday</podfeed:relativeDate>
            <podfeed:latestEpisodeDate>2025-11-02</podfeed:latestEpisodeDate>
        </item>
        
        <item>
            <title>WJFF - FARM AND COUNTRY</title>
            <description>Your window into rural life in the Catskills...</description>
            <link>https://wjff.org/farm-country/feed</link>
            <guid>podcast_11111</guid>
            <pubDate>Thu, 31 Oct 2025 14:00:00 -0500</pubDate>
            <enclosure url="http://localhost:8000/uploads/covers/podcast_11111.jpg" type="image/jpeg"/>
            
            <!-- NEW ELEMENTS -->
            <podfeed:episodeCount>127</podfeed:episodeCount>
            <podfeed:isNew>true</podfeed:isNew>
            <podfeed:relativeDate>2 days ago</podfeed:relativeDate>
            <podfeed:latestEpisodeDate>2025-10-31</podfeed:latestEpisodeDate>
        </item>
    </channel>
</rss>
```

---

## 🚀 Next Steps

1. **Review this plan** - Confirm approach and namespace choice
2. **Implement helper methods** - Add formatRelativeDate() and isNewEpisode()
3. **Extend RSS generation** - Add custom elements to feed items
4. **Test locally** - Verify output with http://localhost:8000/feed.php
5. **Update Flutter app** - Parse new podfeed:* elements
6. **Deploy to production** - Push to Coolify

---

## 📚 References

- **Front-end date logic:** `/assets/js/browse.js` lines 270-300
- **RSS generation:** `/includes/XMLHandler.php` lines 559-642
- **Feed endpoint:** `/feed.php`
- **Podcast data:** `/includes/PodcastManager.php` line 460 (getRSSFeed)

---

## ✅ Success Criteria

- [ ] RSS feed includes episode count for each podcast
- [ ] RSS feed includes "isNew" flag (true/false)
- [ ] RSS feed includes human-readable relative date
- [ ] RSS feed includes ISO 8601 date for parsing
- [ ] Date calculations match front-end display exactly
- [ ] XML validates as proper RSS 2.0
- [ ] Backward compatible (existing parsers ignore new fields)
- [ ] Flutter app can parse and display new metadata
