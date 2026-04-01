<?php
/**
 * Registration Page
 */

require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Captcha.php';

if (Auth::check()) {
    header('Location: players.php');
    exit;
}

$csrfToken = Auth::generateCsrfToken();
$captcha = Captcha::generate();
$flash = Auth::getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Register</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php" class="back-btn">&larr;</a>
            <h1 class="app-title">Register</h1>
            <span></span>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <form id="registerForm" class="form-card">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" name="email" id="email" required autocomplete="email" placeholder="your@email.com">
                </div>

                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" name="name" id="name" required autocomplete="name" placeholder="Your name">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password" placeholder="Min. 8 characters">
                        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">
                            <span class="eye-icon">👁</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-field">
                        <input type="password" name="confirm_password" id="confirm_password" required minlength="8" autocomplete="new-password" placeholder="Repeat password">
                        <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Show password">
                            <span class="eye-icon">👁</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Preferred Hand</label>
                    <div class="btn-group">
                        <button type="button" class="btn-toggle" data-field="hand" data-value="L">Left</button>
                        <button type="button" class="btn-toggle active" data-field="hand" data-value="R">Right</button>
                    </div>
                    <input type="hidden" name="hand" id="hand" value="R">
                </div>

                <div class="form-group captcha-group">
                    <label for="captcha">Verify: <span id="captchaQuestion"><?= $captcha['question'] ?></span></label>
                    <div class="captcha-field">
                        <input type="number" name="captcha" id="captcha" required placeholder="Answer" inputmode="numeric">
                        <button type="button" class="captcha-refresh" id="refreshCaptcha" aria-label="New question">
                            <span>&#x21bb;</span>
                        </button>
                    </div>
                </div>

                <div id="formError" class="form-error hidden"></div>

                <button type="submit" class="btn-primary" id="submitBtn">Create Account</button>

                <p class="form-footer">
                    Already have an account? <a href="login.php">Login</a>
                </p>
            </form>
        </main>
    </div>

    <script src="js/auth.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');

        // Refresh captcha
        document.getElementById('refreshCaptcha').addEventListener('click', async function() {
            try {
                const res = await fetch('api/auth.php?action=captcha');
                const data = await res.json();
                if (data.success) {
                    document.getElementById('captchaQuestion').textContent = data.question;
                    document.getElementById('captcha').value = '';
                }
            } catch (e) {
                console.error('Failed to refresh captcha');
            }
        });

        // Form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideFormError('formError');
            setButtonLoading(submitBtn, true, 'Create Account');

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'verify.php?registered=1&token=' + data.token;
                } else {
                    throw new Error(data.error || 'Registration failed');
                }
            } catch (err) {
                showFormError('formError', err.message);
                setButtonLoading(submitBtn, false, 'Create Account');
                document.getElementById('refreshCaptcha').click();
            }
        });
    });
    </script>
</body>
</html>
