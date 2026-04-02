<?php
/**
 * History Page - List of all sessions
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

$isLoggedIn = Auth::check();

// History requires login
if (!$isLoggedIn) {
    header('Location: login.php');
    exit;
}

$currentPlayerId = Auth::id();
$sessions = Session::forPlayer($currentPlayerId);

Template::pageHead('History');
?>
<body>
    <div class="app-container">
        <?php Template::header('History', 'index.php', '<span class="roll-count">' . count($sessions) . '</span>'); ?>

        <main class="main-content">
            <?php if (empty($sessions)): ?>
            <?php Template::emptyState('No games recorded yet', 'game.php', 'Start New Game'); ?>
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

    <script src="js/api.js"></script>
    <script src="js/ui.js"></script>
    <script src="js/session.js"></script>
</body>
</html>
