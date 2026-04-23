<?php
require_once __DIR__ . '/includes/Auth.php';
$isLoggedIn = Auth::check();
$playerName = Auth::name();

$recentSessions = [];
$liveMatches    = [];
$sinceText      = 'Start your first session';
$firstName      = '';
$initials       = '';

if ($isLoggedIn) {
    $db  = Database::getInstance();
    $pid = Auth::id();

    $parts    = explode(' ', trim($playerName));
    $firstName = $parts[0];
    $initials  = strtoupper(substr($firstName, 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

    $stmt = $db->prepare('
        SELECT s.id, s.session_date, s.hand,
            COUNT(r.id) AS total,
            COALESCE(SUM(
                CASE
                    WHEN r.result = 8         THEN 10
                    WHEN r.result IN (3,4)    THEN 7
                    WHEN r.result IN (7,12)   THEN 5
                    WHEN r.result IN (5,6)    THEN 3
                    WHEN r.result IN (1,2)    THEN 2
                    ELSE 0
                END + COALESCE(r.toucher,0) * 5
            ), 0) AS total_score
        FROM sessions s
        LEFT JOIN rolls r ON r.session_id = s.id
        WHERE s.player_id = :pid
        GROUP BY s.id
        HAVING total > 0
        ORDER BY s.session_date DESC, s.id DESC
        LIMIT 3
    ');
    $stmt->execute(['pid' => $pid]);
    $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recentSessions)) {
        $lastDate = $recentSessions[0]['session_date'];
        $today    = date('Y-m-d');
        if ($lastDate === $today) {
            $sinceText = 'You played today';
        } else {
            $days = (int) ((strtotime($today) - strtotime($lastDate)) / 86400);
            if ($days === 1)      $sinceText = 'Last session: yesterday';
            elseif ($days <= 7)   $sinceText = "Last session: {$days} days ago";
            else                  $sinceText = 'Last session: ' . date('j M', strtotime($lastDate));
        }
    }

    $stmt = $db->prepare('
        SELECT m.id, t1.team_name AS team1, t2.team_name AS team2
        FROM matches m
        JOIN match_teams t1 ON t1.match_id = m.id AND t1.team_number = 1
        JOIN match_teams t2 ON t2.match_id = m.id AND t2.team_number = 2
        JOIN club_members cm ON cm.club_id = m.club_id AND cm.player_id = :pid
        WHERE m.status = "live"
        LIMIT 3
    ');
    $stmt->execute(['pid' => $pid]);
    $liveMatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$hour         = (int) date('H');
$timeGreeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
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
    <link rel="stylesheet" href="css/styles.css?v=4">
    <?php if (!$isLoggedIn): ?>
    <link rel="stylesheet" href="css/pages/landing.css?v=2">
    <?php endif; ?>
    <style>
    /* ── Send Feedback button (footer) ── */
    .feedback-trigger-link {
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 2rem;
        padding: 0.55rem 1.4rem;
        font: inherit;
        font-size: 0.85rem;
        font-weight: 700;
        cursor: pointer;
        letter-spacing: 0.01em;
        transition: background 0.2s;
        margin-top: 0.25rem;
        display: inline-block;
    }
    .feedback-trigger-link:hover { background: #1e3a0e; }

    /* ── Modal overlay ── */
    .feedback-modal {
        position: fixed;
        top: 0; right: 0; bottom: 0; left: 0;
        background: rgba(0,0,0,0.6);
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .feedback-modal[hidden] { display: none !important; }

    .feedback-modal-box {
        background: #fff;
        border-radius: 1rem;
        padding: 1.75rem 1.5rem 1.5rem;
        width: 100%;
        max-width: 440px;
        position: relative;
        box-shadow: 0 24px 64px rgba(0,0,0,0.35);
    }

    .feedback-modal-close {
        position: absolute;
        top: 0.9rem;
        right: 0.9rem;
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: #f0f0f0;
        border: none;
        font-size: 1.1rem;
        line-height: 1;
        color: #555;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .feedback-modal-close:hover { background: #e0e0e0; color: #111; }

    .feedback-modal-box h2 {
        font-size: 1.25rem;
        font-weight: 800;
        color: #2d5016;
        margin: 0 0 0.25rem;
        padding-right: 2rem;
    }
    .feedback-modal-sub {
        font-size: 0.83rem;
        color: #666;
        margin: 0 0 1.25rem;
        line-height: 1.5;
    }

    .feedback-field { margin-bottom: 0.9rem; }
    .feedback-field label {
        display: block;
        font-size: 0.8rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 0.3rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .feedback-optional {
        font-weight: 400;
        color: #888;
        text-transform: none;
        letter-spacing: 0;
        font-size: 0.78rem;
    }
    .feedback-field input,
    .feedback-field textarea {
        width: 100%;
        padding: 0.65rem 0.85rem;
        border: 1.5px solid #cdd8c5;
        border-radius: 0.5rem;
        font-family: inherit;
        font-size: 0.9rem;
        color: #222;
        background: #f9fbf7;
        box-sizing: border-box;
        transition: border-color 0.15s;
    }
    .feedback-field input:focus,
    .feedback-field textarea:focus {
        outline: none;
        border-color: #2d5016;
        background: #fff;
    }
    .feedback-field textarea { resize: vertical; min-height: 100px; }

    .feedback-status {
        padding: 0.6rem 0.85rem;
        border-radius: 0.5rem;
        font-size: 0.85rem;
        margin-bottom: 0.9rem;
        font-weight: 600;
    }
    .feedback-status--ok  { background: #e8f5e9; color: #2e7d32; }
    .feedback-status--err { background: #fdecea; color: #c62828; }

    .feedback-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
        margin-top: 0.25rem;
    }
    .feedback-submit {
        background: #2d5016;
        color: #fff;
        border: none;
        border-radius: 2rem;
        padding: 0.7rem 1.75rem;
        font-family: inherit;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: background 0.2s;
        flex-shrink: 0;
    }
    .feedback-submit:hover:not(:disabled) { background: #1e3a0e; }
    .feedback-submit:disabled { opacity: 0.55; cursor: not-allowed; }

    .feedback-wa {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        color: #128c7e;
        font-size: 0.85rem;
        font-weight: 700;
        text-decoration: none;
    }
    .feedback-wa:hover { text-decoration: underline; }

    /* ── Floating WhatsApp bubble ── */
    .wa-bubble {
        position: fixed;
        bottom: 1.25rem;
        right: 1.25rem;
        z-index: 9998;
        width: 56px;
        height: 56px;
        background: #25d366;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 16px rgba(0,0,0,0.3);
        color: white;
        transition: transform 0.2s, box-shadow 0.2s;
        text-decoration: none;
    }
    .wa-bubble:hover { transform: scale(1.1); box-shadow: 0 6px 24px rgba(0,0,0,0.35); }

    /* ── Facebook community banner ── */
    .fb-join-banner {
        display: flex;
        align-items: center;
        gap: 0.9rem;
        background: #1877f2;
        color: white;
        text-decoration: none;
        border-radius: 0.85rem;
        padding: 0.9rem 1.1rem;
        margin: 0 0 1rem;
        transition: background 0.2s, transform 0.15s;
    }
    .fb-join-banner:hover { background: #1460cc; transform: translateY(-1px); }
    .fb-join-banner svg { flex-shrink: 0; }
    .fb-join-banner-text { flex: 1; }
    .fb-join-banner-text strong { display: block; font-size: 0.95rem; font-weight: 800; }
    .fb-join-banner-text span { font-size: 0.8rem; opacity: 0.88; }
    .fb-join-banner .form-chevron { width: 16px; height: 16px; flex-shrink: 0; opacity: 0.75; }

    /* ── Landing FB highlight ── */
    .community-btn {
        background: #1877f2;
        color: white;
        border: none;
        border-radius: 2rem;
        padding: 0.7rem 1.4rem;
        font: inherit;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: background 0.2s;
    }
    .community-btn:hover { background: #1460cc; }
    </style>
</head>
<body>

<?php if ($isLoggedIn): ?>
<!-- ═══════════════════════════════════════════════════
     LOGGED-IN: personalised dashboard
════════════════════════════════════════════════════ -->
<div class="app-container home-dash">

    <header class="home-header">
        <div class="home-brand">
            <img src="assets/logo-192.png" alt="BowlsTracker" class="home-logo">
            <div>
                <span class="home-greeting-name"><?= htmlspecialchars($timeGreeting) ?>, <?= htmlspecialchars($firstName) ?></span>
                <span class="home-greeting-sub"><?= htmlspecialchars($sinceText) ?></span>
            </div>
        </div>
        <div class="home-header-end">
            <a href="players.php" class="home-avatar" title="Profile"><?= htmlspecialchars($initials) ?></a>
            <button class="home-logout-btn" id="logoutBtn" title="Log out" aria-label="Log out">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                </svg>
            </button>
        </div>
    </header>

    <main class="home-main">

        <a href="game.php" class="record-cta">
            <img src="assets/bowl-icon.svg" alt="" style="width:20px;height:20px;filter:brightness(0) invert(1)">
            Record a Session
        </a>

        <?php if ($recentSessions): ?>
        <section class="home-section">
            <h2 class="home-section-title">Recent form</h2>
            <div class="form-list">
            <?php foreach ($recentSessions as $s):
                $avg = $s['total'] > 0 ? $s['total_score'] / $s['total'] : 0;
                $pct = min(100, (int) round($avg / 10 * 100));
                $dateStr = date('D j M', strtotime($s['session_date']));
                $handClass = strtolower($s['hand']) === 'l' ? 'form-hand-l' : 'form-hand-r';
                $handLabel = $s['hand'] === 'L' ? 'LH' : 'RH';
            ?>
            <a href="stats.php?id=<?= $s['id'] ?>" class="form-row">
                <div class="form-row-meta">
                    <span class="form-date"><?= $dateStr ?></span>
                    <span class="form-hand-badge <?= $handClass ?>"><?= $handLabel ?></span>
                </div>
                <div class="form-bar-wrap">
                    <div class="form-bar" style="--pct:<?= $pct ?>%"></div>
                </div>
                <span class="form-score"><?= $pct ?>%</span>
                <svg class="form-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
            </a>
            <?php endforeach; ?>
            </div>
            <a href="stats.php?all=1" class="home-see-all">All stats &amp; awards &rarr;</a>
        </section>
        <?php endif; ?>

        <?php if ($liveMatches): ?>
        <section class="home-section">
            <h2 class="home-section-title">Live at your club</h2>
            <?php foreach ($liveMatches as $m): ?>
            <a href="matches/view.php?id=<?= $m['id'] ?>" class="live-match-row">
                <span class="live-dot"></span>
                <span class="live-match-teams"><?= htmlspecialchars($m['team1']) ?> vs <?= htmlspecialchars($m['team2']) ?></span>
                <svg class="form-chevron" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
            </a>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <nav class="home-quick" aria-label="More features">
            <a href="history.php" class="quick-link">
                <span class="quick-icon">📜</span>
                <span>History</span>
            </a>
            <a href="challenges/index.php" class="quick-link">
                <span class="quick-icon">🎯</span>
                <span>Challenges</span>
            </a>
            <a href="matches/live.php" class="quick-link">
                <span class="quick-icon">🏆</span>
                <span>Bounce Game</span>
            </a>
            <a href="clubs/index.php" class="quick-link">
                <span class="quick-icon">🏛️</span>
                <span>Clubs</span>
            </a>
            <a href="players.php" class="quick-link">
                <span class="quick-icon">👤</span>
                <span>Profile</span>
            </a>
        </nav>

        <a href="https://www.facebook.com/groups/1945948999340245" target="_blank" rel="noopener" class="fb-join-banner">
            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="white" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
            <div class="fb-join-banner-text">
                <strong>The Green Community</strong>
                <span>Join our Facebook group — tips, updates &amp; fellow bowlers</span>
            </div>
            <svg class="form-chevron" viewBox="0 0 16 16" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
        </a>

    </main>

    <footer class="app-footer">
        <p>Powered by <a href="https://yellowarcher.co.za" target="_blank"><strong>Yellow Archer</strong></a></p>
        <p><button class="feedback-trigger-link" onclick="openFeedback()">Send Feedback</button></p>
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
                <p>BowlsTracker is growing — your feedback shapes what gets built next. Suggest features, report issues, or just say hello.</p>
                <div class="community-actions">
                    <a href="https://www.facebook.com/groups/1945948999340245" target="_blank" rel="noopener" class="community-btn" style="font-size:1rem;padding:0.8rem 1.6rem">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.513c-1.491 0-1.956.93-1.956 1.886v2.268h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>
                        Join The Green Community
                    </a>
                    <button onclick="openFeedback()" class="contact-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        Send Feedback
                    </button>
                    <a href="https://wa.me/27833800689" target="_blank" rel="noopener" class="whatsapp-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp
                    </a>
                </div>
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

<!-- Feedback Modal -->
<div id="feedbackModal" class="feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedbackTitle" hidden>
    <div class="feedback-modal-box">
        <button class="feedback-modal-close" onclick="closeFeedback()" aria-label="Close">&times;</button>
        <h2 id="feedbackTitle">Send Feedback</h2>
        <p class="feedback-modal-sub">Suggestions, bugs, or just a hello — I read every message.</p>
        <form id="feedbackForm">
            <div class="feedback-field">
                <label for="fb-name">Your name</label>
                <input type="text" id="fb-name" name="name" placeholder="e.g. Andrew Els" required maxlength="100">
            </div>
            <div class="feedback-field">
                <label for="fb-email">Email <span class="feedback-optional">(optional — for a reply)</span></label>
                <input type="email" id="fb-email" name="email" placeholder="you@example.com" maxlength="200">
            </div>
            <div class="feedback-field">
                <label for="fb-message">Message</label>
                <textarea id="fb-message" name="message" rows="5" placeholder="What's on your mind?" required maxlength="2000"></textarea>
            </div>
            <div id="feedbackStatus" class="feedback-status" hidden></div>
            <div class="feedback-actions">
                <button type="submit" id="feedbackSubmit" class="feedback-submit">Send Message</button>
                <a href="https://wa.me/27833800689" target="_blank" rel="noopener" class="feedback-wa">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp instead
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Floating WhatsApp bubble -->
<a href="https://wa.me/27833800689" target="_blank" rel="noopener" class="wa-bubble" aria-label="Chat on WhatsApp">
    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
</a>

<script src="js/app.js"></script>
<script>
function openFeedback() {
    const modal = document.getElementById('feedbackModal');
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    modal.querySelector('input, textarea').focus();
}
function closeFeedback() {
    document.getElementById('feedbackModal').hidden = true;
    document.body.style.overflow = '';
    document.getElementById('feedbackStatus').hidden = true;
    document.getElementById('feedbackForm').reset();
}
document.getElementById('feedbackModal').addEventListener('click', function(e) {
    if (e.target === this) closeFeedback();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFeedback();
});
document.getElementById('feedbackForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('feedbackSubmit');
    const status = document.getElementById('feedbackStatus');
    btn.disabled = true;
    btn.textContent = 'Sending…';
    status.hidden = true;
    try {
        const res = await fetch('api/feedback.php', { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        status.hidden = false;
        if (data.success) {
            status.className = 'feedback-status feedback-status--ok';
            status.textContent = 'Message sent! Thanks for the feedback.';
            this.reset();
            setTimeout(closeFeedback, 2000);
        } else {
            status.className = 'feedback-status feedback-status--err';
            status.textContent = data.error || 'Something went wrong.';
            btn.disabled = false;
            btn.textContent = 'Send Message';
        }
    } catch {
        status.hidden = false;
        status.className = 'feedback-status feedback-status--err';
        status.textContent = 'Network error. Try WhatsApp instead.';
        btn.disabled = false;
        btn.textContent = 'Send Message';
    }
});
<?php if ($isLoggedIn): ?>
document.getElementById('logoutBtn').addEventListener('click', async function(e) {
    e.preventDefault();
    const form = new FormData();
    form.append('action', 'logout');
    await fetch('api/auth.php', { method: 'POST', body: form });
    window.location.reload();
});
<?php endif; ?>
</script>
</body>
</html>
