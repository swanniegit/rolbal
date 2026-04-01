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
    <style>
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }
        .quick-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem;
            background: #fff;
            border-radius: 12px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.2s;
        }
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .quick-action .icon {
            font-size: 2rem;
        }
        .quick-action .label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .quick-action .desc {
            font-size: 0.75rem;
            color: #888;
            text-align: center;
        }
        .quick-action.scorer {
            border: 2px solid #ff9800;
            background: linear-gradient(135deg, #fff8e1, #fff);
        }
        .quick-action.scoreboard {
            border: 2px solid #2196f3;
            background: linear-gradient(135deg, #e3f2fd, #fff);
        }
        .match-list { display: flex; flex-direction: column; gap: 0.5rem; }
        .match-card {
            background: #fff;
            border-radius: 10px;
            padding: 1rem;
            text-decoration: none;
            color: inherit;
            display: block;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .match-card:hover { box-shadow: 0 3px 10px rgba(0,0,0,0.12); }
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .match-type {
            font-size: 0.75rem;
            color: #888;
            text-transform: capitalize;
        }
        .match-status {
            font-size: 0.7rem;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-weight: 600;
        }
        .match-status.live {
            background: #ff4444;
            color: white;
            animation: pulse 2s infinite;
        }
        .match-status.setup { background: #ff9800; color: white; }
        .match-status.completed { background: #e8f5e9; color: #2d5016; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .match-teams {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        .team-info { flex: 1; text-align: center; }
        .team-info:first-child { text-align: left; }
        .team-info:last-child { text-align: right; }
        .team-name { font-weight: 600; font-size: 0.9rem; color: #333; }
        .match-score { font-size: 1.5rem; font-weight: bold; color: #2d5016; }
        .vs { color: #ccc; font-size: 0.8rem; }
        .match-end { font-size: 0.75rem; color: #888; text-align: center; margin-top: 0.25rem; }
        .match-links {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #eee;
        }
        .match-link {
            flex: 1;
            padding: 0.5rem;
            border-radius: 6px;
            text-align: center;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
        }
        .match-link.scorer {
            background: #fff3e0;
            color: #e65100;
        }
        .match-link.scorer.claimed {
            background: #ffebee;
            color: #c62828;
        }
        .match-link.view {
            background: #e3f2fd;
            color: #1565c0;
        }
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #888;
        }
        .section-title {
            font-size: 0.8rem;
            font-weight: 700;
            color: #888;
            margin: 1.5rem 0 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .section-title:first-of-type { margin-top: 0; }
    </style>
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
