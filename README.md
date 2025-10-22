# PodFeed Browser

A complete podcast platform combining a modern directory browser with a full-featured podcast hosting system. Features RSS feed aggregation, podcast creation & hosting ("My Podcasts"), audio file management (MP3 & M4A), health monitoring, in-browser audio player, and a stunning dark-themed UI.

---

## ⚠️ CRITICAL: DEVELOPMENT DISCIPLINE - READ FIRST

**These principles MUST be followed on every task. They prevent hours of wasted effort:**

### **🔍 Before Making ANY Changes:**

1. **READ ALL PROVIDED DOCUMENTATION FIRST**
   - Review debug docs, error logs, and user-provided context completely
   - Understand what has already been tried and failed
   - Never skip documentation - it contains the full picture

2. **TRACE THE COMPLETE CODE PATH**
   - Follow the entire flow from user action to server response
   - Read the actual code files - don't assume how things work
   - Check element IDs, form submissions, JavaScript callbacks, server handlers
   - Verify data flow: form → JavaScript → AJAX → server → database

3. **LOOK FOR SIMPLE BUGS FIRST**
   - Element ID mismatches (JavaScript looking for wrong IDs)
   - Form inputs trying to upload files twice
   - Validation blocking legitimate submissions
   - Missing or incorrect field names
   - Callback functions called at wrong times

4. **REASON THROUGH EXPECTED vs ACTUAL BEHAVIOR**
   - What SHOULD happen when the user clicks "Submit"?
   - What IS actually happening? (Check logs, network tab, console)
   - Where does the actual behavior diverge from expected?
   - Is it a code bug or infrastructure issue?

5. **ONLY FIX INFRASTRUCTURE AFTER CODE IS PROVEN CORRECT**
   - Don't create Dockerfiles, modify nginx configs, or change servers
   - Infrastructure changes should be LAST RESORT
   - 95% of bugs are code logic, not server config

### **🚫 NEVER DO THESE:**

- ❌ Stab in the dark with infrastructure changes
- ❌ Create Dockerfiles without verifying code correctness first
- ❌ Modify `.htaccess` when using Nginx (or vice versa)
- ❌ Ask user to "test and see what happens" without analyzing code
- ❌ Make assumptions about element IDs or form behavior
- ❌ Skip reading provided debug documentation
- ❌ Add complexity before finding the simple bug

### **✅ ALWAYS DO THESE:**

- ✅ Read all provided docs and error logs completely
- ✅ Trace code path from start to finish
- ✅ Look for simple bugs (IDs, validation, callbacks)
- ✅ Verify form submission flow in detail
- ✅ Check for double file uploads or duplicate processes
- ✅ Test logic mentally before making changes
- ✅ Add targeted debug logging to confirm hypothesis

### **📝 Real Example - What Went Wrong (Oct 18, 2025):**

**Problem:** Large file upload works via AJAX but form submission freezes

