<?php

/**
 * Login Page - Placeholder for Future Authentication
 * Currently allows access without authentication
 */

require_once __DIR__ . '/config/auth_placeholder.php';
require_once __DIR__ . '/includes/functions.php';

$auth = new AuthPlaceholder();
$message = '';
$messageType = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $result = $auth->authenticate($username, $password);

        if ($result['success']) {
            $redirectUrl = $_GET['redirect'] ?? 'index.php';
            header('Location: ' . $redirectUrl);
            exit;
        } else {
            $message = $result['message'];
            $messageType = 'danger';
        }
    } else {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'danger';
    }
}

// If already authenticated, redirect to main page
if ($auth->isAuthenticated()) {
    $redirectUrl = $_GET['redirect'] ?? 'index.php';
    header('Location: ' . $redirectUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PodFeed Builder</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎧</text></svg>">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-md);
        }

        .login-card {
            max-width: 400px;
            width: 100%;
        }

        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .login-logo {
            font-size: 4rem;
            margin-bottom: var(--spacing-md);
            color: var(--accent-primary);
        }

        .demo-notice {
            background-color: rgba(31, 111, 235, 0.1);
            border: 1px solid rgba(31, 111, 235, 0.2);
            border-radius: var(--border-radius);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
            color: var(--accent-info);
            font-size: var(--font-size-sm);
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <div class="card">
                <div class="login-header">
                    <div class="login-logo"><i class="fa-solid fa-headphones"></i></div>
                    <h1>PodFeed Builder</h1>
                    <p class="text-secondary">Admin Login</p>
                </div>

                <!-- Demo Notice -->
                <div class="demo-notice">
                    <strong>Demo Mode:</strong> This is a placeholder login system.
                    In the current implementation, no authentication is required.
                    You can access the application directly at
                    <a href="index.php" class="text-link">index.php</a>.
                </div>

                <!-- Success/Error Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible">
                        <div class="alert-icon">
                            <?php echo $messageType === 'success' ? '✅' : '❌'; ?>
                        </div>
                        <div>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                        <button type="button" class="alert-close" onclick="this.parentElement.remove()">&times;</button>
                    </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" id="loginForm">
                    <?php echo csrfInput(); ?>

                    <div class="form-group">
                        <label for="username" class="form-label required">Username</label>
                        <input type="text" class="form-control" id="username" name="username"
                            placeholder="Enter username" required autocomplete="username">
                        <div class="form-text">Demo: Use "admin" as username</div>
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label required">Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                            placeholder="Enter password" required autocomplete="current-password">
                        <div class="form-text">Demo: Use "admin123" as password</div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                            🔐 Login
                        </button>
                    </div>
                </form>

                <!-- Links -->
                <div class="text-center mt-4">
                    <p class="text-muted">
                        <small>
                            <a href="index.php" class="text-link">← Back to Main Application</a>
                        </small>
                    </p>
                </div>

                <!-- Future Features -->
                <div class="card mt-4" style="background-color: var(--bg-tertiary);">
                    <div style="padding: var(--spacing-md);">
                        <h4 class="mb-2">Future Authentication Features</h4>
                        <ul class="text-sm text-muted" style="margin: 0; padding-left: var(--spacing-lg);">
                            <li>User registration and management</li>
                            <li>Role-based access control</li>
                            <li>Session timeout handling</li>
                            <li>Password reset functionality</li>
                            <li>Two-factor authentication</li>
                            <li>Login attempt rate limiting</li>
                            <li>Activity logging and audit trails</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Simple form validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password.');
                return false;
            }
        });

        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>

</html>