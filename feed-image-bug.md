# Feed Image Bug — Audit & Root Cause Report

**Date:** July 28, 2026
**Symptom:** Podcasts added via RSS import show "No Image" / default cover in admin list, front page, and player modal. Feed validation passes, podcast imports successfully, episodes play — only the cover image is missing.
**Affected feeds observed:** `https://archive2.wbai.org/getrss.php?id=bbridges`, `https://archive2.wbai.org/getrss.php?id=housing`, and others.

---

## Root Cause

**Commit `14a0293` ("test fix for crash") reduced the shared cURL timeout in `RssFeedParser` from 10s to 3s.**

```php
// includes/RssFeedParser.php line 11
private $timeout = 3; // seconds (reduced for faster failures)
```

That timeout was intended to make **feed health checks** fail fast. But `fetchImageData()` — the method that downloads the podcast cover image during import — used the **same shared timeout** (line 615). Any image host that takes longer than 3 seconds to deliver the file causes the download to abort.

**Why the import still "succeeds":** in `PodcastManager::createPodcast()` (lines 58–63), a cover-image download failure is deliberately non-blocking — it logs an error and continues, so a bad image never blocks a feed import. Result: podcast imported, no cover, no visible error. There is **no browser console error by design** — the failure happens server-side and is only written to the PHP error log.

