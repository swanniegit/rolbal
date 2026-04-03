<?php
/**
 * Competition List Page - Shows competitions for a club
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/Template.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$clubId = isset($_GET['club']) ? (int)$_GET['club'] : 0;
if (!$clubId) {
    Auth::flash('error', 'Club ID required');
    header('Location: ../clubs/index.php');
    exit;
}

if (!ClubMember::isMember($clubId, $playerId)) {
    Auth::flash('error', 'You must be a club member to view competitions');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($clubId);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: ../clubs/index.php');
    exit;
}

$canCreate = Competition::canCreate($playerId, $clubId);
$activeCompetitions = Competition::listByClub($clubId, 'in_progress');
$registrationOpen = Competition::listByClub($clubId, 'registration');
$draftCompetitions = $canCreate ? Competition::listByClub($clubId, 'draft') : [];
$completedCompetitions = Competition::listByClub($clubId, 'completed', 10);

$rightHtml = $canCreate
    ? '<a href="create.php?club=' . $clubId . '" class="header-action">+ New</a>'
    : '<span></span>';

Template::pageHead('Competitions', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Competitions', '../clubs/view.php?slug=' . htmlspecialchars($club['slug']), $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <!-- Draft Competitions (admins only) -->
            <?php if ($draftCompetitions): ?>
            <h3 class="section-title">Draft</h3>
            <div class="competition-list">
                <?php foreach ($draftCompetitions as $comp): ?>
                <a href="manage.php?id=<?= $comp['id'] ?>" class="competition-card">
                    <div class="competition-header">
                        <span class="competition-format"><?= Competition::getFormatLabel($comp['format']) ?></span>
                        <span class="competition-status draft">Draft</span>
                    </div>
                    <div class="competition-name"><?= htmlspecialchars($comp['name']) ?></div>
                    <div class="competition-meta">
                        <span><?= ucfirst($comp['game_type']) ?></span>
                        <span><?= $comp['participant_count'] ?> participants</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Registration Open -->
            <?php if ($registrationOpen): ?>
            <h3 class="section-title">Registration Open</h3>
            <div class="competition-list">
                <?php foreach ($registrationOpen as $comp): ?>
                <a href="view.php?id=<?= $comp['id'] ?>" class="competition-card">
                    <div class="competition-header">
                        <span class="competition-format"><?= Competition::getFormatLabel($comp['format']) ?></span>
                        <span class="competition-status registration">Open</span>
                    </div>
                    <div class="competition-name"><?= htmlspecialchars($comp['name']) ?></div>
                    <div class="competition-meta">
                        <span><?= ucfirst($comp['game_type']) ?></span>
                        <span><?= $comp['participant_count'] ?><?= $comp['max_participants'] ? '/' . $comp['max_participants'] : '' ?> registered</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Active Competitions -->
            <h3 class="section-title">In Progress</h3>
            <?php if ($activeCompetitions): ?>
            <div class="competition-list">
                <?php foreach ($activeCompetitions as $comp): ?>
                <a href="view.php?id=<?= $comp['id'] ?>" class="competition-card">
                    <div class="competition-header">
                        <span class="competition-format"><?= Competition::getFormatLabel($comp['format']) ?></span>
                        <span class="competition-status live">Live</span>
                    </div>
                    <div class="competition-name"><?= htmlspecialchars($comp['name']) ?></div>
                    <div class="competition-meta">
                        <span><?= ucfirst($comp['game_type']) ?></span>
                        <span><?= $comp['participant_count'] ?> participants</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <?php Template::emptyState('No competitions in progress'); ?>
            <?php endif; ?>

            <!-- Completed Competitions -->
            <?php if ($completedCompetitions): ?>
            <h3 class="section-title">Completed</h3>
            <div class="competition-list">
                <?php foreach ($completedCompetitions as $comp): ?>
                <a href="view.php?id=<?= $comp['id'] ?>" class="competition-card">
                    <div class="competition-header">
                        <span class="competition-format"><?= Competition::getFormatLabel($comp['format']) ?></span>
                        <span class="competition-status completed">Completed</span>
                    </div>
                    <div class="competition-name"><?= htmlspecialchars($comp['name']) ?></div>
                    <div class="competition-meta">
                        <span><?= ucfirst($comp['game_type']) ?></span>
                        <span><?= $comp['participant_count'] ?> participants</span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
    .competition-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .competition-card {
        background: var(--surface);
        border-radius: 12px;
        padding: 1rem;
        text-decoration: none;
        color: inherit;
        border: 1px solid var(--border);
    }
    .competition-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    .competition-format {
        font-size: 0.75rem;
        color: var(--text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .competition-status {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-weight: 600;
    }
    .competition-status.draft { background: var(--bg-muted); color: var(--text-secondary); }
    .competition-status.registration { background: #e3f2fd; color: #1565c0; }
    .competition-status.live { background: #ffebee; color: #c62828; }
    .competition-status.completed { background: #e8f5e9; color: #2e7d32; }
    .competition-name {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .competition-meta {
        display: flex;
        gap: 1rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }
    .section-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin: 1rem 0 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    </style>
</body>
</html>
