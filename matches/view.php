<?php
/**
 * Match Viewer Page - Traditional Scoreboard Layout
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/CompetitionFixture.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$matchId) {
    Auth::flash('error', 'Match ID required');
    header('Location: ../clubs/index.php');
    exit;
}

// Verify permissions
if (!GameMatch::canView($playerId, $matchId)) {
    Auth::flash('error', 'Not authorized to view this match');
    header('Location: ../clubs/index.php');
    exit;
}

$match = GameMatch::findWithDetails($matchId);
if (!$match) {
    Auth::flash('error', 'Match not found');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($match['club_id']);
$canScore = GameMatch::canScore($playerId, $matchId);

// Check if this match is part of a competition
$competitionFixture = CompetitionFixture::findByMatch($matchId);
$competition = null;
if ($competitionFixture) {
    $competition = Competition::find($competitionFixture['competition_id']);
}

// Build running totals
$t1Shots = [];
$t2Shots = [];
$t1Running = [];
$t2Running = [];
$t1Total = 0;
$t2Total = 0;

foreach ($match['ends'] as $end) {
    $endNum = $end['end_number'];
    if ($end['scoring_team'] == 1) {
        $t1Shots[$endNum] = $end['shots'];
        $t2Shots[$endNum] = '-';
        $t1Total += $end['shots'];
    } else {
        $t1Shots[$endNum] = '-';
        $t2Shots[$endNum] = $end['shots'];
        $t2Total += $end['shots'];
    }
    $t1Running[$endNum] = $t1Total;
    $t2Running[$endNum] = $t2Total;
}

$totalEnds = $match['target_score'] ?? $match['total_ends'] ?? 21;
$scoringMode = $match['scoring_mode'] ?? 'ends';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>BowlsTracker - Scorecard</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/match-view.css">
</head>
<body>
    <div class="header">
        <a href="index.php?club=<?= $match['club_id'] ?>">&larr; Back</a>
        <h1>Scorecard</h1>
        <?php if ($match['status'] === 'live'): ?>
        <span class="live-badge">LIVE</span>
        <?php elseif ($match['status'] === 'completed'): ?>
        <span class="completed-badge">FINAL</span>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
    </div>

    <!-- Final Score Summary -->
    <div class="scoreboard">
        <div class="final-score">
            <div class="final-team team-1">
                <div class="final-team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                <div class="final-team-score" id="finalScore1"><?= $match['team1_score'] ?></div>
            </div>
            <div class="final-vs">-</div>
            <div class="final-team team-2">
                <div class="final-team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                <div class="final-team-score" id="finalScore2"><?= $match['team2_score'] ?></div>
            </div>
        </div>
        <div class="match-info">
            <?= ucfirst($match['game_type']) ?> ·
            <?php if ($scoringMode === 'first_to'): ?>
                First to <?= $totalEnds ?>
            <?php else: ?>
                End <?= $match['current_end'] ?> of <?= $totalEnds ?>
            <?php endif; ?>
        </div>
        <?php if ($competition): ?>
        <a href="../competitions/view.php?id=<?= $competition['id'] ?>" class="competition-link">
            <?= htmlspecialchars($competition['name']) ?> - <?= CompetitionFixture::getStageName($competitionFixture['stage']) ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- Traditional Scoreboard -->
    <div class="scoreboard" id="scoreboard">
        <!-- Team 1 -->
        <div class="team-section team-1">
            <div class="team-header">
                <span class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></span>
                <span class="players">
                    <?php
                    $players1 = array_map(fn($p) => $p['player_name'], $match['teams'][0]['players'] ?? []);
                    echo htmlspecialchars(implode(', ', $players1));
                    ?>
                </span>
            </div>
            <div class="score-row shots-row" id="t1Shots">
                <div class="row-label">Shots</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell <?= ($t1Shots[$i] ?? '-') === '-' ? 'dash' : '' ?>">
                        <?= $t1Shots[$i] ?? '-' ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="score-row total-row" id="t1Total">
                <div class="row-label">Total</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell"><?= $t1Running[$i] ?? '-' ?></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Ends Row -->
        <div class="score-row ends-row" id="endsRow">
            <div class="row-label">Ends</div>
            <div class="score-cells">
                <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                <div class="score-cell"><?= $i ?></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Team 2 -->
        <div class="team-section team-2">
            <div class="score-row shots-row" id="t2Shots">
                <div class="row-label">Shots</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell <?= ($t2Shots[$i] ?? '-') === '-' ? 'dash' : '' ?>">
                        <?= $t2Shots[$i] ?? '-' ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="score-row total-row" id="t2Total">
                <div class="row-label">Total</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell"><?= $t2Running[$i] ?? '-' ?></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="team-header">
                <span class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></span>
                <span class="players">
                    <?php
                    $players2 = array_map(fn($p) => $p['player_name'], $match['teams'][1]['players'] ?? []);
                    echo htmlspecialchars(implode(', ', $players2));
                    ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($match['status'] === 'live'): ?>
    <div class="refresh-note">Auto-refreshing every 5 seconds</div>
    <?php endif; ?>

    <?php if ($canScore && $match['status'] === 'live'): ?>
    <div class="action-bar">
        <a href="score.php?id=<?= $matchId ?>">Open Scorer</a>
    </div>
    <?php endif; ?>

    <?php if ($match['status'] === 'live'): ?>
    <script>
    const MATCH_ID = <?= $matchId ?>;
    </script>
    <script src="../js/match-view.js"></script>
    <?php endif; ?>
</body>
</html>
