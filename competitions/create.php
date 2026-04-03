<?php
/**
 * Create Competition Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/GameMatch.php';
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

if (!Competition::canCreate($playerId, $clubId)) {
    Auth::flash('error', 'Not authorized to create competitions');
    header('Location: index.php?club=' . $clubId);
    exit;
}

$club = Club::find($clubId);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: ../clubs/index.php');
    exit;
}

$gameTypes = GameMatch::getGameTypes();

Template::pageHead('New Competition', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('New Competition', 'index.php?club=' . $clubId); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>
            <?php Template::formError(); ?>

            <form id="createForm" class="form">
                <input type="hidden" name="club_id" value="<?= $clubId ?>">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="name">Competition Name</label>
                    <input type="text" id="name" name="name" required maxlength="150"
                           placeholder="e.g., Summer Singles Championship">
                </div>

                <div class="form-group">
                    <label for="description">Description (optional)</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Details about the competition..."></textarea>
                </div>

                <div class="form-group">
                    <label>Competition Format</label>
                    <div class="toggle-group format-group">
                        <button type="button" class="toggle-btn active" data-value="knockout">
                            <span class="toggle-title">Knockout</span>
                            <span class="toggle-desc">Single elimination bracket</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="round_robin">
                            <span class="toggle-title">Round Robin</span>
                            <span class="toggle-desc">Everyone plays everyone</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="combined">
                            <span class="toggle-title">Combined</span>
                            <span class="toggle-desc">Groups then knockout</span>
                        </button>
                    </div>
                    <input type="hidden" name="format" value="knockout">
                </div>

                <div class="form-group">
                    <label>Game Type</label>
                    <div class="toggle-group game-type-group">
                        <?php $first = true; foreach ($gameTypes as $type => $config): ?>
                        <button type="button" class="toggle-btn<?= $first ? ' active' : '' ?>" data-value="<?= $type ?>">
                            <span class="toggle-title"><?= ucfirst($type) ?></span>
                            <span class="toggle-desc"><?= $config['players_per_team'] ?> player<?= $config['players_per_team'] > 1 ? 's' : '' ?></span>
                        </button>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <input type="hidden" name="game_type" value="singles">
                </div>

                <!-- Round Robin / Combined Options -->
                <div id="groupOptions" class="form-group" style="display: none;">
                    <label for="group_count">Number of Groups</label>
                    <select id="group_count" name="group_count">
                        <option value="">No groups (single round robin)</option>
                        <option value="2">2 Groups</option>
                        <option value="4">4 Groups</option>
                        <option value="8">8 Groups</option>
                    </select>
                </div>

                <div id="qualifierOptions" class="form-group" style="display: none;">
                    <label for="knockout_qualifiers">Qualifiers per Group</label>
                    <select id="knockout_qualifiers" name="knockout_qualifiers">
                        <option value="2">Top 2</option>
                        <option value="4">Top 4</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Scoring Mode</label>
                    <div class="toggle-group scoring-group">
                        <button type="button" class="toggle-btn active" data-value="ends">
                            <span class="toggle-title">By Ends</span>
                            <span class="toggle-desc">Play fixed number of ends</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="first_to">
                            <span class="toggle-title">First To</span>
                            <span class="toggle-desc">First to reach points</span>
                        </button>
                    </div>
                    <input type="hidden" name="scoring_mode" value="ends">
                </div>

                <div class="form-group">
                    <label for="target_score" id="targetLabel">Number of Ends</label>
                    <input type="number" id="target_score" name="target_score" value="21" min="1" max="50">
                </div>

                <div class="form-group">
                    <label for="max_participants">Max Participants (optional)</label>
                    <input type="number" id="max_participants" name="max_participants" placeholder="Leave empty for unlimited" min="2" max="128">
                </div>

                <button type="submit" class="btn-primary btn-block" id="submitBtn">Create Competition</button>
            </form>
        </main>
    </div>

    <style>
    .form { padding: 0; }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--text-primary);
    }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        background: var(--surface);
    }
    .toggle-group {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 0.5rem;
    }
    .toggle-btn {
        padding: 0.75rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        background: var(--surface);
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .toggle-btn.active {
        border-color: var(--primary);
        background: rgba(45, 80, 22, 0.1);
    }
    .toggle-title {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
    }
    .toggle-desc {
        display: block;
        font-size: 0.75rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }
    .btn-block {
        width: 100%;
        padding: 1rem;
        font-size: 1rem;
        margin-top: 1rem;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('createForm');

        // Toggle button groups
        document.querySelectorAll('.toggle-group').forEach(group => {
            group.querySelectorAll('.toggle-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    group.querySelectorAll('.toggle-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');

                    // Update hidden input
                    const hiddenInput = group.nextElementSibling;
                    if (hiddenInput && hiddenInput.type === 'hidden') {
                        hiddenInput.value = this.dataset.value;
                    }

                    // Handle format-specific options
                    if (group.classList.contains('format-group')) {
                        updateFormatOptions(this.dataset.value);
                    }

                    // Handle scoring mode label
                    if (group.classList.contains('scoring-group')) {
                        document.getElementById('targetLabel').textContent =
                            this.dataset.value === 'ends' ? 'Number of Ends' : 'Points Target';
                    }
                });
            });
        });

        function updateFormatOptions(format) {
            const groupOptions = document.getElementById('groupOptions');
            const qualifierOptions = document.getElementById('qualifierOptions');

            if (format === 'round_robin') {
                groupOptions.style.display = 'block';
                qualifierOptions.style.display = 'none';
            } else if (format === 'combined') {
                groupOptions.style.display = 'block';
                qualifierOptions.style.display = 'block';
                document.getElementById('group_count').value = '2';
            } else {
                groupOptions.style.display = 'none';
                qualifierOptions.style.display = 'none';
            }
        }

        // Form submission
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';

            try {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData);

                const res = await fetch('../api/competition.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    window.location.href = 'manage.php?id=' + result.id;
                } else {
                    document.getElementById('formError').textContent = result.error || 'Failed to create';
                    document.getElementById('formError').style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Competition';
                }
            } catch (err) {
                document.getElementById('formError').textContent = 'Network error';
                document.getElementById('formError').style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Competition';
            }
        });
    });
    </script>
</body>
</html>
