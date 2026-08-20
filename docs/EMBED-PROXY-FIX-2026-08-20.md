# Embed Generator: Proxy Failure, Layout, and SSRF Sweep

**Date:** 2026-08-20
**Reported symptom:** `https://podcast.supersoul.top/embed/iframe-generator.html` did not load its preview.
**Files changed:** `embed/proxy.php`, `embed/feed-allowlist.php` (new), `embed/script.js`,
`embed/iframe-generator.js`, `embed/iframe-generator.css`, `embed/iframe-generator.html`,
`embed/feed-tester.html`, `test-proxy-allowlist.php` (new)

---

## 1. Why the preview was blank

### Root cause

`fetchWithFallback()` in `embed/script.js` never called the same-origin `proxy.php` sitting
beside it. It computed `baseUrl` for exactly that purpose and then **never used it** — the
"Local PHP Proxy" entry was hardcoded to `http://localhost:8080`, which on the live HTTPS
site is both mixed-content-blocked and unreachable.

Production therefore depended entirely on three third-party CORS proxies. All three were
dead at the same time:

| Proxy | Result on 2026-08-20 |
|---|---|
| CorsProxy.io | `403` — free tier now blocks non-localhost origins |
| AllOrigins | `522` — down |
| CodeTabs | `522` — down |
| localhost:8080 | unreachable in production |
| own `proxy.php` | **200, 41KB valid RSS** |

The site's own working proxy was never called.

### Second, hidden cause

Routing to `proxy.php` alone would **not** have fixed playback. Its domain allowlist was
seven hand-typed entries, but the directory serves feeds from `anchor.fm`,
`archive.kpfk.org`, `archive2.wbai.org`, `archive.wjffradio.org`, `podcasts.wbai.org` and
`feeds.soundcloud.com` — none listed. Four of six sampled feeds returned `403`. The
`archive.org` entry never matched `archive.kpfk.org` despite looking like it should.

### Third cause (security)

The allowlist used `stripos($domain, $allowed) !== false` — a substring match anywhere in
the host. `podbean.com.attacker.example` passed. That made the proxy an open relay for
lookalike hosts.

## 2. Fixes

**Same-origin proxy first** (`script.js`, `iframe-generator.js`, `feed-tester.html`).
Derived from `window.location` so it works locally and deployed. Third-party proxies remain
only as fallbacks. The dead `localhost:8080` entry was removed.

**Allowlist derived from the directory** (`embed/feed-allowlist.php`, used by `proxy.php`).
Hosts come from `data/podcasts.xml` and `data/self-hosted-podcasts.xml` rather than a
hand-kept list. Verified in `XMLHandler` / `PodcastManager` that these are exactly the two
sources `feed.php` builds the master feed from, so coverage is complete **by construction** —
a podcast added through the admin UI is proxyable immediately instead of silently 403ing.

**Exact hostname matching** replaces the substring check, plus `CURLOPT_PROTOCOLS` confined
to http/https so a redirect cannot reach `file://` or `gopher://`.

**Removed the `about:blank` round-trip** in `iframe-generator.js` (3 occurrences).
`updatePreview()` set `iframe.src = 'about:blank'` then loaded the real URL 150ms later.
That aborted the document mid-load and cancelled its in-flight stylesheet requests — the
"failed to load a stylesheet" issue Chrome reported at `index.html:0`, `:22`, `:25`. The
`_t=` cache-buster already forces a fresh load, so the bounce only caused a blank flash.

## 3. Verification

`php test-proxy-allowlist.php` → `PASS - 8 directory feeds allowed, 8 hostile URLs blocked`

Production, after deploy — all previously `403`:

```
200  https://archive2.wbai.org/getrss.php?id=oth
200  https://archive.kpfk.org/getrss.php?id=nader
200  https://anchor.fm/s/ef064ec/podcast/rss
200  https://feeds.soundcloud.com/users/soundcloud:users:293609810/sounds.rss
```

Blocked, confirmed: lookalike hosts (`podbean.com.attacker.example`,
`podcast.supersoul.top.attacker.example`), `file://`, `gopher://`, link-local `169.254.169.254`.

## 4. The "controls don't change the preview" report

**Not a bug.** The console log proved the chain worked end to end: dropdown → `theme=light`
in the URL → iframe reload → `Applying theme from param: light`.

The real problem was visibility. `.main-layout` was `display: flex; flex-direction: column`,
so the preview panel always rendered *below* the entire control stack — measured at
**y = 2948px**. The user was changing a control at the top while the result updated
off-screen.

Fixed with a two-column desktop layout (≥1200px): controls left, preview `position: sticky`
beside them, plus a collapse toggle in the preview header. Measured after: **y = 220px**.

A related defect surfaced during measurement: the preview did not widen when the drawer
collapsed. Computed tracks read `"440px 356.328px"` with `.main-layout` only 892px wide in a
1500px viewport. Cause: `.main-layout` is a column-flex item with `margin: 0 auto`, and auto
cross-axis margins suppress `stretch`, so it was shrink-to-fit and `1fr` resolved against
content. Fixed with an explicit `width: 100%`.

## 5. Testing note

Headless Chrome hangs intermittently in this environment. Several verification attempts were
lost to it before switching to the real browser. **Use the actual browser for UI
verification in this repo** — headless is not reliable here. This was a tooling problem, not
an application bug.

