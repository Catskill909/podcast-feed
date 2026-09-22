# Admin Password Setup

**Status:** Live in production. Last updated 2026-09-22.

The admin password is the **`ADMIN_PASSWORD` environment variable**, in plain
text. Change the password by editing that value and redeploying. There is
deliberately no password-change screen in the app.

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

| Banner | Meaning |
|---|---|
| 🟢 **"Using ADMIN_PASSWORD from environment"** | Your variable took effect |
| 🟠 **"ADMIN_PASSWORD not set — using built-in fallback"** | Your variable did not reach PHP; the old default is still live |

**If the banner is orange after you set the variable**, the variable is genuinely
absent from the container. Check that:

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

## The fallback — read before removing it

If `ADMIN_PASSWORD` is unset or blank, `Auth::FALLBACK_PASSWORD` (the old
`auth.js` password) is used instead.

**This is intentional.** Without it, a silently-missing env var would lock you
out of a live site with no way back in except a redeploy. It makes a
misconfiguration degrade to *"the security you had yesterday"* instead of
*"a brick."*

Removing it is a deliberate follow-up step, and only once the login banner is
confirmed green in production — see step 3 in [HANDOFF.md](HANDOFF.md).

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