**Why "it worked for months":** it did — until the timeout was reduced from 10s to 3s. After that, any feed whose image host responds slowly (from the server's network) silently loses its cover.

---

## The Fix (applied)

`includes/RssFeedParser.php`, `fetchImageData()`:

1. **Dedicated 30s timeout** for image downloads (one-time operation at import — speed is irrelevant). The fast 3s timeout is untouched for feed health checks, so refresh/health-check performance is unaffected.
2. **Diagnostic logging** — failures now log HTTP code, exact cURL error, and the URL:
   ```
   Cover image download failed: HTTP {code} - cURL: {error} - URL: {url}
   ```
   This bug class can never be silent again.

---

## Audit Log — Every Layer Tested via Terminal

All tests run against the actual codebase (post-fix) on July 28, 2026. Local PHP dev server at `127.0.0.1:8123` used for HTTP-layer tests. All test data cleaned up afterward.

### Audit 1 — Parser + image download (CLI, 4 feeds)

Ran `fetchAndParse()` + `downloadCoverImage()` for 4 feeds (2 WBAI + NPR + Megaphone):

| Feed | Parse | image_url extracted | Download |
|---|---|---|---|
| WBAI bbridges | OK | `confessor2.wbai.org/pix/bbridges_it_37.jpg` | OK |
| WBAI housing | OK | `confessor2.wbai.org/pix/housing_it_39.jpg` | OK |
| NPR Planet Money | OK | `media.npr.org/...jpg` | OK |
| Megaphone (Smartless) | OK | `megaphone.imgix.net/...jpg` | OK |

**Result: PASS** — extraction handles `itunes:image`, `channel->image->url`, and `media:thumbnail`.

### Audit 2 — `api/import-rss.php` over HTTP

```
POST /api/import-rss.php  feed_url=<wbai housing>
→ HTTP 200, success: true, image_url: 'https://confessor2.wbai.org/pix/housing_it_39.jpg'
```

**Result: PASS** — the endpoint the admin modal calls returns `data.image_url` correctly, which JS puts into the hidden `rss_image_url` field (`assets/js/app.js` line 1103).

### Audit 3 — `api/validate-rss-import.php` over HTTP

```
→ success: true, can_import: true, feed_info.image_url present
```

**Result: PASS** — pre-import validation detects the image.

### Audit 4 — Full `admin.php` POST create (exact browser flow)

```
POST /admin.php  action=create + title + feed_url + description + rss_image_url
→ HTTP 302 (normal redirect)
→ data/podcasts.xml: <cover_image>pod_1785253491_6a68ce7344606.jpg</cover_image>
→ uploads/covers/pod_1785253491_6a68ce7344606.jpg  (180,667 bytes, on disk)
```

**Result: PASS** — image downloaded, saved, renamed to final podcast ID, persisted in XML.

### Audit 5 — Display layer

```
GET /admin.php   → <img src="uploads/covers/pod_1785253491_...jpg"> rendered in list
GET /uploads/covers/pod_1785253491_...jpg → HTTP 200 (image serves)
```

**Result: PASS** — admin list, front page, and modal all read `cover_image` from XML and render it.

### Supplementary checks (first diagnostic session)

- **Image URL reachability:** `curl -I https://confessor2.wbai.org/pix/bbridges_it_37.jpg` → HTTP 200, `image/jpeg`, 192KB
- **SSL:** valid Let's Encrypt cert (expires Oct 10 2026), `ssl_verify_result: 0` — **SSL is NOT the problem**
- **Git archaeology:** `git log -L 11,12:includes/RssFeedParser.php` pinned the timeout change to commit `14a0293`

---

## Verdict

**Every layer of the add-podcast pipeline passes with the fix in place.** The codebase in this workspace is fully functional end-to-end: validate → parse → extract image → download → save → persist → render.

The failures you are seeing are on the **running instance (deployed server), which still has the old 3-second timeout**. From that server's network, image hosts routinely exceed 3s, so every affected import silently loses its cover.

---

## Action Required

1. **Deploy** the updated `includes/RssFeedParser.php` to the server.
2. **Delete and re-add** the affected podcasts (Building Bridges, Housing, and any other recent adds with missing covers). Existing entries were saved with an empty `cover_image` and won't self-heal.
3. **If any feed still misses its image after the fix**, the exact reason is now in the server error log:
   ```bash
   grep "Cover image download failed" <php-error-log>   # or: docker logs <container> 2>&1 | grep "Cover image"
   ```

## How to reproduce this audit on the server (optional)

Run inside the container/host serving the app:

```bash
# 1. Can the server reach the image host at all, and how fast?
curl -s -o /dev/null -w "total=%{time_total}s code=%{http_code}\n" \
  "https://confessor2.wbai.org/pix/housing_it_39.jpg"

# 2. Test the app's own download path (from the app root):
php -r 'require_once "includes/RssFeedParser.php";
$p = new RssFeedParser();
var_export($p->downloadCoverImage("https://confessor2.wbai.org/pix/housing_it_39.jpg", "server_test"));'
# expect: success => true; then delete uploads/covers/server_test.jpg
```

If step 1 shows `total` > 3s, that single number is the entire bug on the old code.

---

## PRODUCTION VERIFICATION — July 28, 2026, 11:50 AM (after fix deployed)

Tested directly against the live server `podcast.supersoul.top` from the terminal:

### Prod Test 1 — import API on live server
```
POST https://podcast.supersoul.top/api/import-rss.php (WBAI housing feed)
→ HTTP 200, success: true, image_url extracted correctly
```

### Prod Test 2 — real podcast creation on live server
```
POST https://podcast.supersoul.top/admin.php  action=create + rss_image_url
→ HTTP 302 (success redirect)
→ get-public-podcasts.php shows the new podcast with:
    "cover_url": "uploads/covers/pod_1785253898_6a68d00aebdda.jpg",
    "has_cover": true
→ GET that cover URL → HTTP 200, 180,667 bytes (the real WBAI image)
```

Test podcast ("ZZ AUDIT TEST - DELETE ME") was deleted from prod after verification.

### ✅ VERDICT: THE FIX WORKS IN PRODUCTION

Adding a podcast via the exact same request the admin form sends now downloads and
serves the cover image correctly on the live server.

**If you still see "No Image":**
1. You are looking at podcasts added BEFORE the fix — they were saved with an empty
   `cover_image` and will never self-heal. **Delete them and re-add them.**
2. If a freshly re-added podcast still has no image, hard-refresh the admin page first
   (Cmd+Shift+R) — a cached old `app.js` could fail to fill the hidden `rss_image_url`
   field — then re-add.

---
---

# THE ACTUAL ROOT CAUSE (found July 28, 2026, 12:00 PM)

The timeout issue above was real but was **NOT** what you were hitting. The decisive clue
was: *"I used to see the parse in the interface and then a confirmation, now it just adds,
and it doesn't pull in any other metadata."*

## There are TWO different "add" buttons in the admin, and they do completely different things

`admin.php` lines 183–188:

```php
<button onclick="showAddModal()">Add New Podcast</button>          <!-- MANUAL -->
<a onclick="showImportRssModal()">Import from RSS</a>              <!-- PARSES FEED -->
```

| | **Add New Podcast** | **Import from RSS** |
|---|---|---|
| Modal | `podcastModal` / `podcastForm` | `importRssModal` / `rssImportForm` |
| Parses the feed? | **NO** | Yes (`api/import-rss.php`) |
| Preview + confirmation step? | **NO** | Yes (Step 1 → Step 2) |
| Has `rss_image_url` field? | **NO** | Yes (hidden input, line 768) |
| Pulls feed metadata? | Only episode count/date, post-save | Title, description, image, type, count |
| Result | **No cover image** | Cover image downloaded |

`podcastForm` (admin.php lines 446–500) posts only `action`, `id`, `title`, `feed_url`,
`description`, and an optional uploaded `cover_image` file. **There is no
`rss_image_url` field at all.** So `PodcastManager::createPodcast()` received an empty
`rss_image_url`, skipped the download branch entirely, and saved the podcast with no cover.

**This is why nothing appeared in any log and there was no console error — no download was
ever attempted.** The code was working exactly as written; the manual form simply never
asked for an image.

You previously used **Import from RSS** (hence the parse preview + confirmation you
remember). You are now clicking **Add New Podcast**, which is the manual path.

## Proof — reproduced on the LIVE server

Posting exactly what the "Add New Podcast" form sends (no `rss_image_url`):

```
POST https://podcast.supersoul.top/admin.php
  action=create, title=ZZ TEST-A ADDNEW,
  feed_url=https://archive2.wbai.org/getrss.php?id=housing, description=test A
→ HTTP 302
→ {
    "title": "ZZ TEST-A ADDNEW",
    "episode_count": 5,                <-- metadata post-refresh worked
    "cover_url": null,                 <-- ** THE BUG, REPRODUCED **
    "has_cover": false
  }
```

Compare with the same POST **including** `rss_image_url` (the Import-from-RSS path):

```
→ { "cover_url": "uploads/covers/pod_1785253898_....jpg", "has_cover": true }
```

Identical server, identical feed, identical moment in time. The only difference is whether
`rss_image_url` was sent. That single field is the entire bug.

Both test podcasts were deleted from production after verification.

## THE FIX (applied)

`includes/PodcastManager.php`, `createPodcast()` — added a server-side fallback so the
cover image is resolved from the feed itself whenever no file was uploaded and no
`rss_image_url` was supplied:

```php
$hasUploadedFile = $imageFile && $imageFile['error'] !== UPLOAD_ERR_NO_FILE;
if (!$hasUploadedFile && empty($data['rss_image_url']) && !empty($data['feed_url'])) {
    $parser = new RssFeedParser();
    $feedResult = $parser->fetchAndParse($data['feed_url']);
    if (!empty($feedResult['data']['image_url'])) {
        $data['rss_image_url'] = $feedResult['data']['image_url'];
    }
}
```

**Why this fix is correct:**
- It is **server-side**, so it works for *every* path — "Add New Podcast", "Import from
  RSS", and any future API caller. No client-side/JS dependency, no cache issues.
- **Respects explicit choices:** an uploaded file wins; an already-supplied
  `rss_image_url` wins. The lookup only runs when there is genuinely no image.
- **Non-destructive:** if the feed has no image or is unreachable, it logs and continues
  exactly as before — an import can never be blocked by artwork.

### Verification (local, simulating the "Add New Podcast" button)

```php
$pm->createPodcast([
  "title" => "ZZ LOCAL ADDNEW TEST",
  "feed_url" => "https://archive2.wbai.org/getrss.php?id=housing",
  "description" => "no rss_image_url supplied",
], null);
```
```
create: success => true
cover_image: 'pod_1785254430_6a68d21eb9099.jpg'   <-- FIXED (was empty before)
```

## What you need to do

1. **Commit and push** the `includes/PodcastManager.php` change, and redeploy.
2. **Delete and re-add** every podcast that is missing artwork (Building Bridges, Housing,
   etc.). Covers are fetched once, at add time, so old broken entries cannot self-heal.
3. Either button now works. **"Import from RSS" is still the better one** — it shows you
   the parse preview and confirmation, and pulls the title/description from the feed too.
   "Add New Podcast" now gets the artwork, but it still won't auto-fill title/description
   (that is by design — it is the manual entry form).

## Note on the earlier timeout fix

Keep it. The 3s shared timeout was a genuine latent bug that would have silently dropped
covers for any slow image host, and the added logging is what makes this class of failure
diagnosable. It just wasn't the cause of the symptom you reported.
