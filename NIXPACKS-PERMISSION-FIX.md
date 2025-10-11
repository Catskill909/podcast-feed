# Nixpacks Permission Fix for Coolify

## 🎯 The Real Problem

**You're using NIXPACKS, not Docker!** That's why the Dockerfile isn't being used and permissions keep breaking.

## ✅ IMMEDIATE FIX (Right Now - 2 minutes)

### In Coolify Terminal:

```bash
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs
```

**This will fix it immediately** but will break again on next deploy.

## 🔧 PERMANENT FIX for Nixpacks

### Option 1: Use Startup Script (RECOMMENDED)

I've created `nixpacks-start.sh` that runs before the app starts.

**In Coolify Dashboard:**

1. Go to your app settings
2. Find "Start Command" or "Custom Start Command"
3. Set it to: `bash nixpacks-start.sh`
4. Save and redeploy

### Option 2: Switch to Dockerfile

If you want to use the Dockerfile instead:

1. Go to Coolify Dashboard
2. Go to your app → Settings
3. Find "Build Pack" or "Build Method"
4. Change from "Nixpacks" to "Dockerfile"
5. Save and redeploy

### Option 3: Post-Deploy Script

In Coolify, you can set a post-deploy script:

```bash
chown -R www-data:www-data /app/data /app/uploads /app/logs
chmod -R 755 /app/data /app/uploads /app/logs
```

## 📋 What to Do RIGHT NOW

### Step 1: Fix Permissions Immediately
```bash
# In Coolify terminal
cd /app
chown -R www-data:www-data data uploads logs
chmod -R 755 data uploads logs
```

### Step 2: Test
- Try deleting a podcast
- Should work now

### Step 3: Commit the Startup Script
```bash
git add nixpacks-start.sh
git commit -m "Add Nixpacks startup script for permissions"
git push origin main
```

### Step 4: Configure Coolify
- Set start command to: `bash nixpacks-start.sh`
- OR switch to Dockerfile build method
- Redeploy

## 🎓 Why This Keeps Happening

### With Nixpacks:
1. Nixpacks auto-detects PHP
2. Creates container automatically
3. **Doesn't know about our permission needs**
4. Files owned by root
5. PHP (www-data) can't write
6. Everything breaks

### The Solution:
- Tell Nixpacks to run our startup script
- Script fixes permissions before app starts
- Permissions correct on every deploy

## 🚀 Quick Reference

### Check if using Nixpacks:
- In Coolify: Look for "Build Pack: Nixpacks"
- OR check deployment logs for "nixpacks"

### Fix permissions manually:
```bash
cd /app && chown -R www-data:www-data data uploads logs && chmod -R 755 data uploads logs
```

### Make startup script executable:
```bash
chmod +x nixpacks-start.sh
```

## ⚡ The Files

- `nixpacks-start.sh` - Startup script for Nixpacks ✅ CREATED
- `Dockerfile` - For Docker builds (not used with Nixpacks)
- `docker-entrypoint.sh` - Old script (can delete)

## 📞 Status

- **Build System:** Nixpacks (not Docker!)
- **Issue:** Permissions reset on every deploy
- **Immediate Fix:** Run chown/chmod in terminal (2 min)
- **Permanent Fix:** Configure startup script in Coolify (5 min)

---

**Next Actions:**
1. ✅ Run permission fix in Coolify terminal NOW
2. ✅ Commit nixpacks-start.sh to Git
3. ⏳ Configure Coolify to use the startup script
4. ⏳ Redeploy and test
