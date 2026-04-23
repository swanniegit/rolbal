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

Template::pageHead('New Competition', ['../css/pages/competition-create.css'], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('New Competition', 'index.php?club=' . $clubId); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>
            <?php Template::formError(); ?>

            <form id="createForm">
                <input type="hidden" name="club_id" value="<?= $clubId ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="csrf_token" value="<?= Auth::generateCsrfToken() ?>">

                <!-- Name & Description -->
                <div class="form-section">
                    <h3>Details</h3>
                    <div class="form-group">
                        <label for="name">Competition Name</label>
                        <input type="text" id="name" name="name" required maxlength="150"
                               placeholder="e.g., Summer Singles Championship">
                    </div>
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <textarea id="description" name="description" rows="2"
                                  placeholder="Details about the competition..."></textarea>
                    </div>
                </div>

                <!-- Format -->
                <div class="form-section">
                    <h3>Format</h3>
                    <div class="toggle-group cols-3 format-group">
                        <button type="button" class="toggle-btn active" data-value="knockout">
                            <span class="toggle-title">Knockout</span>
                            <span class="toggle-desc">Elimination bracket</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="round_robin">
                            <span class="toggle-title">Round Robin</span>
                            <span class="toggle-desc">Everyone plays all</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="combined">
                            <span class="toggle-title">Combined</span>
                            <span class="toggle-desc">Groups + knockout</span>
                        </button>
                    </div>
                    <input type="hidden" name="format" value="knockout">

                    <!-- Round Robin / Combined options -->
                    <div id="sectionOptions" class="options-section" style="display:none; margin-top: 1rem;">
                        <div class="form-group">
                            <label for="group_count">Number of Sections</label>
                            <select id="group_count" name="group_count">
                                <option value="">No sections (single round robin)</option>
                                <option value="2">2 Sections</option>
                                <option value="4">4 Sections</option>
                                <option value="6">6 Sections</option>
                                <option value="7">7 Sections</option>
                                <option value="8">8 Sections</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="teams_per_section">Teams per Section</label>
                            <select id="teams_per_section" name="teams_per_section">
                                <option value="3">3 Teams</option>
                                <option value="4" selected>4 Teams</option>
                                <option value="5">5 Teams</option>
                                <option value="6">6 Teams</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="rink_count">Available Rinks</label>
                            <select id="rink_count" name="rink_count">
                                <option value="4">4 Rinks</option>
                                <option value="5">5 Rinks</option>
                                <option value="6" selected>6 Rinks</option>
                                <option value="7">7 Rinks</option>
                                <option value="8">8 Rinks</option>
                            </select>
                            <span class="help-text">Used for scheduling concurrent matches</span>
                        </div>
                    </div>

                    <div id="qualifierOptions" style="display:none; margin-top: 1rem;">
                        <div class="form-group">
                            <label for="qualifiers_per_section">Knockout Qualifiers per Section</label>
                            <select id="qualifiers_per_section" name="qualifiers_per_section">
                                <option value="1">Winner only</option>
                                <option value="2" selected>Top 2 (Winner + Runner Up)</option>
                                <option value="3">Top 3</option>
                                <option value="4">Top 4</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Game Type -->
                <div class="form-section">
                    <h3>Game Type</h3>
                    <div class="toggle-group cols-4 game-type-group">
                        <?php $first = true; foreach ($gameTypes as $type => $config): ?>
                        <button type="button" class="toggle-btn<?= $first ? ' active' : '' ?>" data-value="<?= $type ?>">
                            <span class="toggle-title"><?= ucfirst($type) ?></span>
                            <span class="toggle-desc"><?= $config['players_per_team'] ?>v<?= $config['players_per_team'] ?></span>
                        </button>
                        <?php $first = false; endforeach; ?>
                    </div>
                    <input type="hidden" name="game_type" value="singles">
                </div>

                <!-- Scoring -->
                <div class="form-section">
                    <h3>Scoring</h3>
                    <div class="toggle-group cols-2 scoring-group">
                        <button type="button" class="toggle-btn active" data-value="ends">
                            <span class="toggle-title">By Ends</span>
                            <span class="toggle-desc">Fixed number of ends</span>
                        </button>
                        <button type="button" class="toggle-btn" data-value="first_to">
                            <span class="toggle-title">First To</span>
                            <span class="toggle-desc">First to reach points</span>
                        </button>
                    </div>
                    <input type="hidden" name="scoring_mode" value="ends">

                    <div class="form-group" style="margin-top: 1rem;">
                        <label for="target_score" id="targetLabel">Number of Ends</label>
                        <input type="number" id="target_score" name="target_score" value="21" min="1" max="50">
                    </div>
                </div>

                <!-- Settings -->
                <div class="form-section">
                    <h3>Settings</h3>
                    <div class="form-group">
                        <label for="max_participants">Max Participants</label>
                        <input type="number" id="max_participants" name="max_participants"
                               placeholder="Leave empty for unlimited" min="2" max="128">
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">Create Competition</button>
            </form>
        </main>
    </div>

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
            const sectionOptions = document.getElementById('sectionOptions');
            const qualifierOptions = document.getElementById('qualifierOptions');

            if (format === 'round_robin') {
                sectionOptions.style.display = 'block';
                qualifierOptions.style.display = 'none';
            } else if (format === 'combined') {
                sectionOptions.style.display = 'block';
                qualifierOptions.style.display = 'block';
                document.getElementById('group_count').value = '4';
            } else {
                sectionOptions.style.display = 'none';
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
