# Podcast Player Modal - Design & Implementation Plan

## 🎯 Overview
A beautiful Material Design dark mode podcast player modal that opens when users click on the Cover or Title in the podcast table. The modal will display podcast information, episode list, and include an integrated audio player with playback controls.

---

## 🎨 Design Specifications

### Visual Style
- **Design System**: Material Design principles
- **Theme**: Dark mode matching current app aesthetic
- **Colors**: Use existing CSS variables from `style.css`
  - Background: `var(--bg-card)`, `var(--bg-secondary)`, `var(--bg-tertiary)`
  - Text: `var(--text-primary)`, `var(--text-secondary)`
  - Accents: `var(--accent-primary)`, `var(--accent-info)`
- **Typography**: 
  - Headings: `var(--font-heading)` (Oswald)
  - Body: `var(--font-family)` (Inter)
- **Animations**: Smooth transitions using `var(--transition-base)`

### Modal Structure

```
┌─────────────────────────────────────────────────────────────┐
│  [X Close]                    PODCAST PLAYER                 │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────┐  PODCAST INFO                                 │
│  │          │  Title: Radio Chatskill                        │
│  │  COVER   │  Description: Local news and talk...          │
│  │  IMAGE   │  Episodes: 245 | Status: Active               │
│  │          │  Latest: Oct 15, 2025                          │
│  └──────────┘                                                │
│                                                               │
├─────────────────────────────────────────────────────────────┤
│  EPISODES                                      [Search 🔍]   │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────────────────────────────────────────────┐│
│  │ [IMG] Episode Title                    [45:23] [⬇][🔗][▶]││
│  │       Oct 15, 2025 • Description preview...             ││
│  ├─────────────────────────────────────────────────────────┤│
│  │ [IMG] Episode Title                    [32:15] [⬇][🔗][▶]││
│  │       Oct 14, 2025 • Description preview...             ││
│  └─────────────────────────────────────────────────────────┘│
│                                                               │
└─────────────────────────────────────────────────────────────┘

WHEN PLAYING:
┌─────────────────────────────────────────────────────────────┐
│  NOW PLAYING: Episode Title                                  │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  12:34 ────────●──────────────────────────── 45:23          │
│           [⏮] [⏪] [⏸] [⏩] [⏭]  [🔊] [⚙️]                  │
└─────────────────────────────────────────────────────────────┘
```

---

## 📋 Component Breakdown

### 1. Modal Container
- **Class**: `.player-modal-overlay`
- **Features**:
  - Full-screen overlay with backdrop blur
  - Click outside to close
  - ESC key to close
  - Smooth fade-in animation
  - Max-width: 1000px (larger than standard modal)
  - Max-height: 90vh with scroll

### 2. Podcast Header Section
**Layout**: Horizontal flex layout
- **Left Side**: Cover image (200x200px)
  - Rounded corners
  - Drop shadow
  - Fallback placeholder if no image
  
- **Right Side**: Podcast metadata
  - **Title**: Large, bold (Oswald font)
  - **Description**: 2-3 line preview with "Read more" expansion
  - **Stats Row**: 
    - Episode count badge
    - Status badge (Active/Inactive)
    - Latest episode date
    - Feed type (RSS 2.0/Atom)
  - **Action Buttons**:
    - Subscribe (RSS icon)
    - Share (link icon)
    - Settings/Edit (gear icon - admin only)

### 3. Episodes List Section
**Header Bar**:
- Title: "EPISODES" (Oswald, uppercase)
- Episode count: "(245 episodes)"
- Search box: Filter episodes by title/description
- Sort dropdown: Newest/Oldest/Title A-Z

**Episode Card** (repeating):
```
┌─────────────────────────────────────────────────────────┐
│ [60x60    Episode Title (truncated to 1 line)           │
│  cover]   Oct 15, 2025 • 45:23                          │
│           Description preview (2 lines max)...          │
│                                                          │
│           [⬇ Download] [🔗 Share] [▶ Play]             │
└─────────────────────────────────────────────────────────┘
```

**Episode Card Details**:
- **Cover**: 60x60px thumbnail (falls back to podcast cover)
- **Title**: Bold, clickable to play
- **Meta**: Date • Duration
- **Description**: 2-line preview with ellipsis
- **Actions**:
  - **Download Icon** (⬇): Download MP3 file
  - **Share Icon** (🔗): Copy episode link
  - **Play/Pause Icon** (▶/⏸): Toggle playback
