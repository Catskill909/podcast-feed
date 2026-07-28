# Type Safety Sweep & Diagnostic Cleanup — 2026-07-28 ✅

Commit: `8e25582` — *Refactor codebase to enforce type hinting and improve type safety*

---

## 🎯 What Started It

An **Import from RSS** attempt on `https://archive2.wbai.org/getrss.php?id=niteshift`
reported:

> Feed has 1 warning(s) — Missing recommended iTunes tags

The feed was valid. Investigating that warning led to a wider cleanup.

---

## 🐛 Bugs Fixed (real, user-visible)

### 1. Validator rejected spec-compliant `itunes:category`

**File:** `includes/RssImportValidator.php` (`checkItunesTags()`)

```php
$hasCategory = !empty($itunesElements->category);   // ❌ always false
```

Per the Apple spec, `itunes:category` carries its value in a **`text` attribute** and is
normally self-closing:

```xml
<itunes:category text="News &amp; Politics" />
```

`empty()` casts the `SimpleXMLElement` to bool; an element with no children and no text
content is **falsy regardless of its attributes**. So every correctly-written feed failed
this check.

```php
$hasAuthor = isset($itunesElements->author) && trim((string) $itunesElements->author) !== '';

$hasCategory = false;
if (isset($itunesElements->category)) {
    foreach ($itunesElements->category as $category) {
        if (trim((string) $category->attributes()['text']) !== '') { $hasCategory = true; break; }
    }
}
```

Result on the WBAI feed: `can_import=true`, level `pass`, **0 warnings**.

> ⚠️ Watch for this pattern anywhere else: `empty()` on a SimpleXML element is false for
> attribute-only tags. `PodcastHealthChecker.php` already did this correctly with `isset()`.

### 2. RSS output published wrong episode durations

**File:** `self-hosted-feed.php` (`formatDuration()`)

Stored feed data contains `<itunes:duration>59:00</itunes:duration>` (MM:SS, 10
occurrences). The old regex only passed through `HH:MM:SS`, so `59:00` fell into
`"59:00" % 3600`. PHP took the leading number and discarded the rest.

| input | before | after |
|---|---|---|
| `59:00` (59 min) | `00:00:59` ❌ | `00:59:00` ✅ |
| `1:30` | `00:00:01` ❌ | `00:01:30` ✅ |
| `abc` | **fatal TypeError** | `0` |
| `3612` | `01:00:12` | `01:00:12` (unchanged) |

It also emitted three `A non-numeric value encountered` warnings **per episode**, printed
into the middle of the XML body — which corrupts the feed for parsers when
`display_errors` is on.

Now parses both colon forms into total seconds and normalises to `HH:MM:SS`.

**All four `formatDuration()` copies were reviewed:**

| File | Signature | Notes |
|---|---|---|
| `self-hosted-feed.php:56` | `string\|int\|float\|null → string` | rewritten (bug above) |
| `api/get-podcast-episodes.php:295` | `string\|int\|float\|null → ?string` | logic was correct; typed + null-safe |
| `includes/AudioUploader.php:423` | `int\|float → string` | `round()` returns float → precision deprecation |
| `api/clone-feed.php:217` | `int → string` | elapsed-time humaniser; call site cast to int |

### 3. PHP deprecations

- **19 `curl_close()`** calls removed across 13 files — deprecated in PHP 8.5, and a no-op
  since PHP 8.0 (the handle is a GC-managed `CurlHandle` object). Matches the precedent
  already in `api/fetch-feed.php:78`.
- **4 `finfo_close()`** calls removed — same reasoning.
- **`e()`** in `functions.php` now casts, removing an `htmlspecialchars(): Passing null`
  deprecation fired on every null field rendered in a template.

---

## 🔧 Type Hints Added

**373 untyped parameters and properties → 0**, across 30 files.

### The rule that mattered

> For **userland** functions, passing `null` to a non-nullable `string` is a **fatal
> TypeError** — unlike internal functions, which only deprecate. Untyped params swallow
> `null` silently.

So anywhere `null` is genuinely reachable, the type is nullable:
`isDuplicate(?string $title, ?string $feedUrl)`, `downloadCoverImage(?string $imageUrl, …)`,
`?array $imageFile = null` on upload params, `validateCSRFToken(?string $token)`.
Genuinely polymorphic values use `mixed` (`sanitizeInput(mixed $data)` recurses on arrays;
`extractText(mixed $element)`).

