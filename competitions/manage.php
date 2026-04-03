<?php
/**
 * Competition Management Page (Admin)
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionParticipant.php';
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

$competition = Competition::getWithDetails($id);
if (!$competition) {
    Auth::flash('error', 'Competition not found');
    header('Location: ../clubs/index.php');
    exit;
}

if (!Competition::canManage($playerId, $id)) {
    Auth::flash('error', 'Not authorized to manage this competition');
    header('Location: view.php?id=' . $id);
    exit;
}

$club = Club::find($competition['club_id']);

// Get pending fixtures (ready to create matches)
$pendingFixtures = CompetitionFixture::getUpcoming($id, 20);

Template::pageHead('Manage Competition', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Manage', 'view.php?id=' . $id); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>
            <?php Template::formError(); ?>
            <?php Template::formMessage(); ?>

            <div class="info-card">
                <h2><?= htmlspecialchars($competition['name']) ?></h2>
                <div class="status-row">
                    <span class="status-badge <?= $competition['status'] ?>"><?= Competition::getStatusLabel($competition['status']) ?></span>
                    <span class="meta"><?= $competition['participant_count'] ?> participants</span>
                </div>
            </div>

            <!-- Status Actions -->
            <div class="action-section">
                <h3>Competition Status</h3>
                <div class="action-buttons">
                    <?php if ($competition['status'] === 'draft'): ?>
                    <button class="btn-action" id="openRegBtn">Open Registration</button>
                    <button class="btn-action primary" id="generateBtn">Generate Fixtures</button>
                    <button class="btn-action danger" id="deleteBtn">Delete</button>
                    <?php elseif ($competition['status'] === 'registration'): ?>
                    <button class="btn-action" id="closeRegBtn">Close Registration</button>
                    <button class="btn-action primary" id="startBtn">Start Competition</button>
                    <?php elseif ($competition['status'] === 'in_progress'): ?>
                    <button class="btn-action primary" id="completeBtn">Mark Completed</button>
                    <button class="btn-action danger" id="cancelBtn">Cancel</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants Management -->
            <div class="action-section">
                <h3>Participants (<?= $competition['participant_count'] ?>)</h3>
                <?php if ($competition['participants']): ?>
                <div class="participant-list">
                    <?php foreach ($competition['participants'] as $i => $p): ?>
                    <div class="participant-row">
                        <span class="seed"><?= $p['seed'] ?? ($i + 1) ?></span>
                        <span class="name"><?= htmlspecialchars($p['display_name']) ?></span>
                        <?php if ($competition['status'] !== 'in_progress'): ?>
                        <button class="btn-small danger" onclick="withdrawParticipant(<?= $p['id'] ?>)">Remove</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="empty-text">No participants registered yet</p>
                <?php endif; ?>
            </div>

            <!-- Fixture Management -->
            <?php if ($competition['status'] === 'in_progress' && $pendingFixtures): ?>
            <div class="action-section">
                <h3>Ready to Play</h3>
                <p class="help-text">Create matches for these fixtures:</p>
                <div class="fixture-list">
                    <?php foreach ($pendingFixtures as $f):
                        $fixture = CompetitionFixture::findWithDetails($f['id']);
                    ?>
                    <div class="fixture-row">
                        <div class="fixture-info">
                            <span class="stage"><?= CompetitionFixture::getStageName($fixture['stage']) ?></span>
                            <span class="teams"><?= htmlspecialchars($fixture['participant1_name']) ?> vs <?= htmlspecialchars($fixture['participant2_name']) ?></span>
                        </div>
                        <?php if ($fixture['match_id']): ?>
                        <a href="../matches/score.php?id=<?= $fixture['match_id'] ?>" class="btn-small">Score</a>
                        <?php else: ?>
                        <button class="btn-small primary" onclick="createMatch(<?= $fixture['id'] ?>)">Create Match</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Combined Format: Generate Knockout -->
            <?php if ($competition['format'] === 'combined' && $competition['status'] === 'in_progress'): ?>
            <div class="action-section">
                <h3>Knockout Stage</h3>
                <button class="btn-action primary" id="generateKnockoutBtn">Generate Knockout Bracket</button>
                <p class="help-text">Generate after group stage is complete</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <style>
    .info-card {
        background: var(--surface);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        border: 1px solid var(--border);
    }
    .info-card h2 {
        margin: 0 0 0.5rem;
        font-size: 1.2rem;
    }
    .status-row {
        display: flex;
        gap: 0.75rem;
        align-items: center;
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
    .meta { color: var(--text-secondary); font-size: 0.85rem; }

    .action-section {
        background: var(--surface);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1rem;
        border: 1px solid var(--border);
    }
    .action-section h3 {
        margin: 0 0 0.75rem;
        font-size: 1rem;
    }
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .btn-action {
        padding: 0.6rem 1rem;
        border: 1px solid var(--border);
        border-radius: 6px;
        background: var(--surface);
        cursor: pointer;
        font-size: 0.9rem;
    }
    .btn-action.primary {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .btn-action.danger {
        color: #c62828;
        border-color: #c62828;
    }

    .participant-list, .fixture-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .participant-row, .fixture-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem;
        background: var(--bg-muted);
        border-radius: 6px;
    }
    .participant-row .seed {
        width: 24px;
        height: 24px;
        background: var(--surface);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .participant-row .name { flex: 1; }
    .fixture-row .fixture-info { flex: 1; }
    .fixture-row .stage {
        display: block;
        font-size: 0.7rem;
        color: var(--text-secondary);
        text-transform: uppercase;
    }
    .fixture-row .teams { font-weight: 500; font-size: 0.9rem; }

    .btn-small {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 4px;
        background: var(--surface);
        cursor: pointer;
    }
    .btn-small.primary {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    .btn-small.danger {
        color: #c62828;
        border-color: #c62828;
    }

    .help-text {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin: 0.5rem 0 0;
    }
    .empty-text {
        color: var(--text-secondary);
        font-style: italic;
    }
    </style>

    <script>
    const competitionId = <?= $id ?>;

    async function apiCall(action, data = {}) {
        try {
            const res = await fetch('../api/competition.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, id: competitionId, ...data })
            });
            return await res.json();
        } catch (err) {
            return { success: false, error: 'Network error' };
        }
    }

    function showMessage(msg, isError = false) {
        const el = document.getElementById(isError ? 'formError' : 'formMessage');
        el.textContent = msg;
        el.style.display = 'block';
        if (!isError) {
            setTimeout(() => el.style.display = 'none', 3000);
        }
    }

    document.getElementById('openRegBtn')?.addEventListener('click', async () => {
        if (!confirm('Open registration?')) return;
        const result = await apiCall('open_registration');
        if (result.success) location.reload();
        else showMessage(result.error, true);
    });

    document.getElementById('closeRegBtn')?.addEventListener('click', async () => {
        if (!confirm('Close registration?')) return;
        const result = await apiCall('close_registration');
        if (result.success) location.reload();
        else showMessage(result.error, true);
    });

    document.getElementById('generateBtn')?.addEventListener('click', async () => {
        if (!confirm('Generate fixtures? This will finalize participant seeding.')) return;
        const btn = event.target;
        btn.disabled = true;
        btn.textContent = 'Generating...';
        const result = await apiCall('generate_fixtures');
        if (result.success) {
            showMessage('Generated ' + result.fixture_count + ' fixtures');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(result.error, true);
            btn.disabled = false;
            btn.textContent = 'Generate Fixtures';
        }
    });

    document.getElementById('startBtn')?.addEventListener('click', async () => {
        if (!confirm('Start competition? Registration will be closed.')) return;
        const result = await apiCall('start');
        if (result.success) location.reload();
        else showMessage(result.error, true);
    });

    document.getElementById('completeBtn')?.addEventListener('click', async () => {
        if (!confirm('Mark competition as completed?')) return;
        const result = await apiCall('complete');
        if (result.success) location.reload();
        else showMessage(result.error, true);
    });

    document.getElementById('cancelBtn')?.addEventListener('click', async () => {
        if (!confirm('Cancel this competition? This cannot be undone.')) return;
        const result = await apiCall('cancel');
        if (result.success) location.reload();
        else showMessage(result.error, true);
    });

    document.getElementById('deleteBtn')?.addEventListener('click', async () => {
        if (!confirm('Delete this competition? This cannot be undone.')) return;
        const res = await fetch('../api/competition.php?id=' + competitionId, { method: 'DELETE' });
        const result = await res.json();
        if (result.success) {
            window.location.href = 'index.php?club=<?= $competition['club_id'] ?>';
        } else {
            showMessage(result.error, true);
        }
    });

    document.getElementById('generateKnockoutBtn')?.addEventListener('click', async () => {
        if (!confirm('Generate knockout bracket from group stage results?')) return;
        const result = await apiCall('generate_knockout');
        if (result.success) {
            showMessage('Generated knockout bracket');
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(result.error, true);
        }
    });

    async function withdrawParticipant(participantId) {
        if (!confirm('Remove this participant?')) return;
        const result = await apiCall('withdraw', { participant_id: participantId });
        if (result.success) location.reload();
        else showMessage(result.error, true);
    }

    async function createMatch(fixtureId) {
        const result = await apiCall('create_match', { fixture_id: fixtureId });
        if (result.success) {
            window.location.href = '../matches/score.php?id=' + result.match_id;
        } else {
            showMessage(result.error, true);
        }
    }
    </script>
</body>
</html>
