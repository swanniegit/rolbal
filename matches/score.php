<?php
/**
 * Match Scorer Page - Interface for recording end scores
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/GameMatch.php';
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

// Handle claim request
if (isset($_GET['claim'])) {
    if (GameMatch::canClaimScorer($playerId, $matchId)) {
        GameMatch::claimScorer($matchId, $playerId);
        Auth::flash('success', 'You are now the scorer for this match');
        header('Location: score.php?id=' . $matchId);
        exit;
    } else {
        Auth::flash('error', 'Cannot claim scorer - must be paid member and scorer not already claimed');
        header('Location: index.php?club=' . GameMatch::find($matchId)['club_id']);
        exit;
    }
}

// Verify permissions
if (!GameMatch::canScore($playerId, $matchId)) {
    Auth::flash('error', 'Not authorized to score this match');
    header('Location: view.php?id=' . $matchId);
    exit;
}

$match = GameMatch::findWithDetails($matchId);
if (!$match) {
    Auth::flash('error', 'Match not found');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($match['club_id']);
$targetScore = $match['target_score'] ?? $match['total_ends'] ?? 21;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Score Match</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/match-scorer.css">
</head>
<body>
    <div class="header">
        <a href="index.php?club=<?= $match['club_id'] ?>">&larr;</a>
        <h1>Score Match</h1>
        <a href="view.php?id=<?= $matchId ?>" class="header-action">View</a>
    </div>

    <div class="content">
        <?php Template::flash($flash); ?>

        <!-- Scoreboard -->
        <div class="scoreboard">
            <div class="scoreboard-header">
                <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                <span class="status-badge <?= $match['status'] ?>"><?= strtoupper($match['status']) ?></span>
            </div>
            <div class="teams-row">
                <div class="team-col left">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                    <div class="team-score" id="team1Score"><?= $match['team1_score'] ?></div>
                </div>
                <div class="team-col center vs-col">-</div>
                <div class="team-col right">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                    <div class="team-score" id="team2Score"><?= $match['team2_score'] ?></div>
                </div>
            </div>
            <div class="end-info">
                End <span id="currentEnd"><?= $match['current_end'] ?></span> of <?= $targetScore ?>
            </div>
        </div>

        <!-- Ends History -->
        <div class="ends-history">
            <h4>Ends</h4>
            <div class="ends-grid" id="endsGrid">
                <?php foreach ($match['ends'] as $end): ?>
                <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                <?php endforeach; ?>
                <?php if (empty($match['ends'])): ?>
                <span style="color: #999; font-size: 0.85rem;">No ends recorded yet</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Start Match (when in setup) -->
        <?php if ($match['status'] === 'setup'): ?>
        <button type="button" class="btn-start" id="startMatchBtn">Start Match</button>
        <div class="action-buttons">
            <button type="button" class="btn-delete" id="deleteBtn">Delete Match</button>
        </div>
        <?php endif; ?>

        <!-- Score Input (when live) -->
        <?php if ($match['status'] === 'live'): ?>
        <div class="score-section">
            <h3>Record End <?= $match['current_end'] ?></h3>

            <div class="team-select">
                <button type="button" class="team-btn team-1" data-team="1"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></button>
                <button type="button" class="team-btn team-2" data-team="2"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></button>
            </div>

            <div class="shots-grid">
                <?php for ($i = 1; $i <= 8; $i++): ?>
                <button type="button" class="shot-btn" data-shots="<?= $i ?>"><?= $i ?></button>
                <?php endfor; ?>
            </div>

            <div class="submit-row">
                <button type="button" class="btn-undo" id="undoBtn" title="Undo last end">&#8630;</button>
                <button type="button" class="btn-submit" id="submitEndBtn" disabled>Submit End</button>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn-complete" id="completeBtn">End Match</button>
        </div>
        <?php endif; ?>
    </div>

    <script>
    const MATCH_ID = <?= $matchId ?>;
    const CLUB_ID = <?= $match['club_id'] ?>;
    const TOTAL_ENDS = <?= $targetScore ?>;
    let currentEnd = <?= $match['current_end'] ?>;
    </script>
    <script src="../js/match-scorer.js"></script>
</body>
</html>
