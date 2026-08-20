<?php

/**
 * Server-side admin authentication.
 *
 * Replaces the old client-side gate in auth.js, which shipped the password
 * to the browser in plain text and only hid the UI (the API endpoints and
 * admin pages were still reachable directly).
 *
 * The password hash comes from the ADMIN_PASSWORD_HASH environment variable,
 * set in the Coolify dashboard. FALLBACK_HASH below is the hash of the old
 * auth.js password and exists so that a missing/unreadable env var degrades
 * to "the previous behaviour" instead of locking the admin out entirely.
 * See docs/HANDOFF.md - removing the fallback is a deliberate follow-up step
 * once the env var is confirmed working in production.
 */
class Auth
{
    /** bcrypt hash of 'podcast2025' - the pre-existing auth.js password. */
    private const FALLBACK_HASH = '$2y$12$I8U8aa/4GgpkWxjCrAqQR.B5BnReTM4a4nirkhnI4/Gimgl2DIbqa';

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
     * The active password hash: environment first, fallback second.
     */
    public static function passwordHash(): string
    {
        $hash = getenv('ADMIN_PASSWORD_HASH');

        if ($hash === false || trim($hash) === '') {
            return self::FALLBACK_HASH;
        }

        return trim($hash);
    }

    /**
     * True when ADMIN_PASSWORD_HASH actually reached PHP.
     * PHP-FPM can be configured with clear_env=on, which strips container
     * environment variables before PHP sees them. Used by the status banner
     * on login.php so this can be verified in production without guessing.
     */
    public static function usingEnvHash(): bool
    {
        $hash = getenv('ADMIN_PASSWORD_HASH');

        return $hash !== false && trim($hash) !== '';
    }

    public static function attempt(string $password): bool
    {
        self::boot();

        if (!password_verify($password, self::passwordHash())) {
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
