# HANDOFF — Admin Authentication Migration

**Date:** 2026-08-19
**Branch:** `main`
**Status:** ✅ Code complete, ⚠️ **not tested in production**
**Context:** This is a **demo application**. No payments, no PII, no destructive
public actions. Full source is on GitHub, so worst-case recovery is a revert.
Risk tolerance was explicitly set to "ship it, verify later."

---

## 1. What this change does (one paragraph)

The admin password used to live in **`auth.js`** as a plain-text JavaScript
constant (`podcast2025`). The browser downloaded it, so anyone could read it via
View Source — and because the check was client-side, every admin page and API
endpoint was reachable directly regardless. This change moves authentication to
the **server**, stores only a **bcrypt hash**, and reads that hash from the
**`ADMIN_PASSWORD_HASH` environment variable** set in the Coolify dashboard.

---

## 2. Deployment context

| Item | Value |
|---|---|
| Platform | Coolify + Nixpacks (nginx + PHP-FPM, docroot `/app`) |
| Repo | https://github.com/Catskill909/podcast-feed |
| Deploy trigger | Push to `main` → Coolify auto-deploys |
| Config loaded at runtime | `config/config.php` |
| `config/config.production.php` | **Dead file** — nothing includes it. Not used. |
| `.htaccess` | **Inert** — nginx does not read `.htaccess`. Its "Protect sensitive files" block does nothing. |

---

## 3. Files changed

### New files
| File | Purpose |
|---|---|
| `includes/Auth.php` | The whole auth layer. Self-contained — deliberately does **not** depend on `config/config.php`, so include-order can't break it. |
| `logout.php` | Clears the session, redirects to login. |
| `docs/HANDOFF.md` | This file. |

### Rewritten
| File | Change |
|---|---|
| `login.php` | Was a non-functional placeholder calling `config/auth_placeholder.php`. Now a real password-only login form (matching the old auth.js UX), dark-themed, self-contained. |

### Deleted
| File | Reason |
|---|---|
| `auth.js` | Shipped the plain-text password to every visitor. Recoverable via git. |

### Gated — admin pages (`Auth::requirePage()`, redirects to `/login.php`)
Inserted immediately after each file's existing `session_start();`:
- `admin.php`
- `menu-manager.php`
- `ads-manager.php`
- `self-hosted-podcasts.php`
- `self-hosted-episodes.php`
- `debug.php`  ← **note: this page was never gated before, not even by the JS modal**

### Gated — admin API endpoints (`Auth::requireApi()`, returns 401 JSON)
Inserted directly after the opening `<?php` in all 17:

```
clone-feed.php            delete-ad.php             delete-menu-item.php
get-analytics-stats.php   import-rss.php            refresh-feed-metadata.php
reorder-menu-items.php    save-menu-branding.php    save-menu-item.php
sort-preference.php       toggle-ad-enabled.php     toggle-menu-item.php
update-ad-settings.php    update-ad-url.php         upload-ad.php
upload-audio-chunk.php    validate-rss-import.php
```

### Left PUBLIC — deliberately, do NOT gate these
These are called by `index.php` (the public browse page and player modal).
Gating them **will break the live public site**:

| Endpoint | Consumer |
|---|---|
| `api/fetch-feed.php` | `player-modal.js`, `app.js` |
| `api/get-podcast-preview.php` | `player-modal.js`, `app.js` |
| `api/download-proxy.php` | `player-modal.js` |
| `api/auto-refresh.php` | `auto-refresh.js` (on index.php **and** admin.php) |
| `api/get-public-podcasts.php` | `browse.js` |
| `api/log-analytics-event.php` | `analytics-tracker.js` |
| `api/health-check.php` | Health probe (POST-only; returns 405 on GET — pre-existing, not a bug) |

Also untouched and public: `index.php`, `app.html`, `features.html`,
`embed/`, `gallery/`, `gallery2/`, `feed.php`, `self-hosted-feed.php`,
`stream.php`, `stream-audio.php`, `mobile-ads-feed.php`.

---

## 4. How `Auth.php` works

```php
Auth::attempt($password)   // password_verify + session_regenerate_id, sets session
Auth::check()              // bool — is this session authenticated
Auth::requirePage()        // redirect to /login.php?redirect=<current URI>
Auth::requireApi()         // 401 + JSON {success:false, error:...}
Auth::logout()             // clear session
Auth::passwordHash()       // env var, or fallback
Auth::usingEnvHash()       // did the env var actually reach PHP?
```

### ⚠️ The fallback — read this before "cleaning it up"

`Auth::FALLBACK_HASH` is the bcrypt hash of the **old auth.js password
(`podcast2025`)**. If `ADMIN_PASSWORD_HASH` is unset or empty, auth falls back
to it.

