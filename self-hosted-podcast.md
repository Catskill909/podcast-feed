# Self-Hosted Podcast Creation System - Planning Document

## 🎯 Executive Summary

**Goal:** Add a modular podcast creation system that allows users to build and host their own podcast feeds directly within the app, then seamlessly add them to the directory using the existing RSS import mechanism.

**Key Principle:** This feature is **modular and non-invasive**. Self-hosted podcasts generate standard RSS 2.0 + iTunes namespace compliant feeds that are imported through the existing `import-rss.php` workflow. No modifications to core podcast management logic required.

---

## 📐 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│  SELF-HOSTED PODCAST CREATION FLOW                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. CREATE PODCAST                                              │
│     └─ User fills beautiful form (metadata + episodes)         │
│        └─ Stored in: data/self-hosted-podcasts.xml             │
│                                                                 │
│  2. GENERATE RSS FEED                                           │
│     └─ Dynamic endpoint: /self-hosted-feed.php?id=xyz          │
│        └─ Reads from self-hosted-podcasts.xml                  │
│        └─ Outputs RSS 2.0 + iTunes namespace XML               │
│                                                                 │
│  3. IMPORT TO DIRECTORY (Existing Mechanism)                    │
│     └─ Copy feed URL: /self-hosted-feed.php?id=xyz             │
│        └─ Use existing "Import from RSS" button                │
│           └─ Goes through import-rss.php (existing)            │
│              └─ Validates feed (existing)                      │
│                 └─ Adds to podcasts.xml (existing)             │
│                                                                 │
│  RESULT: Self-hosted podcast appears alongside external ones!  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Why This Approach?**
- ✅ **Modular:** Completely separate from existing podcast management
- ✅ **Non-invasive:** Zero changes to core PodcastManager or XMLHandler
- ✅ **Reuses existing code:** Import validation, RSS parsing, image handling
- ✅ **Standard compliant:** Generates proper RSS 2.0 + iTunes feeds
- ✅ **Flexible:** Self-hosted feeds can be imported multiple times if needed

---

## 🗂️ Data Storage

### New XML File: `data/self-hosted-podcasts.xml`

Stores self-hosted podcast metadata and episodes separately from main podcasts.xml.

**Structure:**
- Podcast metadata (title, author, category, etc.)
- Episodes array (title, audio URL, duration, etc.)
- iTunes namespace compliance fields
- Status tracking (active/inactive, published/draft)

---

## 🎨 User Interface Components

### 1. Admin Menu Addition

Add button to admin.php navigation:
```html
<button class="btn btn-primary" onclick="openSelfHostedPodcastModal()">
    <i class="fas fa-podcast"></i> Create Self-Hosted Podcast
</button>
```

### 2. Self-Hosted Podcast Form Modal

**Beautiful, modern form with 4 sections:**

#### Section 1: Podcast Information
- Podcast Title (required)
- Description (required, textarea)
- Author Name (required)
- Email (required for iTunes)
- Website URL (optional)
- Cover Image Upload (1400x1400 - 3000x3000, required)

#### Section 2: Podcast Metadata
- **Category** (dropdown with iTunes categories)
  - Arts, Business, Comedy, Education, Fiction, Government, History, Health & Fitness, Kids & Family, Leisure, Music, News, Religion & Spirituality, Science, Society & Culture, Sports, Technology, True Crime, TV & Film
- **Subcategory** (dynamic dropdown based on category)
- **Language** (dropdown: en-us, es, fr, de, etc.)
- **Explicit Content** (Yes/No toggle)
- **Copyright** (auto-populate: "© [Year] [Author]")

#### Section 3: Owner Information (iTunes requirement)
- Owner Name (defaults to author)
- Owner Email (defaults to email)

#### Section 4: Episodes (Dynamic list)
- Add Episode button opens sub-form:
  - Episode Title (required)
  - Description (required)
  - Audio File URL (required, .mp3)
  - Duration (HH:MM:SS or seconds)
  - File Size (bytes)
  - Publication Date (date picker)
  - Episode Number (optional)
  - Season Number (optional)
  - Episode Type (Full/Trailer/Bonus)
  - Explicit (Yes/No)
  - Episode Image (optional)
- Episode list with drag-to-reorder, edit, delete

---

## 🔧 Technical Implementation

### New Files Required

#### Backend PHP Files

**1. `/self-hosted-podcast.php`**
- Management interface for self-hosted podcasts
- List, create, edit, delete operations
- Episode management per podcast
- Copy feed URL for import

