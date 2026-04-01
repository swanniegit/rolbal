<?php
/**
 * Login Page
 */

require_once __DIR__ . '/includes/Auth.php';

if (Auth::check()) {
    header('Location: players.php');
    exit;
}

$csrfToken = Auth::generateCsrfToken();
$flash = Auth::getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Login</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php" class="back-btn">&larr;</a>
            <h1 class="app-title">Login</h1>
            <span></span>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <form id="loginForm" class="form-card">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required autocomplete="email" placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" name="password" id="password" required autocomplete="current-password" placeholder="Your password">
                        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                            <span class="eye-icon">👁</span>
                        </button>
                    </div>
                </div>

                <div id="formError" class="form-error hidden"></div>

                <button type="submit" class="btn-primary" id="submitBtn">Login</button>

                <p class="form-footer">
                    Don't have an account? <a href="register.php">Register</a>
                </p>
            </form>
        </main>
    </div>

    <script src="js/auth.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideFormError('formError');
            setButtonLoading(submitBtn, true, 'Login');

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'players.php';
                } else {
                    throw new Error(data.error || 'Login failed');
                }
            } catch (err) {
                showFormError('formError', err.message);
                setButtonLoading(submitBtn, false, 'Login');
            }
        });
    });
    </script>
</body>
</html>
