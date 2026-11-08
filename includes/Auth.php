<?php

/**
 * Server-side admin authentication.
 *
 * Replaces the old client-side gate in auth.js, which shipped the password
 * to the browser in plain text and only hid the UI (the API endpoints and
 * admin pages were still reachable directly).
 *
 * The password is the ADMIN_PASSWORD environment variable, set in the Coolify
 * dashboard as plain text - change the password by editing that value and
 * redeploying. There is no password-change screen in the app, by design.
 *
 * This fails closed: if ADMIN_PASSWORD is missing or blank, nobody can log in.
 * There used to be a hardcoded fallback password here so that a missing env
 * var could not lock the admin out, which made sense while it was still
 * unproven whether container env vars reached PHP at all. That is now
 * confirmed working in production, so the fallback's justification expired -
 * and because the old password is in this repo's git history and its docs, a
 * fallback means a publicly known password would quietly go live the moment
 * the env var went missing. Failing loudly is the safer end of that trade.
 *
 * The blast radius of failing closed is only the admin login: index.php,
 * feed.php, stream.php and the embed/gallery pages never include this file,
 * so the public site and the RSS feeds keep serving either way.
 * See docs/HANDOFF.md.
 */
class Auth
{
    private const SESSION_KEY = 'admin_authenticated';

    /**
     * Start the session if no other file has already done so.
     * Several admin pages call session_start() themselves for flash messages,
     * so calling it again unguarded would emit a notice.
     */
    private static function boot(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Read ADMIN_PASSWORD from wherever the runtime actually put it.
     *
     * getenv() alone is not enough under PHP-FPM. When the value is passed to
     * the pool as env[...] or as a FastCGI param, it lands in $_SERVER, and it
     * only reaches getenv()/$_ENV if variables_order includes "E". So an env
     * var that is genuinely set in Coolify can still make getenv() return
     * false. Checking all three is what makes "set it in the dashboard"
     * actually work regardless of how Nixpacks wires the pool.
     *
     * Only surrounding whitespace is stripped, since that is a paste artifact
     * rather than part of the password. Returns null when the value is absent
     * or blank everywhere.
     */
    private static function envPassword(): ?string
    {
        foreach ([getenv('ADMIN_PASSWORD'), $_SERVER['ADMIN_PASSWORD'] ?? null, $_ENV['ADMIN_PASSWORD'] ?? null] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * The configured password, or null when ADMIN_PASSWORD is unset or blank.
     * Callers must handle null rather than substituting a default - see the
     * class comment on why there is no fallback.
     */
    public static function password(): ?string
    {
        return self::envPassword();
    }

    /**
     * True when ADMIN_PASSWORD actually reached PHP. When this is false nobody
     * can log in, so login.php uses it to show a configuration error instead of
     * a password form that could never accept anything.
     */
    public static function isConfigured(): bool
    {
        return self::envPassword() !== null;
    }

    /** Wrong passwords allowed per IP within LOCKOUT_SECONDS before refusing. */
    public const MAX_FAILURES = 5;
    public const LOCKOUT_SECONDS = 900;

    /** Where failed attempts are counted. Tests point this at a temp file. */
    public static ?string $attemptsFile = null;

    private static function attemptsFile(): string
    {
        return self::$attemptsFile ?? __DIR__ . '/../data/login-attempts.json';
    }

    /**
     * Read-modify-write the per-IP failure list under an exclusive lock, so
     * parallel guesses cannot each read the same count and slip past the limit.
     * $change receives the IP's recent failure timestamps and returns the new
     * list; the result of the last call is returned.
     */
    private static function withFailures(string $ip, ?callable $change = null): array
    {
        $handle = fopen(self::attemptsFile(), 'c+');
        if ($handle === false) {
            // Can't count attempts (data/ unwritable): log it and allow the
            // attempt rather than locking the owner out of their own admin.
            error_log('[Auth] cannot open login attempts file; throttling disabled');
            return [];
        }
        flock($handle, LOCK_EX);
        $all = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];

        $cutoff = time() - self::LOCKOUT_SECONDS;
        foreach ($all as $key => $times) {
            $all[$key] = array_values(array_filter($times, fn ($t) => $t > $cutoff));
            if ($all[$key] === []) {
                unset($all[$key]);
            }
        }

        $failures = $all[$ip] ?? [];
        if ($change !== null) {
            $failures = $change($failures);
            if ($failures === []) {
                unset($all[$ip]);
            } else {
                $all[$ip] = $failures;
            }
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($all));
        }
        flock($handle, LOCK_UN);
        fclose($handle);

        return $failures;
    }

    public static function isLockedOut(string $ip): bool
    {
        return count(self::withFailures($ip)) >= self::MAX_FAILURES;
    }

    public static function attempt(string $password, string $ip = ''): bool
    {
        self::boot();

        $expected = self::password();

        // No password configured means no login is possible. Checked before the
        // comparison so an unset env var cannot be matched by submitting "".
        if ($expected === null) {
            return false;
        }

        // Refuse while locked out, even if this guess is right, so an attacker
        // can't keep guessing at full speed and just watch for a success.
        if ($ip !== '' && self::isLockedOut($ip)) {
            return false;
        }

        // hash_equals rather than === so the comparison time does not depend on
        // how many leading characters a guess got right.
        if (!hash_equals($expected, $password)) {
            if ($ip !== '') {
                self::withFailures($ip, fn ($times) => [...$times, time()]);
            }
            return false;
        }

        if ($ip !== '') {
            self::withFailures($ip, fn () => []);
        }

        // Rotate the session id on privilege change so a session id captured
        // before login cannot be reused afterwards.
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = true;

        return true;
    }

    public static function check(): bool
    {
        self::boot();

        return !empty($_SESSION[self::SESSION_KEY]);
    }

    public static function logout(): void
    {
        self::boot();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    /**
     * Gate an admin HTML page. Redirects to the login form.
     */
    public static function requirePage(): void
    {
        // Admin pages must never be framed by another site (clickjacking).
        header('X-Frame-Options: DENY');

        if (self::check()) {
            return;
        }

        $target = $_SERVER['REQUEST_URI'] ?? '/admin.php';
        header('Location: /login.php?redirect=' . urlencode($target));
        exit;
    }

    /**
     * Gate an admin API endpoint. Returns JSON rather than a redirect, so the
     * calling JS gets a parseable error instead of a login page in place of
     * the JSON it expected.
     */
    public static function requireApi(): void
    {
        if (self::check()) {
            return;
        }

        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error'   => 'Not authenticated. Please log in at /login.php',
        ]);
        exit;
    }
}
