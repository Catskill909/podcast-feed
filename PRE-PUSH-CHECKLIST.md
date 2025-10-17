# Pre-Push to GitHub Checklist

## Quick Security Verification

Run these commands before pushing to public GitHub:

### 1. Verify .gitignore is Working

```bash
cd /Users/paulhenshaw/Desktop/podcast-feed

# These should all say "ignored"
git check-ignore data/podcasts.xml
git check-ignore uploads/covers/test.jpg
git check-ignore logs/error.log
git check-ignore .env
```

**Expected output:** Each file should show as "ignored"

---

### 2. Check What Will Be Committed

```bash
git status
```

**Should NOT see:**
- `data/podcasts.xml`
- Files in `uploads/covers/` (except `.gitkeep`)
- Files in `logs/` (except `.gitkeep`)
- `.env` files
- `.htpasswd` files

**Should see:**
- `.gitignore`
- `.env.example`
- `.htaccess.example`
- All `.php`, `.js`, `.css` files
- All `.md` documentation files

---

### 3. Review Sensitive Files

```bash
# Check if any sensitive files accidentally added
git ls-files | grep -E "(\.env$|\.htpasswd|podcasts\.xml|\.log$)"
```

**Expected output:** Should be empty (no matches)

---

### 4. Final Safety Check

```bash
# See what would be pushed
git diff --stat origin/main

# Or if first push
git ls-files
```

Review the list - make sure no sensitive data!

---

## ✅ Ready to Push

If all checks pass:

```bash
# Add all files
git add .

# Commit
git commit -m "Initial public release - v2.5.0

- Complete podcast directory management system
- RSS feed auto-import with validation
- In-browser podcast player
- Health monitoring
- Automated episode tracking
- Live feed data system
- Comprehensive documentation"

# Push to GitHub
git push origin main
```

---

## 🔍 Post-Push Verification

After pushing, visit your GitHub repo and verify:

### Should Be Visible:
- ✅ README.md
- ✅ All source code (.php, .js, .css)
- ✅ Documentation (.md files)
- ✅ .gitignore
- ✅ .env.example
- ✅ .htaccess.example

### Should NOT Be Visible:
- ❌ data/podcasts.xml
- ❌ uploads/covers/* (except .gitkeep)
- ❌ logs/*.log (except .gitkeep)
- ❌ .env (if you created one)
- ❌ .htpasswd (if you created one)

---

## 🎯 Quick Commands

```bash
# Clone test (verify .gitignore works)
cd /tmp
git clone YOUR_REPO_URL test-clone
cd test-clone
ls -la data/          # Should only see .gitkeep, not podcasts.xml
ls -la uploads/covers/  # Should only see .gitkeep
ls -la logs/          # Should only see .gitkeep

# Clean up
cd ..
rm -rf test-clone
```

---

## 📋 Checklist Summary

- [ ] Verified .gitignore is working
- [ ] Checked `git status` - no sensitive files
- [ ] Reviewed `git ls-files` - looks good
- [ ] Committed changes
- [ ] Pushed to GitHub
- [ ] Verified on GitHub - no sensitive data visible
- [ ] Clone tested - .gitignore working correctly

---

**Status:** Ready for public GitHub! 🚀

See [GITHUB-SECURITY-AUDIT.md](GITHUB-SECURITY-AUDIT.md) for complete security analysis.
