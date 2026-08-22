# 📚 PodFeed Builder - Documentation Index

**Last Updated:** 2026-08-22  
**Status:** ✅ All systems operational

---

## 🚀 Quick Start

**New to this project?** Start here:

1. **[README.md](README.md)** - Main documentation, features, and deployment setup
2. **[QUICK-START-DEPLOYMENT-FIX.md](QUICK-START-DEPLOYMENT-FIX.md)** - 10-minute deployment setup

---

## 📖 Core Documentation

### Deployment & Production

| Document | Purpose | When to Use |
|----------|---------|-------------|
| **[README.md](README.md)** | Main documentation | First read, overview |
| **[DEPLOYMENT-CHECKLIST.md](DEPLOYMENT-CHECKLIST.md)** | Complete deployment guide | Setting up production |
| **[QUICK-START-DEPLOYMENT-FIX.md](QUICK-START-DEPLOYMENT-FIX.md)** | Fast deployment setup | Quick reference |
| **[deployment-fix.md](deployment-fix.md)** | Comprehensive deployment analysis | Deep dive, troubleshooting |
| **[DEPLOYMENT-ANALYSIS-SUMMARY.md](DEPLOYMENT-ANALYSIS-SUMMARY.md)** | Technical deep dive | Understanding architecture |

### August 2026 Fixes

| Document | Purpose | When to Use |
|----------|---------|-------------|
| **[EMBED-PROXY-FIX-2026-08-20.md](EMBED-PROXY-FIX-2026-08-20.md)** | Embed preview failure, RSS proxy allowlist, SSRF fixes on `fetch-feed.php` / `download-proxy.php`, generator layout | Feed proxying, embed preview, or SSRF questions |
| **[ANALYTICS-EMBED-TRACKING-2026-08-20.md](ANALYTICS-EMBED-TRACKING-2026-08-20.md)** | Why stats showed "no data", embed player tracking, honest empty state | Analytics or stats dashboard questions |
| **[FEED-TEXT-ENTITIES-2026-08-21.md](FEED-TEXT-ENTITIES-2026-08-21.md)** | `&nbsp;` / `&mdash;` rendered literally; `feedText()` normalisation | Any feed text, title or description handling |

### Features & Implementation

| Document | Purpose | When to Use |
|----------|---------|-------------|
| **[RSS-IMPORT-IMPLEMENTATION.md](RSS-IMPORT-IMPLEMENTATION.md)** | RSS import feature docs | Understanding RSS import |
| **[FUTURE-DEV.md](FUTURE-DEV.md)** | Roadmap and planned features | Planning new features |

### Security & Setup

| Document | Purpose | When to Use |
|----------|---------|-------------|
| **[SECURITY-AUDIT.md](SECURITY-AUDIT.md)** | Security best practices | Security review |
| **[PASSWORD-SETUP.md](PASSWORD-SETUP.md)** | Password configuration | Setting up auth |
| **[SIMPLE-AUTH-SETUP.md](SIMPLE-AUTH-SETUP.md)** | Simple auth implementation | Quick auth setup |

---

## 🔧 Configuration Files

### Deployment Scripts

| File | Purpose | Status |
|------|---------|--------|
| `nixpacks-start.sh` | Nixpacks startup script | ✅ Configured |
| `coolify-post-deploy.sh` | Post-deploy automation | ✅ Available |
| `.coolify-volumes.example` | Volume configuration template | ✅ Reference |

### Application Config

| File | Purpose | Location |
|------|---------|----------|
| `config/config.php` | Main app configuration | `/config/` |
| `config/auth_placeholder.php` | Auth structure | `/config/` |
| `.htaccess.example` | Apache configuration | Root |
| `.env.example` | Environment variables template | Root |

---

## 📁 Project Structure

```
podcast-feed/
├── README.md                          # Main documentation
├── DOCUMENTATION-INDEX.md             # This file
├── index.php                          # Main application
├── feed.php                           # RSS feed endpoint
├── check-user.php                     # Diagnostics tool
│
├── config/                            # Configuration
│   ├── config.php                     # Main config
│   └── auth_placeholder.php           # Auth structure
│
├── includes/                          # Core PHP classes
│   ├── PodcastManager.php             # CRUD operations
│   ├── XMLHandler.php                 # XML database
│   ├── ImageUploader.php              # Image handling
│   └── RssFeedParser.php              # RSS import
│
├── api/                               # API endpoints
│   └── import-rss.php                 # RSS import API
│
├── assets/                            # Frontend assets
│   ├── css/                           # Stylesheets
│   └── js/                            # JavaScript
│
├── data/                              # XML database (in volume)
│   ├── podcasts.xml                   # Main database
│   └── backup/                        # Auto backups
│
├── uploads/                           # User uploads (in volume)
│   └── covers/                        # Podcast images
│
├── logs/                              # Application logs (in volume)
│   ├── error.log                      # Error logging
│   └── operations.log                 # Operation tracking
│
└── docs-archive/                      # Historical docs
    ├── old-deployment-attempts/       # Deployment history
    ├── bug-fixes/                     # Resolved bugs
    └── development-notes/             # Dev planning
```