### Properties need care too

A typed property with no default is *uninitialized* and throws on access before assignment.

- `XMLHandler::$dom` → `DOMDocument` (assigned unconditionally in the constructor) ✅
- `AdsXMLHandler::$xml`, `SelfHostedXMLHandler::$xml` → `?DOMDocument = null`
  (assigned in a **loader method**, not the constructor) ✅

### Input hardening this exposed

Typing `validateFeedForImport(string $feedUrlToValidate)` created a real risk: both
`feed_url` entry points read straight from request input, and `TypeError` is an `Error`,
**not** an `Exception` — so the existing `catch (Exception $e)` would not have caught it,
turning a graceful 400 into a fatal 500. `is_string()` guards were added at all three
entry points (`api/validate-rss-import.php`, both handlers in `api/clone-feed.php`).

### The DOMNode mistake (and its fix)

`podcastNodeToArray(DOMNode $node)` was wrong — the body calls `$node->getAttribute()`,
which exists on **`DOMElement`**, not `DOMNode`. Corrected.

Separately, typing `private ?DOMDocument $xml` gave the analyser enough information to
trace `$this->xml->documentElement->getElementsByTagName(...)->item(0)`, which PHP
declares as returning `DOMNode`. That surfaced ~44 pre-existing warnings on
years-old, runtime-correct code. Resolved with **29 `/** @var DOMElement $x */`
annotations** — comments only, zero runtime effect — applied only where the value comes
from `getElementsByTagName()`/`query()` **and** is later used with an element-only method.

### Dead code removed

Unused locals (`$id3v2_flags`, `$version`, `$layer`, `$samplerate`, `$enabledWebAds`,
`$shouldRefresh`, `$contentType`, `$uniqueVisitors`), the unused
`AudioUploader::$allowedMimeTypes` property, and `PodcastAudioDownloader::downloadWithProgress()`
(a never-called stub that only forwarded to `downloadAudioFromUrl()`).

`migrate-missing-fields.php` kept `new XMLHandler();` **without assignment** — the
constructor has a side effect (ensures `podcasts.xml` exists), so it must not be deleted.

---

## ✅ Verification Method

1. **Behavioural baseline.** Captured output of 10 entry points, 2 rendered feeds and 16
   class methods; `git stash`ed to the original code; captured again against the *same*
   data; diffed with timestamps normalised.
   **Result: byte-identical**, except the two removed deprecation notices.
   *(Isolating code from data mattered — the app's lazy feed scanner refreshes
   `latestEpisodeDate` on page load, which changes feed ordering and looks like a
   regression if you compare across runs.)*
2. **Write-path tests** in a sandboxed copy of the project (real data untouched): full
   podcast/episode CRUD, menu CRUD, ads settings, analytics, health recording, helpers —
   **34 passed, 0 failed**.
3. **Live browser run**: home, admin, self-hosted pages and all 3 feeds render; feeds
   parse as valid XML; zero PHP notices in any page body; zero server-log errors.
4. `php -l` clean on all 99 project files.

---

## 🧰 Editor Configuration

`.vscode/settings.json` was added. Root cause of the diagnostic noise: **two full PHP
language servers were installed and both analysed every file** — Intelephense
(`bmewburn`) and DEVSENSE PHP Tools.

```jsonc
"php.problems.scope": "none",              // DEVSENSE stops duplicate reporting
"intelephense.diagnostics.run": "onSave",
"intelephense.diagnostics.exclude": {
  "**/*.php": ["P1132"],                   // codebase is fully typed; no future nagging
  "**/vendor/**": ["*"], "**/docs/**": ["*"],
  "**/docs-archive/**": ["*"], "**/data/backup/**": ["*"]
}
```

DEVSENSE still reports **fatal and parse errors workspace-wide** regardless of this
setting, so the real safety net is intact.

> 💡 Diagnostics appearing "suddenly, file by file" is expected behaviour, not a
> regression: `php.problems.scope` defaults to `"opened"`, so an analyser only reports on
> files you have opened. Pasted diagnostic JSON uses VS Code **MarkerSeverity**:
> `1 = Hint, 2 = Info, 4 = Warning, 8 = Error`.
