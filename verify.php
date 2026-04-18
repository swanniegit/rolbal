<?php
/**
 * Email Verification Landing Page
 */

require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Player.php';
require_once __DIR__ . '/includes/Template.php';

$token = $_GET['token'] ?? '';
$justRegistered = isset($_GET['registered']);
$verified = false;
$error = null;

if ($token && !$justRegistered) {
    if (Player::verify($token)) {
        $verified = true;
    } else {
        $error = 'Invalid or expired verification link.';
    }
}

Template::pageHead('Verify Email');
?>
<body>
    <div class="app-container">
        <?php Template::header('Email Verification', 'index.php'); ?>

        <main class="main-content">
            <?php if ($justRegistered): ?>
            <div class="verify-card">
                <div class="verify-icon success">✓</div>
                <h2>Registration Successful!</h2>
                <p>We've sent a verification link to your email address.</p>
                <p class="verify-note">Click the link in the email to activate your account, then log in. Check your spam folder if you don't see it.</p>
                <a href="login.php" class="btn-secondary">Go to Login</a>
            </div>

            <?php elseif ($verified): ?>
            <div class="verify-card">
                <div class="verify-icon success">✓</div>
                <h2>Email Verified!</h2>
                <p>Your email has been verified successfully. You can now log in to your account.</p>
                <a href="login.php" class="btn-primary">Login Now</a>
            </div>

            <?php elseif ($error): ?>
            <div class="verify-card">
                <div class="verify-icon error">!</div>
                <h2>Verification Failed</h2>
                <p><?= htmlspecialchars($error) ?></p>
                <p class="verify-note">The link may have expired or already been used.</p>
                <a href="register.php" class="btn-secondary">Register Again</a>
            </div>

            <?php else: ?>
            <div class="verify-card">
                <div class="verify-icon">?</div>
                <h2>No Token Provided</h2>
                <p>Please use the verification link from your email.</p>
                <a href="login.php" class="btn-secondary">Go to Login</a>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