- **Hover State**: Slight elevation, border highlight
- **Active State**: When playing, show animated equalizer icon

### 4. Audio Player Overlay (Bottom Sticky)
**Appears when**: User clicks play on any episode

**Layout**:
```
┌─────────────────────────────────────────────────────────┐
│  NOW PLAYING                                             │
│  Episode Title                                    [X]    │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━  │
│  12:34 ────────●──────────────────────────── 45:23      │
│                                                          │
│  [⏮ Prev] [⏪ -15s] [⏸ Pause] [⏩ +15s] [⏭ Next]       │
│                                                          │
│  [🔊 Volume] [⚙️ Speed: 1x] [📋 Queue]                  │
└─────────────────────────────────────────────────────────┘
```

**Features**:
- Sticky to bottom of modal (or screen if modal scrolled)
- Collapsible/expandable
- Playback controls:
  - Previous episode
  - Skip backward 15s
  - Play/Pause (large button)
  - Skip forward 15s
  - Next episode
- Progress bar:
  - Scrubber (draggable)
  - Current time / Total duration
  - Buffered indicator
- Additional controls:
  - Volume slider
  - Playback speed (0.5x, 0.75x, 1x, 1.25x, 1.5x, 2x)
  - Queue/playlist view
- Persist playback when modal closes (mini player in corner)

---

## 🔧 Technical Implementation

### File Structure
```
/assets/
  /css/
    player-modal.css          # New file for player modal styles
  /js/
    player-modal.js           # New file for player functionality
    audio-player.js           # New file for audio player logic
```

### HTML Structure (to add to index.php)

```html
<!-- Podcast Player Modal -->
<div id="playerModal" class="modal-overlay player-modal-overlay">
  <div class="modal modal-xl player-modal">
    
    <!-- Modal Header -->
    <div class="modal-header player-modal-header">
      <h2 class="modal-title">
        <span class="player-icon">🎙️</span>
        <span id="playerModalPodcastTitle">Podcast Player</span>
      </h2>
      <button type="button" class="modal-close" onclick="hidePlayerModal()">&times;</button>
    </div>

    <!-- Modal Body -->
    <div class="modal-body player-modal-body">
      
      <!-- Podcast Info Section -->
      <div class="player-podcast-info">
        <div class="player-podcast-cover">
          <img id="playerPodcastCover" src="" alt="Podcast Cover">
        </div>
        <div class="player-podcast-details">
          <h3 id="playerPodcastName" class="player-podcast-name"></h3>
          <div id="playerPodcastDescription" class="player-podcast-description"></div>
          <div class="player-podcast-meta">
            <span class="badge badge-primary" id="playerEpisodeCount"></span>
            <span class="badge badge-success" id="playerStatus"></span>
            <span class="text-muted" id="playerLatestEpisode"></span>
          </div>
          <div class="player-podcast-actions">
            <button class="btn btn-sm btn-outline" onclick="subscribeToPodcast()">
              <span>📡</span> Subscribe
            </button>
            <button class="btn btn-sm btn-outline" onclick="sharePodcast()">
              <span>🔗</span> Share
            </button>
          </div>
        </div>
      </div>

      <!-- Episodes Section -->
      <div class="player-episodes-section">
        <div class="player-episodes-header">
          <h4>EPISODES</h4>
          <div class="player-episodes-controls">
            <input type="text" id="episodeSearch" class="form-control form-control-sm" 
                   placeholder="Search episodes...">
            <select id="episodeSort" class="form-control form-control-sm">
              <option value="newest">Newest First</option>
              <option value="oldest">Oldest First</option>
              <option value="title">Title A-Z</option>
            </select>
          </div>
        </div>

        <!-- Episodes List -->
        <div id="playerEpisodesList" class="player-episodes-list">
          <!-- Episodes will be dynamically loaded here -->
          <div class="player-loading">
            <div class="spinner"></div>
            <p>Loading episodes...</p>
          </div>
        </div>
      </div>

    </div>

    <!-- Audio Player (Sticky Bottom) -->
    <div id="audioPlayerBar" class="audio-player-bar" style="display: none;">
      <div class="audio-player-info">
        <span class="audio-player-label">NOW PLAYING</span>
        <span id="currentEpisodeTitle" class="audio-player-title"></span>
        <button class="audio-player-close" onclick="stopPlayback()">&times;</button>
      </div>
      
      <div class="audio-player-progress">
        <span id="currentTime" class="audio-time">0:00</span>
        <div class="audio-progress-bar">
          <div id="audioBuffered" class="audio-buffered"></div>
          <div id="audioProgress" class="audio-progress"></div>
          <input type="range" id="audioScrubber" class="audio-scrubber" 
                 min="0" max="100" value="0" step="0.1">
        </div>
        <span id="totalDuration" class="audio-time">0:00</span>
      </div>

      <div class="audio-player-controls">
        <button class="audio-control-btn" onclick="previousEpisode()" title="Previous">
          <span>⏮</span>
        </button>
        <button class="audio-control-btn" onclick="skipBackward()" title="Skip -15s">
          <span>⏪</span>
        </button>
        <button class="audio-control-btn audio-control-play" onclick="togglePlayback()" title="Play/Pause">
          <span id="playPauseIcon">▶</span>
        </button>
        <button class="audio-control-btn" onclick="skipForward()" title="Skip +15s">
          <span>⏩</span>
        </button>
        <button class="audio-control-btn" onclick="nextEpisode()" title="Next">
          <span>⏭</span>
        </button>
      </div>

      <div class="audio-player-extras">
        <div class="audio-volume">
          <button onclick="toggleMute()"><span id="volumeIcon">🔊</span></button>
          <input type="range" id="volumeSlider" min="0" max="100" value="100">
        </div>
        <div class="audio-speed">
          <button onclick="cyclePlaybackSpeed()">
            <span id="speedLabel">1x</span>
          </button>
        </div>
      </div>

      <!-- Hidden audio element -->
      <audio id="audioPlayer" preload="metadata"></audio>
    </div>

  </div>
</div>
```

