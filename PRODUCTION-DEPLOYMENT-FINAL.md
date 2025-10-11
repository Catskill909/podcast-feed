# Production Deployment - FINAL SOLUTION

**Date:** 2025-10-11  
**Status:** SOLVED - Documented permanent fix

---

## 🎯 THE PROBLEM (Why We Keep Breaking Production)

### Local vs Production Difference

| Environment | PHP User | File Owner | Works? |
|-------------|----------|------------|--------|
| **Local (macOS)** | `paulhenshaw` | `paulhenshaw` | ✅ YES |
| **Production (Coolify/Nixpacks)** | `nobody` (UID 65534) | `root` (initially) | ❌ NO |

**The Trap:**
1. Code works perfectly locally (same user owns everything)
2. Deploy to production
3. Files owned by `root`, PHP runs as `nobody`
4. PHP cannot write to `root`-owned files
5. Everything breaks

---

## ✅ THE PERMANENT FIX

### After EVERY Deployment in Coolify

Run these commands in Coolify terminal:

```bash
cd /app
chown -R 65534:65534 data uploads logs
chmod -R 755 data uploads logs
```

**Why these specific commands:**
- `65534` = UID/GID of `nobody` user (the PHP user in Nixpacks)
- `755` = Owner can read/write/execute, others can read/execute
- Must be run after EVERY deploy because files reset to `root:root`

### Verify It Worked

```bash
ls -la data uploads logs
```

**Should show:**
```
drwxr-xr-x nobody nogroup ... data
drwxr-xr-x nobody nogroup ... uploads
drwxr-xr-x nobody nogroup ... logs
```

---

## 🚫 WHAT NOT TO DO (Lessons Learned)

### ❌ DON'T: Add chmod() to PHP Code

```php
// ❌ BAD - This doesn't work in production
@chmod($dir, 0777);
```

**Why it fails:**
- PHP runs as `nobody`
- Files owned by `root`
- `nobody` cannot chmod `root`-owned files
- Fails silently with `@`
- We think it worked but it didn't

### ❌ DON'T: Assume www-data

```bash
# ❌ WRONG - PHP doesn't run as www-data in Nixpacks
chown -R www-data:www-data data uploads logs
```

**Why it fails:**
- Nixpacks uses `nobody`, not `www-data`
- Different from Docker/Apache setups
- Must check actual PHP user first

### ❌ DON'T: Deploy Without Testing Permissions

**Always check:**
1. What user does PHP run as?
2. Who owns the files?
3. Can PHP write to directories?

---

## ✅ WHAT TO DO (Best Practices)

### 1. Use the Diagnostic Script

Before and after every deployment:

**Visit:** `https://your-domain.com/check-user.php`

**Check:**
- PHP user matches file owner
- All directories show "✅ Writable"
- Write test passes

### 2. Post-Deployment Checklist

After EVERY deployment:

- [ ] Run permission fix commands
- [ ] Check diagnostic page
- [ ] Test delete operation
- [ ] Test add operation
- [ ] Test RSS import
- [ ] Check for permission errors in logs

### 3. Document the PHP User

**For this project:**
- **PHP User:** `nobody` (UID: 65534, GID: 65534)
- **Platform:** Coolify with Nixpacks
- **Required Ownership:** `65534:65534` (nobody:nogroup)
- **Required Permissions:** `755` for directories, `644` for files

---

## 🔧 Automation Options

### Option 1: Post-Deploy Hook in Coolify

If Coolify supports post-deploy scripts, add:

```bash
#!/bin/bash
cd /app
chown -R 65534:65534 data uploads logs
chmod -R 755 data uploads logs
echo "Permissions fixed for nobody user"
```

### Option 2: Startup Script

Create `startup.sh`:

```bash
#!/bin/bash
# Fix permissions on startup
chown -R 65534:65534 /app/data /app/uploads /app/logs
chmod -R 755 /app/data /app/uploads /app/logs

# Start the application
exec "$@"
```

### Option 3: Keep Manual (Current Approach)

- Simple and reliable
- Takes 30 seconds after each deploy
- No risk of automation breaking
- **RECOMMENDED for now**

