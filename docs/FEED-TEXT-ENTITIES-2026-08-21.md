# Feed Text: HTML Entities Rendered Literally

**Date:** 2026-08-21
**Reported symptom:** an imported podcast description displayed as
`Cut To The Chase&nbsp;gets straight to the real issues&mdash;bringing activists...`
**Files changed:** `includes/FeedText.php` (new), `includes/RssFeedParser.php`,
`includes/RssImportValidator.php`, `api/get-podcast-episodes.php`,
`api/get-episodes-simple.php`, `migrate-feed-text-entities.php` (new), `.gitignore`

---

## 1. Root cause

`RssFeedParser::extractText()` normalised feed text with:

```php
return trim(strip_tags($text));
```

`strip_tags()` removes **tags**. Entities are not tags, so `&nbsp;` and `&mdash;` passed
through untouched and were written to `data/podcasts.xml` verbatim.

The source feeds double-encode. The feed XML holds `&amp;nbsp;`; the XML parser decodes that
once and hands back the literal string `&nbsp;`; nothing decoded it a second time.

Verified against the real feed
(`https://archive.kpfk.org/getrss.php?id=mornimixcutchasewsylveriver`):

```
RAW from SimpleXML:   Cut To The Chase&nbsp;gets straight to the real issues&mdash;bringing...
OLD strip_tags+trim:  Cut To The Chase&nbsp;gets straight to the real issues&mdash;bringing...
NEW feedText():       Cut To The Chase gets straight to the real issues—bringing...
```

## 2. The shared fix: `includes/FeedText.php`

`feedText($raw, $maxLength = 0)` is now the single entry point for any human-readable value
taken from a feed. In order:

1. `html_entity_decode(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')` — handles named and numeric
   entities (`&nbsp;`, `&mdash;`, `&rsquo;`, `&#8211;`).
2. `strip_tags()` — **after** decoding, deliberately: feeds constantly ship descriptions as
   escaped markup (`&lt;p&gt;Text&lt;/p&gt;`), and decoding first turns those into real tags
   that `strip_tags` then removes. Stripping first would leave the markup visible.
3. Fold `U+00A0` (what `&nbsp;` decodes to) into a normal space and collapse whitespace runs —
   it looks like a space but breaks wrapping and string comparison.
4. Trim, then optionally truncate on character count with `mb_substr`.

Behaviour check:

```
Cut To The Chase&nbsp;gets...&mdash;bringing   => Cut To The Chase gets...—bringing
&lt;p&gt;Escaped &lt;b&gt;markup&lt;/b&gt;     => Escaped markup from a feed
3rd &amp; Fairfax                             => 3rd & Fairfax
Smart &rsquo;quotes&rsquo; and &hellip;       => Smart ’quotes’ and …
<p>Real <b>markup</b></p>                     => Real markup
Line\n\n  breaks   collapsed                  => Line breaks collapsed
```

### Known trade-off

Decoding before `strip_tags()` means a description containing a literal `5 < 10 and x > 3`
can lose the span between `<` and `>`. This risk already existed (`strip_tags` was always
applied) and is the standard trade for handling escaped markup, which is far more common in
real feeds than inline angle brackets.

## 3. Every ingest site fixed (7)

| File | Site |
|---|---|
| `includes/RssFeedParser.php` | `extractText()` — feeds titles and descriptions into `podcasts.xml` |
| `includes/RssImportValidator.php` | channel title + description in the import preview |
| `api/get-podcast-episodes.php` | RSS episode title + description |
| `api/get-podcast-episodes.php` | Atom entry title + description |
| `api/get-episodes-simple.php` | episode title + description |

A repo-wide sweep for `strip_tags` in `includes/` and `api/` now returns no undecoded feed
text. (`AnalyticsManager::sanitize()` also uses `strip_tags`, correctly — it sanitises values
for storage rather than normalising feed prose.)

### Client side was already correct

`assets/js/player-modal.js` `stripHTML()` assigns to `innerHTML` and reads `textContent`,
which decodes entities, and both episode title and description pass through it. The embed's
`decodeHtmlEntities()` uses the same textarea technique. No JS changes were needed.

## 4. Existing data is repaired on read — no manual step

Refreshing a feed does **not** rewrite stored rows: `api/refresh-feed-metadata.php` only
writes `latest_episode_date` and `episode_count`. So fixing the parser alone would have left
every podcast imported before this change still displaying entities until someone ran a
migration on the server.

Instead, `feedText()` is applied at the **read boundary**, which every podcast read passes
through:

- `includes/XMLHandler.php` → `podcastNodeToArray()` (title, description)
- `includes/SelfHostedXMLHandler.php` → `podcastNodeToArray()` and `episodeNodeToArray()`,
  via `normaliseTextField()`, which touches only `title` and `description` and leaves URLs,
  dates and ids alone

`feedText()` is idempotent, so already-clean values pass through untouched. Deploying the
code is therefore sufficient — no shell access, no migration run, and any future row written
by an older code path is also cleaned before it can reach the UI.

Verified by injecting the reported damaged string directly into stored data and reading it
back through `PodcastManager` with no migration run:

```
stored:   Cut To The Chase&nbsp;gets straight to the real issues&mdash;bringing activists
read as:  Cut To The Chase gets straight to the real issues—bringing activists
```

### Optional tidy-up: `migrate-feed-text-entities.php`

The stored XML still holds the raw text until the row is next written. The migration script
cleans the files themselves. It is **optional** — display is already correct without it.

```bash
php migrate-feed-text-entities.php            # dry run, prints every before/after
php migrate-feed-text-entities.php --apply    # writes, after a timestamped backup
```

It backs each file up to `data/<name>.bak-<timestamp>` before writing, refuses to write if
the backup fails, and is idempotent. Backups are gitignored via `data/*.bak-*`.

## 5. Repository hygiene fixed at the same time

`git ls-files -i -c` listed 12 files that were tracked despite being in `.gitignore` —
`data/self-hosted-podcasts.xml`, `logs/error.log`, 6 ad images and 4 cover images.
`.gitignore` has no effect on files git already tracks, so these were carrying stale copies
in the repo.

All 12 were untracked with `git rm --cached` (files left on disk). Checked first that they
were not load-bearing: git held 5 covers, disk has 26, production serves 47 podcasts — so
uploads come from the persistent volume, not the repo. `git ls-files -i -c` now returns
nothing.
