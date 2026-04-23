<?php
/**
 * Bounce Game Scorer — any logged-in player can score
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/Template.php';

if (!Auth::check()) {
    header('Location: ../login.php');
    exit;
}

$playerId = Auth::id();
$flash    = Auth::getFlash();
$matchId  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$matchId) {
    header('Location: ../index.php');
    exit;
}

$match = GameMatch::findWithDetails($matchId);
if (!$match || $match['game_type'] !== 'bounce') {
    Auth::flash('error', 'Bounce game not found');
    header('Location: ../index.php');
    exit;
}

// Any logged-in user can score a bounce game
$shareToken  = $match['share_token'] ?? '';
$viewUrl     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST']
             . rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/') . '/'
             . 'matches/bounce-view.php?token=' . urlencode($shareToken);

$targetScore = $match['target_score'] ?? 21;
$matchTitle  = $match['match_name'] ? htmlspecialchars($match['match_name']) : 'Bounce Game';
$csrfToken   = Auth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a3a6b">
    <title>BowlsTracker – <?= $matchTitle ?></title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/bounce.css">
</head>
<body>
    <div class="header">
        <a href="bounce-view.php?token=<?= urlencode($shareToken) ?>">&larr;</a>
        <h1><?= $matchTitle ?></h1>
        <?php if ($match['status'] === 'live'): ?>
        <span class="badge-live">LIVE</span>
        <?php elseif ($match['status'] === 'setup'): ?>
        <span class="badge-setup">SETUP</span>
        <?php else: ?>
        <span class="badge-done">DONE</span>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php Template::flash($flash); ?>

        <!-- Share link — always visible -->
        <div class="share-card">
            <h4>Share Live Score</h4>
            <div class="share-row">
                <div class="share-url" id="shareUrlBox"><?= htmlspecialchars($viewUrl) ?></div>
                <button class="btn-copy" onclick="copyShareLink()">Copy</button>
                <a class="btn-whatsapp" href="https://wa.me/?text=<?= urlencode('Follow the live score: ' . $viewUrl) ?>" target="_blank">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    WhatsApp
                </a>
            </div>
        </div>

        <!-- Scoreboard -->
        <div class="scoreboard">
            <div class="scoreboard-header">
                <span class="match-label">
                    <?= $match['players_per_team'] ?>v<?= $match['players_per_team'] ?> &middot;
                    <?= $match['bowls_per_player'] ?> bowls
                </span>
                <span>
                    <?php if ($match['scoring_mode'] === 'first_to'): ?>
                    First to <?= $targetScore ?>
                    <?php else: ?>
                    <?= $targetScore ?> ends
                    <?php endif; ?>
                </span>
            </div>
            <div class="teams-row">
                <div class="team-col left">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                    <div class="team-score" id="team1Score"><?= $match['team1_score'] ?></div>
                </div>
                <div class="team-col center">–</div>
                <div class="team-col right">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                    <div class="team-score" id="team2Score"><?= $match['team2_score'] ?></div>
                </div>
            </div>
            <div class="end-info">
                End <span id="currentEnd"><?= $match['current_end'] ?></span>
                <?php if ($match['scoring_mode'] === 'ends'): ?>
                of <?= $targetScore ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ends history -->
        <div class="ends-card">
            <h4>Ends</h4>
            <div class="ends-grid" id="endsGrid">
                <?php foreach ($match['ends'] as $end): ?>
                <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                <?php endforeach; ?>
                <?php if (empty($match['ends'])): ?>
                <span style="color:#bbb;font-size:0.82rem">No ends recorded yet</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Setup controls -->
        <?php if ($match['status'] === 'setup'): ?>
        <button type="button" class="btn-start" id="startMatchBtn">Start Match</button>
        <div class="action-buttons">
            <button type="button" class="btn-delete" id="deleteBtn">Delete Game</button>
        </div>
        <?php endif; ?>

        <!-- Live scoring controls -->
        <?php if ($match['status'] === 'live'): ?>
        <div class="score-section">
            <h3>Record End <span id="endLabel"><?= $match['current_end'] ?></span></h3>
            <div class="team-select">
                <button type="button" class="team-btn team-1" data-team="1">
                    <?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?>
                </button>
                <button type="button" class="team-btn team-2" data-team="2">
                    <?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?>
                </button>
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

        <?php if ($match['status'] === 'completed'): ?>
        <div style="text-align:center;padding:1.5rem;color:#555;font-size:0.95rem">
            Match completed · <a href="bounce-view.php?token=<?= urlencode($shareToken) ?>" style="color:#1a3a6b;font-weight:700">View scorecard</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Victory modal -->
    <div id="victoryModal" class="victory-modal hidden">
        <div class="victory-content">
            <div class="victory-trophy">🏆</div>
            <div class="victory-winner" id="victoryWinner"></div>
            <div class="victory-score" id="victoryScore"></div>
            <a href="bounce-view.php?token=<?= urlencode($shareToken) ?>" class="btn-view-result">View Scorecard</a>
        </div>
    </div>

    <script>
    const MATCH_ID    = <?= $matchId ?>;
    const SHARE_TOKEN = <?= json_encode($shareToken) ?>;
    const CSRF_TOKEN  = <?= json_encode($csrfToken) ?>;
    const SCORING_MODE = <?= json_encode($match['scoring_mode'] ?? 'ends') ?>;
    const TARGET_SCORE = <?= (int)$targetScore ?>;
    const TEAM1_NAME  = <?= json_encode($match['teams'][0]['team_name'] ?? 'Team 1') ?>;
    const TEAM2_NAME  = <?= json_encode($match['teams'][1]['team_name'] ?? 'Team 2') ?>;
    let currentEnd    = <?= (int)$match['current_end'] ?>;
    </script>
    <script src="../js/bounce-score.js"></script>
</body>
</html>
