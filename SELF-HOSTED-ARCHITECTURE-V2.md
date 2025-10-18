# Self-Hosted Podcast Platform - Complete Architecture v2

## 🎯 Core Concept: FULL Podcast Hosting

This is NOT just metadata management - this is a **complete podcast hosting platform** where:
- ✅ Audio files are uploaded and stored on YOUR server
- ✅ Cover images are uploaded and stored on YOUR server  
- ✅ RSS feeds point to YOUR hosted files
- ✅ Everything lives in persistent Coolify volumes
- ✅ Users can create, edit, and manage complete podcasts

---

## 📁 Directory Structure (Coolify Persistent Volumes)

```
/data/
  └── self-hosted-podcasts.xml          # Podcast metadata

/uploads/
  ├── covers/                            # Cover images (existing)
  │   ├── shp_123.jpg                   # Podcast covers
  │   └── ep_456.jpg                    # Episode covers
  └── audio/                             # NEW: Audio files
      ├── shp_123/                       # Podcast folder
      │   ├── episode-001.mp3
      │   ├── episode-002.mp3
      │   └── episode-003.mp3
      └── shp_456/                       # Another podcast
          └── episode-001.mp3

/logs/
  └── self-hosted-operations.log        # Activity log
```

---

## 🎨 Complete Form Fields

### Podcast Creation Form

**Section 1: Basic Information**
- Podcast Title * (text)
- Description * (rich textarea)
- Author Name * (text)
- Email * (email - iTunes requirement)
- Website URL (url - optional)
- **Podcast Cover Image * (file upload - 1400-3000px)**

**Section 2: iTunes Metadata**
- Category * (dropdown with all iTunes categories)
- Subcategory (dynamic dropdown based on category)
- Language (dropdown - en-us, es, fr, de, etc.)
- Explicit Content (dropdown - Yes/No/Clean)
- Podcast Type (dropdown - Episodic/Serial)
- Copyright (text - auto-populate "© 2025 Author")

**Section 3: Advanced (Optional)**
- Subtitle (short description)
- Keywords (comma-separated)
- Complete (toggle - is podcast finished?)

### Episode Creation Form

**Section 1: Episode Details**
- Episode Title * (text)
- Description * (rich textarea)
- **Audio File * (file upload - MP3 only, shows progress)**
- **Episode Cover Image (file upload - optional, inherits podcast cover if empty)**

**Section 2: Episode Metadata**
- Publication Date * (datetime picker)
- Duration (auto-detected from MP3 or manual HH:MM:SS)
- Episode Number (number - optional)
- Season Number (number - optional)
- Episode Type (dropdown - Full/Trailer/Bonus)
- Explicit Content (dropdown - Yes/No/Clean)

**Section 3: Status**
- Status (dropdown - Published/Draft/Scheduled)

---

## 🔧 Technical Implementation

### New PHP Classes

**1. AudioUploader.php**
```php
class AudioUploader {
    private $uploadDir = UPLOADS_DIR . '/audio';
    
    public function uploadAudio($file, $podcastId, $episodeId)
    public function deleteAudio($podcastId, $episodeId)
    public function getAudioInfo($podcastId, $episodeId)
    public function getAudioDuration($filePath)
    public function getAudioFileSize($filePath)
    private function validateAudioFile($file)
}
```

**Features:**
- MP3 validation
- File size limits (configurable, default 500MB)
- Duration extraction using getID3 or similar
- Organized by podcast ID
- Cleanup on delete

**2. Enhanced SelfHostedPodcastManager.php**
```php
// Add audio handling methods
public function uploadEpisodeAudio($podcastId, $episodeId, $audioFile)
public function getEpisodeAudioUrl($podcastId, $episodeId)
```

### File Upload Handling

**Audio Upload:**
- Max size: 500MB (configurable)
- Format: MP3 only
- Storage: `/uploads/audio/{podcast_id}/{episode_id}.mp3`
- Progress bar during upload
- Auto-extract duration and file size

**Image Upload:**
- Podcast covers: 1400x1400 to 3000x3000px
- Episode covers: Same requirements (optional)
- Storage: `/uploads/covers/`

### RSS Feed Generation

**Critical Change:** Feed URLs point to YOUR server

```xml
<enclosure url="https://yourdomain.com/uploads/audio/shp_123/ep_456.mp3" 
           length="42000000" 
           type="audio/mpeg"/>
```

---

## 🎨 Enhanced UI Design

### Form Layout (Full Width)

```
┌────────────────────────────────────────────────────────────┐
│  CREATE NEW PODCAST                                    [×] │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  📝 BASIC INFORMATION                                      │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                            │
│  Podcast Title *                                           │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ My Awesome Podcast                                   │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Description *                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Tell listeners what your podcast is about...         │ │
│  │                                                       │ │
│  │                                                       │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Author Name *              Email *                        │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ John Doe            │   │ john@example.com         │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Website URL (optional)                                    │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ https://example.com                                  │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Podcast Cover Image *                                     │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  ☁️                                                   │ │
│  │  Drag & drop or click to upload                      │ │
│  │  1400x1400 to 3000x3000 • Max 2MB • JPG/PNG         │ │
│  │                                                       │ │
│  │  [Preview appears here]                              │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  🏷️  ITUNES METADATA                                      │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                            │
│  Category *                 Subcategory                    │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ 💻 Technology ▾     │   │ Select subcategory ▾     │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Language               Explicit Content                   │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ 🇺🇸 English (US) ▾  │   │ No ▾                     │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Podcast Type           Copyright                          │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ Episodic ▾          │   │ © 2025 John Doe          │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│                                                            │
│                         [Cancel]  [Create Podcast]         │
└────────────────────────────────────────────────────────────┘
```

