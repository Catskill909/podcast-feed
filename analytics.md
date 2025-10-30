# Podcast Analytics Implementation Plan

## 1. Objectives & Scope
- **Goal:** Introduce first-party analytics focused on *audio engagement* (plays + downloads) originating **only from the public `index.php` modal player**.
- **Accuracy:** Only count one play/download per episode per visitor session, even if they repeatedly pause/resume.
- **Coverage:** Include actions triggered by the episode list buttons (play/download) and player transport controls (next/previous) that start a different episode.
- **Visibility:** Surface analytics inside the existing **Stats modal in `admin.php`**, extending it with interactive radio-station style dashboards without disrupting current statistics.

## 2. Environment Considerations
- The app auto-detects development vs production (localhost ⇒ dev; otherwise ⇒ prod). No manual `.env` file.
- All endpoints must remain relative (no hardcoded domains) so they operate identically in both environments.
- Respect current caching/version busting approach (query-string `?v=time()` on JS/CSS) when shipping new assets.

## 3. Analytics Data Model
### 3.1 Storage Medium
- Create `data/analytics.xml` (mirrors existing XML persistence strategy) with root `<analytics>`.
- Structure by ISO date for efficient aggregation:
  ```xml
  <analytics>
    <day date="2025-10-30">
      <metric type="play" podcast_id="pod123" episode_id="ep_abc" count="17" unique_visitors="15" />
      <metric type="download" podcast_id="pod123" episode_id="ep_xyz" count="4" unique_visitors="4" />
    </day>
  </analytics>
  ```
- Persist **raw event log** for troubleshooting? Decision: maintain aggregated counts only (safer for storage). Enrich with optional `<last_updated>` attribute for housekeeping.

### 3.2 Identifiers
- `podcast_id`: existing ID from `podcasts.xml` (string).
- `episode_id`: hashed identifier generated in `player-modal.js` (`ep_<hash>`). Need deterministic mapping -> ensure backend receives both the hash and the source audio URL + title for correlation.
- `type`: `play` or `download`.

## 4. Event Logging Workflow
### 4.1 Frontend Event Sources
1. **Episode Play button** (`player-modal.js` → `togglePlayback`): trigger log when a new episode loads (before `audioPlayer.play()` completes) and hasn't been logged this session.
2. **Next/Previous controls** (`audio-player.js`): hook after the player switches to a different episode (inside `loadEpisode` / `nextEpisode` / `previousEpisode`). Ensure logging shares same dedup path.
3. **Download button** (`player-modal.js` → `downloadEpisode`): fire log immediately after initiating download.

### 4.2 Deduplication Strategy
- Use `sessionStorage` (scoped per browser tab) to keep a Set for `analytics_played_<episode_id>` and `analytics_downloaded_<episode_id>`.
- On log attempt: if key present, skip API call.
- Backend ALSO enforces idempotency by storing a short-lived hash in `logs/analytics-dedup/DATE.json` (or PHP APCu fallback) to defend against malicious/repeat requests. Implementation detail: store `session_token` (see below) + episode ID for 6 hours.

### 4.3 Session Token
- Generate client-side UUID (persisted in `localStorage` as `pf_analytics_session`). Sent with each event so backend can compute unique visitors per day.
- Consider expiry (e.g., rotate every 24h) to approximate daily unique counts.

### 4.4 API Endpoints
- `api/log-analytics-event.php`
  - Method: POST JSON `{ type: 'play'|'download', podcastId, episodeId, episodeTitle, audioUrl, sessionId, timestamp }`
  - Validations: ensure type whitelist, sanitize IDs, enforce origin (CSRF token optional due to same-origin fetch + POST + session token).
  - Responsibilities:
    1. Load `analytics.xml` (create if missing).
    2. Locate `<day date="YYYY-MM-DD">`; create if absent.
    3. Update or append `<metric>` node (increment `count`, track `unique_visitors` via internal set keyed by sessionId).
    4. Append lightweight entry to `logs/operations.log` for auditing (reuse existing logger pattern).
  - Response: `{ success: true }` or `{ success: false, error: '...' }`.

## 5. PHP Support Layer
- Add `includes/AnalyticsManager.php` modeled after `AdsManager.php` structure.
  - `logEvent(type, podcastId, episodeId, sessionId, metadata = [])`
  - `getDashboardStats(range = '7d')` returning aggregated datasets for charts.
  - `getTopEpisodes(limit = 10)` sorted by plays/downloads.
  - Utility methods for XML read/write with locking (reuse `XMLHandler` approach).

## 6. Stats Modal Enhancements (`admin.php`)
### 6.1 Layout
- Increase modal size (`modal-xl` variant) and add tabs/sections below existing Overview cards:
  1. **Engagement Overview** (cards for total plays, unique listeners, downloads, conversion rate, top format).
  2. **Trend Charts** (7-day play/download line chart, stacked by metric).
  3. **Top Content Table** (top podcasts & episodes with play/download counts).
  4. **Real-time Snapshot** (today vs yesterday metrics with sparkline).
