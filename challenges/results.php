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
    <style>
        .results-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            text-align: center;
        }

        .results-header {
            margin-bottom: 1rem;
        }

        .results-header h2 {
            color: var(--primary);
            font-size: 1.25rem;
            margin-bottom: 0.25rem;
        }

        .results-header p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .score-big {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1;
        }

        .score-max {
            font-size: 1.25rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        .score-percentage {
            font-size: 1.5rem;
            color: var(--secondary);
            font-weight: 700;
            margin-top: 0.5rem;
        }

        .new-best-badge {
            display: inline-block;
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 0.375rem 1rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 700;
            margin-top: 0.75rem;
        }

        .position-display {
            margin-top: 1rem;
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .position-display strong {
            color: var(--primary);
        }

        .breakdown-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .breakdown-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 1rem;
            text-align: center;
        }

        .breakdown-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .breakdown-item {
            background: var(--bg);
            border-radius: 8px;
            padding: 0.75rem;
        }

        .breakdown-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .breakdown-item-desc {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text);
        }

        .breakdown-item-score {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--primary);
        }

        .breakdown-progress {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
        }

        .breakdown-progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        .breakdown-percentage {
            font-size: 0.625rem;
            color: var(--text-muted);
            text-align: right;
            margin-top: 0.25rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .action-buttons a,
        .action-buttons button {
            flex: 1;
            text-decoration: none;
            text-align: center;
        }

        .difficulty-badge {
            display: inline-block;
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: white;
        }

        .difficulty-beginner { background: #4caf50; }
        .difficulty-intermediate { background: #ff9800; }
        .difficulty-advanced { background: #f44336; }

        .completed-info {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }

        .history-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .history-title {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 1rem;
            text-align: center;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--bg);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text);
            transition: background var(--transition);
        }

        .history-item:hover {
            background: #e8f5e9;
        }

        .history-item.current {
            border: 2px solid var(--primary);
            background: #e8f5e9;
        }

        .history-date {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .history-score {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .history-score-value {
            font-weight: 700;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .history-percentage {
            font-size: 0.75rem;
            color: var(--text-muted);
            background: var(--bg-card);
            padding: 0.125rem 0.5rem;
            border-radius: 1rem;
        }

        .history-best {
            background: var(--secondary);
            color: var(--primary-dark);
            font-weight: 600;
        }
    </style>
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