### Episode Form with Audio Upload

```
┌────────────────────────────────────────────────────────────┐
│  ADD NEW EPISODE                                       [×] │
├────────────────────────────────────────────────────────────┤
│                                                            │
│  Episode Title *                                           │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ Episode 1: Introduction                              │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Description *                                             │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ In this episode we discuss...                        │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  🎵 Audio File * (MP3)                                    │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  🎧                                                   │ │
│  │  Drag & drop MP3 file or click to upload            │ │
│  │  Max 500MB • Duration will be auto-detected         │ │
│  │                                                       │ │
│  │  ████████████████░░░░░░░░  75% (uploading...)       │ │
│  │  episode-001.mp3 • 42.5 MB • 45:30                  │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Episode Cover (optional - inherits podcast cover)         │
│  ┌──────────────────────────────────────────────────────┐ │
│  │  🖼️  Click to upload custom episode artwork          │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│  Publication Date *     Duration (auto-detected)           │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ 2025-10-17 18:00 📅│   │ 00:45:30                 │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Episode #              Season #                           │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ 1                   │   │ 1                        │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Episode Type           Explicit                           │
│  ┌─────────────────────┐   ┌──────────────────────────┐  │
│  │ Full ▾              │   │ No ▾                     │  │
│  └─────────────────────┘   └──────────────────────────┘  │
│                                                            │
│  Status                                                    │
│  ┌──────────────────────────────────────────────────────┐ │
│  │ ● Published  ○ Draft  ○ Scheduled                   │ │
│  └──────────────────────────────────────────────────────┘ │
│                                                            │
│                         [Cancel]  [Add Episode]            │
└────────────────────────────────────────────────────────────┘
```

---

## 🚀 Deployment Considerations

### Coolify Persistent Volumes

**Already Configured:**
- `/data` - Persists across deployments ✅
- `/uploads` - Persists across deployments ✅
- `/logs` - Persists across deployments ✅

**New Directory Needed:**
- `/uploads/audio/` - Will be created automatically
- Permissions: 755 (same as covers)

### File Size Limits

**PHP Configuration (php.ini or .htaccess):**
```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 600
memory_limit = 256M
```

**Nginx/Apache:**
- Increase client_max_body_size to 500M
- Adjust timeout settings for large uploads

### Storage Considerations

**Typical Podcast Episode:**
- Audio: 20-100 MB (30-60 minutes)
- Cover: 0.5-2 MB

**Storage Planning:**
- 10 podcasts × 50 episodes × 50MB = ~25GB
- Plan accordingly for your server

---

## 🔒 Security

### File Validation
- ✅ MP3 MIME type checking
- ✅ File extension validation
- ✅ Magic number verification
- ✅ Size limits enforced
- ✅ Malicious file detection

### Access Control
- ✅ Admin password required for uploads
- ✅ Public read access to audio files
- ✅ No directory listing
- ✅ Sanitized filenames

### .htaccess for /uploads/audio/
```apache
# Allow MP3 downloads
<FilesMatch "\\.mp3$">
    Header set Content-Type "audio/mpeg"
    Header set Accept-Ranges "bytes"
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Prevent PHP execution
<FilesMatch "\\.php$">
    Deny from all
</FilesMatch>
```

---

## 📊 Workflow

### Creating a Complete Podcast

1. **Create Podcast**
   - Fill in metadata
   - Upload cover image
   - Save → Podcast created with ID `shp_123`

2. **Add Episodes**
   - Click "Episodes" button
   - Click "Add Episode"
   - Fill in episode details
   - **Upload MP3 file** (shows progress bar)
   - System auto-detects duration and file size
   - Save → Episode stored in `/uploads/audio/shp_123/ep_456.mp3`

3. **RSS Feed Generated**
   - `/self-hosted-feed.php?id=shp_123`
   - Points to YOUR audio files
   - Fully iTunes compliant

4. **Import to Directory** (Optional)
   - Copy RSS feed URL
   - Use "Import from RSS" in main admin
   - Self-hosted podcast appears alongside external ones

5. **Publish**
   - RSS feed is public
   - Can be submitted to Apple Podcasts, Spotify, etc.
   - Audio streams directly from your server

---

## 🎯 Benefits

### Complete Control
- ✅ Own your content
- ✅ No third-party dependencies
- ✅ Custom branding
- ✅ Full analytics access

### Cost Effective
- ✅ No hosting fees per episode
- ✅ No bandwidth limits (within server capacity)
- ✅ Unlimited podcasts and episodes

### Integration
- ✅ Works with existing aggregator
- ✅ Same admin interface
- ✅ Unified management
- ✅ Consistent user experience

---

## 🔄 Migration Path

### Phase 1: Audio Infrastructure
1. Create `/uploads/audio/` directory
2. Build `AudioUploader` class
3. Add audio upload to episode form
4. Test with small files

### Phase 2: Enhanced Forms
1. Redesign podcast form (full width, better layout)
2. Add rich text editors for descriptions
3. Implement progress bars for uploads
4. Add duration auto-detection

### Phase 3: Episode Management
1. Add edit episode functionality
2. Add audio file replacement
3. Add bulk operations
4. Add episode reordering

### Phase 4: Advanced Features
1. Scheduled publishing
2. Draft episodes
3. Episode analytics
4. Transcription support

---

## 📝 Next Steps

1. **Review this architecture** - Confirm this matches your vision
2. **Create audio upload infrastructure** - AudioUploader class
3. **Redesign forms** - Full-width, beautiful layouts with file uploads
4. **Test uploads** - Verify Coolify persistent volumes work
5. **Deploy** - Push to GitHub → Coolify auto-deploys

---

*This is a COMPLETE podcast hosting platform, not just metadata management!*
