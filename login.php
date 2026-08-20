<?php

/**
 * Admin login.
 *
 * Password-only, matching the UX of the old auth.js modal it replaces.
 * Verification happens server-side against Auth::passwordHash().
 */

require_once __DIR__ . '/includes/Auth.php';

$message = '';

// Only allow relative redirect targets, so ?redirect= cannot be used to
// bounce a logged-in admin to an external site.
$redirect = $_GET['redirect'] ?? '/admin.php';
if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/' || str_starts_with($redirect, '//')) {
    $redirect = '/admin.php';
}

if (Auth::check()) {
    header('Location: ' . $redirect);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::attempt($_POST['password'] ?? '')) {
        header('Location: ' . $redirect);
        exit;
    }
    $message = 'Incorrect password.';
}

$usingEnv = Auth::usingEnvHash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PodFeed Builder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0d1117;
            color: #f0f6fc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.5);
        }
        .icon { text-align: center; font-size: 42px; color: #58a6ff; margin-bottom: 18px; }
        h1 { font-size: 22px; text-align: center; margin-bottom: 6px; }
        .sub { text-align: center; color: #8b949e; font-size: 14px; margin-bottom: 28px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #c9d1d9; }
        .field { position: relative; }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 12px 44px 12px 14px;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            color: #f0f6fc;
            font-size: 15px;
        }
        input:focus { outline: none; border-color: #58a6ff; }
        .toggle {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #8b949e; cursor: pointer; font-size: 15px;
        }
        button[type="submit"] {
            width: 100%; margin-top: 20px; padding: 12px;
            background: #238636; border: none; border-radius: 8px;
            color: #fff; font-size: 15px; font-weight: 600; cursor: pointer;
        }
        button[type="submit"]:hover { background: #2ea043; }
        .error {
            background: rgba(248, 81, 73, 0.1);
            border: 1px solid rgba(248, 81, 73, 0.4);
            color: #f85149;
            padding: 10px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 18px;
        }
        .envnote {
            margin-top: 24px; padding-top: 18px; border-top: 1px solid #30363d;
            font-size: 12px; color: #8b949e; text-align: center; line-height: 1.5;
        }
        .envnote .ok { color: #3fb950; }
        .envnote .warn { color: #d29922; }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon"><i class="fas fa-lock"></i></div>
        <h1>Admin Access</h1>
        <p class="sub">Enter the admin password to continue</p>

        <?php if ($message !== ''): ?>
            <div class="error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label for="password">Password</label>
            <div class="field">
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required autofocus>
                <button type="button" class="toggle" id="toggle" title="Show password">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <button type="submit">Sign In</button>
        </form>

        <div class="envnote">
            <?php if ($usingEnv): ?>
                <span class="ok"><i class="fas fa-circle-check"></i> Using ADMIN_PASSWORD_HASH from environment</span>
            <?php else: ?>
                <span class="warn"><i class="fas fa-triangle-exclamation"></i> ADMIN_PASSWORD_HASH not set &mdash; using built-in fallback</span>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('toggle').addEventListener('click', function () {
            const input = document.getElementById('password');
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            this.innerHTML = show ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
            this.title = show ? 'Hide password' : 'Show password';
        });
    </script>
</body>

</html>
