<?php
/**
 * Match List Page - Shows live and recent matches for a club
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/Template.php';

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

$rightHtml = $canCreate
    ? '<a href="create.php?club=' . $clubId . '" class="header-action">+ New</a>'
    : '<span></span>';

Template::pageHead('Live Scores', ['../css/pages/match-index.css'], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Live Scores', '../clubs/view.php?slug=' . htmlspecialchars($club['slug']), $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

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
            <?php Template::emptyState('No live matches right now'); ?>
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
            <?php Template::emptyState('No completed matches yet'); ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
