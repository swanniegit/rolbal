<?php
require_once __DIR__ . '/includes/Auth.php';
$isLoggedIn = Auth::check();
$playerName = Auth::name();
?>
<!DOCTYPE html>
<html lang="en"<?= $isLoggedIn ? '' : ' class="landing-page"' ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="description" content="BowlsTracker — Track your lawn bowls practice, analyse your game, and manage your club.">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BowlsTracker">
    <title>BowlsTracker</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
    <link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
    <link rel="stylesheet" href="css/styles.css?v=2">
    <?php if (!$isLoggedIn): ?>
    <link rel="stylesheet" href="css/pages/landing.css?v=2">
    <?php endif; ?>
</head>
<body>

<?php if ($isLoggedIn): ?>
<!-- ═══════════════════════════════════════════════════
     LOGGED-IN: clean app home
════════════════════════════════════════════════════ -->
<div class="app-container">
    <header class="app-header">
        <div class="login-status">
            <a href="players.php" class="player-link"><?= htmlspecialchars($playerName) ?></a>
            <a href="#" class="logout-link" id="logoutBtn">Logout</a>
        </div>
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

<?php else: ?>
<!-- ═══════════════════════════════════════════════════
     GUEST: promotional landing page
════════════════════════════════════════════════════ -->
<div class="app-container">

    <header class="landing-header">
        <div class="landing-header-brand">
            <img src="assets/logo-192.png" alt="BowlsTracker" class="logo">
            <h1>BowlsTracker</h1>
        </div>
        <nav class="landing-header-nav">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-register">Get Started Free</a>
        </nav>
    </header>

    <section class="landing-hero">
        <h2>Track. Improve. <span>Compete.</span></h2>
        <p>The complete lawn bowls companion — record every bowl, analyse your game, and manage your club all in one place.</p>
        <a href="register.php" class="hero-cta">Start for Free &rarr;</a>
    </section>

    <section class="promo-split">

        <div class="promo-panel promo-panel--bowler">
            <div class="promo-panel-icon"><img src="assets/bowl-icon.svg" alt="Bowl" style="width:2.5rem;height:2.5rem;"></div>
            <h3>You the Bowler</h3>
            <p class="promo-panel-sub">Everything you need to understand your game and sharpen every delivery.</p>
            <ul class="promo-features">
                <li>
                    <div class="promo-feature-icon">🎯</div>
                    <div class="promo-feature-text">
                        <strong>Practice Sessions</strong>
                        <span>Record every bowl on a visual position grid — forehand, backhand, short, middle, long.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">📐</div>
                    <div class="promo-feature-text">
                        <strong>Performance Statistics</strong>
                        <span>See your accuracy trends by delivery type, end length, and hand over time.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">📜</div>
                    <div class="promo-feature-text">
                        <strong>Session History</strong>
                        <span>Browse and replay every past session — spot patterns and track improvement.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">🏆</div>
                    <div class="promo-feature-text">
                        <strong>Skill Challenges</strong>
                        <span>Structured practice routines with a scoring system to benchmark your skill level.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">📱</div>
                    <div class="promo-feature-text">
                        <strong>Works Offline</strong>
                        <span>Install as a PWA or Android app — record sessions even without a signal.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">🆓</div>
                    <div class="promo-feature-text">
                        <strong>Free to Use</strong>
                        <span>Use BowlsTracker completely free until July 2026.</span>
                    </div>
                </li>
            </ul>
        </div>

        <div class="promo-panel promo-panel--club">
            <div class="promo-panel-icon">🏛️</div>
            <h3>Your Club</h3>
            <p class="promo-panel-sub">Tools for clubs to run matches, competitions, and keep members connected.</p>
            <ul class="promo-features">
                <li>
                    <div class="promo-feature-icon">📡</div>
                    <div class="promo-feature-text">
                        <strong>Live Match Scoring</strong>
                        <span>Score Singles, Pairs, Trips, and Fours in real time — auto-refreshing scoreboards for spectators.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">👥</div>
                    <div class="promo-feature-text">
                        <strong>Club Membership</strong>
                        <span>Create your club, invite members, and manage roles — owner, admin, and member.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">🥇</div>
                    <div class="promo-feature-text">
                        <strong>Competitions</strong>
                        <span>Run Round Robin, Knockout, or Combined tournaments with automated fixtures and standings.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">📋</div>
                    <div class="promo-feature-text">
                        <strong>Fixture Management</strong>
                        <span>Schedule matches, track results, and maintain a full competition record for every season.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">💬</div>
                    <div class="promo-feature-text">
                        <strong>WhatsApp Integration</strong>
                        <span>Fixture notifications and score updates delivered directly to your members via WhatsApp.</span>
                    </div>
                </li>
                <li>
                    <div class="promo-feature-icon">🔒</div>
                    <div class="promo-feature-text">
                        <strong>Private &amp; Secure</strong>
                        <span>Club data is visible only to members. Admins control access and scoring permissions.</span>
                    </div>
                </li>
            </ul>
        </div>

    </section>

    <section class="community-section">
        <div class="community-inner">
            <div class="community-text">
                <div class="community-icon">💬</div>
                <h3>Share your thoughts</h3>
                <p>BowlsTracker is growing — your feedback shapes what gets built next. Suggest features, report issues, or just say hello in the community group.</p>
                <a href="https://www.facebook.com/groups/1945948999340245" target="_blank" rel="noopener" class="community-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                    Join the Facebook Group
                </a>
            </div>
        </div>
    </section>

    <section class="install-section">
        <div class="install-section-header">
            <h3>Install on your device</h3>
            <p>BowlsTracker works as a full app — no app store needed. Install it in seconds for the best experience.</p>
        </div>
        <div class="install-cards">
            <div class="install-card">
                <div class="install-card-platform">
                    <div class="install-card-platform-icon">🤖</div>
                    <div><h4>Android</h4><span>Chrome browser</span></div>
                </div>
                <ol class="install-steps">
                    <li>Open <strong>bowlstracker.co.za</strong> in Chrome</li>
                    <li>Tap the <kbd>⋮</kbd> menu in the top-right corner</li>
                    <li>Tap <kbd>Add to Home screen</kbd></li>
                    <li>Tap <kbd>Add</kbd> — the app icon appears on your home screen</li>
                </ol>
            </div>
            <div class="install-card">
                <div class="install-card-platform">
                    <div class="install-card-platform-icon">🍎</div>
                    <div><h4>iPhone &amp; iPad</h4><span>Safari browser</span></div>
                </div>
                <ol class="install-steps">
                    <li>Open <strong>bowlstracker.co.za</strong> in Safari</li>
                    <li>Tap the <kbd>Share</kbd> button at the bottom of the screen</li>
                    <li>Scroll down and tap <kbd>Add to Home Screen</kbd></li>
                    <li>Tap <kbd>Add</kbd> — the app icon appears on your home screen</li>
                </ol>
            </div>
            <div class="install-card">
                <div class="install-card-platform">
                    <div class="install-card-platform-icon">💻</div>
                    <div><h4>Desktop</h4><span>Chrome or Edge</span></div>
                </div>
                <ol class="install-steps">
                    <li>Open <strong>bowlstracker.co.za</strong> in Chrome or Edge</li>
                    <li>Look for the <kbd>⊕</kbd> install icon in the address bar</li>
                    <li>Click it and choose <kbd>Install</kbd></li>
                    <li>BowlsTracker opens as a standalone window — pin it to your taskbar</li>
                </ol>
            </div>
        </div>
    </section>

    <footer class="landing-footer">
        Powered by <a href="https://yellowarcher.co.za" target="_blank">Yellow Archer</a>
    </footer>

</div>
<?php endif; ?>

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