---

## 🎯 Common Tasks

### Deploying Changes

```bash
# 1. Make changes locally
git add .
git commit -m "Your changes"
git push origin main

# 2. Coolify auto-deploys
# 3. That's it! No manual commands needed
```

### Checking Production Status

1. Visit: `https://podcast.supersoul.top/check-user.php`
2. Verify all directories show "✅ Writable"
3. Test CRUD operations

### Adding a New Feature

1. Review **[FUTURE-DEV.md](FUTURE-DEV.md)** for planned features
2. Implement locally and test
3. Update relevant documentation
4. Deploy via git push

### Troubleshooting

1. Check `/check-user.php` for permission issues
2. Review `logs/error.log` for errors
3. Consult **[deployment-fix.md](deployment-fix.md)** for deployment issues
4. Check **[SECURITY-AUDIT.md](SECURITY-AUDIT.md)** for security concerns

---

## 🔍 Key Concepts

### File-Based Storage

This app uses **XML files as the database**, not MySQL/PostgreSQL:
- Database: `data/podcasts.xml`
- Backups: `data/backup/*.xml`
- Persistent volumes ensure data survives deployments

### Persistent Volumes

**Critical for production:**
- `podcast-data` → `/app/data` (database)
- `podcast-uploads` → `/app/uploads` (images)
- `podcast-logs` → `/app/logs` (logs)

**Why:** Data persists across container rebuilds

### Auto-Detection

The app automatically detects environment:
- **Local:** Development mode (localhost)
- **Production:** Production mode (any other domain)

No manual configuration needed!

---

## 📊 Current Status

### ✅ Working Features

- Full CRUD operations (Create, Read, Update, Delete)
- RSS feed auto-import with image download
- Podcast health check validation
- Image upload and management
- XML-based storage with auto-backup
- RSS feed generation
- Dark theme UI
- Password protection
- Search and filter
- Mobile responsive

### ✅ Deployment

- Persistent volumes configured
- Auto-deploy from GitHub
- No manual commands needed
- Data persists across deployments

### 🎯 Planned Features

See **[FUTURE-DEV.md](FUTURE-DEV.md)** for roadmap

---

## 🆘 Getting Help

### Quick Diagnostics

1. **Check permissions:** Visit `/check-user.php`
2. **Check logs:** Review `logs/error.log`
3. **Check volumes:** Verify in Coolify dashboard

### Documentation

- **Deployment issues:** [deployment-fix.md](deployment-fix.md)
- **Security questions:** [SECURITY-AUDIT.md](SECURITY-AUDIT.md)
- **Feature questions:** [README.md](README.md)

### Common Issues

| Issue | Solution | Document |
|-------|----------|----------|
| Permission denied | Check persistent volumes | [QUICK-START-DEPLOYMENT-FIX.md](QUICK-START-DEPLOYMENT-FIX.md) |
| RSS import fails | Check SSL/cURL | [RSS-IMPORT-IMPLEMENTATION.md](RSS-IMPORT-IMPLEMENTATION.md) |
| Images not uploading | Check uploads directory | [README.md](README.md) |
| Auth not working | Check password config | [PASSWORD-SETUP.md](PASSWORD-SETUP.md) |

---

## 📝 Contributing

### Making Changes

1. Work locally on `http://localhost:8000`
2. Test thoroughly
3. Update relevant documentation
4. Commit with clear messages
5. Push to GitHub
6. Coolify auto-deploys

### Documentation Updates

When making changes:
- Update README.md for user-facing changes
- Update technical docs for architecture changes
- Keep this index updated
- Archive old docs to `docs-archive/`

---

## 🎉 Success Metrics

Your setup is working correctly when:

- ✅ Can deploy without manual commands
- ✅ Data persists across deployments
- ✅ All CRUD operations work
- ✅ RSS import functions properly
- ✅ Images upload successfully
- ✅ `/check-user.php` shows all writable
- ✅ No permission errors in logs

---

**Version:** 2.0.0  
**Last Verified:** 2025-10-13  
**Deployment Status:** ✅ Fully Operational  
**Documentation Status:** ✅ Up to Date
