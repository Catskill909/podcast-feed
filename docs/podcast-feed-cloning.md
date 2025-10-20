# Podcast Feed Cloning System - Deep Planning & Architecture

**Date:** October 20, 2025  
**Status:** Planning Phase - NO CODE YET  
**Purpose:** Clone external RSS feeds into "My Podcasts" with full local hosting

---

## 🎯 Executive Summary

Build a **Podcast Feed Cloner** that takes any external RSS feed URL and downloads ALL episodes (audio files + images) into the "My Podcasts" section, converting the external feed into a fully self-hosted podcast with local storage.

### What This Does:
- Takes an RSS feed URL as input
- Downloads ALL audio files from all episodes
- Downloads podcast cover image + all episode images
- Converts external feed metadata into "My Podcasts" format
- Creates a new self-hosted podcast with all episodes
- Generates new RSS feed pointing to locally hosted files

### What This Is NOT:
- Not a simple RSS import (that already exists)
- Not a sync system (one-time clone operation)
- Not a backup system (creates new independent podcast)

---

## 📊 Current Architecture Analysis

### Existing Systems We Can Leverage:

#### 1. **RSS Import System** (admin.php)
- **Location:** `admin.php` + `api/import-rss.php`
- **What it does:** Imports external RSS feeds into main directory
- **What we can reuse:**
  - RSS feed validation (`includes/RssImportValidator.php`)
  - RSS feed parsing (`includes/RssFeedParser.php`)
  - Feed URL fetching and XML parsing
  - Episode metadata extraction

#### 2. **My Podcasts System** (self-hosted-podcasts.php)
- **Location:** `self-hosted-podcasts.php` + `includes/SelfHostedPodcastManager.php`
- **What it does:** Creates and manages self-hosted podcasts
- **What we can reuse:**
  - Podcast creation logic (`createPodcast()`)
  - Episode creation logic (`addEpisode()`)
  - Image upload system (`ImageUploader.php`)
  - Audio upload system (`AudioUploader.php`)
  - XML storage system (`SelfHostedXMLHandler.php`)

#### 3. **File Download Systems**
- **Image Downloader:** Already exists in RSS import (downloads cover images)
- **Audio Uploader:** Handles MP3 files up to 500MB
- **Storage:** Coolify persistent volumes (`uploads/covers/`, `uploads/audio/`)

---

## 🏗️ Proposed Architecture

### High-Level Flow:

```
┌─────────────────────────────────────────────────────────────────┐
│  USER INTERFACE (self-hosted-podcasts.php)                      │
├─────────────────────────────────────────────────────────────────┤
│  1. User clicks "Clone from RSS" button                         │
│  2. Modal opens with URL input field                            │
│  3. User pastes RSS feed URL                                    │
│  4. User clicks "Start Clone"                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  VALIDATION PHASE                                               │
├─────────────────────────────────────────────────────────────────┤
│  1. Validate RSS feed URL (RssImportValidator)                  │
│  2. Parse feed and extract metadata (RssFeedParser)             │
│  3. Count episodes and estimate storage needed                  │
│  4. Show preview: "Found 150 episodes, ~2.5GB total"           │
│  5. User confirms: "Yes, clone all episodes"                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  CLONING PHASE (New: PodcastFeedCloner.php)                    │
├─────────────────────────────────────────────────────────────────┤
│  1. Create podcast in My Podcasts (SelfHostedPodcastManager)   │
│  2. Download podcast cover image (ImageUploader)                │
│  3. FOR EACH EPISODE:                                           │
│     a. Download audio file from episode URL                     │
│     b. Download episode image (if exists)                       │
│     c. Create episode in My Podcasts                            │
│     d. Update progress: "Cloning episode 5 of 150..."          │
│  4. Generate final RSS feed (self-hosted-feed.php)              │
│  5. Show success: "Cloned 150 episodes successfully!"           │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│  RESULT                                                         │
├─────────────────────────────────────────────────────────────────┤
│  - New podcast in "My Podcasts" section                         │
│  - All audio files stored locally in uploads/audio/            │
│  - All images stored locally in uploads/covers/                │
│  - New RSS feed URL pointing to local files                     │
│  - Optional: Import to main directory for browsing              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔧 Technical Implementation Plan

### **CRITICAL: Leverage Existing AJAX Upload System**

The app already has a **battle-tested AJAX upload system** that bypasses PHP server file size limits:
- **Location:** `assets/js/audio-uploader.js` + `api/upload-audio-chunk.php`
- **Capability:** Handles files up to 500MB via chunked AJAX uploads
- **Proven:** Already working in production for "My Podcasts" episode uploads
- **Key Feature:** Uses `XMLHttpRequest` with progress tracking, bypassing PHP `upload_max_filesize`

**Strategy:** Reuse this exact system for cloning downloads by:
1. Download remote audio file to temp location on server
2. Use existing `AudioUploader` class to handle the file (already bypasses limits)
3. Leverage existing `api/upload-audio-chunk.php` endpoint
4. Same progress tracking and error handling

### New Files to Create:

#### 1. **includes/PodcastFeedCloner.php** (NEW - Core Logic)
```
Purpose: Main cloning orchestrator
Dependencies:
  - RssFeedParser (existing)
  - SelfHostedPodcastManager (existing)
  - ImageUploader (existing)
  - AudioUploader (existing) ← REUSE FOR BYPASSING LIMITS
  - PodcastAudioDownloader (NEW - downloads to temp, then uses AudioUploader)

