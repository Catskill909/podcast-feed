# Simple Password Setup Guide

## How It Works

**Super Simple:**
1. User visits your site
2. Browser prompts for password
3. Password saved in browser's localStorage
4. User never asked again on that browser
5. RSS feed (`feed.php`) stays public - no password needed

---

## Setup (30 seconds)

### Step 1: Set Your Password

Edit `auth.js` line 10:

```javascript
const CORRECT_PASSWORD = 'podcast2025';  // Change this!
```

Change `'podcast2025'` to whatever password you want.

**Examples:**
```javascript
const CORRECT_PASSWORD = 'mySecretPass123';
const CORRECT_PASSWORD = 'labor-radio-2025';
const CORRECT_PASSWORD = 'anything-you-want';
```

### Step 2: Deploy

```bash
git add auth.js
git commit -m "Add password protection"
git push
```

**That's it!**

---

## How It Works

### Local Development (localhost)
- ✅ **NO password required**
- Works normally for development

### Production (your domain)
- 🔒 **Password required** on first visit
- Saved in browser localStorage
- Never asked again on same browser

### RSS Feed
- ✅ **Always public** - no password
- Your Flutter app can access freely

---

## User Experience

**First Visit:**
```
┌─────────────────────────────────────┐
│ Enter password to access            │
│ Podcast Directory:                  │
│                                     │
│ [_____________________]             │
│                                     │
│        [OK]    [Cancel]             │
└─────────────────────────────────────┘
```

**After Entering Correct Password:**
- Page loads normally
- Password saved in browser
- Never asked again

**Wrong Password:**
- Shows "Incorrect password. 2 attempts remaining"
- Allows 3 total attempts
- After 3 failures: Shows "Access Denied" page

---

## Managing Access

### Change Password

1. Edit `auth.js`
2. Change `CORRECT_PASSWORD` value
3. Deploy

**All users will need to enter new password on next visit.**

### Clear Saved Password (for testing)

Open browser console (F12) and run:
```javascript
localStorage.removeItem('podcast_auth');
```

Or clear all site data in browser settings.

### Force Re-authentication

Change the password in `auth.js` - all users will be prompted again.

---

## Security Notes

**This is CLIENT-SIDE protection:**
- ✅ Perfect for non-critical data
- ✅ Keeps casual visitors out
- ✅ Super simple to use
- ⚠️ Not for sensitive data (password visible in source)
- ⚠️ Technical users could bypass

**For your use case (XML feed maker):** This is perfect! ✅

---

## Testing

### Test Locally
```bash
php -S localhost:8000
# Visit http://localhost:8000
# Should work WITHOUT password ✓
```

### Test Production
```bash
# Visit https://yourdomain.com
# Should prompt for password ✓

# Enter wrong password
# Should show error and retry ✓

# Enter correct password
# Should save and load page ✓

# Refresh page
# Should NOT ask for password again ✓

# Visit feed
curl https://yourdomain.com/feed.php
# Should work without password ✓
```

---

## Troubleshooting

### Password prompt not showing in production

Check `config/config.php` - environment detection:
```php
// Should detect production correctly
define('ENVIRONMENT', $isLocalhost ? 'development' : 'production');
```

### Password keeps asking every time

Check browser console (F12) for errors. localStorage might be disabled.

### Want to remove password protection

Remove these lines from `index.php`:
```php
<?php if (ENVIRONMENT === 'production'): ?>
<script src="auth.js"></script>
<?php endif; ?>
```

---

## Advantages of This Approach

✅ **Zero server configuration** - pure JavaScript  
✅ **Remembers user** - localStorage persists  
✅ **Works locally** - no password in development  
✅ **RSS feed public** - no auth needed  
✅ **30 second setup** - just change one line  
✅ **No database** - no user management needed  
✅ **One password** - share with your team  
✅ **Easy to change** - edit one file  

---

## Perfect For

- ✅ Internal tools
- ✅ Non-critical data
- ✅ Small teams
- ✅ Quick deployments
- ✅ XML feed makers (like yours!)

---

## Summary

**Setup:**
1. Edit `auth.js` - change password (line 10)
2. Deploy

**Usage:**
- Enter password once per browser
- Never asked again
- RSS feed always public

**Total complexity:** One line of code to change  
**Maintenance:** Zero  
**User friction:** Minimal (one-time password)

Perfect for your podcast feed manager! 🎉