**This is intentional, not an oversight.** PHP-FPM can run with `clear_env=on`,
which strips container env vars before PHP ever sees them. Without the fallback,
a silently-missing env var would lock the admin out of a live site with no way
back in except a redeploy. The fallback makes a misconfiguration degrade to
*"the security you had yesterday"* instead of *"a brick."*

**Removing it is step 3 below — do that only after confirming the env var works.**

---

## 5. NEXT STEPS (in order)

### Step 1 — Set the env var in Coolify
1. Generate a hash locally (do **not** commit the plain password):
   ```bash
   php -r "echo password_hash('YOUR-NEW-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
   ```
2. Coolify dashboard → the `podcast-feed` app → **Configuration → Environment Variables**
3. Add: name `ADMIN_PASSWORD_HASH`, value = the full `$2y$12$...` string
   - ⚠️ Quote it or make sure Coolify doesn't mangle the `$` characters.
4. Redeploy.

### Step 2 — Verify it reached PHP
Visit **`/login.php`**. There is a status line at the bottom of the card:

- 🟢 **"Using ADMIN_PASSWORD_HASH from environment"** → working. Continue to step 3.
- 🟠 **"ADMIN_PASSWORD_HASH not set — using built-in fallback"** → the env var did
  not reach PHP. Likely `clear_env=on` in PHP-FPM. Fix: set `clear_env = no` in
  the FPM pool config, or write the value into a `.env` file and add a loader.
  **Do not proceed to step 3 until this is green.**

### Step 3 — Remove the fallback (only after step 2 is green)
In `includes/Auth.php`, make `passwordHash()` fail closed instead of falling back:
```php
$hash = getenv('ADMIN_PASSWORD_HASH');
if ($hash === false || trim($hash) === '') {
    http_response_code(500);
    exit('ADMIN_PASSWORD_HASH is not configured.');
}
return trim($hash);
```
Then delete the `FALLBACK_HASH` constant.

### Step 4 — Cleanup (independent, safe, not yet done)
Delete from the repo root — these are live on production right now:
```
test-*.php  test-*.html  debug-*.php  view-source.php  css-diagnostic.php
phpinfo-check.php  check-user.php  check-production-config.php
fix-permissions.php  debug-permissions.php  setup-directories.php
mobile-test.php  Dockerfile.broken-backup
*.bak  *.bak2
```
Also 7 API endpoints with **no caller anywhere in the codebase**:
```
api/feed-health.php        api/get-ad-data.php        api/get-episodes-simple.php
api/get-podcast-episodes.php  api/refresh-all-feeds.php
api/test-episodes.php      api/test-feed-direct.php
```
And the now-unused `config/auth_placeholder.php` (superseded by `includes/Auth.php`).

### Step 5 — Nice-to-haves
- Add a **"Log out"** link pointing to `/logout.php` in the admin nav (no UI link exists yet — the route works, it's just not linked).
- Add CSRF tokens to admin POST forms.
- Sessions live in the container's `/tmp`, so **every redeploy logs admins out**. Cosmetic; just log in again. Fix with a persistent session volume if it becomes annoying.

---

## 6. What was tested vs. NOT tested

### ✅ Tested locally (PHP 8.4 built-in server, `php -S`)
- `php -l` syntax check passes on **every** modified file.
- Public routes return 200: `/`, `/index.php`, `/login.php`, `/api/get-public-podcasts.php`
- All 6 admin pages return **302** → `/login.php`
- Admin API endpoints return **401** with valid JSON
- `auth.js` is gone from disk

### ❌ NOT tested — do this when you pick it up
- **Actually logging in** with a correct password and reaching admin (the happy path was never exercised end-to-end).
- Whether admin JS handles a 401 gracefully, or just fails silently mid-action if the session expires.
- File uploads through `upload-audio-chunk.php` / `upload-ad.php` while authenticated.
- **Anything on the real Coolify deployment.** Zero production verification.
- Whether `getenv()` works under this Nixpacks PHP-FPM build (see step 2).

---

## 7. Rollback

Everything is one commit. To undo completely:
```bash
git revert <commit-sha>
git push            # Coolify redeploys automatically
```
To restore just the old JS gate:
```bash
git checkout <commit-sha>^ -- auth.js
```

---

## 8. Honest security assessment

**Better than before:** the password is no longer shipped to browsers, it's
bcrypt-hashed, it's configurable per-environment without a code change, and the
admin pages and write endpoints are genuinely closed rather than merely hidden.

**Still true after this change:**
- No CSRF protection on admin forms.
- No rate limiting on `/login.php` — brute-forceable.
- No HTTPS enforcement in app code (relies on Coolify's proxy).
- Debug/test files remain live on production until step 4 is done.
- Data is XML files on disk; there is no per-user authorization model, just
  one shared admin password.

For a demo with no payments and no user data, this is a reasonable stopping
point. It would **not** be sufficient for anything handling real user accounts.
