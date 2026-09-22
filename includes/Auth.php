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
 * FALLBACK_PASSWORD below is the old auth.js password and is used only when
 * ADMIN_PASSWORD is unset or blank, so a missing env var degrades to "the
 * security you had yesterday" instead of locking the admin out of a live site.
 * The login page shows which one is active. See docs/HANDOFF.md.
 */
class Auth
{
    /** The pre-existing auth.js password, used only when ADMIN_PASSWORD is unset. */
    private const FALLBACK_PASSWORD = 'podcast2025';

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
     * The active password: environment first, fallback second.
     */
    public static function password(): string
    {
        return self::envPassword() ?? self::FALLBACK_PASSWORD;
    }

    /**
     * True when ADMIN_PASSWORD actually reached PHP.
     * Used by the status banner on login.php so this can be verified in
     * production without guessing.
     */
    public static function usingEnvPassword(): bool
    {
        return self::envPassword() !== null;
    }

    public static function attempt(string $password): bool
    {
        self::boot();

        // hash_equals rather than === so the comparison time does not depend on
        // how many leading characters a guess got right.
        if (!hash_equals(self::password(), $password)) {
            return false;
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