**2. `/includes/SelfHostedPodcastManager.php`**
- CRUD operations for podcasts and episodes
- Validation logic
- RSS feed generation

**3. `/includes/SelfHostedXMLHandler.php`**
- XML operations for self-hosted-podcasts.xml
- Add, update, delete, get operations
- Episode management within podcast nodes

**4. `/self-hosted-feed.php`**
- RSS feed generator endpoint
- Accepts `?id=shp_123` parameter
- Outputs RSS 2.0 + iTunes namespace XML
- Public access (read-only)

#### API Endpoints

**5. `/api/self-hosted-podcast.php`**
- AJAX API for podcast operations
- Actions: create, update, delete, get, list

**6. `/api/self-hosted-episode.php`**
- AJAX API for episode operations
- Actions: create, update, delete, reorder, list

#### Frontend Files

**7. `/assets/js/self-hosted-podcast.js`**
- Form validation
- Image upload with preview
- Episode list management
- AJAX calls to API
- Modal management

**8. `/assets/css/self-hosted-podcast.css`**
- Match existing dark theme
- Beautiful form layouts
- Episode list styling
- Responsive design

---

## 🎯 iTunes Namespace Compliance

### Required Channel Tags
- `<itunes:author>` - Podcast author
- `<itunes:summary>` - Brief description
- `<itunes:owner>` - Owner name and email
- `<itunes:image>` - Cover art (1400x1400 to 3000x3000)
- `<itunes:category>` - Primary category with optional subcategory
- `<itunes:explicit>` - Yes/No/Clean

### Required Episode Tags
- `<itunes:duration>` - Episode length (HH:MM:SS or seconds)
- `<itunes:explicit>` - Yes/No/Clean

### Optional Tags
- `<itunes:episode>` - Episode number
- `<itunes:season>` - Season number
- `<itunes:episodeType>` - full, trailer, or bonus
- `<itunes:subtitle>` - Short description
- `<itunes:type>` - episodic or serial

### RSS 2.0 Compliance
- Valid XML structure
- Required channel elements: title, link, description
- Required item elements: title, description, enclosure (audio file)
- Proper date format: RFC 2822 (e.g., "Tue, 15 Oct 2025 10:00:00 GMT")
- GUID for each episode (unique identifier)

---

## 🔄 Integration with Existing System

### Import Workflow

**Step 1:** User creates self-hosted podcast
- Fills form in `/self-hosted-podcast.php`
- Saves to `data/self-hosted-podcasts.xml`
- System generates feed URL: `/self-hosted-feed.php?id=shp_123`

**Step 2:** User imports to directory
- Clicks "Copy Feed URL" button
- Goes to main admin page
- Clicks "Import from RSS"
- Pastes self-hosted feed URL
- Existing validation runs automatically
- Imports successfully!

**Step 3:** Result
- Self-hosted podcast appears in main directory
- Listed alongside external podcasts
- Managed like any other podcast
- Feed updates automatically when episodes are added/edited

---

## 📋 iTunes Category Reference

### Complete Category List

**Arts:** Books, Design, Fashion & Beauty, Food, Performing Arts, Visual Arts

**Business:** Careers, Entrepreneurship, Investing, Management, Marketing, Non-Profit

**Comedy:** Comedy Interviews, Improv, Stand-Up

**Education:** Courses, How To, Language Learning, Self-Improvement

**Fiction:** Comedy Fiction, Drama, Science Fiction

**Health & Fitness:** Alternative Health, Fitness, Medicine, Mental Health, Nutrition, Sexuality

**Kids & Family:** Education for Kids, Parenting, Pets & Animals, Stories for Kids

**Leisure:** Animation & Manga, Automotive, Aviation, Crafts, Games, Hobbies, Home & Garden, Video Games

**Music:** Music Commentary, Music History, Music Interviews

**News:** Business News, Daily News, Entertainment News, News Commentary, Politics, Sports News, Tech News

**Religion & Spirituality:** Buddhism, Christianity, Hinduism, Islam, Judaism, Religion, Spirituality

**Science:** Astronomy, Chemistry, Earth Sciences, Life Sciences, Mathematics, Natural Sciences, Nature, Physics, Social Sciences

**Society & Culture:** Documentary, Personal Journals, Philosophy, Places & Travel, Relationships

**Sports:** Baseball, Basketball, Cricket, Fantasy Sports, Football, Golf, Hockey, Rugby, Running, Soccer, Swimming, Tennis, Volleyball, Wilderness, Wrestling