**What I Did Wrong:**
1. Created Apache Dockerfile (user had Nginx)
2. Tried to add Traefik labels (UI doesn't exist)
3. Modified `.htaccess` for Apache (running Nginx)
4. Created `nixpacks.toml` without checking code
5. Hours of infrastructure changes with no progress

**What I Should Have Done:**
1. Read the code in `self-hosted-episodes.php`
2. Trace: Form submission → Audio file input → AJAX upload → Form POST
3. Found Bug #1: Element ID mismatch (`'audio_url'` vs `'audioUrlInput'`)
4. Found Bug #2: Image validation too strict for episodes
5. Found Bug #3: Form trying to upload 251MB file AGAIN after AJAX already uploaded it
6. **Total fix time: 15 minutes of code analysis**

**The Simple Truth:**
- AJAX uploaded the file successfully ✅
- Form still had the 251MB file attached ❌
- Form tried to upload it again → timeout
- **Solution:** Clear and disable the file input after AJAX completes

---

## 🎯 IMPORTANT: What This App Does

**PodFeed Builder is BOTH a feed aggregator AND a complete podcast hosting platform.**

### **Dual Purpose:**
1. **Feed Aggregator** - Creates a meta-feed (RSS feed of RSS feeds) for consumption by external applications (Flutter app, podcast players, etc.). Aggregates multiple podcast feeds into a single manageable directory.
2. **Podcast Hosting Platform** - Full podcast creation system where you can create podcasts from scratch, upload audio files, manage episodes, and generate iTunes-compliant RSS feeds.

### **Critical Architecture Principle:**

```
┌─────────────────────────────────────────────────────────────┐
│  DATA FLOW - UNDERSTAND THIS TO AVOID BUGS!                │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SOURCE FEEDS (External)                                    │
│  └─ Podcast episodes, dates, metadata                       │
│     └─ THE SOURCE OF TRUTH                                  │
│                                                             │
│  YOUR DATABASE (podcasts.xml)                               │
│  └─ Podcast title, feed URL, cover image                    │
│  └─ Cached episode dates (updated by cron)                  │
│     └─ FOR DISPLAY ONLY, NOT SOURCE OF TRUTH               │
│                                                             │
│  YOUR FEED (feed.php)                                       │
│  └─ Aggregated list of podcasts                            │
│     └─ Points to source feeds                               │
│        └─ Flutter app reads this                            │
│                                                             │
│  FLUTTER APP (External)                                     │
│  └─ Reads your feed                                         │
│     └─ Fetches episodes from source feeds                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### **What This Means For Development:**

⚠️ **CRITICAL:** Latest episode dates and episode counts should ALWAYS come from the source RSS feeds, never from your database during import.

**Why?**
- You don't host the podcasts - you just list them
- Source feeds are the single source of truth
- Your database is just a cache for performance
- Episode data must stay current with source feeds

**How It Works:**
1. **Import:** Saves podcast metadata (title, URL, image) - NO episode data
2. **Cron Job:** Fetches episode data from source feeds every 30 minutes
3. **Display:** Shows cached episode data from database (fast)
4. **Modal/Preview:** Fetches live from source feeds (always current)
5. **Your Feed:** Points to source feeds (Flutter app fetches from source)

**Don't Try To:**
- ❌ Store episode dates during import (they'll be stale)
- ❌ Fetch RSS feeds on every page load (too slow)
- ❌ Treat your database as the source of truth for episodes

**Do:**
- ✅ Let cron job update episode data regularly
- ✅ Cache episode data for display performance
- ✅ Fetch live data only when needed (modals, health checks)
- ✅ Always point to source feeds in your output

### **Current Capabilities:**
✅ **Feed Aggregator** - Import and manage external podcast RSS feeds
✅ **Podcast Hosting Platform ("My Podcasts")** - Create and host your own podcasts with audio uploads
✅ **Podcast Player Website** - Public browsing interface with in-browser player
✅ **Episode Management System** - Full CRUD operations for episodes

**Future Potential:**
- Analytics dashboard
- Automated transcription
- Multi-user podcast networks
- Monetization features

---

## ✅ DEPLOYMENT STATUS

### **Production Deployment (Coolify/Nixpacks)**

**🎉 FULLY CONFIGURED & WORKING!**

Your app is deployed with persistent volumes - data persists across all deployments automatically.

#### **What's Configured:**

✅ **Persistent Volumes** - Data stored outside container  
✅ **Permissions Set** - PHP can read/write all directories  
✅ **Auto-Deploy** - Push to GitHub → Coolify deploys → Works!  
✅ **Verified Working** - Tested and confirmed (2025-10-13)

#### **Current Deployment Workflow:**

```bash
# 1. Make changes locally
git add .
git commit -m "Your changes"
git push origin main

# 2. Coolify auto-deploys
# 3. Done! No manual commands needed ✅
```

**Your data (podcasts, images, logs) persists automatically across all deployments.**

### **Essential Documentation**

#### Deployment:
1. 📋 **[DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md)** - Complete deployment guide
2. ⚡ **[QUICK-START-DEPLOYMENT-FIX.md](QUICK-START-DEPLOYMENT-FIX.md)** - Fast setup guide
3. 📊 **[DEPLOYMENT-ANALYSIS-SUMMARY.md](DEPLOYMENT-ANALYSIS-SUMMARY.md)** - Technical deep dive
4. 🔒 **[SECURITY-AUDIT.md](SECURITY-AUDIT.md)** - Security best practices

#### Automation & Sorting:
5. 🎉 **[TODAYS-WORK-SUMMARY.md](TODAYS-WORK-SUMMARY.md)** - Complete summary of Oct 13, 2025 updates
6. 🚀 **[PRODUCTION-DEPLOYMENT-READY.md](PRODUCTION-DEPLOYMENT-READY.md)** - Production readiness guide
7. 🔄 **[AUTOMATION-COMPLETE.md](AUTOMATION-COMPLETE.md)** - Automated scanning setup
8. 📊 **[SERVER-SIDE-SORTING-COMPLETE.md](SERVER-SIDE-SORTING-COMPLETE.md)** - Sorting implementation
9. 📝 **[sort-options.md](sort-options.md)** - Original planning document

#### Latest Episode Data (NEW - Oct 17, 2025):
10. 📡 **[DEEP-AUDIT-LATEST-EPISODE.md](DEEP-AUDIT-LATEST-EPISODE.md)** - Complete audit of all data sources
11. 🎯 **[ONE-TRUTH-IMPLEMENTED.md](ONE-TRUTH-IMPLEMENTED.md)** - Live feed data implementation
12. 🔧 **[CACHE-BUSTING-FIX.md](CACHE-BUSTING-FIX.md)** - Asset versioning system
13. 📋 **[one-truth-latest-episode-fix.md](one-truth-latest-episode-fix.md)** - Technical deep dive

**Quick Diagnostics:**
- Visit `/check-user.php` in production to verify permissions
- All directories should show "✅ Writable"

---

## 🚀 Features

### **🎙️ My Podcasts - Complete Podcast Hosting (October 17, 2025)** ✨✨
- **Complete Podcast Creation**: Create podcasts from scratch with full metadata
- **Audio File Uploads**: Upload and host MP3 files directly on your server (up to 500MB)
- **Episode Management**: Add, edit, and delete episodes with full control
- **Cover Image Management**: Upload podcast and episode artwork
- **iTunes Compliance**: Generates standard RSS 2.0 + iTunes namespace feeds
- **Seamless Integration**: Your podcasts integrate with existing directory
- **RSS Feed Generator**: Each podcast gets its own public RSS feed URL
- **Beautiful Management UI**: Dark-themed interface matching existing design
- **Persistent Storage**: All files stored in Coolify persistent volumes
- **No Breaking Changes**: Modular architecture, zero impact on existing features
- **~1,850 Lines of Code**: Complete system with validation and error handling

### **🔄 Podcast Feed Cloning - MAJOR NEW FEATURE (October 20, 2025)** ✨✨✨
- **Clone Entire Podcasts**: Import complete podcasts from any RSS feed URL with one click
- **Full Audio Download & Hosting**: Downloads ALL episode audio files and hosts them locally on your server
- **Automatic Metadata Import**: Extracts podcast title, description, author, category, and all episode data
- **Cover Image Cloning**: Downloads and hosts podcast cover images automatically
- **Episode Image Support**: Optionally downloads individual episode artwork
- **Smart Validation**: Pre-clone validation shows episode count and estimated storage requirements
- **Graceful Failure Handling**: Continues cloning even if some episodes fail, shows success/failure counts
- **Progress Feedback**: Animated spinner with time estimates during cloning process
- **iTunes-Compliant Output**: Generates valid RSS 2.0 + iTunes namespace feeds for cloned podcasts
- **Complete Self-Hosting**: Transform external podcasts into fully self-hosted versions
- **Bypass PHP Limits**: Handles large files (up to 500MB per episode) using existing upload infrastructure
- **Optional Directory Import**: Can automatically add cloned podcasts to main directory
- **Episode Limiting**: Option to clone only the last N episodes (useful for testing)
- **~2,000 Lines of New Code**: Complete orchestration system with error handling and logging

### **📢 Banner Ads Management System (October 22, 2025)** ✨✨✨
- **Complete Ad Management**: Upload, manage, and display banner advertisements
- **Three Ad Types**: Web banners (728x90), phone banners (320x50), tablet banners (728x90)
- **Live Preview**: Real-time banner rotation preview with configurable timing
- **Drag-and-Drop Upload**: Easy file upload with strict dimension validation
- **URL Management**: Add destination URLs to banners via clean modal interface
- **Clickable Banners**: Banners link to destination URLs, open in new tab
- **Front-End Integration**: Rotating banner display on public homepage
- **RSS Feed**: Mobile banner feed for app consumption with click tracking
- **Drag-to-Reorder**: Sortable.js integration for easy reordering
- **On/Off Toggles**: Enable/disable web and mobile ads independently
- **Configurable Rotation**: Adjust rotation duration (5-60s) and fade duration (0.5-3s)
- **Material Design UI**: Beautiful dark-themed interface with smooth animations
- **Production Ready**: Uses same patterns as existing features, Coolify-compatible
- **~3,600 Lines of Code**: Complete system with admin interface, public display, and RSS feed

### **🎨 Public Browsing Interface (October 17, 2025)** ✨
- **Beautiful Podcast Grid**: Responsive card layout with cover images and overlays
- **Hover Effects**: Smooth animations with play button overlay on hover
- **Smart Stats Bar**: Clean display of podcast and episode counts
- **Real-time Search**: Filter podcasts as you type
- **Multiple Sort Options**: Latest episodes, alphabetical, most episodes
- **Click to Play**: Click any podcast card to open the player modal
- **Mobile Optimized**: Perfect experience on all devices
- **No Password Required**: Public-facing interface for end users
- **Staggered Animations**: Beautiful fade-in effects on page load

### **🔐 Admin Management Panel**
- **Full CRUD Operations**: Create, Read, Update, Delete podcast entries
- **RSS Feed Auto-Import** ✨: Import podcasts from any RSS feed with one click
- **RSS Feed Validation** ✨: Pre-import validation catches bad feeds before they cause issues
- **Podcast Health Check** ✨: Validate RSS 2.0 structure, iTunes namespace, and feed accessibility
- **Podcast Preview Cards** ✨: Click cover/title to see comprehensive RSS metadata in beautiful modal
- **Image Management**: Upload or auto-download cover images with validation
- **XML-Based Storage**: Lightweight file-based storage system
- **RSS Feed Generation**: Standard-compliant RSS feed output for app integration
- **Dark Theme Interface**: Modern Material Design dark mode with Oswald + Inter fonts
- **Real-time Validation**: Client-side and server-side form validation
- **Search & Filter**: Search through podcast entries
- **Password Protection**: Secure admin access with authentication modal

### **🆕 Automated Features (October 2025)**
- **Automated Feed Scanning** 🔄: Cron job updates episode dates every 30 minutes automatically
- **Smart Sorting** 📊: Server-side sorting by latest episode dates, title, or status
- **Episode Date Tracking** 📅: Automatically extracts and stores latest episode publication dates
- **Sort Persistence** 💾: Sort preferences saved server-side for consistent feed output
- **Auto-Sync Across Browsers** 🔄: Changes sync automatically across all browsers and machines
- **Real-Time Updates** ⚡: No hard refresh needed - polls for changes every 30 seconds
- **Zero Maintenance** ✨: Set it and forget it - fully automated updates
- **Production Ready** 🚀: Auto-detects environment, handles HTTPS, no hardcoded URLs

### **🎭 UI/UX Features (October 2025)**
- **Podcast Preview Cards** 👁️: Click info button to see full RSS metadata
- **Beautiful Dark Modal**: Two-column layout with large image and comprehensive details
- **Hover Effects**: Eye icon overlay on covers, gradient underline on titles
- **Quick Actions**: Edit, Refresh, Health Check, Delete from preview
- **Smart Formatting**: Intelligent date display (Today, Yesterday, etc.) and language names
- **Comprehensive Data**: Shows title, description, episodes, category, author, language, pub date

### **🎧 Podcast Player Modal (October 16, 2025)** ✨
- **In-Browser Audio Player** 🎵: Play podcast episodes directly in your browser
- **Episode Management**: Browse, search, and sort all episodes from any podcast
- **Full Playback Controls**: Play/pause, skip ±15s, previous/next episode, progress scrubber
- **Advanced Features**: Volume control, playback speed (0.5x-2x), keyboard shortcuts
- **Beautiful UI**: Material Design dark mode with smooth animations
- **Episode Actions**: Download MP3 files, play episodes instantly
- **Smart Behavior**: Speed resets between podcasts, audio stops when modal closes
- **Client-Side Parsing**: Fast RSS feed parsing directly in browser
- **Responsive Design**: Works perfectly on desktop, tablet, and mobile

### **📡 Live Feed Data (October 17, 2025)** ✨
- **Always Fresh Modals** 🔄: Player and info modals fetch live data from RSS feeds
- **One Source of Truth**: RSS feeds are the ultimate source, not cached database
- **Smart Caching**: Main page shows cached data for fast loads, modals show live data
- **Refresh Button**: Manual refresh updates cached data and display immediately
- **Cache Busting**: Version-based asset loading prevents stale JavaScript
- **Consistent Display**: All locations use identical date calculation logic
- **Performance Optimized**: Fast page loads with fresh data on demand

## 📋 Requirements

- **PHP**: 7.4 or higher
- **Extensions**: xml, dom, gd, fileinfo
- **Web Server**: Apache, Nginx, or built-in PHP server
- **Permissions**: Write access to data/, uploads/, and logs/ directories

## 🛠️ Installation

1. **Clone or download** the project files to your web server directory

2. **Set up permissions** for required directories:
   ```bash
   chmod 755 data/ uploads/ logs/
   chmod 755 uploads/covers/
   ```

3. **🔐 IMPORTANT: Change the default password** in `auth.js`:
   ```javascript
   // Line 10 in auth.js
   const CORRECT_PASSWORD = 'your-secure-password-here';  // Change from 'podcast2025'!
   ```
   
   **Note:** This is client-side protection for casual use. For production with sensitive data, see [GITHUB-SECURITY-AUDIT.md](GITHUB-SECURITY-AUDIT.md) for additional security options (HTTP Basic Auth, IP whitelisting, etc.).

4. **Configure the application** (optional - auto-detects by default):
   - APP_URL is auto-detected from server
   - Environment auto-detected (localhost = development, else = production)
   - HTTPS auto-detected from server headers

5. **Test the installation** by visiting `index.php` in your browser

## 📁 Project Structure

```
podcast-feed/
├── index.php                    # Public podcast browser
├── admin.php                    # Admin management interface
├── feed.php                     # RSS XML output endpoint
├── ads-manager.php              # Banner ads management (NEW)
├── mobile-ads-feed.php          # Mobile ads RSS feed (NEW)
├── login.php                    # Placeholder login page
├── README.md                    # This file
├── config/
│   ├── config.php               # App configuration
│   └── auth_placeholder.php     # Authentication structure
├── includes/
│   ├── PodcastManager.php              # Core CRUD operations
│   ├── XMLHandler.php                  # XML file management
│   ├── ImageUploader.php               # Image upload handling
│   ├── AudioUploader.php               # Audio file upload handling
│   ├── RssImportValidator.php          # RSS feed validation
│   ├── SelfHostedPodcastManager.php    # Self-hosted podcast logic
│   ├── SelfHostedXMLHandler.php        # Self-hosted XML operations
│   ├── AdsManager.php                  # Ads business logic (NEW)
│   ├── AdsXMLHandler.php               # Ads XML operations (NEW)
│   ├── AdsImageUploader.php            # Ads image upload (NEW)
│   └── functions.php                   # Utility functions
├── api/
│   ├── upload-ad.php                   # Ad upload endpoint (NEW)
│   ├── delete-ad.php                   # Ad deletion endpoint (NEW)
│   ├── update-ad-settings.php          # Ad settings endpoint (NEW)
│   ├── update-ad-url.php               # Ad URL endpoint (NEW)
│   └── get-ad-data.php                 # Ad data endpoint (NEW)
├── assets/
│   ├── css/
│   │   ├── style.css           # Main dark theme styles
│   │   ├── components.css      # UI components
│   │   ├── browse.css          # Public browse page styles
│   │   ├── player-modal.css    # Audio player modal styles
│   │   ├── ads-manager.css     # Ads manager styles (NEW)
│   │   └── web-banner.css      # Front-end banner styles (NEW)
│   └── js/
│       ├── app.js              # Admin application logic
│       ├── browse.js           # Public browse page logic
│       ├── player-modal.js     # Player modal functionality
│       ├── audio-player.js     # Audio playback controls
│       ├── ads-manager.js      # Ads manager logic (NEW)
│       └── validation.js       # Form validation
├── data/
│   ├── podcasts.xml                 # Aggregated podcast directory
│   ├── self-hosted-podcasts.xml     # My Podcasts (self-hosted)
│   ├── ads-config.xml               # Banner ads configuration (NEW)
│   └── backup/                      # XML backups
├── uploads/
│   ├── covers/                      # Podcast cover images
│   ├── audio/                       # Self-hosted audio files
│   └── ads/                         # Banner ad images (NEW)
│       ├── web/                     # Web banners (728x90)
│       └── mobile/                  # Mobile banners (320x50, 728x90)
├── self-hosted-podcasts.php         # My Podcasts management page
├── self-hosted-episodes.php         # Episode management page
├── self-hosted-feed.php             # RSS feed generator
└── logs/
    ├── error.log                    # Error logging
    └── operations.log               # Activity logging
```

## 🎯 Usage

### **Public Browsing (index.php)** 🌐 NEW

**For End Users:**

1. **Visit the homepage** - No password required!
2. **Browse podcasts** in beautiful card grid layout
3. **Search** - Type to filter podcasts in real-time
4. **Sort** - Choose from Latest Episodes, Alphabetical, or Most Episodes
5. **Click any podcast** - Opens player modal with full episode list
6. **Play episodes** - Stream directly in browser with full controls
7. **Access Admin** - Click "Admin" in header (requires password)

**Features:**
- Clean, minimal stats bar showing podcast and episode counts
- Hover effects reveal play button overlay
- Podcast titles displayed on image with gradient overlay
- Episode count badge on each card
- "New" badge for podcasts with episodes in last 7 days
- Latest episode date shown below description
- Responsive grid adapts to all screen sizes

### **Admin Management (admin.php)** 🔐

**For Administrators:**

Access the admin panel by clicking "Admin" in the header or visiting `/admin.php` directly. Password required.

#### **Option 1: Create Your Own Podcast ("My Podcasts")** ✨ NEW

1. Click **"My Podcasts"** button in header
2. Click **"Create New Podcast"**
3. Fill in podcast metadata:
   - Basic info (title, description, author, email, website)
   - iTunes metadata (category, language, explicit flag, type)
   - Upload cover image (1400-3000px)
4. Click **"Create Podcast"**
5. Click **"Episodes"** button to manage episodes
6. Add episodes:
   - Upload MP3 audio files (up to 500MB)
   - Add episode metadata (title, description, duration)
   - Optional episode artwork
   - Set publication date and status
7. Your podcast gets its own RSS feed URL
8. Optionally import to main directory using "Import from RSS"

**My Podcasts Features:**
- Complete podcast creation from scratch
- Audio file hosting on your server
- Episode management (add, edit, delete)
- iTunes-compliant RSS feed generation
- Persistent storage in Coolify volumes
- Seamless integration with existing directory

#### **Option 2: Import from RSS Feed** ✨

1. Click **"Import from RSS"** button
2. Paste any podcast RSS feed URL
3. Click **"Fetch Feed"**
4. **NEW: Automatic Validation** (2-3 seconds)
   - ✅ **Perfect feeds**: Brief success message, auto-continues
   - ⚠️ **Feeds with warnings**: Shows issues, you choose to continue or cancel
   - ❌ **Bad feeds**: Shows errors, import blocked with helpful suggestions
5. System automatically extracts:
   - Podcast title
   - Description
   - Cover image (auto-downloads)
   - Episode count
   - Feed type (RSS 2.0, Atom, iTunes)
6. Preview and edit extracted data
7. Click **"Import Podcast"** to save

**Validation Checks:**
- Valid RSS 2.0/Atom XML structure
- Feed URL accessibility (HTTP 200)
- Cover image exists and meets size requirements (1400-3000px)
- Required fields present (title, link, description)
- At least one episode exists
- iTunes namespace and tags (warning if missing)
- Response time (warning if >5 seconds)

#### **Option 3: Add Manually**

1. Click **"Add New Podcast"** button
2. Enter podcast title (3-200 characters)
3. Enter RSS feed URL (must be valid HTTP/HTTPS URL)
4. Upload cover image (1400x1400 to 3000x3000 pixels, max 2MB)
5. Click **"Add Podcast"** to save

### **Health Check** 🏥 NEW

1. Click the health check (🏥) button next to any podcast
2. System validates:
   - **Feed URL**: Accessibility and response time
   - **RSS 2.0 Structure**: Required elements and format
   - **iTunes Namespace**: Apple Podcasts compatibility
   - **Cover Image**: Availability and format
3. View detailed results with color-coded status badges
4. Click "Check Again" to re-run validation

### **Sorting & Filtering** 📊 NEW

1. **Sort Options**: Click the sort dropdown to choose:
   - **Newest Episodes**: Shows podcasts with latest episodes first (default)
   - **Oldest Episodes**: Shows podcasts with oldest episodes first
   - **A-Z**: Alphabetical by title
   - **Z-A**: Reverse alphabetical
   - **Active First**: Active podcasts at top
   - **Inactive First**: Inactive podcasts at top

2. **Auto-Sync**: Sort preferences sync automatically
   - Changes save to server instantly
   - All browsers/machines see same order
   - External apps (Flutter) get correctly sorted feed
   - Polls for changes every 30 seconds
   - No hard refresh needed!

3. **View Feed**: Click "View Feed" button
   - Shows RSS XML with current sort applied
   - URL includes sort parameters automatically
   - Copy URL for use in podcast apps

4. **Refresh Feed Data**: Click 🔄 button on any podcast
   - Fetches latest episode date from RSS feed
   - Updates episode count
   - Re-sorts automatically

### **Podcast Player** 🎧 (October 16, 2025)

**Available on both public and admin pages!**

1. **Click on any podcast card** (public) or **cover/title** (admin)
2. Beautiful player modal opens with full episode list:
   - Podcast cover and information at top
   - Complete list of episodes with covers and metadata
   - Search and sort episodes
   - Download or play any episode
3. **Play Episodes**:
   - Click play button on any episode
   - Audio player bar appears at bottom
   - Full controls: play/pause, skip, scrubber, volume, speed
   - Keyboard shortcuts (Space, arrows, M for mute)
4. **Episode Actions**:
   - Download: Save MP3 file to your device
   - Play: Stream episode directly in browser
5. **Close**: Press Escape, click X, or click outside modal
   - Audio stops automatically when modal closes
   - Playback speed resets to 1.0x

### **Podcast Preview** 👁️ (Info Button)

1. **Click the info button (ℹ️)** next to any podcast
2. Preview modal opens with comprehensive details:
   - Large podcast image (240x240px)
   - Full description
   - Episode count (highlighted)
   - Latest episode date (smart formatting)
   - Category from RSS feed
   - Author information
   - Language (human-readable)
   - Publication date
   - Image dimensions
3. **Quick Actions** at bottom:
   - Edit: Opens edit modal
   - Refresh: Updates feed metadata
   - Health Check: Runs diagnostics
   - Delete: Removes podcast
4. **Close**: Press Escape or click outside modal

### **Other Actions**

- **Edit**: Click ✏️ to modify podcast details
- **Delete**: Click 🗑️ to remove podcast (with confirmation)
- **Toggle Status**: Click status badge to activate/deactivate
- **View Feed**: Click feed URL to see RSS XML
- **Stats**: Click "Stats" in navigation for directory statistics

### **Banner Ads Management** 📢 (October 22, 2025)

**Access:** Click "Ads Manager" in admin panel or visit `/ads-manager.php`

#### **Upload Banners:**
1. **Web Banners (728x90px)**:
   - Drag image to upload zone or click to browse
   - System validates exact dimensions
   - Appears in live preview and public homepage
   
2. **Mobile Banners**:
   - **Phone (320x50px)**: Small mobile banners
   - **Tablet (728x90px)**: Larger mobile banners
   - Included in RSS feed for mobile app

#### **Manage Banners:**
- **Add URLs**: Click "Add URL" button, enter destination URL in modal
- **Reorder**: Drag ad cards to change rotation sequence
- **Delete**: Click X button, confirm deletion
- **Toggle On/Off**: Enable/disable web or mobile ads
- **Adjust Timing**: 
  - Rotation Duration: 5-60 seconds
  - Fade Duration: 0.5-3 seconds

#### **Front-End Display:**
- Banner appears under header on `index.php`
- Rotates automatically based on settings
- Clickable if URL is set
- Only shows when enabled

#### **Mobile RSS Feed:**
- URL: `https://your-domain.com/mobile-ads-feed.php`
- Copy from ads manager interface
- Use in mobile/tablet apps
- Includes dimensions, URLs, display order

## 📡 RSS Feed URLs

### For Podcast Apps

Your RSS feed uses **server-side sort preferences** for consistent output:

```
# Default (uses saved preference from admin panel)
https://your-domain.com/feed.php

# Override with explicit sort parameters
https://your-domain.com/feed.php?sort=episodes&order=desc  # Newest episodes
https://your-domain.com/feed.php?sort=episodes&order=asc   # Oldest episodes
https://your-domain.com/feed.php?sort=title&order=asc      # Alphabetical A-Z
https://your-domain.com/feed.php?sort=date&order=desc      # Newest added
https://your-domain.com/feed.php?sort=status&order=desc    # Active first
```

**How It Works:**
- Admin changes sort in any browser → Saves to server
- `feed.php` reads saved preference as default
- External apps get consistently sorted feed
- URL parameters can override saved preference

### Sort Parameters

| Parameter | Values | Description |
|-----------|--------|-------------|
| `sort` | `episodes`, `date`, `title`, `status` | What to sort by |
| `order` | `asc`, `desc` | Ascending or descending |

**Default**: Uses saved preference from admin panel (changeable anytime)

### Automated Updates

- **Episode Data**: Feed automatically updates every 30 minutes via cron job
- **Sort Preferences**: Sync across all browsers automatically (30-second polling)
- **Latest Episode Dates**: Fetched from source RSS feeds automatically
- **No Manual Refresh**: Changes appear automatically in all browsers
- **Always Fresh**: Content and sorting stay current

## 🔧 Configuration

### Image Upload Settings

Modify in `config/config.php`:

```php
define('MIN_IMAGE_WIDTH', 1400);
define('MIN_IMAGE_HEIGHT', 1400);
define('MAX_IMAGE_WIDTH', 3000);
define('MAX_IMAGE_HEIGHT', 3000);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
```

### Application Settings

```php
define('APP_NAME', 'Your Podcast Directory');
define('APP_URL', 'http://your-domain.com/path');
define('ENVIRONMENT', 'production'); // or 'development'
```

## 🛡️ Security Features

- **Input Sanitization**: All user inputs are sanitized and validated
- **XSS Prevention**: HTML entities escaped in output
- **File Upload Security**: MIME type and dimension validation
- **CSRF Protection**: Built-in CSRF token system (ready for auth)
- **Rate Limiting**: Structure for preventing abuse
- **Error Handling**: Comprehensive error logging and user feedback

## 📱 Mobile Support

The interface is fully responsive and works on:
- Desktop computers
- Tablets
- Mobile phones
- Touch devices with drag-and-drop support

## 🔮 Future Authentication

The system includes a placeholder authentication structure ready for:

- **User Management**: Admin user registration and login
- **Role-Based Access**: Different permission levels
- **Session Management**: Secure session handling
- **Password Security**: Proper password hashing
- **Login Attempt Limiting**: Brute force protection
- **Activity Logging**: User action audit trails

To enable authentication, modify the `AuthPlaceholder` class in `config/auth_placeholder.php`.

## 📊 RSS Feed Format

The generated RSS feed follows standard specifications:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>Available Podcasts Directory</title>
        <description>Directory of available podcasts for mobile app integration</description>
        <link>http://your-domain.com/podcast-feed/</link>
        <atom:link href="http://your-domain.com/podcast-feed/feed.php" rel="self" type="application/rss+xml"/>
        
        <item>
            <title><![CDATA[Podcast Title]]></title>
            <description><![CDATA[Available podcast feed]]></description>
            <link><![CDATA[https://feeds.example.com/podcast.xml]]></link>
            <guid>pod_123456789</guid>
            <pubDate>Thu, 09 Jan 2025 16:49:54 GMT</pubDate>
            <enclosure url="http://your-domain.com/podcast-feed/uploads/covers/image.jpg" type="image/jpeg"/>
        </item>
    </channel>
</rss>
```

## 🐛 Troubleshooting

### Common Issues

**Permissions Error**
```
Solution: Check directory permissions
chmod 755 data/ uploads/ logs/
```

**Image Upload Fails**
```
Check: File size, dimensions, and MIME type
Max size: 2MB
Dimensions: 1400x1400 to 3000x3000 pixels
Formats: JPG, PNG, GIF
```

**XML Feed Not Loading**
```
Check: PHP XML extension installed
Verify: data/podcasts.xml file exists and is readable
```

**Styling Issues**
```
Verify: CSS files are loading correctly
Check: Browser cache (try hard refresh)
```

### Error Logs

Check these files for detailed error information:
- `logs/error.log` - Application errors
- `logs/operations.log` - User operations
- Server error logs (Apache/Nginx)

## 🔧 Development

### Local Development Setup

1. Use PHP built-in server:
   ```bash
   php -S localhost:8000
   # Or if port 8000 is in use:
   php -S localhost:8080
   ```

2. Update `APP_URL` in config:
   ```php
   define('APP_URL', 'http://localhost:8000');
   ```

3. Set development mode:
   ```php
   define('ENVIRONMENT', 'development');
   ```

4. Access the app:
   ```
   http://localhost:8000
   # Or: http://localhost:8080
   ```

### Code Structure

- **Object-Oriented PHP**: Clean, maintainable code structure
- **Separation of Concerns**: Logic separated from presentation
- **Error Handling**: Comprehensive try-catch blocks
- **Input Validation**: Client-side and server-side validation
- **Documentation**: Inline comments and method documentation

## 📝 API Reference

### RSS Feed Endpoint

**GET** `/feed.php`
- Returns RSS XML feed
- Content-Type: `application/rss+xml`
- Includes all active podcast entries
- Cached for 1 hour

### Internal Methods

**PodcastManager Class:**
- `createPodcast($data, $imageFile)` - Add new podcast
- `updatePodcast($id, $data, $imageFile)` - Update existing
- `deletePodcast($id)` - Remove podcast
- `getAllPodcasts($includeImageInfo)` - Get all entries
- `getRSSFeed()` - Generate RSS XML

## 🤝 Contributing

1. Follow existing code style and structure
2. Add comments for complex logic
3. Test changes thoroughly
4. Update documentation as needed
5. Check for security implications

## 📄 License

This project is provided as-is for educational and commercial use. Modify as needed for your requirements.

## 🆘 Support

For issues, questions, or feature requests:

1. Check the troubleshooting section above
2. Review error logs for detailed information
3. Verify system requirements are met
4. Test with minimal configuration first

---

## 📚 Documentation

### **Quick Links**
- 📖 **[DOCUMENTATION-INDEX.md](DOCUMENTATION-INDEX.md)** - Complete documentation index
- ⚡ **[QUICK-START-DEPLOYMENT-FIX.md](QUICK-START-DEPLOYMENT-FIX.md)** - Fast deployment setup
- 📋 **[DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md)** - Production deployment guide
- 🔒 **[SECURITY-AUDIT.md](SECURITY-AUDIT.md)** - Security best practices
- 🚀 **[FUTURE-DEV.md](FUTURE-DEV.md)** - Roadmap and planned features
- 📡 **[RSS-IMPORT-IMPLEMENTATION.md](RSS-IMPORT-IMPLEMENTATION.md)** - RSS import feature docs
- 👁️ **[PODCAST-PREVIEW-FEATURE.md](PODCAST-PREVIEW-FEATURE.md)** - Preview cards feature
- 🎧 **[PLAYER-MODAL-IMPLEMENTATION.md](PLAYER-MODAL-IMPLEMENTATION.md)** - Podcast player modal (Oct 16)
- 🎙️ **[SELF-HOSTED-IMPLEMENTATION-SUMMARY.md](SELF-HOSTED-IMPLEMENTATION-SUMMARY.md)** - Self-hosted platform (NEW Oct 17)
- 📐 **[SELF-HOSTED-ARCHITECTURE-V2.md](SELF-HOSTED-ARCHITECTURE-V2.md)** - Technical architecture
- 📖 **[SELF-HOSTED-PODCAST-GUIDE.md](SELF-HOSTED-PODCAST-GUIDE.md)** - User guide

### **Historical Documentation**
- 📁 **[docs-archive/](docs-archive/)** - Archived development and debugging docs

---

**Version**: 4.0.0  
**Last Updated**: October 17, 2025  
**Compatibility**: PHP 7.4+, Modern Browsers  
**Status**: ✅ Production Ready - Fully Automated  
**Features**: **Self-Hosted Podcast Platform**, **Public Podcast Browser**, **Admin Management Panel**, Audio File Uploads, Episode Management, RSS Auto-Import with Validation, Health Check, Auto-Sync Sorting, Podcast Preview Cards, **In-Browser Podcast Player**, **Live Feed Data**, Material Design UI, Persistent Storage, Cache Busting