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
 * genuinely set in the dashboard could read as "not set", silently leaving the
 * built-in fallback password live on a site the operator believed they had
 * just changed the password on.
 *
 * These cover the class - every place the runtime can put the value, plus the
 * blank and whitespace-padded cases - not just the one path that broke.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/Auth.php';

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

const FALLBACK = 'podcast2025';
const NEW_PASSWORD = 'Podcast2026';

echo "Unset everywhere -> built-in fallback\n";
resetEnv();
ok('fallback is the active password', Auth::password() === FALLBACK);
ok('login banner reports "not set"', Auth::usingEnvPassword() === false);

echo "Set via getenv() (plain container env)\n";
resetEnv();
putenv('ADMIN_PASSWORD=' . NEW_PASSWORD);
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('fallback is no longer accepted', Auth::password() !== FALLBACK);
ok('login banner reports "from environment"', Auth::usingEnvPassword() === true);

echo "Set via \$_SERVER only (PHP-FPM env[]/fastcgi_param - the regression)\n";
resetEnv();
$_SERVER['ADMIN_PASSWORD'] = NEW_PASSWORD;
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('login banner reports "from environment"', Auth::usingEnvPassword() === true);

echo "Set via \$_ENV only\n";
resetEnv();
$_ENV['ADMIN_PASSWORD'] = NEW_PASSWORD;
ok('env password is active', Auth::password() === NEW_PASSWORD);
ok('login banner reports "from environment"', Auth::usingEnvPassword() === true);

echo "Present but blank -> must not count as configured\n";
resetEnv();
putenv('ADMIN_PASSWORD=');
$_SERVER['ADMIN_PASSWORD'] = '   ';
ok('blank/whitespace treated as unset', Auth::usingEnvPassword() === false);
ok('fallback keeps the admin out of a lockout', Auth::password() === FALLBACK);

echo "Padded with whitespace/newline (paste artifact)\n";
resetEnv();
$_SERVER['ADMIN_PASSWORD'] = "  " . NEW_PASSWORD . " \n";
ok('password is trimmed', Auth::password() === NEW_PASSWORD);

echo "Passwords containing characters Coolify/shells like to mangle\n";
foreach (['P@ss w0rd!', 'a$b$c', 'quote"and\'quote', 'Podcast2026#$%'] as $candidate) {
    resetEnv();
    $_SERVER['ADMIN_PASSWORD'] = $candidate;
    ok("round-trips intact: $candidate", Auth::password() === $candidate);
}

resetEnv();
echo $failures === 0 ? "\nALL PASS\n" : "\n$failures FAILURE(S)\n";
exit($failures === 0 ? 0 : 1);
