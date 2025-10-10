# Development Workflow - Local & Production

**Date:** 2025-10-10  
**Purpose:** Prevent deployment disasters by testing in both environments

---

## 🎯 Golden Rules

### **Rule #1: Local ≠ Production**
- **Local:** Your user owns everything, permissions don't matter
- **Production:** Docker/Coolify has strict user/permission model
- **Never assume local behavior = production behavior**

### **Rule #2: Test Before Deploy**
- Test locally first ✅
- Test in production-like environment (Docker) ✅
- Then deploy to production ✅

### **Rule #3: Don't Fix What Works**
- If production works, don't "improve" it
- Document what works, leave it alone
- Only change when there's a real bug

---

## 🔄 Development Workflow

### **Phase 1: Local Development**

**Environment:**
- macOS/Windows/Linux
- PHP built-in server: `php -S localhost:8000`
- HTTP (not HTTPS)
- Your user owns all files
- No permission restrictions

**What to Test:**
- ✅ Feature works
- ✅ No PHP errors
- ✅ UI looks correct
- ✅ Database operations work

**What NOT to Trust:**
- ❌ File permissions (they'll differ in production)
- ❌ HTTP vs HTTPS behavior
- ❌ User/ownership issues
- ❌ Docker-specific problems

### **Phase 2: Local Docker Testing (CRITICAL)**

**Before deploying to production, test in Docker locally:**

```bash
# Build the Docker image
docker build -t podfeed-test .

# Run it
docker run -p 8000:80 podfeed-test

# Test at http://localhost:8000
```

**What to Test:**
- ✅ All CRUD operations (Create, Read, Update, Delete)
- ✅ File uploads work
- ✅ File deletions work
- ✅ Logs are written
- ✅ Backups are created
- ✅ No permission errors

**If it fails here:**
- ❌ **DO NOT DEPLOY**
- Fix the Docker setup first
- Test again until it works

### **Phase 3: Production Deployment**

**Only deploy after:**
- ✅ Local works
- ✅ Local Docker works
- ✅ All tests pass

**After Deployment:**
- ✅ Test all CRUD operations
- ✅ Check logs for errors
- ✅ Verify images load (HTTPS)
- ✅ Test RSS import
- ✅ Test health check

---

## 🐳 Docker vs Local Differences

### **File Permissions**

| Environment | Owner | Permissions | PHP Can Write? |
|-------------|-------|-------------|----------------|
| **Local** | Your user | Any | ✅ Always |
| **Docker** | www-data | 755+ | ⚠️ Only if owned by www-data |

**Lesson:** Never use `chmod()` in PHP code - it won't work in Docker.

### **HTTPS Detection**

| Environment | Protocol | `$_SERVER['HTTPS']` | Works? |
|-------------|----------|---------------------|--------|
| **Local** | HTTP | Not set | ✅ |
| **Production** | HTTPS (via proxy) | May not be set | ❌ Need X-Forwarded-Proto |

**Solution:** Check multiple headers:
```php
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
    (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
);
```

### **User Context**

| Environment | PHP Runs As | Can chmod? | Can chown? |
|-------------|-------------|------------|------------|
| **Local** | Your user | ✅ Yes | ✅ Yes |
| **Docker** | www-data | ❌ No | ❌ No |

**Lesson:** Don't try to fix permissions at runtime in PHP.

---

## ✅ Pre-Deployment Checklist

### **Before Committing:**
- [ ] Code works locally
- [ ] No PHP errors/warnings
- [ ] All features tested
- [ ] Code follows existing patterns
- [ ] No hardcoded values (use config)

### **Before Pushing:**
- [ ] Tested in local Docker (if file system changes)
- [ ] All CRUD operations work in Docker
- [ ] No permission errors in Docker
- [ ] Commit message is descriptive

### **Before Deploying:**
- [ ] Code pushed to GitHub
- [ ] Reviewed changes in GitHub
- [ ] No sensitive data committed
- [ ] Ready to test in production

### **After Deploying:**
- [ ] App loads without errors
- [ ] Test add podcast
- [ ] Test edit podcast
- [ ] Test delete podcast
- [ ] Test RSS import
- [ ] Test image upload
- [ ] Test health check
- [ ] Check logs for errors
- [ ] Verify HTTPS (no mixed content)

---

## 🚨 What We Learned Today

### **The Problem:**
1. App worked perfectly in production
2. Made changes that worked locally
3. Deployed to production
4. **Everything broke** (permission errors)
5. Spent 2 hours fixing

### **Root Cause:**
- Added `chmod()` calls in `config.php`
- Worked locally (we own files)
- Failed in Docker (www-data can't chmod root-owned files)
- Didn't test in Docker before deploying

### **The Fix:**
- Reverted code changes
- Fixed permissions manually in Coolify (ONE TIME):
  ```bash
  cd /app
  chown -R www-data:www-data data uploads logs
  chmod -R 777 data uploads logs
  ```
- Documented the process

### **Prevention:**
- ✅ Test in Docker before deploying
- ✅ Don't modify file permissions in PHP
- ✅ Don't "improve" working code
- ✅ Understand local vs production differences

---

## 🛠️ Production Maintenance

### **One-Time Setup (After Fresh Deploy):**

If you ever redeploy from scratch, run these commands in Coolify terminal:

```bash
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 777 data uploads logs
```

**When to run:**
- ✅ First deployment
- ✅ After changing build pack
- ✅ After major Coolify updates
- ✅ If permission errors appear

**When NOT to run:**
- ❌ On every deployment (permissions persist)
- ❌ In PHP code (won't work)
- ❌ As part of build process (too complex)

### **Regular Deployments:**

Normal code updates don't require permission fixes. Just:
1. Commit changes
2. Push to GitHub
3. Coolify auto-deploys
4. Test the changes

Permissions stay fixed from the one-time setup.

---

## 📋 Testing Environments

### **1. Local Development**
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
php -S localhost:8000
```
- **Use for:** Feature development, UI changes, quick tests
- **Don't trust:** Permissions, HTTPS, Docker behavior

### **2. Local Docker**
```bash
docker build -t podfeed-test .
docker run -p 8000:80 podfeed-test
```
- **Use for:** Testing file operations, permissions, production-like behavior
- **Don't trust:** Exact Coolify behavior (close enough though)

### **3. Production (Coolify)**
```
https://podcast.supersoul.top
```
- **Use for:** Final testing, real users
- **Don't trust:** As a testing ground (test locally first!)

---

## 🎓 Best Practices

### **DO:**
- ✅ Test locally first
- ✅ Test in Docker before deploying
- ✅ Use environment detection (`ENVIRONMENT` constant)
- ✅ Check multiple HTTPS headers
- ✅ Keep config simple
- ✅ Document what works
- ✅ Have rollback plan

### **DON'T:**
- ❌ Use `chmod()` or `chown()` in PHP
- ❌ Assume local = production
- ❌ Deploy without testing
- ❌ "Improve" working code
- ❌ Hardcode values
- ❌ Commit sensitive data
- ❌ Skip the checklist

---

## 🔄 Git Workflow

### **Feature Development:**
```bash
# 1. Make changes locally
# 2. Test locally
php -S localhost:8000

# 3. If file system changes, test in Docker
docker build -t podfeed-test .
docker run -p 8000:80 podfeed-test

# 4. Commit
git add .
git commit -m "Feature: Description of what changed"

# 5. Push
git push origin main

# 6. Coolify auto-deploys

# 7. Test in production
# - Visit https://podcast.supersoul.top
# - Test all affected features
# - Check logs
```

### **Emergency Rollback:**
```bash
# Find last working commit
git log --oneline

# Revert to it
git revert <commit-hash>
git push origin main

# Or hard reset (DANGER - only in emergency)
git reset --hard <commit-hash>
git push origin main --force
```

---

## 📊 Environment Comparison

| Feature | Local | Local Docker | Production |
|---------|-------|--------------|------------|
| **Protocol** | HTTP | HTTP | HTTPS |
| **PHP User** | Your user | www-data | www-data |
| **Permissions** | Unrestricted | Restricted | Restricted |
| **File Ownership** | Your user | www-data/root | www-data/root |
| **Can chmod** | ✅ Yes | ❌ No | ❌ No |
| **Auto-deploy** | N/A | N/A | ✅ Yes |
| **Real users** | ❌ No | ❌ No | ✅ Yes |

---

## 🎯 Quick Reference

### **Starting Local Server:**
```bash
cd /Users/paulhenshaw/Desktop/podcast-feed
php -S localhost:8000
```

### **Testing in Docker:**
```bash
docker build -t podfeed-test .
docker run -p 8000:80 podfeed-test
# Visit http://localhost:8000
```

### **Fixing Production Permissions:**
```bash
# In Coolify terminal
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 777 data uploads logs
```

### **Checking Deployment:**
```bash
# In Coolify terminal
cd /app
ls -la data uploads logs
# Should show: drwxrwxrwx www-data www-data
```

---

## ✅ Success Criteria

### **Local Development:**
- ✅ Feature works
- ✅ No errors in browser console
- ✅ No PHP errors/warnings
- ✅ UI looks correct

### **Docker Testing:**
- ✅ All CRUD operations work
- ✅ Files can be created/deleted
- ✅ Images upload successfully
- ✅ No permission errors

### **Production:**
- ✅ All features work
- ✅ HTTPS loads correctly
- ✅ No mixed content warnings
- ✅ Images load via HTTPS
- ✅ No errors in logs
- ✅ Health checks pass

---

**Remember:** Local success ≠ Production success. Always test in Docker before deploying!

**Last Updated:** 2025-10-10  
**Status:** Battle-tested after today's deployment disaster 😅