**Technology:** (no subcategories)

**True Crime:** (no subcategories)

**TV & Film:** After Shows, Film History, Film Interviews, Film Reviews, TV Reviews

---

## 🚀 Implementation Phases

### Phase 1: Core Infrastructure
- Create XML handler and podcast manager classes
- Basic CRUD operations
- XML structure setup

### Phase 2: RSS Feed Generation
- Implement feed generator endpoint
- iTunes namespace compliance
- Test with feed validators

### Phase 3: Admin Interface
- Create management page
- Add menu button to admin
- Podcast form modal
- Category dropdowns
- Image upload

### Phase 4: Episode Management
- Episode CRUD functionality
- Episode list with reorder
- Episode image upload
- Date/time pickers

### Phase 5: Integration & Testing
- Test RSS import workflow
- Validate feeds
- Test in podcast players
- Cross-browser testing
- Mobile responsiveness

### Phase 6: Polish & Documentation
- User documentation
- Help modal updates
- Error handling
- Loading states
- Success/error messages

---

## 🔒 Security Considerations

### Input Validation
- Sanitize all inputs (XSS prevention)
- Validate URLs (audio files, images, website)
- Check file types (images only for covers)
- Limit file sizes (2MB for images)
- Validate XML structure before saving

### File Upload Security
- Reuse existing ImageUploader class
- Unique filenames with podcast/episode IDs
- MIME type validation
- Dimension validation (1400-3000px)

### Access Control
- Same password protection as admin panel
- No public access to self-hosted-podcast.php
- Public read-only access to self-hosted-feed.php
- Rate limiting on feed endpoint (optional)

---

## 🧪 Testing Checklist

### RSS Feed Validation
- [ ] Cast Feed Validator
- [ ] Podbase Validator
- [ ] Apple Podcasts
- [ ] Spotify
- [ ] Google Podcasts
- [ ] Overcast
- [ ] Pocket Casts

### Functionality Testing
- [ ] Create podcast with all fields
- [ ] Create podcast with minimal fields
- [ ] Upload cover images (various sizes)
- [ ] Add/edit/delete episodes
- [ ] Reorder episodes
- [ ] Import self-hosted feed to directory
- [ ] Verify feed updates
- [ ] Test special characters
- [ ] Test long descriptions
- [ ] Test invalid URLs
- [ ] Test missing required fields

### Security Testing
- [ ] XSS prevention
- [ ] File upload validation
- [ ] Access control
- [ ] CSRF protection
- [ ] Rate limiting

---

## 📚 User Documentation Outline

### How to Create a Self-Hosted Podcast

1. Navigate to Admin Panel
2. Click "Create Self-Hosted Podcast"
3. Fill in podcast information
4. Add episodes
5. Save podcast
6. Copy generated feed URL
7. Import to directory using "Import from RSS"

### Managing Episodes

- Add new episodes
- Edit existing episodes
- Delete episodes
- Reorder episodes (drag & drop)
- Publish/unpublish episodes

### Best Practices

- Use high-quality cover art (3000x3000 recommended)
- Host audio files on reliable CDN
- Include detailed episode descriptions
- Use proper categories for discoverability
- Set explicit flags accurately
- Keep episode metadata up to date

---

## 💡 Future Enhancements

### Phase 2 Features (Optional)
- Audio file upload (host files locally)
- Bulk episode import (CSV)
- Episode templates
- Scheduled publishing
- Analytics integration
- Automatic transcription
- Episode artwork generator
- RSS feed statistics

### Advanced Features
- Multi-user support (different podcast owners)
- Podcast networks (group related podcasts)
- Monetization (sponsor tags, donations)
- Distribution to multiple platforms
- Podcast website generator
- Email notification for new episodes

---

## 📊 Success Metrics

### Technical Metrics
- RSS feed validation passes 100%
- Page load time < 2 seconds
- Zero breaking changes to existing code
- 100% test coverage for new code

### User Metrics
- Time to create first podcast < 10 minutes
- Form completion rate > 80%
- Import success rate > 95%
- User satisfaction score > 4/5

---

## 🎯 Next Steps

1. **Review this plan** with stakeholders
2. **Approve architecture** and approach
3. **Set timeline** for implementation
4. **Assign resources** (developer time)
5. **Create detailed specs** for Phase 1
6. **Begin development** with core infrastructure

---

*Last Updated: October 17, 2025*  
*Version: 1.0*  
*Status: Planning Phase*
