# Analytics: "No Data" Diagnosis and Embed Tracking

**Date:** 2026-08-20
**Reported symptom:** the stats view said "no data" despite the user clicking around for two days.
**Files changed:** `embed/analytics.js` (new), `embed/script.js`, `embed/index.html`, `.gitignore`

---

## 1. The data was never lost

Production held **222 metrics** across 42 days in `data/analytics.xml` — intact. The newest
entry was **2026-07-31**; the report was made on **2026-08-20**.

The stats view defaults to the **7 Days** button (`api/get-analytics-stats.php` defaults to
`range=7d`). A 7-day window over data that stops 20 days earlier is legitimately empty, so
the dashboard said "no data". Clicking **All Time** shows everything.

## 2. Why nothing had been recorded for 20 days

`assets/js/analytics-tracker.js` was loaded on exactly one page:

```
index.php:340   <script src="assets/js/analytics-tracker.js?v=1.0.0"></script>
```

It listens for `audio:episodeStarted` and `audio:episodeDownloaded`, dispatched by
`assets/js/audio-player.js` and `assets/js/player-modal.js`.

**`embed/script.js` contained no analytics code at all.** Admin pages have none either. So
every play inside an embed — most real listening — was invisible, and the two days of
clicking happened in the admin and the embed generator, neither of which is tracked.

### Ruled out

- **Not a deploy wipe.** Production's file differs from the repo copy; `data/` is on a
  persistent volume.
- **Not the type-safety refactor** (`8e25582`, 2026-07-28). Its analytics changes are pure
  type hints and `@var` docblocks with no behaviour change, and production logged normally
  on Jul 29–31, after that deploy.
- **Not the auth work.** `index.php` is still public (`200`) and still serves the tracker.
- **Not a broken endpoint.** `api/log-analytics-event.php` is live and unauthenticated
  (correct for a public player); an invalid event type returns the proper validation error,
  proving the request reaches `AnalyticsManager`.

## 3. Fix: embed player tracking

New `embed/analytics.js` posts the same events, to the same endpoint, in the same payload
shape as the main tracker. Wired into `embed/script.js`:

- **Play** — hooked on the audio element's `play` event rather than at each call site, so
  every path that starts playback is counted. The tracker dedupes per episode per session,
  so resuming after a pause does not re-log.
- **Download** — hooked in `downloadEpisode()`.

### Endpoint path

The endpoint is resolved with `new URL('../api/log-analytics-event.php', location.href)`
rather than written as a relative path. This is the same defect fixed elsewhere on
2026-08-20: the embed lives at `/embed/`, where the main tracker's relative
`api/log-analytics-event.php` resolves to `/embed/api/...`, which returns **404** (verified).

### ID matching — the part that had to be right

Events only aggregate if the embed emits the *same* IDs as the public browser page:

- **Podcast ID** — read from the master feed's `<guid>` (e.g. `pod_1785501078_6a6c959697a7f`),
  not the embed's internal array index.
- **Episode ID** — `'ep_' + hashCode(audioUrl + feedIndex)`, the algorithm copied verbatim
  from `assets/js/player-modal.js`. It is computed at parse time **in feed order**, before
  the embed applies sorting or an episode limit — otherwise the same episode would get a
  different ID depending on the viewer's sort setting.

Verified identical against the main player's implementation:

```
MATCH ep_4bia8a    MATCH ep_wmd99w    MATCH ep_wrbphq
ID schemes are identical
```

Events missing either ID are dropped rather than written, so a malformed row never pollutes
the dashboard.

### Verified end to end

A POST in the exact shape the embed now sends was accepted and persisted
(`{"success":true}`, metric count 21 → 22); the test row was then removed from the local
data file.

## 4. Fix: `data/analytics.xml` untracked

It was committed to git while `data/podcasts.xml` and `data/self-hosted-podcasts.xml` were
already ignored. The repo copy was a snapshot last touched 2026-01-10 — seven months stale.
Production is currently safe because `data/` is a persistent volume, but if that mapping
ever changed, a deploy would drop the stale snapshot on top of live analytics.

Added to `.gitignore` and removed from the index with `git rm --cached` (the local file is
untouched).

## 5. Stats UI fixes

### Honest empty state (the actual reported bug)

`showEmptyState()` rendered **"No Analytics Data Yet"** whenever the selected range returned
nothing — indistinguishable from having lost the data, which is how this was reported.

`getDashboardStats()` now also returns `lastEventDate`: the newest day with any metric,
computed across **all** data rather than the selected range. The dashboard uses it to tell
the truth:

> 🗓️ **Nothing in this time range**
> No plays or downloads in the selected range. Last recorded activity was **10 Jan 2026**.
> [ View all time ]

The button calls a new `selectRange('all')`, which switches range and syncs the range
buttons' active state. The original "No Analytics Data Yet" message is still used when the
store genuinely holds nothing (`lastEventDate === null`), and its wording now mentions
embeds as well as the public player.

When a podcast filter is active the copy narrows to "No activity for this podcast", so an
empty *filter* is not mistaken for an empty *range*.

Verified against the local store, which reproduces the reported condition exactly:

```
range=7d   plays=0   downloads=0   lastEventDate='2026-01-10'
range=30d  plays=0   downloads=0   lastEventDate='2026-01-10'
range=all  plays=14  downloads=7   lastEventDate='2026-01-10'
```

### 90-day range removed from the UI

At this volume (222 events over 42 days) 90d and All Time return near-identical results.
The button is gone from `admin.php`; `'90d'` is deliberately **kept** in `$validRanges` in
`api/get-analytics-stats.php` so any existing bookmark or API caller keeps working.

### Dead code removed

`AnalyticsManager::getTopEpisodes()` had no callers anywhere — `getDashboardStats()` already
computes top episodes inline via `aggregateStats()`. Removed (42 lines). `getTopPodcasts()`
was referenced in the earlier audit but does not exist in the file; nothing to remove.

## 6. Known gaps (not addressed)

- **Admin pages are untracked.** Intentional — admin activity is not audience data.
- **`embed/script.js` has two `downloadEpisode` declarations** (lines ~357 and ~1483). The
  second overrides the first, so the earlier `async downloadEpisode(episode)` is dead code.
  The analytics hook was added to the effective one. Worth deleting the dead copy.

## 7. Storage note

At 101KB / 222 events, the XML store is adequate. A database migration would cost days and
fix nothing currently observable. Revisit past roughly 50k events.
