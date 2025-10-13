# PodFeed Builder

A modern, feature-rich podcast directory management system with RSS feed auto-import, health monitoring, and a beautiful dark-themed interface.

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

#### Automation & Sorting (NEW):
5. 🎉 **[TODAYS-WORK-SUMMARY.md](TODAYS-WORK-SUMMARY.md)** - Complete summary of Oct 13, 2025 updates
6. 🚀 **[PRODUCTION-DEPLOYMENT-READY.md](PRODUCTION-DEPLOYMENT-READY.md)** - Production readiness guide
7. 🔄 **[AUTOMATION-COMPLETE.md](AUTOMATION-COMPLETE.md)** - Automated scanning setup
8. 📊 **[SERVER-SIDE-SORTING-COMPLETE.md](SERVER-SIDE-SORTING-COMPLETE.md)** - Sorting implementation
9. 📝 **[sort-options.md](sort-options.md)** - Original planning document

**Quick Diagnostics:**
- Visit `/check-user.php` in production to verify permissions
- All directories should show "✅ Writable"

---

## 🚀 Features

### **Core Features**
- **Full CRUD Operations**: Create, Read, Update, Delete podcast entries
- **RSS Feed Auto-Import** ✨: Import podcasts from any RSS feed with one click
- **Podcast Health Check** ✨: Validate RSS 2.0 structure, iTunes namespace, and feed accessibility
- **Image Management**: Upload or auto-download cover images with validation
- **XML-Based Storage**: Lightweight file-based storage system
- **RSS Feed Generation**: Standard-compliant RSS feed output for app integration
- **Dark Theme Interface**: Modern Material Design dark mode with Oswald + Inter fonts
- **Real-time Validation**: Client-side and server-side form validation
- **Search & Filter**: Search through podcast entries
- **Custom Password Protection**: Beautiful dark mode authentication modal

### **🆕 Automated Features (October 2025)**
- **Automated Feed Scanning** 🔄: Cron job updates episode dates every 30 minutes automatically
- **Smart Sorting** 📊: Server-side sorting by latest episode dates, title, or status
- **Episode Date Tracking** 📅: Automatically extracts and stores latest episode publication dates
- **Sort Synchronization** 🔗: Admin panel and RSS feed sorting stay in sync
- **Zero Maintenance** ✨: Set it and forget it - fully automated updates
- **Production Ready** 🚀: Auto-detects environment, handles HTTPS, no hardcoded URLs

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

3. **Configure the application** in `config/config.php`:
   ```php
   define('APP_URL', 'http://your-domain.com/podcast-feed');
   ```

4. **Test the installation** by visiting `index.php` in your browser

## 📁 Project Structure

```
podcast-feed/
├── index.php                    # Main CRUD interface
├── feed.php                     # RSS XML output endpoint
├── login.php                    # Placeholder login page
├── README.md                    # This file
├── config/
│   ├── config.php               # App configuration
│   └── auth_placeholder.php     # Authentication structure
├── includes/
│   ├── PodcastManager.php       # Core CRUD operations
│   ├── XMLHandler.php           # XML file management
│   ├── ImageUploader.php        # Image upload handling
│   └── functions.php            # Utility functions
├── assets/
│   ├── css/
│   │   ├── style.css           # Main dark theme styles
│   │   └── components.css      # UI components
│   └── js/
│       ├── app.js              # Main application logic
│       └── validation.js       # Form validation
├── data/
│   ├── podcasts.xml            # XML data storage
│   └── backup/                 # XML backups
├── uploads/
│   └── covers/                 # Podcast cover images
└── logs/
    ├── error.log              # Error logging
    └── operations.log         # Activity logging
```

## 🎯 Usage

### **Option 1: Import from RSS Feed** ✨ NEW

1. Click **"Import from RSS"** button
2. Paste any podcast RSS feed URL
3. System automatically extracts:
   - Podcast title
   - Description
   - Cover image (auto-downloads)
   - Episode count
   - Feed type (RSS 2.0, Atom, iTunes)
4. Preview and edit extracted data
5. Click **"Import Podcast"** to save

### **Option 2: Add Manually**

1. Click **"Add New Podcast"** button
2. Enter podcast title (3-200 characters)
3. Enter RSS feed URL (must be valid HTTP/HTTPS URL)
4. Upload cover image (1400x1400 to 2400x2400 pixels, max 2MB)
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

2. **View Feed**: Click "View Feed" button
   - Shows RSS XML with current sort applied
   - URL includes sort parameters automatically
   - Copy URL for use in podcast apps

3. **Refresh Feed Data**: Click 🔄 button on any podcast
   - Fetches latest episode date from RSS feed
   - Updates episode count
   - Re-sorts automatically

### **Other Actions**

- **Edit**: Click ✏️ to modify podcast details
- **Delete**: Click 🗑️ to remove podcast (with confirmation)
- **Toggle Status**: Click status badge to activate/deactivate
- **View Feed**: Click feed URL to see RSS XML
- **Stats**: Click "Stats" in navigation for directory statistics

## 📡 RSS Feed URLs

### For Podcast Apps

Your RSS feed supports sorting parameters for different use cases:

```
# Default (Newest episodes first - recommended)
https://your-domain.com/feed.php

# With explicit sort parameters
https://your-domain.com/feed.php?sort=episodes&order=desc  # Newest episodes
https://your-domain.com/feed.php?sort=episodes&order=asc   # Oldest episodes
https://your-domain.com/feed.php?sort=title&order=asc      # Alphabetical A-Z
https://your-domain.com/feed.php?sort=date&order=desc      # Newest added
https://your-domain.com/feed.php?sort=status&order=desc    # Active first
```

### Sort Parameters

| Parameter | Values | Description |
|-----------|--------|-------------|
| `sort` | `episodes`, `date`, `title`, `status` | What to sort by |
| `order` | `asc`, `desc` | Ascending or descending |

**Default**: `sort=episodes&order=desc` (newest episodes first)

### Automated Updates

- Feed automatically updates every 30 minutes via cron job
- Latest episode dates fetched from source RSS feeds
- No manual refresh required
- Always shows freshest content

## 🔧 Configuration

### Image Upload Settings

Modify in `config/config.php`:

```php
define('MIN_IMAGE_WIDTH', 1400);
define('MIN_IMAGE_HEIGHT', 1400);
define('MAX_IMAGE_WIDTH', 2400);
define('MAX_IMAGE_HEIGHT', 2400);
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
Dimensions: 1400x1400 to 2400x2400 pixels
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
   ```

2. Update `APP_URL` in config:
   ```php
   define('APP_URL', 'http://localhost:8000');
   ```

3. Set development mode:
   ```php
   define('ENVIRONMENT', 'development');
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

### **Historical Documentation**
- 📁 **[docs-archive/](docs-archive/)** - Archived development and debugging docs

---

**Version**: 2.0.0  
**Last Updated**: October 2025  
**Compatibility**: PHP 7.4+, Modern Browsers  
**Status**: ✅ Production Ready - Deployment Issues Solved  
**Features**: RSS Auto-Import, Health Check, Material Design UI, Persistent Storage