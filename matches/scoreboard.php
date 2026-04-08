<?php
/**
 * Multi-Match Scoreboard - Display all live matches for a club
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();

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

$liveMatches = GameMatch::getLiveMatchesForClub($clubId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>BowlsTracker - Live Scoreboard</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/match-scoreboard.css">
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php?club=<?= $clubId ?>" class="back-link">&larr;</a>
        </div>
        <div style="text-align: center;">
            <h1>Live Scoreboard</h1>
            <div class="club-name"><?= htmlspecialchars($club['name']) ?></div>
        </div>
        <div class="live-badge">
            <span class="live-dot"></span>
            LIVE
        </div>
    </div>

    <div class="matches-grid" id="matchesGrid">
        <?php if (empty($liveMatches)): ?>
        <div class="empty-state">
            <h2>No Live Matches</h2>
            <p>There are no matches in progress right now.</p>
        </div>
        <?php else: ?>
            <?php foreach ($liveMatches as $match): ?>
            <div class="match-card" data-match-id="<?= $match['id'] ?>">
                <div class="match-header">
                    <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                    <span class="match-end">End <?= $match['current_end'] ?></span>
                </div>
                <div class="score-display">
                    <div class="team-block left team-1">
                        <div class="team-name"><?= htmlspecialchars($match['team1_name'] ?: 'Team 1') ?></div>
                        <div class="team-score" id="t1-<?= $match['id'] ?>"><?= $match['team1_score'] ?></div>
                    </div>
                    <div class="vs">-</div>
                    <div class="team-block right team-2">
                        <div class="team-name"><?= htmlspecialchars($match['team2_name'] ?: 'Team 2') ?></div>
                        <div class="team-score" id="t2-<?= $match['id'] ?>"><?= $match['team2_score'] ?></div>
                    </div>
                </div>
                <div class="ends-row" id="ends-<?= $match['id'] ?>">
                    <?php foreach ($match['ends'] as $end): ?>
                    <div class="end-chip team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($match['scorer_name']): ?>
                <div class="scorer-info">Scorer: <?= htmlspecialchars($match['scorer_name']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="refresh-note">Auto-refreshing every 5 seconds</div>

    <script>
    const CLUB_ID = <?= $clubId ?>;
    </script>
    <script src="../js/match-scoreboard.js"></script>
</body>
</html>
