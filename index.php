<?php
require_once __DIR__ . '/includes/Auth.php';
$isLoggedIn = Auth::check();
$playerName = Auth::name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="description" content="BowlsTracker - Lawn Bowls Statistics Tracker">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BowlsTracker">

    <title>BowlsTracker - Lawn Bowls Stats</title>

    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header">
            <?php if ($isLoggedIn): ?>
            <div class="login-status">
                <a href="players.php" class="player-link"><?= htmlspecialchars($playerName) ?></a>
                <a href="#" class="logout-link" id="logoutBtn">Logout</a>
            </div>
            <?php else: ?>
            <div class="login-status">
                <a href="login.php" class="login-link">Login</a>
            </div>
            <?php endif; ?>
            <div class="logo-container">
                <img src="assets/logo-192.png" alt="BowlsTracker" class="logo">
            </div>
            <h1 class="app-title">BowlsTracker</h1>
            <p class="app-tagline">Lawn Bowls Statistics</p>
        </header>

        <main class="main-content">
            <nav class="main-nav">
                <a href="game.php" class="nav-card">
                    <img src="assets/bowl-icon.svg" alt="" class="nav-icon-img">
                    <span class="nav-label">New Game</span>
                </a>
                <?php if ($isLoggedIn): ?>
                <a href="stats.php" class="nav-card">
                    <span class="nav-icon">📊</span>
                    <span class="nav-label">Statistics</span>
                </a>
                <a href="history.php" class="nav-card">
                    <span class="nav-icon">📜</span>
                    <span class="nav-label">History</span>
                </a>
                <a href="players.php" class="nav-card">
                    <span class="nav-icon">👥</span>
                    <span class="nav-label">Players</span>
                </a>
                <?php endif; ?>
                <a href="challenges/index.php" class="nav-card">
                    <span class="nav-icon">🎯</span>
                    <span class="nav-label">Challenges</span>
                </a>
                <a href="clubs/index.php" class="nav-card">
                    <span class="nav-icon">🏛️</span>
                    <span class="nav-label">Clubs</span>
                </a>
            </nav>
        </main>

        <footer class="app-footer">
            <p>Powered by <a href="https://yellowarcher.co.za" target="_blank"><strong>Yellow Archer</strong></a></p>
        </footer>
    </div>

    <script src="js/app.js"></script>
    <?php if ($isLoggedIn): ?>
    <script>
    document.getElementById('logoutBtn').addEventListener('click', async function(e) {
        e.preventDefault();
        const form = new FormData();
        form.append('action', 'logout');
        await fetch('api/auth.php', { method: 'POST', body: form });
        window.location.reload();
    });
    </script>
    <?php endif; ?>
</body>
</html>
