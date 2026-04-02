<?php
/**
 * Play Challenge Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/ChallengeAttempt.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/Template.php';

// Require login
if (!Auth::check()) {
    header('Location: ../login.php');
    exit;
}

$playerId = Auth::id();
$challengeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$challengeId) {
    header('Location: index.php');
    exit;
}

$challenge = Challenge::findWithSequences($challengeId);

if (!$challenge || !$challenge['is_active']) {
    header('Location: index.php');
    exit;
}

// Check for existing attempt or create new one
$attemptId = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;

if ($attemptId) {
    $attempt = ChallengeAttempt::find($attemptId);
    if (!$attempt || (int)$attempt['player_id'] !== $playerId || (int)$attempt['challenge_id'] !== $challengeId) {
        header('Location: index.php');
        exit;
    }
    // If completed, redirect to results
    if ($attempt['completed_at']) {
        header('Location: results.php?attempt=' . $attemptId);
        exit;
    }
} else {
    // Check for active attempt
    $existing = ChallengeAttempt::getActiveAttempt($playerId, $challengeId);
    if ($existing) {
        $attemptId = $existing['id'];
    }
}

// Get progress if we have an attempt
$progress = null;
if ($attemptId) {
    $progress = ChallengeAttempt::getProgress($attemptId);
    if ($progress['is_complete'] && !$progress['completed_at']) {
        ChallengeAttempt::complete($attemptId);
        header('Location: results.php?attempt=' . $attemptId);
        exit;
    }
}

$sequences = $challenge['sequences'];
$totalBowls = $challenge['total_bowls'];
$maxScore = $challenge['max_possible_score'];

// Current position
$currentSeqIndex = $progress ? $progress['current_sequence_index'] : 0;
$currentBowlInSeq = $progress ? $progress['current_bowl_in_sequence'] : 1;
$currentSeq = $sequences[$currentSeqIndex] ?? null;
$totalScore = $progress ? $progress['total_score'] : 0;
$rollCount = $progress ? $progress['roll_count'] : 0;

Template::pageHead($challenge['name'], ['../css/pages/challenge-play.css'], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header($challenge['name'], 'index.php', '<span class="roll-count" id="totalScore">' . $totalScore . '</span>'); ?>

        <main class="main-content">
            <?php if (!$attemptId): ?>
            <!-- Start Prompt -->
            <div class="start-prompt">
                <h2>Ready to Start?</h2>
                <p><?= htmlspecialchars($challenge['description']) ?></p>

                <div class="sequence-preview">
                    <div class="sequence-preview-title">Challenge Overview</div>
                    <div class="sequence-preview-list">
                        <?php foreach ($sequences as $seq): ?>
                        <div class="sequence-preview-item">
                            <span><?= $seq['bowl_count'] ?> bowls</span>
                            <span><?= END_LENGTHS[$seq['end_length']] ?> - <?= DELIVERIES[$seq['delivery']] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <p><strong><?= $totalBowls ?></strong> bowls total | Max score: <strong><?= $maxScore ?></strong></p>

                <form id="startForm" method="POST" action="../api/challenge.php">
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="challenge_id" value="<?= $challengeId ?>">
                    <button type="submit" class="btn-primary">Start Challenge</button>
                </form>
            </div>

            <?php else: ?>
            <!-- Challenge Info -->
            <div class="challenge-info">
                <div class="sequence-info">
                    <div>
                        <span class="sequence-text">
                            Sequence <strong id="currentSeqNum"><?= $currentSeqIndex + 1 ?></strong>/<?= count($sequences) ?>
                        </span>
                        <span class="end-length-indicator" id="endLengthBadge">
                            <?= $currentSeq ? END_LENGTHS[$currentSeq['end_length']] : '' ?>
                        </span>
                    </div>
                    <span class="delivery-indicator <?= $currentSeq && $currentSeq['delivery'] == 14 ? 'delivery-forehand' : 'delivery-backhand' ?>" id="deliveryBadge">
                        <?= $currentSeq ? DELIVERIES[$currentSeq['delivery']] : '' ?>
                    </span>
                </div>
            </div>

            <!-- Score Display -->
            <div class="score-display">
                <span class="score-current" id="scoreDisplay"><?= $totalScore ?></span>
                <span class="score-max">/ <?= $maxScore ?></span>
            </div>

            <!-- Progress -->
            <div class="game-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill" style="width: <?= ($rollCount / $totalBowls) * 100 ?>%"></div>
                </div>
                <div class="progress-detail">
                    <span>Bowl <strong id="currentBowlNum"><?= $currentBowlInSeq ?></strong>/<?= $currentSeq ? $currentSeq['bowl_count'] : 0 ?> in sequence</span>
                    <span id="rollCountDisplay"><?= $rollCount ?>/<?= $totalBowls ?> total</span>
                </div>
            </div>

            <!-- Bowl Indicator -->
            <div class="bowl-indicator">
                <h2 id="bowlHeader">Bowl <?= $currentBowlInSeq ?></h2>
            </div>

            <!-- Result Grid -->
            <div class="roll-step" id="stepResult">
                <div class="result-row">
                    <div class="green-container">
                        <!-- Row 1: Top miss bar -->
                        <button type="button" class="btn-miss btn-miss-top" data-value="22">Too Long / Ditch</button>
                        <!-- Row 2: Left bar, Grid, Right bar -->
                        <button type="button" class="btn-miss btn-miss-left" data-value="20">Too Far Left</button>
                        <div class="green-grid">
                            <button type="button" class="btn-pos" data-value="5">Long Left</button>
                            <button type="button" class="btn-pos" data-value="7">Long Centre</button>
                            <button type="button" class="btn-pos" data-value="6">Long Right</button>
                            <button type="button" class="btn-pos" data-value="3">Level Left</button>
                            <button type="button" class="btn-pos target" data-value="8">Centre</button>
                            <button type="button" class="btn-pos" data-value="4">Level Right</button>
                            <button type="button" class="btn-pos" data-value="1">Short Left</button>
                            <button type="button" class="btn-pos" data-value="12">Short Centre</button>
                            <button type="button" class="btn-pos" data-value="2">Short Right</button>
                        </div>
                        <button type="button" class="btn-miss btn-miss-right" data-value="21">Too Far Right</button>
                        <!-- Row 3: Bottom miss bar -->
                        <button type="button" class="btn-miss btn-miss-bottom" data-value="23">Too Short</button>
                    </div>
                    <button type="button" class="btn-toucher" id="toucherBtn">Toucher</button>
                </div>
            </div>

            <!-- Action Bar -->
            <div class="action-bar">
                <button type="button" class="btn-secondary" id="undoBtn" <?= $rollCount === 0 ? 'disabled' : '' ?>>Undo</button>
                <button type="button" class="btn-secondary" id="quitBtn">Quit</button>
            </div>

            <!-- Score Popup -->
            <div class="score-popup" id="scorePopup">+0</div>

            <!-- Hidden Data -->
            <input type="hidden" id="attemptId" value="<?= $attemptId ?>">
            <input type="hidden" id="challengeId" value="<?= $challengeId ?>">
            <input type="hidden" id="totalBowls" value="<?= $totalBowls ?>">
            <input type="hidden" id="maxScore" value="<?= $maxScore ?>">
            <input type="hidden" id="sequencesJson" value='<?= json_encode($sequences) ?>'>
            <input type="hidden" id="currentRollCount" value="<?= $rollCount ?>">
            <input type="hidden" id="currentTotalScore" value="<?= $totalScore ?>">
            <?php endif; ?>
        </main>
    </div>

    <script src="../js/api.js"></script>
    <script src="../js/ui.js"></script>
    <script src="../js/challenge.js"></script>
</body>
</html>
