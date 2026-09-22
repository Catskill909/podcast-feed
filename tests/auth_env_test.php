<?php

/**
 * Auth env-var resolution tests.
 *
 * Run: php tests/auth_env_test.php
 *
 * The admin password is the plain-text ADMIN_PASSWORD environment variable,
 * set in the Coolify dashboard. The failure mode these guard against: the
 * value was read with getenv() only, but under PHP-FPM a value passed to the
 * pool as env[...] or as a FastCGI param lands in $_SERVER and reaches
 * getenv()/$_ENV only when variables_order contains "E". So a variable
 * genuinely set in the dashboard could read as "not set" - which, back when
 * there was a hardcoded fallback password, silently put a publicly known
 * password live on a site the operator believed they had just secured.
 *
 * Auth now fails closed instead, so the second half of these tests asserts
 * that an unset variable makes login impossible rather than easy.
 *
 * These cover the class - every place the runtime can put the value, plus the
 * blank and whitespace-padded cases - not just the one path that broke.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/Auth.php';

// Auth::attempt() starts a session, and PHP cannot start one once output has
// been sent. In the real app that never happens - login.php calls attempt()
// before rendering. Here the assertions print as they go, so start the session
// up front and let Auth::boot() see it is already active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Clear every place the password could be read from. */
function resetEnv(): void
{
    putenv('ADMIN_PASSWORD');
    unset($_SERVER['ADMIN_PASSWORD'], $_ENV['ADMIN_PASSWORD']);
}

$failures = 0;
function ok(string $label, bool $cond): void
{
    global $failures;
    if ($cond) {
        echo "  pass  $label\n";
        return;
    }
    $failures++;
    echo "  FAIL  $label\n";
}

const OLD_FALLBACK = 'podcast2025';
const NEW_PASSWORD = 'Podcast2026';

echo "Unset everywhere -> fails closed, no login possible\n";
resetEnv();
ok('no password is configured', Auth::password() === null);
ok('login page reports not configured', Auth::isConfigured() === false);
ok('the removed fallback is NOT accepted', Auth::attempt(OLD_FALLBACK) === false);
ok('empty submission is not accepted', Auth::attempt('') === false);
ok('arbitrary submission is not accepted', Auth::attempt('anything') === false);

echo "Set via getenv() (plain container env)\n";
resetEnv();
putenv('ADMIN_PASSWORD=' . NEW_PASSWORD);
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('old fallback is not accepted', Auth::password() !== OLD_FALLBACK);
ok('login page reports configured', Auth::isConfigured() === true);

echo "Set via \$_SERVER only (PHP-FPM env[]/fastcgi_param - the regression)\n";
resetEnv();
$_SERVER['ADMIN_PASSWORD'] = NEW_PASSWORD;
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('login page reports configured', Auth::isConfigured() === true);

echo "Set via \$_ENV only\n";
resetEnv();
$_ENV['ADMIN_PASSWORD'] = NEW_PASSWORD;
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('login page reports configured', Auth::isConfigured() === true);

echo "Present but blank -> must not count as configured\n";
resetEnv();
putenv('ADMIN_PASSWORD=');
$_SERVER['ADMIN_PASSWORD'] = '   ';
ok('blank/whitespace treated as unset', Auth::isConfigured() === false);
ok('blank does not become a usable password', Auth::password() === null);
ok('submitting the blank value is not accepted', Auth::attempt('   ') === false);
ok('submitting empty is not accepted', Auth::attempt('') === false);

echo "Padded with whitespace/newline (paste artifact)\n";
resetEnv();
$_SERVER['ADMIN_PASSWORD'] = "  " . NEW_PASSWORD . " \n";
ok('password is trimmed', Auth::password() === NEW_PASSWORD);

echo "No hardcoded password survives anywhere in the class\n";
$source = file_get_contents(__DIR__ . '/../includes/Auth.php');
ok('Auth.php contains no FALLBACK_PASSWORD constant', !str_contains($source, 'FALLBACK_PASSWORD'));
ok('Auth.php does not contain the old password as a literal', !str_contains($source, "'" . OLD_FALLBACK . "'"));

echo "Passwords containing characters Coolify/shells like to mangle\n";
foreach (['P@ss w0rd!', 'a$b$c', 'quote"and\'quote', 'Podcast2026#$%'] as $candidate) {
    resetEnv();
    $_SERVER['ADMIN_PASSWORD'] = $candidate;
    ok("round-trips intact: $candidate", Auth::password() === $candidate);
}

resetEnv();
echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
