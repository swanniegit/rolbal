<?php
/**
 * Competition View Page - Dashboard showing competition details
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionParticipant.php';
require_once __DIR__ . '/../includes/CompetitionFixture.php';
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

$competition = Competition::getWithDetails($id);
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

$club = Club::find($competition['club_id']);
$canManage = Competition::canManage($playerId, $id);
$canRegister = Competition::canRegister($playerId, $id);
$isRegistered = CompetitionParticipant::isPlayerRegistered($id, $playerId);

// Get fixtures
$upcomingFixtures = CompetitionFixture::getUpcoming($id, 5);
$liveFixtures = CompetitionFixture::getLive($id);

// Get groups if applicable
$groups = [];
if (in_array($competition['format'], ['round_robin', 'combined'])) {
    $groups = CompetitionRoundRobin::getGroups($id);
}

$rightHtml = $canManage
    ? '<a href="manage.php?id=' . $id . '" class="header-action">Manage</a>'
    : '<span></span>';

Template::pageHead($competition['name'], [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header($competition['name'], 'index.php?club=' . $competition['club_id'], $rightHtml); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>

            <!-- Competition Info -->
            <div class="info-card">
                <div class="info-row">
                    <span class="info-label">Format</span>
                    <span class="info-value"><?= Competition::getFormatLabel($competition['format']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Game Type</span>
                    <span class="info-value"><?= ucfirst($competition['game_type']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="status-badge <?= $competition['status'] ?>"><?= Competition::getStatusLabel($competition['status']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Participants</span>
                    <span class="info-value"><?= $competition['participant_count'] ?><?= $competition['max_participants'] ? '/' . $competition['max_participants'] : '' ?></span>
                </div>
                <?php if ($competition['description']): ?>
                <p class="description"><?= nl2br(htmlspecialchars($competition['description'])) ?></p>
                <?php endif; ?>
            </div>

            <!-- Registration CTA -->
            <?php if ($canRegister && !$isRegistered): ?>
            <a href="register.php?id=<?= $id ?>" class="btn-primary btn-block">Register Now</a>
            <?php elseif ($isRegistered && $competition['status'] === 'registration'): ?>
            <div class="registered-notice">You are registered for this competition</div>
            <?php endif; ?>

            <!-- Quick Links -->
            <div class="quick-links">
                <?php if ($competition['format'] === 'knockout' || ($competition['format'] === 'combined' && $competition['status'] === 'in_progress')): ?>
                <a href="bracket.php?id=<?= $id ?>" class="quick-link">
                    <span class="icon">&#127942;</span>
                    <span>View Bracket</span>
                </a>
                <?php endif; ?>

                <?php if (in_array($competition['format'], ['round_robin', 'combined'])): ?>
                <a href="sections.php?id=<?= $id ?>" class="quick-link">
                    <span class="icon">&#128203;</span>
                    <span>Sections</span>
                </a>
                <a href="standings.php?id=<?= $id ?>" class="quick-link">
                    <span class="icon">&#128202;</span>
                    <span>Standings</span>
                </a>
                <?php endif; ?>

                <a href="#participants" class="quick-link">
                    <span class="icon">&#128101;</span>
                    <span>Participants (<?= $competition['participant_count'] ?>)</span>
                </a>
            </div>

            <!-- Live Fixtures -->
            <?php if ($liveFixtures): ?>
            <h3 class="section-title">Live Now</h3>
            <div class="fixture-list">
                <?php foreach ($liveFixtures as $fixture):
                    $f = CompetitionFixture::findWithDetails($fixture['id']);
                ?>
                <div class="fixture-card live">
                    <div class="fixture-stage"><?= CompetitionFixture::getStageName($f['stage']) ?></div>
                    <div class="fixture-teams">
                        <span class="team"><?= htmlspecialchars($f['participant1_name']) ?></span>
                        <span class="score"><?= $f['score1'] ?? '-' ?> : <?= $f['score2'] ?? '-' ?></span>
                        <span class="team"><?= htmlspecialchars($f['participant2_name']) ?></span>
                    </div>
                    <?php if ($f['match_id']): ?>
                    <a href="../matches/view.php?id=<?= $f['match_id'] ?>" class="fixture-link">View Match</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Upcoming Fixtures -->
            <?php if ($upcomingFixtures): ?>
            <h3 class="section-title">Upcoming</h3>
            <div class="fixture-list">
                <?php foreach ($upcomingFixtures as $fixture):
                    $f = CompetitionFixture::findWithDetails($fixture['id']);
                ?>
                <div class="fixture-card">
                    <div class="fixture-stage"><?= CompetitionFixture::getStageName($f['stage']) ?></div>
                    <div class="fixture-teams">
                        <span class="team"><?= htmlspecialchars($f['participant1_name']) ?></span>
                        <span class="vs">vs</span>
                        <span class="team"><?= htmlspecialchars($f['participant2_name']) ?></span>
                    </div>
                    <?php if ($f['scheduled_at']): ?>
                    <div class="fixture-time"><?= date('M j, g:i A', strtotime($f['scheduled_at'])) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Participants -->
            <h3 class="section-title" id="participants">Participants</h3>
            <?php if ($competition['participants']): ?>
            <div class="participant-list">
                <?php foreach ($competition['participants'] as $i => $p): ?>
                <div class="participant-item">
                    <span class="seed"><?= $p['seed'] ?? ($i + 1) ?></span>
                    <span class="name"><?= htmlspecialchars($p['display_name']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <?php Template::emptyState('No participants yet'); ?>
            <?php endif; ?>
        </main>
    </div>

    <style>
    .info-card {
        background: var(--surface);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border);
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--border);
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--text-secondary); }
    .info-value { font-weight: 500; }
    .description {
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border);
        color: var(--text-secondary);
    }
    .status-badge {
        padding: 0.2rem 0.5rem;
        border-radius: 4px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .status-badge.draft { background: var(--bg-muted); }
    .status-badge.registration { background: #e3f2fd; color: #1565c0; }
    .status-badge.in_progress { background: #ffebee; color: #c62828; }
    .status-badge.completed { background: #e8f5e9; color: #2e7d32; }

    .btn-block {
        display: block;
        width: 100%;
        padding: 1rem;
        text-align: center;
        margin-bottom: 1rem;
    }
    .registered-notice {
        background: #e8f5e9;
        color: #2e7d32;
        padding: 1rem;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 1rem;
    }

    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }
    .quick-link {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        text-decoration: none;
        color: inherit;
    }
    .quick-link .icon {
        display: block;
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .section-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin: 1.5rem 0 0.75rem;
        text-transform: uppercase;
    }

    .fixture-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .fixture-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 0.75rem;
    }
    .fixture-card.live {
        border-color: #c62828;
        background: #fff5f5;
    }
    .fixture-stage {
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }
    .fixture-teams {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
    }
    .fixture-teams .team {
        flex: 1;
        font-weight: 500;
    }
    .fixture-teams .team:last-child { text-align: right; }
    .fixture-teams .vs, .fixture-teams .score {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }
    .fixture-teams .score { font-weight: 600; color: var(--text-primary); }
    .fixture-time {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 0.5rem;
    }
    .fixture-link {
        display: block;
        text-align: center;
        margin-top: 0.5rem;
        color: var(--primary);
        font-size: 0.85rem;
    }

    .participant-list {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .participant-item {
        display: flex;
        gap: 0.75rem;
        padding: 0.75rem;
        background: var(--surface);
        border-radius: 6px;
    }
    .participant-item .seed {
        width: 24px;
        height: 24px;
        background: var(--bg-muted);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .participant-item .name { font-weight: 500; }
    </style>
</body>
</html>
