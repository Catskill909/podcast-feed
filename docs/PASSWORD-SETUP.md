# Admin Password Setup

**Status:** Live in production. Last updated 2026-09-22.

The admin password is the **`ADMIN_PASSWORD` environment variable**, in plain
text. Change the password by editing that value and redeploying. There is
deliberately no password-change screen in the app.

**There is no fallback password.** If `ADMIN_PASSWORD` is not set, nobody can
log in — the login page says so and tells you how to fix it. This affects only
the admin area; the public site and RSS feeds are unaffected either way.

> **Superseded approach.** This used to be a client-side check in `auth.js`
> with the password in a JavaScript constant. That file has been deleted.
> Older docs that tell you to edit `auth.js` are describing a system that no
> longer exists — editing it would not change anything, because there is
> nothing to edit. See [HANDOFF.md](HANDOFF.md) for why it changed.

---

## Setting the password

### Coolify (production)

1. Coolify dashboard → the `podcast-feed` app
2. **Configuration → Environment Variables → Add**
3. Name `ADMIN_PASSWORD`, value = the password you want, e.g. `Podcast2026`
4. **Redeploy**

### Local development

Either export it in your shell:

```bash
ADMIN_PASSWORD='your-password' php -S localhost:8000
```

…or copy `.env.example` to `.env` and set it there.

---

## Confirming it worked

Visit **`/login.php`**. A status line at the bottom of the card tells you which
password is actually in use — you never have to guess:

| What you see | Meaning |
|---|---|
| 🟢 Login form + **"Using ADMIN_PASSWORD from environment"** | Your variable took effect |
| 🔴 **"ADMIN_PASSWORD is not set"**, no form, HTTP 503 | Your variable did not reach PHP; **login is disabled** |

**If you see the red panel after setting the variable**, it is genuinely absent
from the container. Check that:

- it is saved on the **right Coolify resource**, and
- the app was **redeployed** after saving, and
- the value is **not blank** — a blank or whitespace-only value is deliberately
  treated as unset.

The usual PHP-FPM cause is already handled in code: a value delivered to the
pool as `env[...]` or as a FastCGI param lands in `$_SERVER` and reaches
`getenv()` only when `variables_order` contains `E`, so `Auth::envPassword()`
checks `getenv()`, `$_SERVER` and `$_ENV` in turn.

---

## What the value can contain

Leading and trailing whitespace is stripped, because that is a paste artifact
rather than part of a password. Everything else is taken literally: interior
spaces, `$`, `#`, quotes, and other punctuation all work.

If Coolify's own field does any shell-style interpolation, quote the value so
`$` is not eaten.

---

## No fallback — this fails closed

If `ADMIN_PASSWORD` is unset or blank, `Auth::password()` returns `null` and
every login attempt is refused. `/login.php` returns **503**, hides the password
form, and shows the Coolify steps.

There used to be a hardcoded fallback (the old `auth.js` password) so a missing
variable could not lock you out. It was removed on 2026-09-22 once the env var
was confirmed working in production: that old password is in this repo's git
history and docs, so keeping it meant a **publicly known password** would go
live the moment the variable went missing — silently, with nothing to alert you.

The lockout it protected against is narrow and recoverable:

- **Only admin login is affected.** `index.php`, `feed.php`, `stream.php` and
  the embed/gallery pages never include `Auth.php`. Listeners and podcast apps
  keep working.
- **Recovery** is setting the variable in Coolify and redeploying — the same two
  minutes as the initial setup.
- **The failure is loud**, not silent: a red panel that names the variable.

---

## What is and is not protected

**Requires the password:** `admin.php`, `ads-manager.php`, the admin API
endpoints, and anything else calling `Auth::requirePage()` or
`Auth::requireApi()`.

**Stays public, no password:** `index.php`, `feed.php`, `app.html`,
`features.html`, `embed/`, `gallery/`, `stream.php` — listeners and podcast
apps are unaffected.

Sessions are server-side PHP sessions. The session id is rotated on login, so a
session id captured beforehand cannot be reused. Log out at `/logout.php`.

---

## Testing

Run the test suite:

```bash
php tests/auth_env_test.php
```

It covers every place the runtime can deliver the variable (`getenv`,
`$_SERVER`, `$_ENV`), plus blank, whitespace-padded, and shell-metacharacter
values.

Exercise the real login flow locally:

```bash
ADMIN_PASSWORD='Podcast2026' php -S 127.0.0.1:8777 -t .
```

| Check | Expected |
|---|---|
| `/login.php` banner | green, "from environment" |
| Sign in with `Podcast2026` | 302 redirect to `/admin.php` |
| Sign in with anything else | "Incorrect password." |
| `/admin.php` with no session | 302 redirect to `/login.php` |
| `curl /feed.php` | works, no password |

And the unconfigured state, which should refuse everything:

```bash
env -u ADMIN_PASSWORD php -S 127.0.0.1:8777 -t .
```

| Check | Expected |
|---|---|
| `/login.php` | **503**, red "not set" panel, no password form |
| Any sign-in attempt | refused — no session granted |
| `/index.php`, `/feed.php` | **200** — public site unaffected |

---

## Security notes

The password is verified **server-side**. It never reaches the browser, and the
admin pages and API endpoints are gated individually — so a direct request to an
endpoint is rejected, not merely hidden from the UI. This is the substantive
difference from the old `auth.js` approach, where View Source revealed the
password and every endpoint was reachable regardless.

The tradeoff of storing it as plain text: anyone with access to your Coolify
dashboard can read the password. That is a deliberate simplicity choice. It is
not exposed to site visitors.

Auth fails closed, so a missing or blank variable disables login rather than
falling back to any default.

Comparison uses `hash_equals`, so response timing does not reveal how many
leading characters a guess got right.

**Not implemented**, by design — this is a single shared admin password:
multiple user accounts, roles, per-user activity logs, and rate limiting on
login attempts.

---

## Related

- [HANDOFF.md](HANDOFF.md) — design rationale, remaining hardening steps
- `includes/Auth.php` — the implementation
- `tests/auth_env_test.php` — the tests
- `.env.example` — the variable, documented inline
