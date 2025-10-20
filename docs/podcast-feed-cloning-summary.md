# Podcast Feed Cloning - Executive Summary

**Date:** October 20, 2025  
**Status:** Planning Complete - Updated with User Feedback  
**Document:** See full plan in `podcast-feed-cloning.md`

---

## 🎯 What We're Building

A **Podcast Feed Cloner** that downloads entire external RSS feeds (all episodes, audio files, and images) into your "My Podcasts" section, creating a fully self-hosted copy.

**Example:** Clone "The Daily Show" (150 episodes, 2.5GB) → All files stored locally → New RSS feed on your server

---

## ✅ Critical Requirements from User Feedback

### 1. **Leverage Existing AJAX Upload System** 🔥

**You already have this built!**
- **Location:** `assets/js/audio-uploader.js` + `api/upload-audio-chunk.php`
- **Capability:** Bypasses PHP `upload_max_filesize` limits (handles 500MB files)
- **Status:** Working in production for "My Podcasts" episode uploads

**Strategy for Cloning:**
```
1. Download remote audio file to temp location on server
2. Pass to existing AudioUploader->uploadAudio() method
3. AudioUploader handles it (already bypasses PHP limits)
4. Same progress tracking and error handling
```

**Why This Matters:**
- No need to reinvent the wheel
- Already battle-tested in production
- Handles large files (500MB+) without issues
- Built-in progress tracking

---

### 2. **Beautiful Live Progress Modal** ✨

**User wants to see exactly what's happening during cloning:**

```
┌─────────────────────────────────────────────────────────┐
│  🔄 Cloning "The Daily Show"...                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  PHASE 1: Creating Podcast                      ✅     │
│  • Downloading cover image...                   ✅     │
│  • Creating podcast metadata...                 ✅     │
│  • Generating podcast ID...                     ✅     │
│                                                         │
│  PHASE 2: Cloning Episodes (45/150)            🔄     │
│  Current: "Breaking News - Oct 20"                     │
│  ████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░         │
│  Downloading audio: 12.5 MB / 17.2 MB (73%)            │
│                                                         │
│  • Downloading episode image...                 🔄     │
│  • Creating episode metadata...                 ⏳     │
│  • Saving to XML...                             ⏳     │
│                                                         │
│  Overall Progress: 45 of 150 episodes (30%)            │
│  ████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░         │
│                                                         │
│  ✅ Completed: 44 episodes                             │
│  ⏳ Remaining: 105 episodes                            │
│  ⚠️ Failed: 1 episode (404 error)                      │
│                                                         │
│  📊 Stats:                                              │
│  • Downloaded: 748 MB / 2.5 GB                         │
│  • Time Elapsed: 3m 42s                                 │
│  • Estimated Remaining: 8m 15s                          │
│  • Speed: 3.4 MB/s                                      │
│                                                         │
│  [Cancel Cloning]                                       │
└─────────────────────────────────────────────────────────┘
```

**Key Features:**
- **Phase-based display** (Creating Podcast → Cloning Episodes)
- **Real-time updates** via AJAX polling (every 2 seconds)
- **Nested progress bars** (overall + current file)
- **Live statistics** (speed, time remaining, success/fail counts)
- **Visual indicators** (✅ done, 🔄 in progress, ⏳ pending, ⚠️ error)
- **Detailed actions** ("Downloading audio...", "Creating XML...")

---

### 3. **Complete Delete Cleanup** 🗑️

**When user deletes a cloned podcast, remove EVERYTHING:**

```php
✅ Podcast cover image (uploads/covers/shp_xxx.jpg)
✅ ALL episode images (uploads/covers/ep_xxx_*.jpg)
✅ ALL audio files (uploads/audio/shp_xxx/*.mp3)
✅ Podcast directory (uploads/audio/shp_xxx/)
✅ XML entries (data/self-hosted-podcasts.xml)
✅ Temp/progress files
```

**Current Status:**
- ✅ **Already working:** Cover image, episode images, XML deletion
- ❌ **Needs to be added:** Audio file cleanup

**What to Add:**
```php
// In SelfHostedPodcastManager->deletePodcast()
// Add BEFORE deleting from XML:

// Delete all audio files
foreach ($podcast['episodes'] as $episode) {
    if (strpos($episode['audio_url'], AUDIO_URL) !== false) {
        $this->audioUploader->deleteAudio($id, $episode['id']);
    }
}

// Delete entire podcast audio directory
$podcastAudioDir = UPLOADS_DIR . '/audio/' . $id;
if (is_dir($podcastAudioDir)) {
    $files = glob($podcastAudioDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) unlink($file);
    }
    rmdir($podcastAudioDir);
}
```