Key Methods:
  - validateFeedForCloning($feedUrl)
  - estimateStorageRequirements($feedUrl)
  - cloneFeed($feedUrl, $options)
  - downloadEpisodeAudio($audioUrl, $podcastId, $episodeId) ← Uses existing upload system
  - updateCloneProgress($current, $total)
```

#### 2. **includes/PodcastAudioDownloader.php** (NEW - Audio Download)
```
Purpose: Download audio files from external URLs, then use existing AudioUploader
Strategy: Two-step process to leverage existing upload bypass system

Key Methods:
  - downloadAudioFromUrl($url, $podcastId, $episodeId)
    → Downloads to temp location
    → Calls AudioUploader->uploadAudio() to bypass PHP limits
    → Returns same format as existing upload system
  
  - validateRemoteAudioFile($url)
  - getRemoteFileSize($url)
  - streamDownloadWithProgress($url, $destination)
  
Note: This class acts as a bridge between remote downloads and 
      the existing AJAX upload system that bypasses server limits
```

#### 3. **api/clone-feed.php** (NEW - AJAX Endpoint)
```
Purpose: Handle cloning requests via AJAX
Returns: JSON with progress updates

Endpoints:
  - POST /api/clone-feed.php?action=validate
  - POST /api/clone-feed.php?action=start
  - GET  /api/clone-feed.php?action=progress&job_id=xxx
  - POST /api/clone-feed.php?action=cancel&job_id=xxx
```

#### 4. **assets/js/feed-cloner.js** (NEW - Frontend)
```
Purpose: UI for cloning process

Features:
  - Modal with URL input
  - Progress bar with episode count
  - Real-time status updates
  - Cancel button
  - Error handling
