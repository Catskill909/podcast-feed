# Deployment Guide - Podcast Directory Manager

## Pre-Deployment Checklist

### 1. Configuration Files

- [ ] Copy `.env.example` to `.env` and update with production values
- [ ] Update `APP_URL` in `.env` to your production domain
- [ ] Set `ENVIRONMENT=production` in `.env`
- [ ] Generate secure admin password hash and update `.env`
- [ ] Copy `config/config.production.php` to `config/config.php` (or update existing)
- [ ] Ensure `ERROR_REPORTING=0` and `DISPLAY_ERRORS=0` for production

### 2. Security

- [ ] Change default admin credentials (username: admin, password: admin123)
- [ ] Generate strong `SESSION_SECRET` for `.env`
- [ ] Ensure HTTPS is enabled on your server
- [ ] Verify `session.cookie_secure` is set to `1` in config
- [ ] Review file upload restrictions (max 2MB, allowed types)
- [ ] Set proper file permissions (see below)

### 3. File Permissions

```bash
# Set directory permissions
chmod 755 data/
chmod 755 uploads/
chmod 755 uploads/covers/
chmod 755 logs/
chmod 755 data/backup/

# Set file permissions
chmod 644 data/podcasts.xml
chmod 644 config/config.php
chmod 600 .env  # Restrict access to environment file

# Ensure web server can write to these directories
chown -R www-data:www-data data/ uploads/ logs/
# Or for nginx: chown -R nginx:nginx data/ uploads/ logs/
```

### 4. Required PHP Extensions

Verify these extensions are installed:
- `xml` - XML parsing
- `dom` - DOM manipulation
- `gd` - Image processing
- `fileinfo` - File type detection
- `mbstring` - Multibyte string support

Check with: `php -m`

### 5. Server Requirements

- PHP 7.4 or higher
- Web server (Apache/Nginx)
- Write permissions for data/, uploads/, logs/ directories
- At least 50MB free disk space (more for uploads)

## Coolify Deployment

### Step 1: Git Repository Setup

1. Initialize git repository (if not already done):
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
git init
git add .
git commit -m "Initial commit - Podcast Directory Manager"
```

2. Push to your remote repository:
```bash
git remote add origin <your-git-repo-url>
git push -u origin main
```

### Step 2: Coolify Configuration

1. **Create New Application** in Coolify
   - Type: PHP Application
   - Repository: Your git repository URL
   - Branch: main

2. **Build Configuration**
   - Build Pack: PHP
   - PHP Version: 8.1 or higher
   - Document Root: `/` (application root)

3. **Environment Variables**
   Add these in Coolify's environment settings:
   ```
   APP_URL=https://your-domain.com
   ENVIRONMENT=production
   ERROR_REPORTING=0
   DISPLAY_ERRORS=0
   TIMEZONE=UTC
   ```

4. **Port Configuration**
   - Internal Port: 80 (or as configured)
   - Public Port: 443 (HTTPS)

5. **Persistent Storage**
   Mount these directories to persist data:
   - `/data` - XML database and backups
   - `/uploads` - Cover images
   - `/logs` - Application logs

### Step 3: Post-Deployment

1. **Verify Directory Structure**
   SSH into your container and check:
   ```bash
   ls -la data/ uploads/ logs/
   ```

2. **Test Application**
   - Visit your domain
   - Try creating a test podcast
   - Verify RSS feed at `/feed.php`
   - Test image upload functionality

3. **Monitor Logs**
   ```bash
   tail -f logs/error.log
   tail -f logs/operations.log
   ```

## Important URLs

- **Admin Interface**: `https://your-domain.com/index.php`
- **RSS Feed**: `https://your-domain.com/feed.php`
- **Login Page**: `https://your-domain.com/login.php` (currently placeholder)

## Backup Strategy

### Automated Backups

The application automatically creates XML backups:
- Location: `data/backup/`
- Retention: Last 10 backups
- Triggered: On every XML modification

### Manual Backup

```bash
# Backup data directory
tar -czf podcast-backup-$(date +%Y%m%d).tar.gz data/ uploads/

# Download from server
scp user@server:/path/to/podcast-backup-*.tar.gz ./backups/
```

### Restore from Backup

```bash
# Extract backup
tar -xzf podcast-backup-YYYYMMDD.tar.gz

# Copy specific XML file
cp data/backup/podcasts_YYYY-MM-DD_HH-MM-SS.xml data/podcasts.xml
```

## Security Considerations

### 1. Authentication (Future Implementation)

Currently, the application has **NO AUTHENTICATION**. Anyone with the URL can access the admin interface.

**Immediate Actions:**
- Use Coolify's basic auth feature to protect the application
- Or implement IP whitelisting
- Or add `.htaccess` password protection

**Future Implementation:**
- The auth system is structured but not enforced
- Update `config/auth_placeholder.php` to enable authentication
- Change `isAuthenticated()` to return actual auth status

### 2. File Upload Security

Current protections:
- File type validation (MIME type checking)
- File size limits (2MB max)
- Image dimension validation
- Unique filename generation
- Restricted upload directory

### 3. Input Validation

All user inputs are:
- Sanitized with `htmlspecialchars()`
- Validated for type and format
- Checked for duplicates
- Limited in length

### 4. CSRF Protection

CSRF tokens are implemented but only used in login form currently.

## Troubleshooting

### Issue: Uploads not working

```bash
# Check permissions
ls -la uploads/covers/
# Should show: drwxr-xr-x www-data www-data

# Check PHP upload settings
php -i | grep upload
```

### Issue: XML file not updating

```bash
# Check file permissions
ls -la data/podcasts.xml
# Should be writable by web server

# Check logs
tail -f logs/error.log
```

### Issue: RSS feed not generating

```bash
# Test feed directly
curl https://your-domain.com/feed.php

# Check for XML errors
php -l includes/XMLHandler.php
```

### Issue: Images not displaying

- Verify `APP_URL` is correct in config
- Check that uploads directory is accessible via web
- Verify file permissions on uploaded images

## Monitoring

### Health Checks

Create a simple health check endpoint:
- URL: `/feed.php` (already exists)
- Expected: Valid XML response
- Status: 200 OK

### Log Monitoring

Monitor these files:
- `logs/error.log` - PHP errors
- `logs/operations.log` - CRUD operations
- Web server access logs

### Disk Space

Monitor disk usage for:
- `uploads/covers/` - Can grow with image uploads
- `data/backup/` - Automatic backups (limited to 10)
- `logs/` - Log files (should rotate)

## Performance Optimization

### 1. Enable OPcache

Add to php.ini:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### 2. RSS Feed Caching

The feed already has cache headers (1 hour).
Consider adding server-side caching for high traffic.

### 3. Image Optimization

Consider adding image compression:
- Use ImageMagick or GD for optimization
- Compress images on upload
- Generate thumbnails if needed

## Maintenance

### Regular Tasks

**Weekly:**
- Check disk space usage
- Review error logs
- Verify backups are being created

**Monthly:**
- Update PHP dependencies (if any)
- Review and clean old logs
- Test backup restoration

**As Needed:**
- Update PHP version
- Security patches
- Feature updates

## Support

For issues or questions:
1. Check logs: `logs/error.log` and `logs/operations.log`
2. Verify configuration in `.env` and `config/config.php`
3. Review this deployment guide
4. Check file permissions and directory structure

## Version History

- **v1.0.0** - Initial release
  - CRUD operations for podcasts
  - RSS feed generation
  - Image upload with validation
  - Status management (active/inactive)
  - Description field support
  - Automatic XML backups
