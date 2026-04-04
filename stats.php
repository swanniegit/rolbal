<?php
/**
 * Statistics Page
 */

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/Session.php';
require_once __DIR__ . '/includes/Roll.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Template.php';

$isLoggedIn = Auth::check();
$sessionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$session = null;
$stats = null;
$sessions = [];

if ($sessionId) {
    // Allow viewing specific session stats (e.g., after game completion)
    $session = Session::find($sessionId);
    if ($session) {
        $stats = Roll::stats($sessionId);
    }
} elseif ($isLoggedIn) {
    // Only logged-in users can browse all sessions
    $sessions = Session::forPlayer(Auth::id());
} else {
    // Not logged in and no session ID - redirect to login
    header('Location: login.php');
    exit;
}

$backHref = $sessionId ? 'game.php?id=' . $sessionId : 'index.php';
$rollCount = $stats ? $stats['total'] : 0;

Template::pageHead('Statistics', ['css/pages/stats.css']);
?>
<body>
    <div class="app-container">
        <?php Template::header('Statistics', $backHref, '<span class="roll-count">' . $rollCount . '</span>'); ?>

        <main class="main-content">
            <?php if (!$session): ?>
            <?php if (empty($sessions)): ?>
            <?php Template::emptyState('No games recorded yet', 'game.php', 'Start New Game'); ?>
            <?php else: ?>
            <h2 class="section-title">Select a Game</h2>
            <div class="session-list">
                <?php foreach ($sessions as $s): ?>
                <a href="stats.php?id=<?= $s['id'] ?>" class="session-card">
                    <div class="session-card-header">
                        <span class="badge"><?= HANDS[$s['hand']] ?></span>
                        <span class="session-date"><?= date('d M Y', strtotime($s['session_date'])) ?></span>
                    </div>
                    <?php if ($s['description']): ?>
                    <p class="session-desc"><?= htmlspecialchars($s['description']) ?></p>
                    <?php endif; ?>
                    <div class="session-meta">
                        <span><?= $s['roll_count'] ?> bowls</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="session-info">
                <span class="badge"><?= HANDS[$session['hand']] ?></span>
                <span><?= date('d M Y', strtotime($session['session_date'])) ?></span>
                <?php if ($session['description']): ?>
                    <span>- <?= htmlspecialchars($session['description']) ?></span>
                <?php endif; ?>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['total'] ?></span>
                    <span class="stat-label">Total Bowls</span>
                </div>
                <div class="stat-card highlight">
                    <span class="stat-value"><?= $stats['touchers'] ?></span>
                    <span class="stat-label">Touchers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['results'][8] ?? 0 ?></span>
                    <span class="stat-label">Centre</span>
                </div>
                <div class="stat-card">
                    <span class="stat-value"><?= $stats['total'] > 0 ? round(($stats['touchers'] / $stats['total']) * 100) : 0 ?>%</span>
                    <span class="stat-label">Toucher Rate</span>
                </div>
            </div>

            <!-- Position Breakdown -->
            <div class="stats-section">
                <h2>Position Breakdown</h2>
                <div class="stats-table">
                    <?php
                    $positionGroups = [
                        'Long' => [5 => 'Left', 7 => 'Centre', 6 => 'Right'],
                        'Level' => [3 => 'Left', 8 => 'Centre', 4 => 'Right'],
                        'Short' => [1 => 'Left', 12 => 'Centre', 2 => 'Right']
                    ];
                    foreach ($positionGroups as $row => $positions): ?>
                    <div class="stats-row">
                        <span class="row-label"><?= $row ?></span>
                        <?php foreach ($positions as $code => $label):
                            $count = $stats['results'][$code] ?? 0;
                            $pct = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
                        ?>
                        <div class="stats-cell <?= $code === 8 ? 'highlight' : '' ?>">
                            <span class="cell-count"><?= $count ?></span>
                            <span class="cell-pct"><?= $pct ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- End Length Breakdown -->
            <div class="stats-section">
                <h2>By End Length</h2>
                <div class="bar-chart">
                    <?php
                    $maxEndLength = max($stats['end_lengths'] ?: [1]);
                    foreach (END_LENGTHS as $code => $label):
                        $count = $stats['end_lengths'][$code] ?? 0;
                        $pct = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
                        $barWidth = $maxEndLength > 0 ? ($count / $maxEndLength) * 100 : 0;
                    ?>
                    <div class="bar-row">
                        <span class="bar-label"><?= $label ?></span>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: <?= $barWidth ?>%"></div>
                        </div>
                        <span class="bar-value"><?= $count ?> (<?= $pct ?>%)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Zone Analysis -->
            <div class="stats-section">
                <h2>Zone Analysis</h2>
                <div class="zone-grid">
                    <?php
                    // Calculate zone stats
                    $zones = [
                        'Short' => ($stats['results'][1] ?? 0) + ($stats['results'][2] ?? 0) + ($stats['results'][12] ?? 0),
                        'Level' => ($stats['results'][3] ?? 0) + ($stats['results'][4] ?? 0) + ($stats['results'][8] ?? 0),
                        'Long' => ($stats['results'][5] ?? 0) + ($stats['results'][6] ?? 0) + ($stats['results'][7] ?? 0),
                        'Left' => ($stats['results'][1] ?? 0) + ($stats['results'][3] ?? 0) + ($stats['results'][5] ?? 0),
                        'Centre' => ($stats['results'][7] ?? 0) + ($stats['results'][8] ?? 0) + ($stats['results'][12] ?? 0),
                        'Right' => ($stats['results'][2] ?? 0) + ($stats['results'][4] ?? 0) + ($stats['results'][6] ?? 0),
                    ];
                    foreach ($zones as $zone => $count):
                        $pct = $stats['total'] > 0 ? round(($count / $stats['total']) * 100) : 0;
                    ?>
                    <div class="zone-card">
                        <span class="zone-name"><?= $zone ?></span>
                        <span class="zone-value"><?= $count ?></span>
                        <span class="zone-pct"><?= $pct ?>%</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="action-bar">
                <a href="index.php" class="btn-secondary">Home</a>
                <a href="game.php" class="btn-primary">New Game</a>
            </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