- Keep current “Directory Overview” section at top; analytics content follows.

### 6.2 Frontend Rendering
- Extend `assets/js/app.js` stats modal logic to fetch analytics payload via new endpoint `api/get-analytics-stats.php` when modal opens.
- Use **Chart.js** via CDN (lightweight, no bundler). Load once when Stats modal opens to preserve performance.
- Build modular components (cards, tables) using existing design tokens (`components.css`). Add dedicated `assets/css/analytics.css` if needed.

## 7. Data API for Admin
- `api/get-analytics-stats.php` (GET): returns aggregated datasets for charts and tables:
  ```json
  {
    "success": true,
    "overview": {
      "totalPlays": 1234,
      "uniqueListeners": 876,
      "downloads": 342,
      "playToDownloadRate": 0.28
    },
    "dailySeries": [ { "date": "2025-10-24", "plays": 120, "downloads": 45 }, ... ],
    "topEpisodes": [ { "episodeId": "ep_123", "title": "...", "podcastTitle": "...", "plays": 45, "downloads": 10 } ],
    "topPodcasts": [...],
    "updatedAt": "2025-10-30T14:05:00Z"
  }
  ```
- Calculations performed in `AnalyticsManager` to keep PHP responsible for heavy lifting (JS stays presentation-only).

## 8. Frontend Integration (index.php)
- Inject lightweight analytics helper script (`assets/js/analytics-tracker.js`) loaded after `player-modal.js` / `audio-player.js` to access their globals.
- Responsibilities:
  - Provide `AnalyticsTracker.logPlay(episode, podcast)` and `AnalyticsTracker.logDownload(episode, podcast)`.
  - Manage session ID, dedup caches, API requests with retry/backoff.
  - Listen to custom events fired from `audio-player.js` (emit `window.dispatchEvent(new CustomEvent('audio:episodeStarted', { detail: ... }))`).

## 9. Modifications to Existing JS
- **`player-modal.js`**
  - After `togglePlayback()` loads a new episode, dispatch `audio:episodeSelected` with metadata.
  - Wrap download button handler to call `AnalyticsTracker.logDownload(...)` before starting download.
- **`audio-player.js`**
  - Inside `loadEpisode()`, emit `audio:episodeStarted` once metadata loads.
  - Ensure `nextEpisode()` / `previousEpisode()` call `loadEpisode()` (already true) so analytics hook fires automatically.
  - Provide `getCurrentPodcast()` accessor or reference via `window.playerModal.currentPodcast` for additional metadata.

## 10. UI/UX Guidelines for Analytics Section
- Maintain dark theme using existing CSS variables (`--bg-secondary`, `--text-muted`, etc.).
- KPI cards mimic `stats-modal-card` styling with new accent colors (e.g., plays = neon green, downloads = electric blue).
- Charts: gradient backgrounds with subtle grid. Provide empty-state messaging when no analytics yet.
- Add “Export CSV” button (phase 2) placeholder; disabled until backend ready.

## 11. Security & Privacy
- No PII captured. Session IDs random UUID (no IP).
- Rate-limit API (simple throttle per session: ignore >50 events/min).
- Sanitize inputs and enforce same-origin fetch (respond 400 if `$_SERVER['HTTP_ORIGIN']` mis-matched when available).
- Ensure analytics files and directories remain writable per README guidance.

## 12. Testing Strategy
1. **Unit-Level:** PHP unit tests not available; rely on manual tests verifying XML writes and aggregated outputs (add CLI script? optional).
2. **Functional:**
   - Open modal, play multiple episodes, verify counts increment once per episode.
   - Use next/previous buttons; confirm new plays logged.
   - Attempt repeated play of same episode (same session) → count stays constant.
   - Reload page (new session) → counts increment (ensures session ID persistence).
   - Trigger downloads; ensure counts update and dedup works.
3. **Admin UI:**
   - Stats modal loads analytics data without blocking existing overview.
   - Charts render with sample data; empty-state shows helpful message when dataset empty.

## 13. Deployment & Migration
- Ship with empty `analytics.xml` seeded in repo (or auto-create on first write).
- On deployment, ensure `data/` remains writable (already true per README).
- Version bump JS assets (`?v=` already handled).
- Document new feature in `FUTURE-DEV.md` / changelog post-implementation.

## 14. Implementation Phases
1. **Infrastructure (Backend):** AnalyticsManager class, XML schema, logging endpoint.
2. **Frontend Tracking:** analytics-tracker helper, integrate with modal/player.
3. **Admin Dashboard:** API for aggregated stats, UI extension, Chart.js integration.
4. **Polish & QA:** Empty states, performance tuning, documentation updates.

## 15. Open Questions / Follow-ups
- Do we need per-podcast filters or time range selector in Stats modal? (Assume yes in future; design modular data API.)
- Should downloads triggered by right-click/save-as be counted? (Current download button only; possible enhancement with server-side streaming logs.)
- Retention policy for analytics (e.g., auto-trim older than 1 year) to avoid XML bloat.
