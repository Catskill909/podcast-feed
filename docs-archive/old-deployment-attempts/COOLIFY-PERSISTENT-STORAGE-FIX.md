# THE REAL FIX: Coolify Persistent Storage

**Date:** 2025-10-11  
**Status:** PERMANENT SOLUTION - No more manual commands!

---

## 🎯 THE PROBLEM WE'VE BEEN HAVING

Every deployment resets file ownership to `root:root` because:
- The container rebuilds from scratch
- Files in the container are owned by root
- PHP runs as `nobody`
- `nobody` can't write to `root`-owned files

**We've been manually fixing this after every deploy - THIS IS WRONG!**

---

## ✅ THE REAL SOLUTION: Persistent Volumes

Coolify has a feature called **Persistent Storage** that:
1. Stores data OUTSIDE the container
2. Mounts it into the container at runtime
3. **Preserves ownership and permissions across deployments**
4. This is how it's SUPPOSED to work!

---

## 🔧 How to Set Up Persistent Storage in Coolify

### Step 1: Go to Your App in Coolify Dashboard

1. Open Coolify
2. Click on your podcast-feed application
3. Go to **"Storage"** or **"Persistent Storage"** tab

### Step 2: Add Persistent Volumes

Add these three volumes:

#### Volume 1: Data Directory
- **Name:** `data`
- **Source Path:** (leave empty for Docker volume, or specify host path)
- **Destination Path:** `/app/data`
- **Click "Add"**

#### Volume 2: Uploads Directory
- **Name:** `uploads`
- **Source Path:** (leave empty)
- **Destination Path:** `/app/uploads`
- **Click "Add"**

#### Volume 3: Logs Directory
- **Name:** `logs`
- **Source Path:** (leave empty)
- **Destination Path:** `/app/logs`
- **Click "Add"**

### Step 3: Set Permissions ONE TIME

After adding the volumes, connect to Coolify terminal and run:

```bash
cd /app
chown -R 65534:65534 data uploads logs
chmod -R 755 data uploads logs
```

**This is the LAST TIME you'll need to run these commands!**

### Step 4: Redeploy

Click "Redeploy" in Coolify.

The volumes will now persist across deployments with the correct ownership!

---

## 🎉 What This Fixes

### Before (Without Persistent Volumes):
1. Deploy app
2. Files created as `root:root`
3. PHP can't write
4. Run manual commands
5. Works until next deploy
6. **Repeat forever** 😫

### After (With Persistent Volumes):
1. Deploy app
2. Volumes mounted with correct ownership
3. PHP can write immediately
4. Deploy again
5. **Still works!** 🎉
6. Never run commands again!

---

## 📋 Why This Works

### Docker Volumes Persist:
- Data stored outside container
- Survives container rebuilds
- Ownership preserved
- Permissions preserved

### Container Rebuilds Don't Affect Volumes:
- New container created
- Volumes mounted from external storage
- Files already have correct ownership
- No permission reset!

---

## 🔍 Verify It's Working

### After Setting Up Volumes:

1. **Deploy the app**
2. **Visit** `/check-user.php`
3. **Should show:** All directories writable ✅
4. **Try deleting** a podcast
5. **Should work** without running any commands!

### Test Persistence:

1. **Make a change** to code
2. **Push to GitHub**
3. **Coolify redeploys**
4. **Visit** `/check-user.php` again
5. **Should STILL show:** All directories writable ✅
6. **Delete should STILL work!**

---

## 🚨 Important Notes

### One-Time Setup:
- You only need to set up volumes ONCE
- Permissions set ONCE after creating volumes
- Never need to run commands again

### Existing Data:
- Your current data will be preserved
- Coolify will migrate it to the volumes
- No data loss

### Backups:
- Volumes can be backed up separately
- More reliable than container storage
- Can be restored independently

---

## 📚 Documentation References

- [Coolify Persistent Storage Docs](https://coolify.io/docs/knowledge-base/persistent-storage)
- Volumes are the standard Docker way to persist data
- This is how production apps SHOULD be deployed

---

## 🎯 Action Plan

### Immediate Steps:

1. ✅ Go to Coolify Dashboard
2. ✅ Open your podcast-feed app
3. ✅ Go to Storage/Persistent Storage tab
4. ✅ Add three volumes (data, uploads, logs)
5. ✅ Run permission commands ONE TIME
6. ✅ Redeploy
7. ✅ Test delete operation
8. ✅ Make a code change and deploy again
9. ✅ Verify delete still works (without running commands)

### Expected Result:

**You'll NEVER need to run those terminal commands again!**

---

## 💡 Why We Didn't Do This Before

We didn't know about Coolify's persistent storage feature!

We were treating the container like a server, but:
- Containers are ephemeral (temporary)
- Data should be in volumes (permanent)
- This is Docker best practice
- Coolify makes it easy with the UI

---

## ✅ Success Criteria

You'll know it's working when:

1. ✅ Deploy without running commands
2. ✅ All file operations work immediately
3. ✅ Deploy again
4. ✅ Still works without commands
5. ✅ `/check-user.php` always shows writable
6. ✅ No more permission errors ever

---

**This is the proper, permanent solution!**

No more manual commands after every deploy.  
No more permission errors.  
Just push and it works! 🚀
