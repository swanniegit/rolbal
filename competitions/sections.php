<?php
/**
 * Competition Sections Page - Section card display for round robin results
 *
 * Displays each section (group) with team cards showing:
 * - Position number
 * - Team name
 * - Match results (For/Against/Agg/Points per opponent)
 * - Totals row
 * - Winner/Runner Up badges
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionStandings.php';
require_once __DIR__ . '/../includes/CompetitionRoundRobin.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    Auth::flash('error', 'Competition ID required');
    header('Location: ../clubs/index.php');
    exit;
}

$competition = Competition::find($id);
if (!$competition) {
    Auth::flash('error', 'Competition not found');
    header('Location: ../clubs/index.php');
    exit;
}

if (!Competition::canView($playerId, $id)) {
    Auth::flash('error', 'Not authorized to view this competition');
    header('Location: ../clubs/index.php');
    exit;
}

// Only applicable for round robin or combined formats
if (!in_array($competition['format'], ['round_robin', 'combined'])) {
    header('Location: view.php?id=' . $id);
    exit;
}

$canManage = Competition::canManage($playerId, $id);
$qualifiersCount = $competition['qualifiers_per_section'] ?? 2;

// Get all section card data
$sections = CompetitionStandings::getAllSectionsCardData($id);

// Get participant position mapping for display
$participantPositions = [];
foreach ($sections as $section) {
    foreach ($section['teams'] as $team) {
        $participantPositions[$team['id']] = $team['position'];
    }
}

$rightHtml = '<a href="view.php?id=' . $id . '" class="header-action">Overview</a>';

Template::pageHead($competition['name'] . ' - Sections', ['pages/competition-sections.css'], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Section Results', 'view.php?id=' . $id, $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <!-- Competition Title Bar -->
            <div class="competition-header">
                <h1><?= htmlspecialchars($competition['name']) ?></h1>
                <span class="status-badge <?= $competition['status'] ?>"><?= Competition::getStatusLabel($competition['status']) ?></span>
            </div>

            <!-- Sections Grid -->
            <?php if (empty($sections)): ?>
            <?php Template::emptyState('No sections generated yet', 'view.php?id=' . $id, 'Back to Overview'); ?>
            <?php else: ?>
            <div class="sections-container">
                <?php foreach ($sections as $section): ?>
                <div class="section-block">
                    <h2 class="section-title"><?= htmlspecialchars($section['name']) ?></h2>

                    <div class="team-cards">
                        <?php foreach ($section['teams'] as $team): ?>
                        <div class="team-card <?= $team['rank'] ? strtolower(str_replace(' ', '-', $team['rank'])) : '' ?>">
                            <!-- Card Header -->
                            <div class="card-header">
                                <span class="section-label"><?= htmlspecialchars($section['name']) ?></span>
                                <span class="team-name"><?= htmlspecialchars($team['name']) ?></span>
                                <span class="position-badge"><?= $team['position'] ?></span>
                            </div>

                            <!-- Results Table -->
                            <table class="results-table">
                                <thead>
                                    <tr>
                                        <th>Opposition</th>
                                        <th>For</th>
                                        <th>Agst</th>
                                        <th>Agg</th>
                                        <th>Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($team['matches'])): ?>
                                    <tr>
                                        <td colspan="5" class="no-matches">No matches played yet</td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($team['matches'] as $match): ?>
                                    <tr>
                                        <td class="opponent"><?= $participantPositions[$match['opponent_id']] ?? '?' ?></td>
                                        <td class="stat"><?= $match['for'] ?></td>
                                        <td class="stat"><?= $match['against'] ?></td>
                                        <td class="stat <?= $match['agg'] > 0 ? 'positive' : ($match['agg'] < 0 ? 'negative' : '') ?>"><?= $match['agg'] > 0 ? '+' . $match['agg'] : $match['agg'] ?></td>
                                        <td class="stat points"><?= $match['points'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td>Totals</td>
                                        <td class="stat"><?= $team['totals']['for'] ?></td>
                                        <td class="stat"><?= $team['totals']['against'] ?></td>
                                        <td class="stat <?= $team['totals']['agg'] > 0 ? 'positive' : ($team['totals']['agg'] < 0 ? 'negative' : '') ?>"><?= $team['totals']['agg'] > 0 ? '+' . $team['totals']['agg'] : $team['totals']['agg'] ?></td>
                                        <td class="stat points"><?= $team['totals']['points'] ?></td>
                                    </tr>
                                </tfoot>
                            </table>

                            <!-- Rank Badge -->
                            <?php if ($team['rank']): ?>
                            <div class="rank-badge"><?= $team['rank'] ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Legend -->
            <div class="legend">
                <div class="legend-item">
                    <span class="legend-color winner"></span>
                    <span>Winner - Advances to Knockout</span>
                </div>
                <?php if ($qualifiersCount >= 2): ?>
                <div class="legend-item">
                    <span class="legend-color runner-up"></span>
                    <span>Runner Up - Advances to Knockout</span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Quick Navigation -->
            <div class="quick-nav">
                <a href="standings.php?id=<?= $id ?>" class="nav-btn">View Full Standings</a>
                <?php if ($competition['format'] === 'combined' && CompetitionStandings::isGroupStageComplete($id)): ?>
                <a href="bracket.php?id=<?= $id ?>" class="nav-btn primary">View Knockout Bracket</a>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <?php if ($canManage && $competition['status'] === 'in_progress'): ?>
    <script>
    // Refresh data every 30 seconds when competition is live
    setTimeout(() => location.reload(), 30000);
    </script>
    <?php endif; ?>
</body>
</html>
