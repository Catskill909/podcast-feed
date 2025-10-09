# Podcast Directory Manager

A comprehensive podcast XML feed management system with a dark-themed interface that enables users to manage podcast directory entries through full CRUD operations.

## 🚀 Features

- **Full CRUD Operations**: Create, Read, Update, Delete podcast entries
- **Image Upload Management**: Cover image upload with strict dimension validation (1400x1400 to 2400x2400px)
- **XML-Based Storage**: Lightweight file-based storage system using XML
- **RSS Feed Generation**: Standard-compliant RSS feed output for app integration
- **Dark Theme Interface**: Modern, responsive dark mode design
- **Real-time Validation**: Client-side and server-side form validation
- **Search & Filter**: Search through podcast entries
- **Placeholder Authentication**: Structure ready for future admin login functionality

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

### Adding a New Podcast

1. Click **"Add New Podcast"** button
2. Enter podcast title (3-200 characters)
3. Enter RSS feed URL (must be valid HTTP/HTTPS URL)
4. Upload cover image (1400x1400 to 2400x2400 pixels, max 2MB)
5. Click **"Add Podcast"** to save

### Editing an Existing Podcast

1. Click the edit (✏️) button next to any podcast
2. Modify the desired fields
3. Upload a new cover image if needed
4. Click **"Update Podcast"** to save changes

### Deleting a Podcast

1. Click the delete (🗑️) button next to any podcast
2. Confirm deletion in the modal dialog
3. The podcast and its cover image will be permanently removed

### Viewing the RSS Feed

- **Web View**: Click "View Feed" in the navigation or visit `feed.php`
- **RSS URL**: Copy the feed URL from the RSS Feed card for app integration
- **Direct Link**: `http://your-domain.com/podcast-feed/feed.php`

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

**Version**: 1.0.0  
**Last Updated**: January 2025  
**Compatibility**: PHP 7.4+, Modern Browsers