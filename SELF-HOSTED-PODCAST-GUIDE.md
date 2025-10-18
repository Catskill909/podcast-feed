# Self-Hosted Podcast System - Quick Start Guide

## 🎉 What's New

You can now create and host your own podcast feeds directly within your app! This modular system generates standard RSS 2.0 + iTunes compliant feeds that integrate seamlessly with your existing podcast directory.

---

## 🚀 How It Works

```
1. CREATE PODCAST → 2. ADD EPISODES → 3. GENERATE FEED → 4. IMPORT TO DIRECTORY
```

**The Beauty:** Self-hosted podcasts use the same RSS import mechanism as external podcasts. No special handling needed!

---

## 📋 Quick Start

### Step 1: Access Self-Hosted Podcasts

1. Go to your admin panel (`admin.php`)
2. Click the green **"Create Self-Hosted Podcast"** button in the header
3. You'll see the self-hosted podcast management page

### Step 2: Create Your First Podcast

1. Click **"Create New Podcast"** button
2. Fill in the required information:
   - **Podcast Title** (required)
   - **Description** (required)
   - **Author Name** (required)
   - **Email** (required for iTunes)
   - **Cover Image** (1400x1400 to 3000x3000 pixels)
   - **Category** (select from iTunes categories)
   - **Language** (default: English US)
   - **Explicit Content** (Yes/No/Clean)

3. Click **"Create Podcast"**

### Step 3: Add Episodes

1. From the podcast list, click **"Episodes"** button
2. Click **"Add New Episode"**
3. Fill in episode details:
   - **Episode Title** (required)
   - **Description** (required)
   - **Audio File URL** (required - must be publicly accessible .mp3)
   - **Duration** (HH:MM:SS or seconds)
   - **File Size** (in bytes)
   - **Publication Date**
   - **Episode Number** (optional)
   - **Season Number** (optional)
   - **Episode Type** (Full/Trailer/Bonus)
   - **Status** (Published/Draft)

4. Click **"Add Episode"**
5. Repeat for all episodes

### Step 4: Get Your RSS Feed URL

1. Return to the self-hosted podcasts page
2. Each podcast card shows its RSS Feed URL
3. Click **"Copy"** to copy the feed URL

### Step 5: Import to Your Directory

1. Go back to the main admin panel
2. Click **"Import from RSS"**
3. Paste your self-hosted feed URL
4. The existing validation system will check your feed
5. Click **"Import Podcast"**

**Done!** Your self-hosted podcast now appears alongside external podcasts in your directory.

---

## 🎯 Key Features

### Beautiful Interface
- **Card Grid Layout** - Visual podcast management
- **Episode Management** - Add, edit, delete episodes
- **RSS Feed Preview** - View generated feed anytime
- **One-Click Copy** - Easy feed URL copying

### iTunes Compliance
- **All Required Tags** - Author, owner, image, category, explicit
- **Episode Metadata** - Numbers, seasons, types
- **Proper Formatting** - RFC 2822 dates, valid XML structure
- **Category System** - All 18 iTunes categories + subcategories

### Modular Architecture
- **Separate Storage** - `data/self-hosted-podcasts.xml`
- **No Core Changes** - Existing podcast management untouched
- **Standard RSS** - Works with any podcast player
- **Reuses Validation** - Same import checks as external feeds

---

## 📁 Files Created

### Backend
- `/self-hosted-podcasts.php` - Podcast list and management
- `/self-hosted-episodes.php` - Episode management
- `/self-hosted-feed.php` - RSS feed generator
- `/includes/SelfHostedXMLHandler.php` - XML operations
- `/includes/SelfHostedPodcastManager.php` - Business logic

### Data
- `/data/self-hosted-podcasts.xml` - Podcast and episode storage
- `/uploads/covers/` - Podcast and episode images (shared)

---

## 🎨 iTunes Categories Available

- **Arts** (Books, Design, Fashion & Beauty, Food, Performing Arts, Visual Arts)
- **Business** (Careers, Entrepreneurship, Investing, Management, Marketing, Non-Profit)
- **Comedy** (Comedy Interviews, Improv, Stand-Up)
- **Education** (Courses, How To, Language Learning, Self-Improvement)
- **Fiction** (Comedy Fiction, Drama, Science Fiction)
- **Health & Fitness** (Alternative Health, Fitness, Medicine, Mental Health, Nutrition, Sexuality)
- **History**
- **Kids & Family** (Education for Kids, Parenting, Pets & Animals, Stories for Kids)
- **Leisure** (Animation & Manga, Automotive, Aviation, Crafts, Games, Hobbies, Home & Garden, Video Games)
- **Music** (Music Commentary, Music History, Music Interviews)
- **News** (Business News, Daily News, Entertainment News, News Commentary, Politics, Sports News, Tech News)
- **Religion & Spirituality** (Buddhism, Christianity, Hinduism, Islam, Judaism, Religion, Spirituality)
- **Science** (Astronomy, Chemistry, Earth Sciences, Life Sciences, Mathematics, Natural Sciences, Nature, Physics, Social Sciences)
- **Society & Culture** (Documentary, Personal Journals, Philosophy, Places & Travel, Relationships)
- **Sports** (Baseball, Basketball, Cricket, Fantasy Sports, Football, Golf, Hockey, Rugby, Running, Soccer, Swimming, Tennis, Volleyball, Wilderness, Wrestling)
- **Technology**
- **True Crime**
- **TV & Film** (After Shows, Film History, Film Interviews, Film Reviews, TV Reviews)