**Why This Matters:**
- Prevents storage bloat (2.5GB per cloned podcast)
- No orphaned files cluttering server
- Clean slate for new clones

---

## 🏗️ Architecture Overview

### Files to Create:

1. **`includes/PodcastFeedCloner.php`** - Main orchestrator
2. **`includes/PodcastAudioDownloader.php`** - Downloads remote audio, uses existing AudioUploader
3. **`api/clone-feed.php`** - AJAX endpoint for progress updates
4. **`assets/js/feed-cloner.js`** - Frontend UI and progress modal

### Files to Enhance:

1. **`self-hosted-podcasts.php`** - Add "Clone from RSS" button
2. **`includes/SelfHostedPodcastManager.php`** - Add audio cleanup to `deletePodcast()`

### Reuse Existing:

- ✅ `RssFeedParser.php` - Parse external feeds
- ✅ `AudioUploader.php` - Handle audio (bypasses PHP limits)
- ✅ `ImageUploader.php` - Handle images
- ✅ `SelfHostedXMLHandler.php` - XML storage
- ✅ `api/upload-audio-chunk.php` - AJAX upload endpoint

---

## 🎨 User Flow

1. **User clicks "Clone from RSS"** on `self-hosted-podcasts.php`
2. **Enter RSS feed URL** in modal
3. **System validates feed** and shows preview:
   - "Found 150 episodes"
   - "Estimated size: 2.5 GB"
   - "Average episode: 17 MB"
4. **User clicks "Start Cloning"**
5. **Beautiful progress modal appears** showing:
   - Phase 1: Creating podcast (✅ done in seconds)
   - Phase 2: Cloning episodes (🔄 live progress)
   - Real-time stats and progress bars
6. **Success!** New podcast in "My Podcasts" with all episodes hosted locally

---

## 📊 Technical Strategy

### How We Bypass PHP Upload Limits:

```
External Feed → Download to temp → AudioUploader class → Local storage
                                    ↑
                            Already bypasses PHP limits!
```

**Key Insight:** Don't fight PHP limits. Use the existing system that already solves this problem.

### Progress Tracking:

```
Server (PHP)                    Frontend (JavaScript)
─────────────                   ──────────────────────
Clone in progress               AJAX poll every 2s
↓                               ↓
Update progress file            Read progress file
(JSON with stats)               (JSON with stats)
↓                               ↓
Continue cloning                Update modal UI
```

---

## 🚀 Implementation Phases

### Phase 1: Core Cloning (Week 1)
- Create `PodcastFeedCloner.php`
- Create `PodcastAudioDownloader.php` (uses existing AudioUploader)
- Basic cloning without UI

### Phase 2: UI & Progress Modal (Week 1-2)
- Add "Clone from RSS" button
- Create beautiful live progress modal
- AJAX polling for real-time updates
- Create `feed-cloner.js`

### Phase 3: Delete Cleanup (Week 2)
- Enhance `deletePodcast()` to remove audio files
- Add directory cleanup
- Test complete removal

### Phase 4: Polish & Testing (Week 3)
- Error handling
- Edge cases
- Performance optimization
- Documentation

---

## 📝 Key Metrics

**Estimated Development:**
- **Time:** 3 weeks
- **Code:** ~2,500 lines
- **Files:** 4 new, 2 enhanced

**Example Clone:**
- **Input:** RSS feed URL
- **Output:** 150 episodes, 2.5GB, all local
- **Time:** ~15-30 minutes (depends on internet speed)

**Storage Impact:**
- **Before Clone:** 0 bytes
- **After Clone:** 2.5 GB
- **After Delete:** 0 bytes (complete cleanup)

---

## ✅ What Makes This Plan Strong

1. **Leverages existing code** - Reuses battle-tested upload system
2. **Beautiful UX** - Live progress modal shows exactly what's happening
3. **Complete cleanup** - No orphaned files on delete
4. **Surgical integration** - Minimal changes to existing codebase
5. **Proven architecture** - Uses same patterns as "My Podcasts"

---

## 🎯 Next Steps

1. ✅ **Planning complete** - This document + full plan
2. ⏳ **User review** - Get approval on approach
3. ⏳ **Begin Phase 1** - Core cloning logic
4. ⏳ **Build progress modal** - Beautiful live UI
5. ⏳ **Add delete cleanup** - Complete removal
6. ⏳ **Test & deploy** - Production ready

---

**Full Details:** See `podcast-feed-cloning.md` (1,075 lines of comprehensive planning)

**Status:** Ready for implementation - No code written yet (planning phase only)
