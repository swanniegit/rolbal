<?php
/**
 * Challenges List Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Challenge.php';
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();

// Get challenges with player's best scores if logged in
if ($playerId) {
    $challenges = Challenge::allWithPlayerBest($playerId);
} else {
    $challenges = Challenge::all();
}

$difficultyColors = [
    'beginner' => '#4caf50',
    'intermediate' => '#ff9800',
    'advanced' => '#f44336'
];

Template::pageHead('Challenges', [], '#2d5016', '../');
?>
    <style>
        .challenge-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .challenge-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.25rem;
            box-shadow: var(--shadow);
            text-decoration: none;
            color: var(--text);
            transition: transform var(--transition), box-shadow var(--transition);
            display: block;
        }

        .challenge-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.5rem;
        }

        .challenge-name {
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--primary);
        }

        .difficulty-badge {
            font-size: 0.625rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: white;
        }

        .challenge-description {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            line-height: 1.4;
        }

        .challenge-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .challenge-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .challenge-stats span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .best-score {
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            transition: transform var(--transition), box-shadow var(--transition);
            display: inline-block;
        }

        .best-score:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .no-score {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-style: italic;
        }

        .in-progress-score {
            background: #fff3e0;
            color: #e65100;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .login-prompt-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-prompt-card h3 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .login-prompt-card p {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
    </style>
<body>
    <div class="app-container">
        <?php Template::header('Challenges', '../index.php'); ?>

        <main class="main-content">
            <?php if (!$isLoggedIn): ?>
            <div class="login-prompt-card">
                <h3>Login to Play</h3>
                <p>Create an account to play challenges and track your scores.</p>
                <div class="auth-buttons">
                    <a href="../login.php" class="btn-primary" style="width: auto; display: inline-block; padding: 0.75rem 1.5rem;">Login</a>
                    <a href="../register.php" class="btn-secondary">Register</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($challenges)): ?>
            <?php Template::emptyState('No challenges available yet.'); ?>
            <?php else: ?>
            <div class="challenge-grid">
                <?php foreach ($challenges as $challenge): ?>
                <?php
                $playUrl = '../login.php';
                if ($isLoggedIn) {
                    $playUrl = 'play.php?id=' . $challenge['id'];
                    if (isset($challenge['active_attempt']) && $challenge['active_attempt']) {
                        $playUrl .= '&attempt=' . $challenge['active_attempt']['id'];
                    }
                }
                ?>
                <a href="<?= $playUrl ?>" class="challenge-card">
                    <div class="challenge-header">
                        <span class="challenge-name"><?= htmlspecialchars($challenge['name']) ?></span>
                        <span class="difficulty-badge" style="background: <?= $difficultyColors[$challenge['difficulty']] ?>">
                            <?= ucfirst($challenge['difficulty']) ?>
                        </span>
                    </div>
                    <div class="challenge-description">
                        <?= htmlspecialchars($challenge['description'] ?? '') ?>
                    </div>
                    <div class="challenge-meta">
                        <div class="challenge-stats">
                            <span><?= $challenge['total_bowls'] ?? 0 ?> bowls</span>
                            <span><?= $challenge['sequence_count'] ?? 0 ?> sequences</span>
                        </div>
                        <?php if ($isLoggedIn && isset($challenge['active_attempt']) && $challenge['active_attempt']): ?>
                            <span class="in-progress-score">
                                In Progress (<?= $challenge['active_attempt']['roll_count'] ?>/<?= $challenge['total_bowls'] ?>)
                            </span>
                        <?php elseif ($isLoggedIn && isset($challenge['best_score']) && $challenge['best_score']): ?>
                            <a href="results.php?attempt=<?= $challenge['best_score']['id'] ?>" class="best-score" onclick="event.stopPropagation();">
                                Best: <?= $challenge['best_score']['total_score'] ?>/<?= $challenge['max_possible_score'] ?>
                            </a>
                        <?php elseif ($isLoggedIn): ?>
                            <span class="no-score">Not played</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
