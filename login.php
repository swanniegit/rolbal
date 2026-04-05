<?php
/**
 * Login Page
 */

require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$csrfToken = Auth::generateCsrfToken();
$flash = Auth::getFlash();

Template::pageHead('Login');
?>
<body>
    <div class="app-container">
        <?php Template::header('Login', 'index.php'); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

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

                <?php Template::formError(); ?>

                <button type="submit" class="btn-primary" id="submitBtn">Login</button>

                <p class="form-footer">
                    Don't have an account? <a href="register.php">Register</a>
                </p>
            </form>
        </main>
    </div>

    <script src="js/api.js"></script>
    <script src="js/ui.js"></script>
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
                    window.location.href = 'index.php';
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