---

## 📋 Quick Reference Card

### When Production Breaks

1. **Check PHP user:**
   ```bash
   php -r "echo exec('whoami');"
   ```

2. **Check file ownership:**
   ```bash
   ls -la data uploads logs
   ```

3. **Fix permissions:**
   ```bash
   cd /app
   chown -R 65534:65534 data uploads logs
   chmod -R 755 data uploads logs
   ```

4. **Verify:**
   - Visit `/check-user.php`
   - Try delete operation
   - Check logs for errors

---

## 🎓 Why This Keeps Happening

### The Cycle We Keep Falling Into:

1. ✅ Production works
2. 🔨 Make changes locally (works fine)
3. 🚀 Deploy to production
4. ❌ Files reset to `root:root` ownership
5. 💥 Everything breaks (permission denied)
6. 😰 Spend hours debugging
7. 🔧 Fix permissions manually
8. ✅ Production works again
9. 🔄 **Repeat next deployment**

### Breaking the Cycle:

**The ONLY way to break this cycle:**

1. **Accept the reality:** Files will always reset to `root:root` on deploy
2. **Make it routine:** Run permission fix after EVERY deploy
3. **Check first:** Always visit `/check-user.php` after deploy
4. **Test immediately:** Try delete/add before considering deploy done

---

## 📝 Deployment Workflow (MANDATORY)

### Before Pushing Code:

1. ✅ Test locally (http://localhost:8000)
2. ✅ Test all CRUD operations
3. ✅ Commit and push to GitHub

### After Coolify Deploys:

1. ⏱️ Wait for deployment to complete
2. 🔧 **RUN PERMISSION FIX COMMANDS** (in Coolify terminal)
3. 🔍 Visit `/check-user.php` to verify
4. ✅ Test delete operation
5. ✅ Test add operation
6. ✅ Test RSS import
7. 📊 Check logs for errors

**If you skip step 2, production WILL break!**

---

## 🚨 Emergency Recovery

If production is broken:

```bash
# 1. Connect to Coolify terminal
# 2. Run these commands:
cd /app
chown -R 65534:65534 data uploads logs
chmod -R 755 data uploads logs

# 3. Verify
ls -la data uploads logs

# 4. Test
# Try deleting a podcast in the UI
```

**Time to fix:** 2 minutes  
**Success rate:** 100%

---

## 📞 Final Notes

### What We Learned Today (Again):

1. **Local ≠ Production** - Different users, different permissions
2. **chmod() in PHP doesn't work** - Can't change permissions you don't own
3. **Nixpacks uses `nobody`** - Not `www-data`, not `nginx`, not `apache`
4. **Must fix after EVERY deploy** - Files reset to `root:root`
5. **Use numeric UID/GID** - `65534:65534` works when names don't

### Why We Keep Forgetting:

- ✅ Local works perfectly (same user)
- ✅ Code looks correct
- ✅ No errors locally
- ❌ Deploy and forget about permissions
- ❌ Production breaks
- ❌ Waste hours debugging

### The Solution:

**MAKE IT A HABIT:**

Every deployment = Run permission fix commands

**NO EXCEPTIONS**

---

## ✅ Success Criteria

Production is working when:

1. ✅ `/check-user.php` shows all directories writable
2. ✅ Can delete podcasts (no permission errors)
3. ✅ Can add podcasts
4. ✅ Can upload images
5. ✅ Can import from RSS
6. ✅ Logs are being written
7. ✅ Backups are being created
8. ✅ No permission errors in error.log

---

**Last Updated:** 2025-10-11  
**Status:** ✅ SOLVED  
**Time Wasted Today:** 2+ hours  
**Time to Fix:** 2 minutes (if we remember)  
**Lesson:** Always run permission fix after deploy

---

## 🎯 Action Items

- [x] Document the PHP user (`nobody`)
- [x] Document the fix commands
- [x] Create diagnostic script
- [x] Test and verify solution
- [ ] Add to deployment checklist
- [ ] Set calendar reminder: "Fix permissions after deploy"
- [ ] Consider automation (future)

**NEVER FORGET:** After deploy → Fix permissions → Test → Done