### CSS Architecture (player-modal.css)

**Key Classes**:
- `.player-modal-overlay` - Full screen overlay
- `.player-modal` - Main modal container (max-width: 1000px)
- `.player-podcast-info` - Podcast header section (flex layout)
- `.player-podcast-cover` - Cover image container
- `.player-podcast-details` - Metadata and description
- `.player-episodes-section` - Episodes container
- `.player-episode-card` - Individual episode card
- `.player-episode-card.playing` - Active playing state
- `.audio-player-bar` - Sticky audio player
- `.audio-progress-bar` - Scrubber container
- `.audio-control-btn` - Playback buttons

**Responsive Breakpoints**:
- Desktop (>768px): Side-by-side layout
- Tablet (768px): Stacked layout
- Mobile (<480px): Compact controls

### JavaScript Architecture

#### player-modal.js
**Purpose**: Handle modal display and episode management

**Key Functions**:
```javascript
// Modal control
showPlayerModal(podcastId)
hidePlayerModal()
loadPodcastData(podcastId)
loadEpisodes(podcastId)

// Episode list management
renderEpisodeCard(episode)
filterEpisodes(searchTerm)
sortEpisodes(sortBy)

// Episode actions
playEpisode(episodeId)
downloadEpisode(episodeUrl, title)
shareEpisode(episodeId)
```

#### audio-player.js
**Purpose**: Handle audio playback and controls

**Key Functions**:
```javascript
// Playback control
togglePlayback()
stopPlayback()
previousEpisode()
nextEpisode()
skipForward(seconds = 15)
skipBackward(seconds = 15)

// Progress management
updateProgress()
seekTo(time)
onTimeUpdate()

// Volume & speed
setVolume(level)
toggleMute()
setPlaybackSpeed(speed)
cyclePlaybackSpeed()

// State management
savePlaybackState()
restorePlaybackState()
```

---

## 🔌 API Integration

### Existing API to Reuse
From the info modal implementation, we can reuse:
- `api/get-podcast-preview.php` - Get podcast metadata

### New API Endpoints Needed

#### 1. Get Podcast Episodes
**File**: `api/get-podcast-episodes.php`
**Method**: GET
**Parameters**: 
- `podcast_id` (required)
- `limit` (optional, default: 50)
- `offset` (optional, default: 0)

