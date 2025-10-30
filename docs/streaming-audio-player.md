# Standalone Streaming Audio Player Plan

## 1. Objectives & Constraints
- Deliver a standalone web component that plays the WPFW Icecast stream (`https://streams.pacifica.org:9000/wpfw_128`) discovered from the provided M3U (`http://docs.pacifica.org/wpfw/wpfw.m3u`).
- Follow existing podcast app dark mode + material design language (font stack, spacing scale, badge styling, etc.).
- Large, highly visible play / pause control; separate volume control.
- Layout: artwork on left, live metadata on right, controls below metadata block.
- Avoid CORS issues by proxying stream discovery + metadata through PHP in our existing API layer.
- Keep implementation self-contained so it can be integrated later without refactoring core app.

## 2. Data Sources
| Purpose | Source | Notes |
|---------|--------|-------|
| Stream URL | `http://docs.pacifica.org/wpfw/wpfw.m3u` | M3U contains final Icecast mount; we will fetch via PHP to avoid client-side CORS.
| Live metadata (Now Playing) | Confessor JSON API (sample payload provided) | Includes `global`, `current`, and `next` blocks with station + show metadata. Will proxy via PHP and poll on the client.

## 3. Backend Endpoints (PHP)
1. **`api/streaming/fetch-stream-url.php`**
   - Fetches the M3U file server-side, extracts first valid stream URL, returns JSON `{ "success": true, "stream_url": "..." }`.
   - Adds cache headers (e.g. cache for 5 minutes) to reduce repeated upstream hits.
2. **`api/streaming/fetch-metadata.php`**
   - Calls the Confessor playlist API (e.g. `https://confessor.wpfwfm.org/playlist/api/?field=now`) server-side using station/mount identifiers.
   - Normalises response to `{ title, artist, show, start_time, dj, pledge_url, artwork, next_show }`.
   - Includes graceful fallback if metadata missing; returns last known values to keep UI populated.
   - Poll-safe (no caching) because metadata must update rapidly.
3. **Shared helpers** (optional): consider `includes/StreamingProxy.php` to encapsulate fetching + error handling, reusable later when integrating.
4. Ensure correct CORS handling: API should send `Access-Control-Allow-Origin: *` or match app domain so the standalone widget can live elsewhere.

### 3.1 Metadata Mapping Notes
- **Global block**: exposes station branding + stream info (`gl_station`, `gl_city`, `gl_desc`, `listenurl`). Use for static header text and fallback stream URL.
- **Current block**: contains live show metadata (`sh_name`, `sh_desc`, `sh_djname`, `cur_start`, `cur_end`, `pledge`, `sh_photo`). Map these to UI cards (title, host, pledge CTA, artwork).
- **Next block**: preview of upcoming show (`sh_name`, `nxt_start`, etc.); optional secondary display underneath metadata.
- Persist `ph_id` or `sh_altid` if we need to detect changes between polls (compare with previous response to avoid redundant UI updates).

## 4. Frontend Architecture
- **Entry HTML** (`streaming-audio-player.php` or `streaming-audio-player.html`) containing the player shell + references to CSS/JS bundles.
- **JS Module** (`assets/js/streaming-audio-player.js`):
  - State machine with states: `idle → loading → playing/paused → error`.
  - Responsibilities:
    - Fetch stream URL via `fetch-stream-url.php` once on load.
    - Initialise `<audio>` element with `preload="none"`, attach play/pause + volume handlers.
    - Poll metadata endpoint (interval 15s; adjustable) and update UI.
    - Handle network or playback errors with toast/banner.
    - Provide public `initPlayer(options)` to support later embedding.
- **CSS (`assets/css/streaming-audio-player.css`)** using existing design tokens (`var(--bg-primary)`, `--accent-primary`, etc.).

## 5. UI Layout & Styling
```
┌───────────────────────────────────────┐
│ ┌─────────────┐ ┌───────────────────┐ │
│ │  Artwork    │ │   Metadata block  │ │
│ │  (square)   │ │  • Station name   │ │
│ └─────────────┘ │  • Now playing    │ │
│                  │  • Show info      │ │
│ ───────────────────────────────────── │
│ Controls Row: [Play/Pause] [Volume] [Mute] [Live badge]
└───────────────────────────────────────┘
```
- Background: gradient similar to existing audio bar (`var(--bg-secondary)` to `var(--bg-tertiary)`).
- Typography: use existing font stack (`var(--font-primary)` / `var(--font-mono)` for metadata timestamps).
- Play button: 72px circular, accent color, drop shadow.
- Volume slider: horizontal range input styled per existing controls.
- Metadata area includes:
  - Station logo (provided asset or fallback icon).
  - Track title (marquee if overflow).
  - Artist / show info with subtle separators.
  - "LIVE" badge (using `.badge.badge-success` variant) that pulses.
- Responsive behavior: stack vertically under 768px (artwork top, metadata middle, controls bottom) with full-width controls.

## 6. Metadata Refresh Logic
- Use `setInterval` (default 15 seconds) to call `fetch-metadata.php`.
- Debounce UI updates to avoid flicker; only update fields that changed.
- Maintain `lastUpdated` timestamp; display "Updated HH:MM:SS" in metadata footer.
- Graceful degradation: if metadata unavailable for >3 consecutive polls, show fallback message and stop marquee.

## 7. Implementation Steps
1. **Scaffold documentation & environment variables**
   - Verify we have Icecast metadata endpoint; add config constant if needed (e.g. `ICECAST_METADATA_URL`).
2. **Create PHP proxy helpers** in `includes/StreamingProxy.php` (or similar) handling HTTP GET with timeout, error handling, and optional caching.
3. **Implement `fetch-stream-url.php`** returning JSON; include basic logging on failure.
4. **Implement `fetch-metadata.php`** with JSON response + normalisation.
5. **Build frontend HTML shell** (`streaming-audio-player.php`) pulling in shared header assets if required.
6. **Author dedicated CSS** for the player following dark material style.
7. **Create JS controller** to wire REST endpoints, audio element, UI state, and metadata polling.
8. **Add accessibility**: keyboard focus states, ARIA labels (`aria-pressed`, `aria-live`).
9. **Smoke test**: confirm playback works, metadata updates, proxies respect CORS.
10. **Future integration hook**: expose `window.StreamingPlayer` with init method for embedding inside admin/dashboard later.

## 8. Open Questions / Follow-ups
- Confirm exact Icecast metadata endpoint format; adjust proxy accordingly.
- Obtain final artwork asset for station (placeholder for now).
- Decide on authentication (if any) for APIs when embedding outside admin (likely public but rate limited).
- Determine whether to auto-start playback on load or wait for user gesture (browser autoplay policies suggest manual tap only).