## 6. Bug-class sweep (see section 7 for open items)

| Class | Where it came from | Rest of codebase |
|---|---|---|
| A. Third-party CORS proxy instead of same-origin | `script.js` | 3 files, all fixed; third-party now fallback-only |
| B. Hardcoded `localhost` in shipped code | `script.js` | Only demo/test files (`test-embed.html`, `tesst.html`) and a console hint — not live paths |
| C. Substring host matching | `proxy.php` | **None** — unique to `proxy.php` |
| D. Server-side fetcher with no host allowlist | `proxy.php` | **2 open endpoints found — see below** |

## 7. SSRF fixes in the API endpoints

Two unauthenticated endpoints were the same class as the original `proxy.php` defect, and
worse — they had no host check at all. Both confirmed exploitable on production before the
fix: `api/fetch-feed.php?url=https://example.com/` returned `200` with example.com's HTML,
and `api/download-proxy.php` returned arbitrary remote content as `audio/mpeg`.

Either let an anonymous caller make the server fetch arbitrary URLs: reaching internal
services and cloud metadata endpoints, and laundering traffic through the server's IP.

### Shared guard: `includes/FeedUrlGuard.php`

`embed/feed-allowlist.php` moved here, since it is no longer embed-specific. It now provides:

- `feedUrlIsAllowed($url, $ownHost)` — exact-host allowlist derived from the directory.
- `feedUrlHostIsPublic($url)` — resolves the host and rejects private, loopback,
  link-local and reserved space. **Every** resolved A/AAAA record is checked, not just the
  first, because a hostname can return both a public and a private record.

### `api/fetch-feed.php` — allowlist

Applied `feedUrlIsAllowed()` to the external-fetch branch only. The pre-existing
self-hosted branch (localhost + `self-hosted-feed.php`, which includes the file directly
rather than fetching over HTTP) is untouched and still works.

### `api/download-proxy.php` — public-address guard

An allowlist is the **wrong** tool here, and this is worth remembering: episode audio is
routinely served from a different host than the feed. The verification download came from
`mcdn.podbean.com`, which is not in the feed allowlist — allowlisting feed hosts would have
broken downloads outright. It uses `feedUrlHostIsPublic()` instead.

### Redirect bypass (both files)

A pre-flight host check alone is bypassable: a permitted host can redirect to an internal
address. Closed in both:

- `CURLOPT_PROTOCOLS` / `CURLOPT_REDIR_PROTOCOLS` confined to http/https.
- `download-proxy.php`: the existing HEAD request resolves redirects; its
  `CURLINFO_EFFECTIVE_URL` is re-checked **before any bytes are streamed**, and the GET is
  then pinned to that address with `CURLOPT_FOLLOWLOCATION => false`.
- `fetch-feed.php`: `CURLINFO_EFFECTIVE_URL` is checked after the fetch and the body is
  discarded rather than relayed if the redirects ended somewhere non-public.

### Verification

```
php test-proxy-allowlist.php
PASS - 8 directory feeds allowed, 8 hostile URLs blocked, 8 private hosts rejected
```

Live behaviour:

| Request | Before | After |
|---|---|---|
| `fetch-feed.php?url=https://example.com/` | 200 + body | **403** |
| `fetch-feed.php?url=http://169.254.169.254/...` | 200 | **403** |
| `fetch-feed.php` + real directory feed | 200 | **200, 100 items** |
| `download-proxy.php` + link-local / loopback | 200 | **403** |
| `download-proxy.php` + real episode MP3 | 200 | **200, valid MP3 streamed** |

### Not affected

The admin-side fetchers already sit behind auth guards, which is correct since they must
fetch feeds that are not yet in the directory: `import-rss.php`, `validate-rss-import.php`,
`refresh-feed-metadata.php`, `clone-feed.php`.

### Known issue (deferred): blind SSRF in `fetch-feed.php`

**Severity: low. Accepted for beta. Not scheduled.**

`fetch-feed.php` still *performs* the HTTP request before discarding a disallowed redirect
target. The response body is never relayed, so nothing is exfiltrated, but timing and
error-shape differences can reveal whether an internal host exists.

Narrower than a textbook blind SSRF: the allowlist means an attacker cannot choose the
target directly. They must already control one of the directory's own feed hosts
(podbean.com, archive2.wbai.org, democracynow.org, …) and make it redirect inward. Anyone
holding that position has better options than probing this network, and the yield is
reconnaissance rather than data.

**Revisit if any of these become true:**

- the allowlist is loosened, or arbitrary user-supplied feed URLs become fetchable without auth
- this endpoint is reused for something that returns richer errors to the caller
- the app moves to an environment where internal-host discovery is itself sensitive
  (shared VPC, reachable metadata service with real credentials)

**Fix when needed:** replace `CURLOPT_FOLLOWLOCATION` with a manual redirect loop that runs
`feedUrlHostIsPublic()` on every hop before following it, rather than only on the final
effective URL.

---

## 8. Status of this work

This is **beta software**. It runs on a live host at `podcast.supersoul.top`, but it has not
been formally released or systematically beta-tested — production here means "in use", not
"hardened and signed off". The fixes above were verified against the specific cases listed
in each section, not by a broad regression suite; no such suite exists in this repo beyond
`test-proxy-allowlist.php`.

Treat the security fixes in section 7 as the load-bearing part of this change set. They
close holes that were confirmed exploitable on the live host.