**Response**:
```json
{
  "success": true,
  "data": {
    "podcast_id": "abc123",
    "podcast_title": "Radio Chatskill",
    "total_episodes": 245,
    "episodes": [
      {
        "id": "ep001",
        "title": "Episode Title",
        "description": "Episode description...",
        "pub_date": "2025-10-15T10:00:00Z",
        "duration": "45:23",
        "duration_seconds": 2723,
        "audio_url": "https://example.com/episode.mp3",
        "image_url": "https://example.com/episode-cover.jpg",
        "file_size": "42.5 MB",
        "episode_number": 245
      }
    ]
  }
}
```

#### 2. Track Episode Play
**File**: `api/track-episode-play.php`
**Method**: POST
**Purpose**: Analytics - track which episodes are played

**Payload**:
```json
{
  "podcast_id": "abc123",
  "episode_id": "ep001",
  "timestamp": "2025-10-15T14:30:00Z"
}
```

---

## 🎯 User Interactions & Flows

### Flow 1: Open Player Modal
1. User clicks on podcast **Cover** or **Title** in table
2. Modal fades in with backdrop blur
3. Podcast info loads (from existing API)
4. Episodes list loads (from new API)
5. Show loading spinner while fetching
6. Render episode cards when data arrives

### Flow 2: Play Episode
1. User clicks **Play** button on episode card
2. Audio player bar slides up from bottom
3. Episode card shows "playing" state (animated equalizer)
4. Audio starts loading (show buffering indicator)
5. Playback begins when ready
6. Progress bar updates in real-time
7. Other episode cards show "Play" button

### Flow 3: Download Episode
1. User clicks **Download** icon
2. Browser initiates download of MP3 file
3. Show toast notification: "Downloading episode..."
4. Download completes via browser

### Flow 4: Share Episode
1. User clicks **Share** icon
2. Copy episode URL to clipboard
3. Show toast notification: "Link copied!"
4. URL format: `https://yoursite.com/episode?id=ep001`

### Flow 5: Search Episodes
1. User types in search box
2. Debounced search (300ms delay)
3. Filter episodes by title/description
4. Show "No results" if empty
5. Clear search shows all episodes

### Flow 6: Close Modal with Active Playback
1. User closes modal (X button or ESC)
2. Modal closes
3. **Mini player appears** in bottom-right corner of page
4. Mini player shows: Cover, Title, Play/Pause
5. Click mini player to reopen modal
6. Playback continues seamlessly

---

## 🎨 Reusable Components from Existing Code

### From Info Modal
- Modal overlay structure
- Modal header/body/footer layout
- Close button behavior
- ESC key handler
- Click outside to close
- Smooth animations

### From Existing CSS
- Color variables
- Typography system
- Button styles
- Badge styles
- Form controls
- Spacing utilities
- Responsive breakpoints

### From app.js
- Modal show/hide functions
- Event listener patterns
- Alert/toast notifications
- Debounce utility
- Format time utility

---

## 📱 Responsive Design

### Desktop (>768px)
- Two-column layout (cover + info)
- 3 episodes per row (grid)
- Full audio controls visible
- Modal width: 1000px

### Tablet (768px)
- Stacked layout (cover above info)
- 2 episodes per row
- Compact audio controls
- Modal width: 90vw

### Mobile (<480px)
- Single column
- 1 episode per row
- Minimal audio controls (essential only)
- Modal width: 95vw
- Swipe gestures for scrubber

---

## ✅ Implementation Checklist

### Phase 1: Structure & Styling
- [ ] Create `player-modal.css`
- [ ] Add HTML structure to index.php
- [ ] Style podcast info section
- [ ] Style episodes list section
- [ ] Style audio player bar
- [ ] Add responsive breakpoints
- [ ] Test on different screen sizes

### Phase 2: Modal Functionality
- [ ] Create `player-modal.js`
- [ ] Implement `showPlayerModal()`
- [ ] Implement `hidePlayerModal()`
- [ ] Update table click handlers (Cover & Title)
- [ ] Load podcast data from API
- [ ] Render podcast info section
- [ ] Add ESC and overlay click handlers

### Phase 3: Episodes List
- [ ] Create API endpoint `get-podcast-episodes.php`
- [ ] Parse RSS feed for episodes
- [ ] Implement `loadEpisodes()`
- [ ] Implement `renderEpisodeCard()`
- [ ] Add search functionality
- [ ] Add sort functionality
- [ ] Add loading states
- [ ] Add empty states

