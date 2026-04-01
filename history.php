<?php
/**
 * History Page - List of all sessions
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Auth.php';

$isLoggedIn = Auth::check();

// History requires login
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$currentPlayerId = Auth::id();
$sessions = Session::forPlayer($currentPlayerId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Rolbal - History</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="css/styles.css?v=3">
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php" class="back-btn">&larr;</a>
            <h1 class="app-title">History</h1>
            <span class="roll-count"><?= count($sessions) ?></span>
        </header>

        <main class="main-content">
            <?php if (empty($sessions)): ?>
            <div class="empty-state">
                <p>No games recorded yet</p>
                <a href="game.php" class="btn-primary">Start New Game</a>
            </div>
            <?php else: ?>
            <div class="session-list">
                <?php foreach ($sessions as $s):
                    $totalBowls = $s['bowls_per_end'] * $s['total_ends'];
                    $isComplete = $s['roll_count'] >= $totalBowls;
                    $progress = $totalBowls > 0 ? round(($s['roll_count'] / $totalBowls) * 100) : 0;
                ?>
                <div class="session-card-wrap">
                    <div class="session-card-main">
                        <div class="session-card-header">
                            <span class="badge"><?= HANDS[$s['hand']] ?></span>
                            <span class="session-date"><?= date('d M Y', strtotime($s['session_date'])) ?></span>
                            <?php if ($isComplete): ?>
                            <span class="badge-complete">Complete</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($s['description']): ?>
                        <p class="session-desc"><?= htmlspecialchars($s['description']) ?></p>
                        <?php endif; ?>
                        <div class="session-meta">
                            <span><?= $s['roll_count'] ?> / <?= $totalBowls ?> bowls</span>
                            <span><?= $s['total_ends'] ?> ends &times; <?= $s['bowls_per_end'] ?> bowls</span>
                        </div>
                        <?php if (!$isComplete): ?>
                        <div class="progress-bar mini">
                            <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="session-card-actions">
                        <?php if (!$isComplete): ?>
                        <a href="game.php?id=<?= $s['id'] ?>" class="btn-icon" title="Continue">&#9654;</a>
                        <?php endif; ?>
                        <a href="stats.php?id=<?= $s['id'] ?>" class="btn-icon" title="Stats">&#128202;</a>
                        <?php if ($isLoggedIn && isset($s['player_id']) && $s['player_id'] == $currentPlayerId): ?>
                        <button type="button"
                                class="btn-icon visibility-toggle"
                                data-session-id="<?= $s['id'] ?>"
                                data-public="<?= $s['is_public'] ?? 1 ?>"
                                title="<?= ($s['is_public'] ?? 1) ? 'Public' : 'Private' ?>">
                            <span class="visibility-icon"><?= ($s['is_public'] ?? 1) ? '👁' : '👁‍🗨' ?></span>
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn-icon btn-delete" data-id="<?= $s['id'] ?>" title="Delete">&#128465;</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="js/session.js"></script>
</body>
</html>
