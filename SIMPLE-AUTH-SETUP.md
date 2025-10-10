# Simple Authentication Setup Guide

## Overview

This guide provides the **simplest possible authentication** for your Podcast Directory Manager. Perfect for non-critical data where you just need basic protection.

---

## ✅ What's Already Done

- ✅ Config auto-detects localhost vs production
- ✅ Error handling switches automatically
- ✅ APP_URL auto-configures based on domain
- ✅ RSS feed (`feed.php`) will be public (no auth needed)

**You can now develop locally and deploy to production without changing any code!**

---

## 🔐 Three Simple Auth Options (Pick One)

### Option 1: HTTP Basic Auth (.htaccess) ⭐ RECOMMENDED

**Best for:** Simple, built-in, works everywhere

**Setup Time:** 2 minutes

#### Step 1: Create Password File

```bash
# On your server (or locally to test)
cd /Users/paulhenshaw/Desktop/podcast-feed

# Create password file (replace 'admin' with your username)
htpasswd -c .htpasswd admin
# Enter password when prompted (e.g., your-secure-password)

# Set permissions
chmod 644 .htpasswd
```

#### Step 2: Activate .htaccess

```bash
# Copy the example file
cp .htaccess.example .htaccess

# Edit .htaccess and update this line:
# AuthUserFile /absolute/path/to/.htpasswd
# Change to your actual path, e.g.:
# AuthUserFile /var/www/podcast-feed/.htpasswd
```

#### Step 3: Deploy

```bash
git add .htaccess .htpasswd
git commit -m "Add basic auth"
git push
```

**That's it!** Your admin interface is now password-protected, but the RSS feed remains public.

---

### Option 2: Coolify Basic Auth ⭐ EASIEST

**Best for:** If you're using Coolify

**Setup Time:** 30 seconds

1. In Coolify dashboard, go to your application
2. Click **"Security"** tab
3. Enable **"Basic Authentication"**
4. Set username and password
5. Save and redeploy

**Done!** Coolify handles everything automatically.

---

### Option 3: IP Whitelist (No Password)

**Best for:** If you always access from same location

**Setup Time:** 1 minute

Create `.htaccess`:

```apache
# Allow only from your IP
Order Deny,Allow
Deny from all
Allow from YOUR.IP.ADDRESS.HERE

# Allow RSS feed for everyone
<Files "feed.php">
    Allow from all
</Files>
```

Find your IP: https://whatismyipaddress.com/

---

## 🎯 Recommended: Option 1 (HTTP Basic Auth)

**Why?**
- Simple and standard
- No external dependencies
- Works with any hosting
- RSS feed stays public
- Easy to add more users

**Quick Setup:**

```bash
# 1. Create password
htpasswd -c .htpasswd admin

# 2. Copy and edit .htaccess
cp .htaccess.example .htaccess
nano .htaccess  # Update AuthUserFile path

# 3. Done!
```

---

## 🧪 Testing

### Test Locally (Development)

```bash
# Start local server
php -S localhost:8000

# Visit http://localhost:8000
# Should work WITHOUT password (it's localhost)

# RSS feed should work
curl http://localhost:8000/feed.php
```

### Test Production

```bash
# Visit your domain
https://yourdomain.com
# Should prompt for username/password

# RSS feed should be public
curl https://yourdomain.com/feed.php
# Should work without auth
```

---

## 📝 Managing Users (HTTP Basic Auth)

### Add Another User

```bash
# Add user (without -c flag to avoid overwriting)
htpasswd .htpasswd newuser
```

### Change Password

```bash
# Same command as adding
htpasswd .htpasswd admin
```

### Remove User

```bash
# Edit .htpasswd and delete the line
nano .htpasswd
```

---

## 🚀 Deployment Checklist

- [ ] Choose auth method (Option 1 recommended)
- [ ] Test locally (should work without password)
- [ ] Create `.htpasswd` if using Option 1
- [ ] Copy `.htaccess.example` to `.htaccess`
- [ ] Update `AuthUserFile` path in `.htaccess`
- [ ] Add to git: `git add .htaccess .htpasswd`
- [ ] Commit: `git commit -m "Add authentication"`
- [ ] Push: `git push origin main`
- [ ] Deploy in Coolify
- [ ] Test: Visit your domain (should ask for password)
- [ ] Test: Visit `/feed.php` (should work without password)

---

## 🔧 Troubleshooting

### "Internal Server Error" after adding .htaccess

**Cause:** Wrong path in `AuthUserFile`

**Fix:** Use absolute path, not relative:
```apache
# Wrong
AuthUserFile .htpasswd

# Right
AuthUserFile /var/www/podcast-feed/.htpasswd
```

Find absolute path:
```bash
pwd  # Shows current directory
# Use that path + /.htpasswd
```

### Password prompt not appearing

**Cause:** .htaccess not being read

**Fix:** Check Apache config allows `.htaccess`:
```apache
# In Apache config, ensure:
AllowOverride All
```

Or use Coolify's built-in basic auth instead.

### RSS feed asking for password

**Cause:** `<Files "feed.php">` section not working

**Fix:** Make sure this is in your `.htaccess`:
```apache
<Files "feed.php">
    Satisfy Any
    Allow from all
</Files>
```

---

## 🎉 Summary

**For the simplest setup:**

1. Use **HTTP Basic Auth** (.htaccess)
2. One username/password protects everything
3. RSS feed stays public
4. Works locally without password
5. Works in production with password
6. No code changes needed

**Total setup time:** 2 minutes  
**Maintenance:** Zero  
**Security level:** Good enough for non-critical XML feed data

---

## 📞 Need More Security Later?

The placeholder auth system in `config/auth_placeholder.php` is ready for:
- Multiple user accounts
- Role-based permissions
- Session management
- Activity logging

But for now, simple HTTP Basic Auth is perfect for your use case!
