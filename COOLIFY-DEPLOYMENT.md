# Coolify Deployment Guide

**Project:** PodFeed Builder  
**Date:** 2025-10-10  
**Platform:** Coolify (Docker)

---

## 🚨 Permission Error Fix

If you see: `Warning: mkdir(): Permission denied in /app/config/config.php`

This means the application doesn't have write permissions to create directories.

---

## ✅ Solution: Pre-create Directories

### **Option 1: Via Coolify Terminal**

1. Go to your Coolify dashboard
2. Open your application
3. Click **"Terminal"** or **"Execute Command"**
4. Run these commands:

```bash
cd /app
mkdir -p data data/backup uploads uploads/covers logs
chmod -R 755 data uploads logs
chown -R www-data:www-data data uploads logs
```

### **Option 2: Via Dockerfile**

Add this to your `Dockerfile` (create if it doesn't exist):

```dockerfile
FROM php:8.1-apache

# Install required PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy application files
COPY . /var/www/html/

# Create required directories with proper permissions
RUN mkdir -p /var/www/html/data \
    /var/www/html/data/backup \
    /var/www/html/uploads \
    /var/www/html/uploads/covers \
    /var/www/html/logs && \
    chmod -R 755 /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs && \
    chown -R www-data:www-data /var/www/html/data \
    /var/www/html/uploads \
    /var/www/html/logs

# Set working directory
WORKDIR /var/www/html

# Expose port 80
EXPOSE 80
```

### **Option 3: Via Docker Compose**

If using `docker-compose.yml`:

```yaml
version: '3.8'
services:
  podfeed:
    build: .
    ports:
      - "80:80"
    volumes:
      - ./data:/var/www/html/data
      - ./uploads:/var/www/html/uploads
      - ./logs:/var/www/html/logs
    environment:
      - ENVIRONMENT=production
```

Then create directories locally:
```bash
mkdir -p data data/backup uploads uploads/covers logs
chmod -R 755 data uploads logs
```

---

## 📁 Required Directory Structure

```
/app/
├── data/
│   ├── podcasts.xml
│   └── backup/
├── uploads/
│   └── covers/
└── logs/
    ├── error.log
    └── operations.log
```

---

## 🔐 Correct Permissions

| Directory | Owner | Permissions | Purpose |
|-----------|-------|-------------|---------|
| `data/` | www-data | 755 | XML storage |
| `data/backup/` | www-data | 755 | XML backups |
| `uploads/` | www-data | 755 | File uploads |
| `uploads/covers/` | www-data | 755 | Cover images |
| `logs/` | www-data | 755 | Error logs |

---

## 🔧 Environment Configuration

Make sure your `config/config.php` has the correct environment:

```php
define('ENVIRONMENT', 'production'); // NOT 'development'
```

This ensures:
- ✅ SSL verification enabled for cURL
- ✅ Error logging to file (not display)
- ✅ Production-ready settings

---

## 🐳 Coolify-Specific Settings

### **1. Environment Variables**

In Coolify, set these environment variables:

```
ENVIRONMENT=production
APP_URL=https://your-domain.com
```

### **2. Build Pack**

- **Type:** PHP
- **Version:** 8.1 or higher
- **Web Server:** Apache or Nginx

### **3. Persistent Storage**

Map these volumes in Coolify:

| Local Path | Container Path | Purpose |
|------------|----------------|---------|
| `./data` | `/app/data` | Database |
| `./uploads` | `/app/uploads` | Images |
| `./logs` | `/app/logs` | Logs |

---

## 🚀 Deployment Checklist

### **Before Deployment:**
- [ ] Set `ENVIRONMENT=production` in config
- [ ] Update `APP_URL` to your domain
- [ ] Test locally with production settings
- [ ] Commit all changes to GitHub

### **After Deployment:**
- [ ] Create required directories (see Option 1 above)
- [ ] Set correct permissions (755 for directories)
- [ ] Set correct ownership (www-data:www-data)
- [ ] Test file upload functionality
- [ ] Test RSS import functionality
- [ ] Check error logs for issues

### **Verification:**
- [ ] Visit your domain - should load without errors
- [ ] Try adding a podcast manually
- [ ] Try importing from RSS
- [ ] Check if images upload/download correctly
- [ ] Run health check on a podcast
- [ ] Check logs directory for error.log

---

## 🐛 Troubleshooting

### **Issue: Permission Denied Errors**

**Symptom:** `mkdir(): Permission denied` or `fopen(): Permission denied`

**Solution:**
```bash
cd /app
chmod -R 755 data uploads logs
chown -R www-data:www-data data uploads logs
```

### **Issue: Images Not Uploading**

**Symptom:** "Failed to upload image" error

**Solution:**
```bash
chmod -R 755 uploads/covers
chown -R www-data:www-data uploads/covers
```

### **Issue: XML Not Saving**

**Symptom:** "Failed to save podcast" error

**Solution:**
```bash
chmod -R 755 data
chown -R www-data:www-data data
touch data/podcasts.xml
chmod 644 data/podcasts.xml
```

### **Issue: RSS Import Fails**

**Symptom:** "Failed to fetch feed" error

**Possible Causes:**
1. SSL certificate issues (check ENVIRONMENT=production)
2. Firewall blocking outbound requests
3. cURL not installed

**Solution:**
```bash
# Check if cURL is installed
php -m | grep curl

# If not, install it
apt-get update && apt-get install -y php-curl
```

### **Issue: 500 Internal Server Error**

**Solution:**
1. Check error logs: `tail -f logs/error.log`
2. Check Apache/Nginx logs
3. Verify PHP version (7.4+)
4. Check file permissions

---

## 📊 Monitoring

### **Check Logs:**
```bash
# Application errors
tail -f /app/logs/error.log

# Operations log
tail -f /app/logs/operations.log

# Apache errors (if using Apache)
tail -f /var/log/apache2/error.log
```

### **Check Disk Space:**
```bash
df -h /app
```

### **Check Permissions:**
```bash
ls -la /app/data
ls -la /app/uploads
ls -la /app/logs
```

---

## 🔄 Updating the Application

### **Via Coolify:**

1. Push changes to GitHub
2. Coolify auto-deploys (if enabled)
3. Or manually trigger deployment in Coolify dashboard

### **Manual Update:**

```bash
cd /app
git pull origin main
# Directories and permissions persist
```

---

## 🔒 Security Recommendations

1. **Use HTTPS:** Always use SSL/TLS in production
2. **Set Strong Password:** Update `AUTH_PASSWORD` in config
3. **Restrict Access:** Use Coolify's IP whitelist if needed
4. **Regular Backups:** Backup `data/` and `uploads/` directories
5. **Monitor Logs:** Check logs regularly for suspicious activity
6. **Update Dependencies:** Keep PHP and extensions updated

---

## 📞 Support

If issues persist:

1. Check Coolify logs in dashboard
2. Check application logs in `/app/logs/`
3. Verify all directories exist and have correct permissions
4. Test with a simple PHP info page to verify environment

---

## ✅ Success Indicators

Your deployment is successful when:

- ✅ Homepage loads without errors
- ✅ Can add podcasts manually
- ✅ Can import from RSS feeds
- ✅ Images upload and display correctly
- ✅ Health checks work
- ✅ No permission errors in logs
- ✅ RSS feed generates correctly at `/feed.php`

---

**Last Updated:** 2025-10-10  
**Version:** 2.0.0  
**Platform:** Coolify + Docker
