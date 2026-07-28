# RSS Import Modal — Stale State Reset Fix ✅

**Date:** 2026-07-28
**File changed:** `assets/js/app.js`
**Reported by:** user ("after you dismiss the add modal with the error and then import from RSS again the error is there")

---

## 🐛 The Bug

Opening **Import from RSS** after a previous attempt showed the *previous* attempt's result:

- Import a bad feed → red error panel appears → dismiss modal → click **Import from RSS** again → **the old error is still there.**
- Import a good feed → green "Feed validated successfully!" panel → dismiss → reopen → **the old success message is still there.**

Both directions were reproducible.

## 🔍 Root Cause

The validation result renders into a dedicated container, `#rssValidationPanel`
(`admin.php:717`), which is written to by three functions:

| Function | Line | Writes |
|---|---|---|
| `showValidationSuccess()` | ~990 | green success panel |
| `showValidationWarnings()` | ~1011 | amber warnings + Cancel / Continue buttons |
| `showValidationErrors()` | ~1056 | red blocking-error panel |

Each sets `panel.innerHTML = ...` and `panel.style.display = 'block'`.

**`resetRssImportModal()` never touched `#rssValidationPanel`.**

It reset step 1 / step 2, the inline error bar, the loading spinner, the buttons and the
text inputs — but not the validation panel. The panel was only ever hidden in one place:
the top of `fetchRssFeedData()` (line ~931), i.e. *during the next fetch*, which is far
too late. Since `resetRssImportModal()` is called by **both** `showImportRssModal()` and
`hideImportRssModal()`, neither opening nor closing the modal cleared the result.

## 🛠 The Fix

Four state leaks were fixed in `resetRssImportModal()`, not just the reported one:

### 1. Validation panel not cleared (the reported bug)
```js
const validationPanel = document.getElementById('rssValidationPanel');
if (validationPanel) {
    validationPanel.style.display = 'none';
    validationPanel.innerHTML = '';   // hiding alone is not enough
}
```
Hidden **and** emptied — hiding alone would leave the markup to reappear.

### 2. Dangling promise when closing mid-warning
`showValidationWarnings()` returns a Promise resolved only by the `Cancel` /
`Continue Anyway` buttons. Closing the modal while that prompt was open left
`fetchRssFeedData()` awaiting **forever**, so its `finally` block (which hides the
spinner and re-enables the fetch button) never ran.

The resolver is now tracked and settled on reset:
```js
let pendingRssValidationResolve = null;   // module scope

// in resetRssImportModal()
if (pendingRssValidationResolve) {
    const resolvePending = pendingRssValidationResolve;
    pendingRssValidationResolve = null;
    resolvePending(false);
}
```

### 3. Stale inline error text
`#rssImportErrorMessage` kept its previous `textContent`. Now cleared.

### 4. Stale button state
`importRssFeed()` sets the import button to `disabled` + `"Importing..."`. If that
attempt failed and the modal was reopened, the button stayed disabled and mislabelled.
`resetRssImportModal()` now restores `disabled = false` and the original label, and
re-enables `#rssFetchButton` defensively.

## ✅ Verification

Driven in a real headless browser (Playwright) against `admin.php`, comparing the
committed pre-fix `app.js` with the fixed version.

**Error path** — feed `https://example.com/not-a-feed.xml`:

| Step | Before fix | After fix |
|---|---|---|
| after failed validation | `display:block`, 1064 chars | `display:block`, 1064 chars |
| after dismissing modal | `display:block`, **1064 chars** | `display:none`, **0 chars** |
| after reopening modal | `display:block`, **1064 chars** ❌ | `display:none`, **0 chars** ✅ |

**Success path** — feed `https://archive2.wbai.org/getrss.php?id=niteshift`:

| Step | Before fix | After fix |
|---|---|---|
| after preview loaded | `display:block`, 423 chars | `display:block`, 423 chars |
| after reopening modal | `"Feed validated successfully!"` ❌ | `display:none`, 0 chars ✅ |

Also confirmed clean on reopen: `fetchButton.disabled=false`,
`importButton.disabled=false`, label `"Import Podcast"`, step 1 visible, URL input empty.

`node --check assets/js/app.js` passes. No new console/page errors (the observed HTTP 422
is the validation API's correct response for an invalid feed).

## 📌 Note for future modal work

Any modal that renders results into a container must clear that container in its reset
function — **hiding is not resetting**. Prefer clearing in the reset path rather than at
the start of the next action, so both open and close leave a clean slate.