---

## 💡 Tips & Best Practices

### Audio Hosting
- Host MP3 files on a reliable CDN or server
- Ensure files are publicly accessible (no authentication required)
- Use consistent file naming (e.g., `episode-001.mp3`)
- Keep file sizes reasonable (typically 20-100 MB)

### Cover Art
- **Recommended Size:** 3000x3000 pixels (maximum quality)
- **Minimum Size:** 1400x1400 pixels
- **Format:** JPG or PNG
- **File Size:** Under 2MB
- Use square images only
- Ensure text is readable at small sizes

### Episode Metadata
- **Duration:** Can be in HH:MM:SS format or seconds
- **File Size:** Helps podcast apps estimate download time
- **Episode Numbers:** Optional but recommended for serial podcasts
- **Publication Date:** Can be in the past or future (for scheduling)

### Feed Updates
- After adding/editing episodes, the RSS feed updates automatically
- No need to re-import to your directory
- The feed URL never changes
- Podcast players will fetch new episodes automatically

---

## 🔧 Troubleshooting

### Feed Validation Errors

**Problem:** Feed doesn't pass validation when importing

**Solutions:**
- Ensure all required fields are filled
- Check that audio URLs are publicly accessible
- Verify cover image meets size requirements (1400-3000px)
- Make sure email is in valid format
- Confirm MP3 files are actually .mp3 format

### Audio Files Not Playing

**Problem:** Episodes show but won't play

**Solutions:**
- Verify audio URL is publicly accessible (test in browser)
- Ensure URL ends with `.mp3`
- Check that file is actually an MP3 (not M4A or other format)
- Verify CORS headers if hosting on different domain

### Cover Image Not Showing

**Problem:** Podcast image doesn't appear

**Solutions:**
- Check image file size (must be under 2MB)
- Verify dimensions (1400x1400 to 3000x3000 pixels)
- Ensure image format is JPG or PNG
- Try re-uploading the image

---

## 🎯 Workflow Example

**Scenario:** Creating a weekly podcast with 3 episodes

1. **Create Podcast** (5 minutes)
   - Title: "Tech Talk Weekly"
   - Description: "Weekly discussions about technology"
   - Author: "John Doe"
   - Email: "john@example.com"
   - Upload 3000x3000 cover image
   - Category: Technology
   - Language: English (US)

2. **Add Episodes** (5 minutes each)
   - Episode 1: "Introduction to AI"
     - Audio: `https://cdn.example.com/tech-talk/ep001.mp3`
     - Duration: 00:45:00
     - File Size: 42000000 bytes
     - Episode Number: 1
     - Status: Published
   
   - Episode 2: "Cloud Computing Basics"
     - Audio: `https://cdn.example.com/tech-talk/ep002.mp3`
     - Duration: 00:52:00
     - File Size: 48000000 bytes
     - Episode Number: 2
     - Status: Published
   
   - Episode 3: "Cybersecurity Tips" (upcoming)
     - Audio: `https://cdn.example.com/tech-talk/ep003.mp3`
     - Duration: 00:38:00
     - File Size: 35000000 bytes
     - Episode Number: 3
     - Status: Draft (will publish later)

3. **Copy Feed URL**
   - `https://yourdomain.com/self-hosted-feed.php?id=shp_1729123456`

4. **Import to Directory**
   - Go to admin panel
   - Click "Import from RSS"
   - Paste feed URL
   - Validation passes ✅
   - Import successful!

5. **Result**
   - Podcast appears in public browse page
   - Users can play episodes
   - Feed updates when you add new episodes
   - Works in all podcast players

---

## 🔐 Security Notes

- Self-hosted podcast management requires admin password
- RSS feeds are publicly accessible (read-only)
- Audio files must be hosted externally (not uploaded to this system)
- Image uploads use same security as existing podcast images
- All inputs are sanitized and validated

---

## 🚀 Next Steps

### Immediate Actions
1. Create your first self-hosted podcast
2. Add a few test episodes
3. Import the feed to your directory
4. Test in a podcast player (Apple Podcasts, Spotify, etc.)

### Future Enhancements (Optional)
- Audio file upload (host files locally)
- Bulk episode import from CSV
- Episode scheduling (auto-publish at specific time)
- Analytics (track downloads and plays)
- Episode artwork (unique image per episode)
- Transcription support

---

## 📚 Additional Resources

### Feed Validators
- [Cast Feed Validator](https://castfeedvalidator.com/)
- [Podbase Validator](https://podba.se/validate/)

### iTunes Podcast Requirements
- [Apple Podcasts Connect](https://podcastsconnect.apple.com/)
- [RSS Feed Requirements](https://help.apple.com/itc/podcasts_connect/#/itcb54353390)

### RSS 2.0 Specification
- [RSS 2.0 Spec](https://www.rssboard.org/rss-specification)

---

## ✅ Success Checklist

Before importing your self-hosted feed:

- [ ] Podcast has title, description, author, and email
- [ ] Cover image is 1400x1400 to 3000x3000 pixels
- [ ] Category is selected
- [ ] At least one episode is added
- [ ] Episode has title, description, and audio URL
- [ ] Audio URL is publicly accessible .mp3 file
- [ ] Feed URL has been copied
- [ ] Ready to import!

---

**Version:** 1.0  
**Last Updated:** October 17, 2025  
**Status:** ✅ Production Ready

---

**Questions or Issues?** Check the main README.md or review the planning document in `self-hosted-podcast.md`.
