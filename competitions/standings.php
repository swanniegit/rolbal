<?php
/**
 * Competition Standings View
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
    Auth::flash('error', 'Not authorized');
    header('Location: ../clubs/index.php');
    exit;
}

$groups = CompetitionRoundRobin::getGroups($id);
$hasGroups = !empty($groups);

if ($hasGroups) {
    $standingsByGroup = CompetitionStandings::getAllGroupStandings($id);
} else {
    $standings = CompetitionStandings::getStandings($id);
}

Template::pageHead('Standings', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Standings', 'view.php?id=' . $id); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <h2 class="competition-title"><?= htmlspecialchars($competition['name']) ?></h2>

            <?php if ($hasGroups): ?>
                <?php foreach ($standingsByGroup as $groupData): ?>
                <div class="group-section">
                    <h3 class="group-title"><?= htmlspecialchars($groupData['group']['group_name']) ?></h3>
                    <?php renderStandingsTable($groupData['standings']); ?>
                </div>
                <?php endforeach; ?>
            <?php elseif (!empty($standings)): ?>
                <?php renderStandingsTable($standings); ?>
            <?php else: ?>
                <?php Template::emptyState('No standings yet'); ?>
            <?php endif; ?>
        </main>
    </div>

    <style>
    .competition-title {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .group-section {
        margin-bottom: 2rem;
    }
    .group-title {
        font-size: 1rem;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid var(--primary);
    }
    .standings-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .standings-table th,
    .standings-table td {
        padding: 0.5rem 0.25rem;
        text-align: center;
    }
    .standings-table th {
        font-weight: 600;
        color: var(--text-secondary);
        font-size: 0.7rem;
        text-transform: uppercase;
    }
    .standings-table th:first-child,
    .standings-table td:first-child {
        text-align: left;
    }
    .standings-table tbody tr {
        border-bottom: 1px solid var(--border);
    }
    .standings-table tbody tr:hover {
        background: var(--bg-muted);
    }
    .standings-table .pos {
        font-weight: 600;
        width: 24px;
    }
    .standings-table .name {
        font-weight: 500;
        max-width: 120px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .standings-table .pts {
        font-weight: 700;
        color: var(--primary);
    }
    .standings-table .diff {
        color: var(--text-secondary);
    }
    .qualifier-row {
        background: rgba(45, 80, 22, 0.1);
    }
    </style>
</body>
</html>

<?php
function renderStandingsTable(array $standings): void {
    if (empty($standings)) {
        echo '<p class="empty">No matches played yet</p>';
        return;
    }
    ?>
    <table class="standings-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Team</th>
                <th>P</th>
                <th>W</th>
                <th>L</th>
                <th>D</th>
                <th>+/-</th>
                <th class="pts">Pts</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($standings as $i => $s): ?>
            <tr class="<?= $i < 2 ? 'qualifier-row' : '' ?>">
                <td class="pos"><?= $s['position'] ?? ($i + 1) ?></td>
                <td class="name"><?= htmlspecialchars($s['participant_name'] ?? 'Unknown') ?></td>
                <td><?= $s['played'] ?></td>
                <td><?= $s['won'] ?></td>
                <td><?= $s['lost'] ?></td>
                <td><?= $s['drawn'] ?></td>
                <td class="diff"><?= $s['shot_diff'] >= 0 ? '+' . $s['shot_diff'] : $s['shot_diff'] ?></td>
                <td class="pts"><?= $s['points'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>
