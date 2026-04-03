<?php
/**
 * Competition Bracket View
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionBracket.php';
require_once __DIR__ . '/../includes/CompetitionFixture.php';
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

$bracket = CompetitionBracket::getBracketStructure($id);

Template::pageHead('Bracket', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Bracket', 'view.php?id=' . $id); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <h2 class="competition-title"><?= htmlspecialchars($competition['name']) ?></h2>

            <?php if (empty($bracket)): ?>
            <?php Template::emptyState('Bracket not yet generated'); ?>
            <?php else: ?>

            <div class="bracket-container">
                <?php foreach ($bracket as $stage => $fixtures): ?>
                <div class="bracket-round">
                    <h3 class="round-title"><?= CompetitionFixture::getStageName($stage) ?></h3>
                    <div class="round-fixtures">
                        <?php foreach ($fixtures as $f): ?>
                        <div class="bracket-match <?= $f['status'] ?>">
                            <div class="match-slot <?= $f['winner_id'] == $f['participant1_id'] ? 'winner' : '' ?>">
                                <span class="participant"><?= htmlspecialchars($f['participant1_name']) ?></span>
                                <span class="score"><?= $f['score1'] ?? '-' ?></span>
                            </div>
                            <div class="match-slot <?= $f['winner_id'] == $f['participant2_id'] ? 'winner' : '' ?>">
                                <span class="participant"><?= htmlspecialchars($f['participant2_name']) ?></span>
                                <span class="score"><?= $f['score2'] ?? '-' ?></span>
                            </div>
                            <?php if ($f['status'] === 'live'): ?>
                            <span class="live-badge">LIVE</span>
                            <?php endif; ?>
                            <?php if ($f['match_id']): ?>
                            <a href="../matches/view.php?id=<?= $f['match_id'] ?>" class="match-link">View</a>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endif; ?>
        </main>
    </div>

    <style>
    .competition-title {
        font-size: 1.2rem;
        margin-bottom: 1.5rem;
        text-align: center;
    }
    .bracket-container {
        display: flex;
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 1rem;
    }
    .bracket-round {
        min-width: 180px;
        flex-shrink: 0;
    }
    .round-title {
        font-size: 0.8rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        margin-bottom: 0.75rem;
        text-align: center;
    }
    .round-fixtures {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        justify-content: space-around;
        min-height: 200px;
    }
    .bracket-match {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
        position: relative;
    }
    .bracket-match.live {
        border-color: #c62828;
    }
    .bracket-match.completed {
        opacity: 0.9;
    }
    .match-slot {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid var(--border);
    }
    .match-slot:last-of-type { border-bottom: none; }
    .match-slot.winner {
        background: #e8f5e9;
        font-weight: 600;
    }
    .match-slot .participant {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.85rem;
    }
    .match-slot .score {
        margin-left: 0.5rem;
        font-weight: 600;
        min-width: 20px;
        text-align: right;
    }
    .live-badge {
        position: absolute;
        top: -8px;
        right: 8px;
        background: #c62828;
        color: white;
        font-size: 0.6rem;
        padding: 0.15rem 0.4rem;
        border-radius: 3px;
        font-weight: 600;
    }
    .match-link {
        display: block;
        text-align: center;
        padding: 0.25rem;
        background: var(--bg-muted);
        font-size: 0.75rem;
        color: var(--primary);
        text-decoration: none;
    }

    /* Responsive: Stack vertically on mobile */
    @media (max-width: 600px) {
        .bracket-container {
            flex-direction: column;
        }
        .bracket-round {
            min-width: auto;
        }
        .round-fixtures {
            min-height: auto;
        }
    }
    </style>
</body>
</html>