```

---

## 🎨 User Interface Design

### Location: self-hosted-podcasts.php

#### New Button (Header):
```
[Create New Podcast]  [Clone from RSS]  [Back to Admin]
```

#### Clone Modal (Collapsible):
```
┌─────────────────────────────────────────────────────────────┐
│  🔄 Clone Podcast from RSS Feed                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  RSS Feed URL:                                              │
│  [https://example.com/podcast/feed.xml              ]      │
│                                                             │
│  [Validate Feed]                                            │
│                                                             │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  📊 Feed Preview:                                           │
│  ✓ Podcast: "The Daily Show"                               │
│  ✓ Episodes: 150 episodes found                            │
│  ✓ Estimated Size: ~2.5 GB                                 │
│  ✓ Average Episode: 17 MB                                  │
│                                                             │
│  ⚠️ Warning: This will download ALL episodes to your       │
│     server. Make sure you have enough storage space.       │
│                                                             │
│  Options:                                                   │
│  ☑ Download episode images                                 │
│  ☑ Import to main directory after cloning                  │
│  ☐ Clone only last 50 episodes (optional limit)            │
│                                                             │
│  [Cancel]  [Start Cloning]                                 │
└─────────────────────────────────────────────────────────────┘
```

#### **Beautiful Live Progress Modal** (During Cloning) ✨ NEW
```
┌─────────────────────────────────────────────────────────────┐
│  🔄 Cloning "The Daily Show"...                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ╔═══════════════════════════════════════════════════════╗ │
│  ║  PHASE 1: Creating Podcast                      ✅   ║ │
│  ║  • Downloading cover image...                   ✅   ║ │
│  ║  • Creating podcast metadata...                 ✅   ║ │
│  ║  • Generating podcast ID...                     ✅   ║ │
│  ╚═══════════════════════════════════════════════════════╝ │
│                                                             │
│  ╔═══════════════════════════════════════════════════════╗ │
│  ║  PHASE 2: Cloning Episodes (45/150)            🔄   ║ │
│  ║                                                       ║ │
│  ║  Current Episode: "Breaking News - Oct 20"           ║ │
│  ║  ┌─────────────────────────────────────────────────┐ ║ │
│  ║  │ ████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░ │ ║ │
│  ║  │ Downloading audio: 12.5 MB / 17.2 MB (73%)     │ ║ │
│  ║  └─────────────────────────────────────────────────┘ ║ │
│  ║                                                       ║ │
│  ║  • Downloading episode image...                 🔄   ║ │
│  ║  • Creating episode metadata...                 ⏳   ║ │
│  ║  • Saving to XML...                             ⏳   ║ │
│  ╚═══════════════════════════════════════════════════════╝ │
│                                                             │
│  Overall Progress: 45 of 150 episodes (30%)                 │
│  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░        │
│                                                             │
│  ✅ Completed: 44 episodes                                 │
│  ⏳ Remaining: 105 episodes                                │
│  ⚠️ Failed: 1 episode (404 error)                          │
│                                                             │
│  📊 Stats:                                                  │
│  • Downloaded: 748 MB / 2.5 GB                             │
│  • Time Elapsed: 3m 42s                                     │
│  • Estimated Remaining: 8m 15s                              │
│  • Speed: 3.4 MB/s                                          │
│                                                             │
│  [Cancel Cloning]                                           │
└─────────────────────────────────────────────────────────────┘
```

**Key Features of Progress Modal:**
- **Real-time updates** via AJAX polling (every 2 seconds)
- **Phase-based display** showing current operation
- **Nested progress bars** (overall + current file)
- **Live statistics** (speed, time remaining, success/fail counts)
- **Visual indicators** (✅ done, 🔄 in progress, ⏳ pending, ⚠️ error)
- **Detailed current action** ("Downloading audio...", "Creating XML...")
- **Beautiful modern design** matching existing dark theme

#### Success Modal:
```
┌─────────────────────────────────────────────────────────────┐
│  ✅ Cloning Complete!                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Successfully cloned "The Daily Show"                       │
│                                                             │
│  📊 Summary:                                                │
│  ✓ 150 episodes cloned                                     │
│  ✓ 2.48 GB downloaded                                      │
│  ✓ RSS feed generated                                       │
│  ✓ Added to main directory                                 │
│                                                             │
│  RSS Feed URL:                                              │
│  https://yoursite.com/self-hosted-feed.php?id=shp_xxx      │
│                                                             │
│  [View Podcast]  [Manage Episodes]  [Close]                │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔄 Detailed Process Flow

### Phase 1: Validation & Preview (2-5 seconds)

```php
// User enters RSS feed URL
$feedUrl = "https://example.com/podcast/feed.xml";

// 1. Validate feed using existing validator
$validator = new RssImportValidator();
$validation = $validator->validateFeedForImport($feedUrl);

if (!$validation['can_import']) {
    // Show error: "Invalid feed"
    return;
}

// 2. Parse feed and extract ALL episodes
$parser = new RssFeedParser();
$feedData = $parser->fetchAndParse($feedUrl);

// 3. Calculate storage requirements
$totalEpisodes = count($feedData['episodes']);
$totalSize = 0;
$audioUrls = [];

foreach ($feedData['episodes'] as $episode) {
    $audioUrl = $episode['audio_url'];
    $fileSize = $this->getRemoteFileSize($audioUrl); // HEAD request
    $totalSize += $fileSize;
    $audioUrls[] = $audioUrl;
}

// 4. Show preview to user
return [
    'podcast_title' => $feedData['title'],
    'episode_count' => $totalEpisodes,
    'total_size' => $totalSize,
    'total_size_formatted' => formatFileSize($totalSize),
    'average_episode_size' => $totalSize / $totalEpisodes,
    'estimated_time' => estimateDownloadTime($totalSize)
];
```

### Phase 2: Podcast Creation (1-2 seconds)

```php
// User confirms cloning
// 1. Create podcast in My Podcasts
$podcastManager = new SelfHostedPodcastManager();

$podcastData = [
    'title' => $feedData['title'],
    'description' => $feedData['description'],
    'author' => $feedData['author'],
    'email' => $feedData['email'] ?: 'cloned@example.com',
    'website_url' => $feedData['link'],
    'category' => $feedData['category'],
    'language' => $feedData['language'],
    'explicit' => $feedData['explicit'],
    'podcast_type' => 'episodic'
];

// 2. Download and save cover image
$coverImageUrl = $feedData['image_url'];
$coverImageFile = $this->downloadImageToTemp($coverImageUrl);

// 3. Create podcast
$result = $podcastManager->createPodcast($podcastData, $coverImageFile);
$podcastId = $result['id'];
```

### Phase 3: Episode Cloning Loop (MAIN PROCESS)

```php
// For each episode in feed
$cloner = new PodcastFeedCloner();
$totalEpisodes = count($feedData['episodes']);
$clonedCount = 0;

foreach ($feedData['episodes'] as $index => $episode) {
    // Update progress - LIVE UPDATES TO MODAL
    $cloner->updateProgress([
        'phase' => 'cloning_episodes',
        'current_episode' => $clonedCount + 1,
        'total_episodes' => $totalEpisodes,
        'episode_title' => $episode['title'],
        'action' => 'downloading_audio',
        'percent' => ($clonedCount / $totalEpisodes) * 100
    ]);
    
    // 1. Download audio file from external URL
    // USES EXISTING AJAX UPLOAD SYSTEM TO BYPASS PHP LIMITS
    $audioUrl = $episode['audio_url'];
    $audioDownloader = new PodcastAudioDownloader();
    
    $episodeId = 'ep_' . time() . '_' . uniqid();
    
    // This internally uses AudioUploader->uploadAudio() 
    // which bypasses PHP upload_max_filesize limits
    $audioResult = $audioDownloader->downloadAudioFromUrl(
        $audioUrl, 
        $podcastId, 
        $episodeId
    );
    
    if (!$audioResult['success']) {
        // Log error and continue
        error_log("Failed to download episode: " . $episode['title']);
        $cloner->logFailedEpisode($episode['title'], $audioResult['message']);
        continue;
    }
    
    // Update progress - downloading image
    $cloner->updateProgress([
        'action' => 'downloading_image',
        'percent' => (($clonedCount + 0.5) / $totalEpisodes) * 100
    ]);
    
    // 2. Download episode image (if exists)
    $episodeImageFile = null;
    if (!empty($episode['image_url'])) {
        $episodeImageFile = $this->downloadImageToTemp($episode['image_url']);
    }
    
    // Update progress - creating metadata
    $cloner->updateProgress([
        'action' => 'creating_metadata'
    ]);
    
    // 3. Create episode in My Podcasts
    $episodeData = [
        'title' => $episode['title'],
        'description' => $episode['description'],
        'audio_url' => $audioResult['url'], // Local URL
        'duration' => $audioResult['duration'],
        'file_size' => $audioResult['file_size'],
        'pub_date' => $episode['pub_date'],
        'status' => 'published',
        'episode_number' => $index + 1,
        'season_number' => 1
    ];
    
    $episodeResult = $podcastManager->addEpisode(
        $podcastId, 
        $episodeData, 
        $episodeImageFile, 
        null // No audio file upload (already downloaded via AudioUploader)
    );
    
    if ($episodeResult['success']) {
        $clonedCount++;
        
        // Update progress - episode complete
        $cloner->updateProgress([
            'action' => 'episode_complete',
            'completed_count' => $clonedCount
        ]);
    }
    
    // Clean up temp files
    if ($episodeImageFile) {
        unlink($episodeImageFile['tmp_name']);
    }
}
```

### Phase 4: Finalization (1 second)

```php
// 1. Generate RSS feed (automatic via self-hosted-feed.php)
$feedUrl = APP_URL . "/self-hosted-feed.php?id=" . $podcastId;

// 2. Optional: Import to main directory
if ($options['import_to_directory']) {
    $mainManager = new PodcastManager();
    $mainManager->importFromSelfHosted($podcastId, $feedUrl);
}

// 3. Return success
return [
    'success' => true,
    'podcast_id' => $podcastId,
    'episodes_cloned' => $clonedCount,
    'feed_url' => $feedUrl,
    'total_size' => $totalSize
];
```

---

## 🗑️ Complete Cleanup on Delete - CRITICAL REQUIREMENT

### **Delete Podcast Must Remove ALL Assets**

When a user deletes a cloned podcast from `self-hosted-podcasts.php`, the system MUST perform **complete cleanup**:

#### What Must Be Deleted:

```php
// When deletePodcast($id) is called:

1. ✅ Podcast cover image (uploads/covers/shp_xxx.jpg)
2. ✅ ALL episode images (uploads/covers/ep_xxx_*.jpg)
3. ✅ ALL audio files (uploads/audio/shp_xxx/*.mp3)
4. ✅ Podcast directory (uploads/audio/shp_xxx/)
5. ✅ XML entries (data/self-hosted-podcasts.xml)
6. ✅ Any temp/progress files (data/clone_progress_xxx.json)
```

#### Current Implementation Status:

**GOOD NEWS:** The existing `SelfHostedPodcastManager->deletePodcast()` already handles most of this!

```php
// From includes/SelfHostedPodcastManager.php (lines 134-173)
public function deletePodcast($id) {
    // ✅ Already deletes cover image
    if ($podcast['cover_image']) {
        $this->imageUploader->deleteImage($podcast['cover_image']);
    }

    // ✅ Already deletes episode images
    if (!empty($podcast['episodes'])) {
        foreach ($podcast['episodes'] as $episode) {
            if (!empty($episode['episode_image'])) {
                $this->imageUploader->deleteImage($episode['episode_image']);
            }
        }
    }

    // ✅ Already deletes from XML
    $this->xmlHandler->deletePodcast($id);
}
```

#### What Needs to Be Added:

**Audio File Cleanup** - Currently NOT implemented for cloned podcasts!

```php
// ENHANCEMENT NEEDED in SelfHostedPodcastManager->deletePodcast()

// Add BEFORE deleting from XML:
if (!empty($podcast['episodes'])) {
    foreach ($podcast['episodes'] as $episode) {
        // Delete audio file if hosted locally
        if (!empty($episode['audio_url']) && 
            strpos($episode['audio_url'], AUDIO_URL) !== false) {
            $this->audioUploader->deleteAudio($id, $episode['id']);
        }
    }
}

// Delete entire podcast audio directory
$podcastAudioDir = UPLOADS_DIR . '/audio/' . $id;
if (is_dir($podcastAudioDir)) {
    // Remove all files in directory
    $files = glob($podcastAudioDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    // Remove directory
    rmdir($podcastAudioDir);
}
```

#### Verification Checklist:

After delete operation, verify:
- [ ] No orphaned images in `uploads/covers/`
- [ ] No orphaned audio files in `uploads/audio/`
- [ ] No empty podcast directories in `uploads/audio/`
- [ ] XML entry removed from `data/self-hosted-podcasts.xml`
- [ ] No temp/progress files remaining

#### Storage Reclamation:

For a cloned podcast with 150 episodes:
- **Before Delete:** ~2.5 GB used
- **After Delete:** 0 bytes (complete cleanup)
- **Benefit:** Prevents storage bloat from abandoned clones

---

## 🛡️ Error Handling & Edge Cases

### Critical Considerations:

#### 1. **Storage Space**
- **Problem:** User may not have enough disk space
- **Solution:** Check available space before starting
- **Implementation:** 
  ```php
  $availableSpace = disk_free_space(UPLOADS_DIR);
  if ($totalSize > $availableSpace * 0.8) { // 80% threshold
      throw new Exception("Insufficient storage space");
  }
  ```

#### 2. **Download Failures**
- **Problem:** Some audio files may fail to download (404, timeout, etc.)
- **Solution:** Skip failed episodes, log errors, continue
- **Implementation:**
  ```php
  try {
      $audioResult = $audioDownloader->download($url);
  } catch (Exception $e) {
      error_log("Episode download failed: " . $e->getMessage());
      $failedEpisodes[] = $episode['title'];
      continue; // Skip to next episode
  }
  ```

#### 3. **Timeout Issues**
- **Problem:** Cloning 150 episodes may take 30+ minutes
- **Solution:** Use background job system or chunked processing
- **Implementation:**
  ```php
  // Option A: Increase PHP timeout
  set_time_limit(3600); // 1 hour
  
  // Option B: Background job (better)
  $jobId = $cloner->startBackgroundClone($feedUrl);
  // Poll for progress via AJAX
  ```

#### 4. **Large Files**
- **Problem:** Some episodes may be >500MB (video podcasts)
- **Solution:** Set max file size limit, skip oversized files
- **Implementation:**
  ```php
  $maxFileSize = 500 * 1024 * 1024; // 500MB
  if ($remoteFileSize > $maxFileSize) {
      error_log("Episode too large: " . $episode['title']);
      continue;
  }
  ```

#### 5. **Duplicate Detection**
- **Problem:** User may try to clone same feed twice
- **Solution:** Check if podcast already exists by feed URL
- **Implementation:**
  ```php
  $existing = $podcastManager->findPodcastByFeedUrl($feedUrl);
  if ($existing) {
      throw new Exception("This podcast has already been cloned");
  }
  ```

#### 6. **Partial Clones**
- **Problem:** Cloning interrupted mid-process
- **Solution:** Track progress, allow resume
- **Implementation:**
  ```php
  // Save progress to database
  $progress = [
      'podcast_id' => $podcastId,
      'total_episodes' => $totalEpisodes,
      'cloned_episodes' => $clonedCount,
      'failed_episodes' => $failedEpisodes,
      'status' => 'in_progress'
  ];
  file_put_contents(DATA_DIR . "/clone_progress_{$jobId}.json", json_encode($progress));
  ```

---

## 📦 Data Storage Structure

### File System Layout:

```
uploads/
├── covers/
│   ├── shp_cloned_podcast_123.jpg          # Podcast cover
│   ├── ep_cloned_episode_001.jpg           # Episode 1 image
│   ├── ep_cloned_episode_002.jpg           # Episode 2 image
│   └── ...
└── audio/
    └── shp_cloned_podcast_123/             # Podcast folder
        ├── ep_cloned_episode_001.mp3       # Episode 1 audio
        ├── ep_cloned_episode_002.mp3       # Episode 2 audio
        └── ...

data/
├── self-hosted-podcasts.xml                # Podcast metadata
└── clone_progress_xxx.json                 # Cloning progress (temp)
```

### XML Structure (self-hosted-podcasts.xml):

```xml
<podcast id="shp_cloned_podcast_123">
    <title>The Daily Show (Cloned)</title>
    <description>Cloned from external RSS feed</description>
    <source_feed_url>https://example.com/podcast/feed.xml</source_feed_url>
    <cloned_date>2025-10-20 12:00:00</cloned_date>
    <episodes>
        <episode id="ep_cloned_episode_001">
            <title>Episode 1</title>
            <audio_url>https://yoursite.com/uploads/audio/shp_cloned_podcast_123/ep_cloned_episode_001.mp3</audio_url>
            <original_audio_url>https://example.com/episode1.mp3</original_audio_url>
            <!-- ... -->
        </episode>
    </episodes>
</podcast>
```

---

## ⚡ Performance Optimization

### Strategies:

#### 1. **Parallel Downloads**
```php
// Download multiple episodes simultaneously
$maxParallel = 3; // Download 3 episodes at once
$multiCurl = new MultiCurlDownloader();
$multiCurl->downloadBatch($audioUrls, $maxParallel);
```

#### 2. **Chunked Processing**
```php
// Process in batches of 10 episodes
$batchSize = 10;
$batches = array_chunk($episodes, $batchSize);

foreach ($batches as $batch) {
    $this->processBatch($batch);
    // Save progress after each batch
}
```

#### 3. **Resume Capability**
```php
// Check for existing progress
$progress = $this->loadProgress($jobId);
if ($progress) {
    $startIndex = $progress['cloned_episodes'];
    $episodes = array_slice($episodes, $startIndex);
}
```

#### 4. **Streaming Downloads**
```php
// Stream large files instead of loading into memory
$fp = fopen($destination, 'w');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_exec($ch);
fclose($fp);
```

---

## 🎯 Integration Points

### How This Fits with Existing Code:

#### 1. **self-hosted-podcasts.php** (UI)
```php
// Add new button in header
<button class="btn btn-secondary" onclick="showCloneModal()">
    <i class="fas fa-clone"></i> Clone from RSS
</button>

// Add clone modal (similar to create podcast modal)
<div id="cloneFeedModal" class="modal-overlay">
    <!-- Clone UI here -->
</div>
```

#### 2. **SelfHostedPodcastManager.php** (Reuse)
```php
// Use existing methods:
- createPodcast($data, $imageFile)
- addEpisode($podcastId, $episodeData, $imageFile, $audioFile)

// No modifications needed!
```

#### 3. **AudioUploader.php** (Minor Extension)
```php
// Add new method for URL downloads
public function uploadAudioFromUrl($url, $podcastId, $episodeId) {
    // Download from URL
    // Validate
    // Save to uploads/audio/
    // Return same format as uploadAudio()
}
```

#### 4. **ImageUploader.php** (Minor Extension)
```php
// Add new method for URL downloads
public function uploadImageFromUrl($url, $podcastId) {
    // Download from URL
    // Validate dimensions
    // Save to uploads/covers/
    // Return same format as uploadImage()
}
```

---

## 🧪 Testing Strategy

### Test Cases:

#### 1. **Small Feed (5 episodes)**
- Purpose: Quick validation
- Expected: Complete in <1 minute

#### 2. **Medium Feed (50 episodes)**
- Purpose: Realistic scenario
- Expected: Complete in 5-10 minutes

#### 3. **Large Feed (200+ episodes)**
- Purpose: Stress test
- Expected: Complete in 30-60 minutes

#### 4. **Failed Downloads**
- Purpose: Error handling
- Test: Feed with some 404 audio URLs
- Expected: Skip failed, continue with rest

#### 5. **Timeout Simulation**
- Purpose: Timeout handling
- Test: Feed with very slow audio URLs
- Expected: Skip after timeout, continue

#### 6. **Storage Full**
- Purpose: Disk space handling
- Test: Clone when disk is nearly full
- Expected: Error before starting

---

## 🚀 Implementation Phases

### Phase 1: Core Cloning (Week 1)
- [ ] Create `PodcastFeedCloner.php`
- [ ] Create `PodcastAudioDownloader.php`
- [ ] Add download methods to `AudioUploader.php`
- [ ] Add download methods to `ImageUploader.php`
- [ ] Basic cloning without UI

### Phase 2: UI Integration (Week 1)
- [ ] Add "Clone from RSS" button to `self-hosted-podcasts.php`
- [ ] Create clone modal with URL input
- [ ] Add validation preview
- [ ] Create `assets/js/feed-cloner.js`

### Phase 3: Progress Tracking (Week 2)
- [ ] Create `api/clone-feed.php` endpoint
- [ ] Add progress bar UI
- [ ] Implement real-time updates via AJAX polling
- [ ] Add cancel functionality

### Phase 4: Error Handling (Week 2)
- [ ] Add storage space check
- [ ] Add duplicate detection
- [ ] Add failed episode tracking
- [ ] Add resume capability

### Phase 5: Optimization (Week 3)
- [ ] Add parallel downloads
- [ ] Add chunked processing
- [ ] Add background job system
- [ ] Performance testing

### Phase 6: Polish (Week 3)
- [ ] Add success/error notifications
- [ ] Add detailed logging
- [ ] Create user documentation
- [ ] Final testing

---

## 📝 Configuration Options

### User-Configurable Settings:

```php
// config/config.php additions
define('CLONE_MAX_EPISODES', 500);           // Max episodes to clone
define('CLONE_MAX_FILE_SIZE', 500 * 1024 * 1024); // 500MB per file
define('CLONE_TIMEOUT', 3600);               // 1 hour max
define('CLONE_PARALLEL_DOWNLOADS', 3);       // Download 3 at once
define('CLONE_BATCH_SIZE', 10);              // Process 10 episodes per batch
define('CLONE_RETRY_FAILED', true);          // Retry failed downloads
define('CLONE_MAX_RETRIES', 3);              // Max retry attempts
```

### Per-Clone Options:

```javascript
// User can choose:
{
    downloadEpisodeImages: true,      // Download episode artwork
    importToDirectory: true,          // Add to main directory
    limitEpisodes: null,              // Limit to last N episodes
    skipLargeFiles: true,             // Skip files >500MB
    retryFailed: true                 // Retry failed downloads
}
```

---

## 🔒 Security Considerations

### Important Safeguards:

#### 1. **URL Validation**
```php
// Only allow HTTP/HTTPS
// Block local/private IPs
// Validate SSL certificates
```

#### 2. **File Type Validation**
```php
// Only allow MP3 audio files
// Validate MIME types
// Check magic numbers
```

#### 3. **Rate Limiting**
```php
// Limit cloning operations per user
// Prevent abuse
```

#### 4. **Storage Quotas**
```php
// Set max storage per user
// Prevent disk space exhaustion
```

---

## 📊 Success Metrics

### How to Measure Success:

1. **Completion Rate:** % of clones that complete successfully
2. **Average Time:** Time to clone 50 episodes
3. **Error Rate:** % of episodes that fail to download
4. **Storage Efficiency:** Disk space used vs. expected
5. **User Satisfaction:** Feedback on UI/UX

---

## 🎯 Future Enhancements

### Post-MVP Features:

1. **Incremental Sync:** Update cloned podcast with new episodes
2. **Selective Cloning:** Choose specific episodes to clone
3. **Batch Cloning:** Clone multiple podcasts at once
4. **Cloud Storage:** Option to store files in S3/DigitalOcean Spaces
5. **Transcoding:** Convert audio to different formats/bitrates
6. **Metadata Editing:** Edit episode metadata during clone
7. **Schedule Cloning:** Schedule cloning for off-peak hours

---

## 📚 Documentation Needed

### User Documentation:
1. How to clone a podcast feed
2. Storage requirements guide
3. Troubleshooting common issues
4. Best practices

### Developer Documentation:
1. API reference for `PodcastFeedCloner`
2. Architecture diagrams
3. Database schema
4. Testing guide

---

## ✅ Pre-Implementation Checklist

Before writing any code, verify:

- [ ] User has reviewed and approved this plan
- [ ] Storage requirements are understood
- [ ] Performance expectations are clear
- [ ] Error handling strategy is agreed upon
- [ ] UI/UX mockups are approved (especially live progress modal)
- [ ] Testing strategy is defined
- [ ] Timeline is realistic
- [ ] All dependencies are identified
- [ ] Security considerations are addressed
- [ ] Documentation plan is in place
- [ ] **AJAX upload bypass system** is understood and will be leveraged
- [ ] **Complete delete cleanup** requirements are clear
- [ ] **Live progress modal** design is approved

---

## 🎬 Next Steps

1. **Review this document** with stakeholders
2. **Get approval** on architecture and approach
3. **Create detailed mockups** for UI
4. **Set up development environment** for testing
5. **Begin Phase 1 implementation** (Core Cloning)

---

## 📝 Key Updates from User Feedback (Oct 20, 2025)

### ✅ Critical Requirements Added:

1. **Leverage Existing AJAX Upload System**
   - Reuse `audio-uploader.js` + `api/upload-audio-chunk.php`
   - Already bypasses PHP `upload_max_filesize` limits (handles 500MB files)
   - Proven in production for "My Podcasts" episode uploads
   - Strategy: Download remote files → Use existing AudioUploader class

2. **Beautiful Live Progress Modal**
   - Phase-based display (Creating Podcast → Cloning Episodes)
   - Real-time updates via AJAX polling (every 2 seconds)
   - Nested progress bars (overall + current file)
   - Live statistics (speed, time, success/fail counts)
   - Visual indicators (✅ ✓ 🔄 ⏳ ⚠️)
   - Shows exactly what's happening: "Downloading audio...", "Creating XML..."

3. **Complete Delete Cleanup**
   - Delete podcast must remove ALL assets:
     - Podcast cover image
     - ALL episode images
     - ALL audio files (entire podcast audio directory)
     - XML entries
     - Temp/progress files
   - Prevents storage bloat from abandoned clones
   - Existing code already handles images + XML
   - **Need to add:** Audio file cleanup in `deletePodcast()`

### 🎯 Implementation Priority:

1. **Phase 1:** Core cloning with AJAX upload bypass
2. **Phase 2:** Beautiful live progress modal (HIGH PRIORITY)
3. **Phase 3:** Complete delete cleanup enhancement
4. **Phase 4:** Error handling and optimization

---

**Status:** ✅ Planning Complete - Ready for Review  
**Estimated Development Time:** 3 weeks  
**Estimated Lines of Code:** ~2,500 lines (added progress modal complexity)  
**Risk Level:** Medium (file downloads, storage, timeouts)  
**Impact:** HIGH - Transforms app into complete podcast migration tool

**Key Differentiators:**
- Leverages existing battle-tested upload system
- Beautiful real-time progress tracking
- Complete cleanup on delete (no orphaned files)
- Surgical integration with existing codebase

---

*This is a living document. Update as requirements change or new insights emerge.*
