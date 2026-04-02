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
                <p>Your account has been created.</p>

                <?php if ($token): ?>
                <p class="verify-note">For demo purposes, click below to verify your account immediately:</p>
                <a href="verify.php?token=<?= htmlspecialchars($token) ?>" class="btn-primary">Verify Email</a>
                <?php else: ?>
                <p class="verify-note">Please check your email to verify your account before logging in.</p>
                <a href="login.php" class="btn-secondary">Go to Login</a>
                <?php endif; ?>
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
