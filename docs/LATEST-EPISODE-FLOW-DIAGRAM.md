# Latest Episode Date - Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         SINGLE SOURCE OF TRUTH                       │
│                                                                      │
│                    📄 data/podcasts.xml                              │
│                                                                      │
│              <latest_episode_date>2025-10-16 14:00:00</...>         │
│                                                                      │
│                    Format: YYYY-MM-DD HH:MM:SS                       │
└─────────────────────────────────────────────────────────────────────┘
                                    ▲
                                    │
                    ┌───────────────┼───────────────┐
                    │               │               │
                    │               │               │
        ┌───────────▼─────┐  ┌──────▼──────┐  ┌────▼─────────┐
        │  CRON JOB       │  │   MANUAL    │  │   IMPORT     │
        │  (Hourly)       │  │   REFRESH   │  │   PROCESS    │
        │                 │  │   BUTTON    │  │              │
        │  auto-scan-     │  │  refresh-   │  │  index.php   │
        │  feeds.php      │  │  feed-      │  │  (lines      │
        │                 │  │  metadata   │  │   36-49)     │
        │  Lines 78-93    │  │  .php       │  │              │
        │                 │  │             │  │              │
        │  Runs every     │  │  User       │  │  On new      │
        │  hour           │  │  clicks     │  │  podcast     │
        └─────────────────┘  └─────────────┘  └──────────────┘
                    │               │               │
                    └───────────────┼───────────────┘
                                    │
                                    │ All update via:
                                    │ PodcastManager::
                                    │ updatePodcastMetadata()
                                    │
                    ┌───────────────▼───────────────┐
                    │                               │
                    │   📡 RssFeedParser            │
                    │   fetchFeedMetadata()         │
                    │                               │
                    │   Fetches from live RSS feed  │
                    │   Returns: YYYY-MM-DD HH:MM:SS│
                    │                               │
                    └───────────────────────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                         DATA DISTRIBUTION                            │
└─────────────────────────────────────────────────────────────────────┘

                    📄 data/podcasts.xml
                            │
                            │ Read by PodcastManager
                            │
            ┌───────────────┼───────────────┐
            │               │               │
            │               │               │
    ┌───────▼──────┐  ┌─────▼──────┐  ┌────▼─────────┐
    │  index.php   │  │  API       │  │  Modals      │
    │  (Server)    │  │  Endpoints │  │  (Client)    │
    │              │  │            │  │              │
    │  Renders:    │  │  Returns:  │  │  Read from:  │
    │  <tr data-   │  │  JSON with │  │  HTML data   │
    │  latest-     │  │  latest_   │  │  attributes  │
    │  episode=    │  │  episode_  │  │              │
    │  "...">      │  │  date      │  │              │
    └──────┬───────┘  └─────┬──────┘  └──────┬───────┘
           │                │                │
           │                │                │
           └────────────────┼────────────────┘
                            │
                            │ All use same format:
                            │ YYYY-MM-DD HH:MM:SS
                            │
            ┌───────────────▼───────────────┐
            │                               │
            │   🎨 DISPLAY LAYER            │
            │   (All use same calculation)  │
            │                               │
            └───────────────┬───────────────┘
                            │
            ┌───────────────┼───────────────┐
            │               │               │
            │               │               │
    ┌───────▼──────┐  ┌─────▼──────┐  ┌────▼─────────┐
    │  Main Page   │  │  Player    │  │  Podcast     │
    │  Table       │  │  Modal     │  │  Info Modal  │
    │              │  │            │  │              │
    │  app.js      │  │  player-   │  │  app.js      │
    │  formatLatest│  │  modal.js  │  │  (preview)   │
    │  EpisodeDate │  │  formatDate│  │              │
    │  ()          │  │  ()        │  │              │
    │              │  │            │  │              │
    │  Lines       │  │  Lines     │  │  Lines       │
    │  1663-1705   │  │  616-644   │  │  1517-1546   │
    │              │  │            │  │              │
    │  Shows:      │  │  Shows:    │  │  Shows:      │
    │  "Yesterday" │  │  "Yesterday│  │  "Yesterday" │
    └──────────────┘  └────────────┘  └──────────────┘


┌─────────────────────────────────────────────────────────────────────┐
│                    CALCULATION LOGIC (SHARED)                        │
└─────────────────────────────────────────────────────────────────────┘

    Input: "2025-10-16 14:00:00"
        │
        ▼
    new Date(dateString)
        │
        ▼
    Reset to midnight
    (strip time component)
        │
        ▼
    Calculate days difference
    from today at midnight
        │
        ▼
    ┌─────────────────────────────────┐
    │  diffDays === 0  →  "Today"     │
    │  diffDays === 1  →  "Yesterday" │
    │  diffDays < 7    →  "X days ago"│
    │  diffDays >= 7   →  "Oct 16"    │
    └─────────────────────────────────┘
        │
        ▼
    Display to user


┌─────────────────────────────────────────────────────────────────────┐
│                         UPDATE FLOW                                  │
└─────────────────────────────────────────────────────────────────────┘

    User clicks refresh button
            │
            ▼
    api/refresh-feed-metadata.php
            │
            ├─→ Fetch from RSS feed
            │   (RssFeedParser)
            │
            ├─→ Update XML database
            │   (PodcastManager)
            │
            └─→ Return JSON response
                    │
                    ▼
            JavaScript receives response
                    │
                    ├─→ Update row.dataset.latestEpisode
                    │   (Line 1342 in app.js)
                    │
                    └─→ Show success message
                            │
                            ▼
                    All three display locations
                    now show updated date
                    (no page reload needed!)


┌─────────────────────────────────────────────────────────────────────┐
│                    CACHE BUSTING FLOW                                │
└─────────────────────────────────────────────────────────────────────┘

    Developer updates JavaScript
            │
            ▼
    Update ASSETS_VERSION in config.php
    (e.g., '20251017_1001' → '20251017_1430')
            │
            ▼
    Deploy to production
            │
            ▼
    User loads page
            │
            ▼
    Browser requests:
    app.js?v=20251017_1430
            │
            ├─→ New version! Fetch from server
            │   (not from cache)
            │
            └─→ Execute new JavaScript
                    │
                    ▼
            Latest functions available
            All features work correctly


┌─────────────────────────────────────────────────────────────────────┐
│                         KEY PRINCIPLES                               │
└─────────────────────────────────────────────────────────────────────┘

    1. ONE SOURCE OF TRUTH
       └─→ data/podcasts.xml

    2. ONE UPDATE METHOD
       └─→ PodcastManager::updatePodcastMetadata()

    3. ONE DATA FORMAT
       └─→ YYYY-MM-DD HH:MM:SS

    4. ONE DATA ATTRIBUTE
       └─→ data-latest-episode

    5. ONE CALCULATION LOGIC
       └─→ Midnight normalization + day difference

    6. ONE CACHE STRATEGY
       └─→ ASSETS_VERSION parameter


┌─────────────────────────────────────────────────────────────────────┐
│                    CONSISTENCY GUARANTEES                            │
└─────────────────────────────────────────────────────────────────────┘

    ✅ All dates come from XML database
    ✅ All updates go through PodcastManager
    ✅ All displays read from data-latest-episode
    ✅ All calculations use same JavaScript logic
    ✅ All users get latest code via cache busting

    = CONSISTENT DATES EVERYWHERE! 🎉
```
