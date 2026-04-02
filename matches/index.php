<?php
/**
 * Match List Page - Shows live and recent matches for a club
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$clubId = isset($_GET['club']) ? (int)$_GET['club'] : 0;
if (!$clubId) {
    Auth::flash('error', 'Club ID required');
    header('Location: ../clubs/index.php');
    exit;
}

// Verify club membership
if (!ClubMember::isMember($clubId, $playerId)) {
    Auth::flash('error', 'You must be a club member to view matches');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($clubId);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: ../clubs/index.php');
    exit;
}

$canCreate = GameMatch::canCreate($playerId, $clubId);
$isPaid = GameMatch::isPaidMember($playerId);
$liveMatches = GameMatch::listByClub($clubId, 'live');
$recentMatches = GameMatch::listByClub($clubId, 'completed', 10);
$setupMatches = $canCreate ? GameMatch::listByClub($clubId, 'setup') : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Live Scores</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/pages/match-index.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="../clubs/view.php?slug=<?= htmlspecialchars($club['slug']) ?>" class="back-btn">&larr;</a>
            <h1 class="app-title">Live Scores</h1>
            <?php if ($canCreate): ?>
            <a href="create.php?club=<?= $clubId ?>" class="header-action">+ New</a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="scoreboard.php?club=<?= $clubId ?>" class="quick-action scoreboard">
                    <span class="icon">&#128250;</span>
                    <span class="label">Scoreboard</span>
                    <span class="desc">View all live matches</span>
                </a>
                <?php if ($canCreate): ?>
                <a href="create.php?club=<?= $clubId ?>" class="quick-action scorer">
                    <span class="icon">&#9998;</span>
                    <span class="label">New Match</span>
                    <span class="desc">Create & score a match</span>
                </a>
                <?php else: ?>
                <div class="quick-action scorer" style="opacity: 0.5; cursor: not-allowed;">
                    <span class="icon">&#9998;</span>
                    <span class="label">Scorer</span>
                    <span class="desc"><?= $isPaid ? 'Admins only' : 'Premium only' ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Setup Matches (only for admins) -->
            <?php if ($setupMatches): ?>
            <h3 class="section-title">Setting Up</h3>
            <div class="match-list">
                <?php foreach ($setupMatches as $match): ?>
                <div class="match-card">
                    <div class="match-header">
                        <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                        <span class="match-status setup">Setup</span>
                    </div>
                    <div class="match-teams">
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team1_name'] ?: 'Team 1') ?></div>
                        </div>
                        <div class="vs">vs</div>
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team2_name'] ?: 'Team 2') ?></div>
                        </div>
                    </div>
                    <div class="match-links">
                        <a href="score.php?id=<?= $match['id'] ?>" class="match-link scorer">Configure & Start</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Live Matches -->
            <h3 class="section-title">Live Now</h3>
            <?php if ($liveMatches): ?>
            <div class="match-list">
                <?php foreach ($liveMatches as $match):
                    $canScoreThis = GameMatch::canScore($playerId, $match['id']);
                    $scorerClaimed = !empty($match['scorer_id']);
                ?>
                <div class="match-card">
                    <div class="match-header">
                        <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                        <span class="match-status live">LIVE</span>
                    </div>
                    <div class="match-teams">
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team1_name'] ?: 'Team 1') ?></div>
                        </div>
                        <div class="match-score">
                            <?= $match['team1_score'] ?> - <?= $match['team2_score'] ?>
                        </div>
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team2_name'] ?: 'Team 2') ?></div>
                        </div>
                    </div>
                    <div class="match-end">End <?= $match['current_end'] ?></div>
                    <div class="match-links">
                        <?php if ($canScoreThis): ?>
                        <a href="score.php?id=<?= $match['id'] ?>" class="match-link scorer">Scorer</a>
                        <?php elseif ($scorerClaimed): ?>
                        <span class="match-link scorer claimed">Scorer Claimed</span>
                        <?php elseif ($isPaid): ?>
                        <a href="score.php?id=<?= $match['id'] ?>&claim=1" class="match-link scorer">Claim Scorer</a>
                        <?php else: ?>
                        <span class="match-link scorer" style="opacity: 0.5;">Premium Only</span>
                        <?php endif; ?>
                        <a href="view.php?id=<?= $match['id'] ?>" class="match-link view">Scorecard</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No live matches right now</div>
            <?php endif; ?>

            <!-- Recent Matches -->
            <h3 class="section-title">Recent</h3>
            <?php if ($recentMatches): ?>
            <div class="match-list">
                <?php foreach ($recentMatches as $match): ?>
                <a href="view.php?id=<?= $match['id'] ?>" class="match-card">
                    <div class="match-header">
                        <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                        <span class="match-status completed">Completed</span>
                    </div>
                    <div class="match-teams">
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team1_name'] ?: 'Team 1') ?></div>
                        </div>
                        <div class="match-score">
                            <?= $match['team1_score'] ?> - <?= $match['team2_score'] ?>
                        </div>
                        <div class="team-info">
                            <div class="team-name"><?= htmlspecialchars($match['team2_name'] ?: 'Team 2') ?></div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">No completed matches yet</div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