### Phase 4: Audio Player
- [ ] Create `audio-player.js`
- [ ] Implement play/pause toggle
- [ ] Implement progress bar scrubber
- [ ] Implement time display
- [ ] Implement skip forward/backward
- [ ] Implement previous/next episode
- [ ] Implement volume control
- [ ] Implement playback speed
- [ ] Add keyboard shortcuts (spacebar, arrows)

### Phase 5: Episode Actions
- [ ] Implement download episode
- [ ] Implement share episode (copy link)
- [ ] Add toast notifications
- [ ] Track episode plays (analytics)

### Phase 6: Advanced Features
- [ ] Persist playback state (localStorage)
- [ ] Mini player when modal closed
- [ ] Queue/playlist management
- [ ] Keyboard shortcuts
- [ ] Remember playback position
- [ ] Auto-play next episode

### Phase 7: Testing & Polish
- [ ] Test all playback controls
- [ ] Test on different browsers
- [ ] Test on mobile devices
- [ ] Test with different podcast feeds
- [ ] Test error handling (404 audio, network errors)
- [ ] Optimize performance
- [ ] Add loading animations
- [ ] Add micro-interactions

---

## 🚀 Future Enhancements

### V2 Features
- [ ] Chapters support (if in RSS)
- [ ] Transcript display
- [ ] Show notes formatting
- [ ] Episode bookmarks
- [ ] Playback history
- [ ] Favorites/starred episodes
- [ ] Offline download queue
- [ ] Sleep timer
- [ ] Equalizer settings

### V3 Features
- [ ] Comments/notes on episodes
- [ ] Social sharing (Twitter, Facebook)
- [ ] Email episode link
- [ ] Embed player code generator
- [ ] Cross-device sync (cloud)
- [ ] Playlist creation
- [ ] Episode recommendations

---

## 📊 Performance Considerations

### Optimization Strategies
1. **Lazy load episodes**: Load 20 at a time, infinite scroll
2. **Cache podcast data**: Store in sessionStorage
3. **Debounce search**: 300ms delay
4. **Optimize images**: Use responsive images, lazy load
5. **Preload next episode**: When 80% through current
6. **Service worker**: Cache audio files for offline
7. **Virtual scrolling**: For podcasts with 1000+ episodes

### Loading States
- Skeleton screens for episode cards
- Spinner for initial load
- Progress indicator for audio buffering
- Shimmer effect for images loading

---

## 🔒 Security & Error Handling

### Security
- Validate all podcast IDs
- Sanitize episode data (XSS prevention)
- CORS headers for audio files
- Rate limiting on API endpoints
- Validate audio URLs before playing

### Error Handling
- Network errors: Show retry button
- 404 audio files: Show error message, skip to next
- Malformed RSS: Graceful degradation
- Slow loading: Show timeout message
- Browser compatibility: Check for Audio API support

---

## 📝 Code Style Guidelines

### JavaScript
- ES6+ syntax
- Async/await for API calls
- Error handling with try/catch
- JSDoc comments for functions
- Consistent naming conventions

### CSS
- BEM methodology for class names
- CSS variables for theming
- Mobile-first approach
- Avoid !important
- Group related properties

### HTML
- Semantic markup
- ARIA labels for accessibility
- Data attributes for state
- Progressive enhancement

---

## 🎓 Learning from Info Modal

### What Worked Well
✅ Hybrid approach (RSS + database fallback)
✅ Reusing existing modal structure
✅ Smooth animations and transitions
✅ Responsive design
✅ Error handling

### What to Improve
⚠️ Don't over-engineer initially
⚠️ Test incrementally
⚠️ Follow existing patterns
⚠️ Document as we build
⚠️ Start simple, add features later

---

## 🎬 Next Steps

1. **Review this plan** with user
2. **Create CSS file** with base styles
3. **Add HTML structure** to index.php
4. **Create JavaScript files** with core functions
5. **Build API endpoint** for episodes
6. **Test basic functionality**
7. **Iterate and refine**

---

## 📚 References

- Material Design Guidelines: https://material.io/design
- Web Audio API: https://developer.mozilla.org/en-US/Web/API/Web_Audio_API
- RSS 2.0 Spec: https://www.rssboard.org/rss-specification
- Podcast RSS Best Practices: https://help.apple.com/itc/podcasts_connect

---

**Status**: Planning Complete ✅
**Ready for Implementation**: Yes
**Estimated Development Time**: 2-3 days
**Priority**: High
