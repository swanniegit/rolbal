<?php
/**
 * Challenge Results Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/ChallengeAttempt.php';
require_once __DIR__ . '/../includes/constants.php';

// Require login
if (!Auth::check()) {
    header('Location: ../login.php');
    exit;
}

$playerId = Auth::id();
$attemptId = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;

if (!$attemptId) {
    header('Location: index.php');
    exit;
}

$attempt = ChallengeAttempt::find($attemptId);

if (!$attempt || (int)$attempt['player_id'] !== $playerId) {
    header('Location: index.php');
    exit;
}

// Get challenge details
$challenge = Challenge::findWithSequences($attempt['challenge_id']);
$breakdown = ChallengeAttempt::getScoreBreakdown($attemptId);

// Get player's best score for comparison
$bestScore = Challenge::getBestScoreForPlayer($attempt['challenge_id'], $playerId);
$isNewBest = $bestScore && (int)$bestScore['total_score'] === (int)$attempt['total_score'];

// Calculate percentage
$percentage = $attempt['max_possible_score'] > 0
    ? round(($attempt['total_score'] / $attempt['max_possible_score']) * 100, 1)
    : 0;

// Get leaderboard position
$leaderboard = Challenge::getLeaderboard($attempt['challenge_id'], 100);
$position = null;
foreach ($leaderboard as $index => $entry) {
    if ((int)$entry['player_id'] === $playerId && (int)$entry['total_score'] === (int)$attempt['total_score']) {
        $position = $index + 1;
        break;
    }
}

// Get previous attempts for this challenge
$previousAttempts = ChallengeAttempt::forPlayerChallenge($playerId, $attempt['challenge_id'], 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <title>Results - Rolbal</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/pages/challenge-results.css">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php" class="back-btn">&larr;</a>
            <h1 class="app-title">Results</h1>
            <span></span>
        </header>

        <main class="main-content">
            <!-- Score Card -->
            <div class="results-card">
                <div class="results-header">
                    <h2><?= htmlspecialchars($challenge['name']) ?></h2>
                    <p>
                        <span class="difficulty-badge difficulty-<?= $challenge['difficulty'] ?>">
                            <?= ucfirst($challenge['difficulty']) ?>
                        </span>
                    </p>
                </div>

                <div class="score-big">
                    <?= $attempt['total_score'] ?><span class="score-max">/<?= $attempt['max_possible_score'] ?></span>
                </div>

                <div class="score-percentage"><?= $percentage ?>%</div>

                <?php if ($isNewBest): ?>
                <div class="new-best-badge">New Personal Best!</div>
                <?php endif; ?>

                <?php if ($position): ?>
                <div class="position-display">
                    Leaderboard: <strong>#<?= $position ?></strong>
                </div>
                <?php endif; ?>

                <?php if ($attempt['completed_at']): ?>
                <div class="completed-info">
                    Completed <?= date('M j, Y g:i A', strtotime($attempt['completed_at'])) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Breakdown -->
            <div class="breakdown-section">
                <div class="breakdown-title">Score Breakdown</div>
                <div class="breakdown-list">
                    <?php foreach ($breakdown as $seq): ?>
                    <div class="breakdown-item">
                        <div class="breakdown-item-header">
                            <span class="breakdown-item-desc"><?= htmlspecialchars($seq['description']) ?></span>
                            <span class="breakdown-item-score"><?= $seq['score'] ?>/<?= $seq['max_score'] ?></span>
                        </div>
                        <div class="breakdown-progress">
                            <div class="breakdown-progress-fill" style="width: <?= $seq['percentage'] ?>%"></div>
                        </div>
                        <div class="breakdown-percentage"><?= $seq['percentage'] ?>%</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Previous Attempts -->
            <?php if (count($previousAttempts) > 1 || (count($previousAttempts) === 1 && (int)$previousAttempts[0]['id'] !== $attemptId)): ?>
            <div class="history-section">
                <div class="history-title">Previous Attempts</div>
                <div class="history-list">
                    <?php
                    $bestScoreValue = $bestScore ? (int)$bestScore['total_score'] : 0;
                    foreach ($previousAttempts as $prevAttempt):
                        $isCurrent = (int)$prevAttempt['id'] === $attemptId;
                        $isBest = (int)$prevAttempt['total_score'] === $bestScoreValue;
                    ?>
                    <a href="results.php?attempt=<?= $prevAttempt['id'] ?>"
                       class="history-item <?= $isCurrent ? 'current' : '' ?>">
                        <span class="history-date">
                            <?= date('M j, Y', strtotime($prevAttempt['completed_at'])) ?>
                            <?= $isCurrent ? '(this attempt)' : '' ?>
                        </span>
                        <span class="history-score">
                            <span class="history-score-value"><?= $prevAttempt['total_score'] ?>/<?= $prevAttempt['max_possible_score'] ?></span>
                            <span class="history-percentage <?= $isBest ? 'history-best' : '' ?>">
                                <?= $prevAttempt['percentage'] ?>%
                            </span>
                        </span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="action-buttons">
                <a href="play.php?id=<?= $attempt['challenge_id'] ?>" class="btn-primary">Play Again</a>
                <a href="index.php" class="btn-secondary">All Challenges</a>
            </div>
        </main>
    </div>
</body>
</html>
