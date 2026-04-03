<?php
/**
 * Competition Registration Page
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/Competition.php';
require_once __DIR__ . '/../includes/CompetitionParticipant.php';
require_once __DIR__ . '/../includes/GameMatch.php';
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

if (!Competition::canRegister($playerId, $id)) {
    Auth::flash('error', 'Registration not available');
    header('Location: view.php?id=' . $id);
    exit;
}

if (CompetitionParticipant::isPlayerRegistered($id, $playerId)) {
    Auth::flash('info', 'You are already registered');
    header('Location: view.php?id=' . $id);
    exit;
}

$club = Club::find($competition['club_id']);
$members = Club::getMembers($competition['club_id']);
$gameConfig = GameMatch::GAME_TYPES[$competition['game_type']];
$positions = $gameConfig['positions'];
$isSingles = $competition['game_type'] === 'singles';

Template::pageHead('Register', [], '#2d5016', '../');
?>
<body>
    <div class="app-container">
        <?php Template::header('Register', 'view.php?id=' . $id); ?>

        <main class="main-content">
            <?php Template::flash($flash); ?>
            <?php Template::formError(); ?>

            <div class="competition-info">
                <h2><?= htmlspecialchars($competition['name']) ?></h2>
                <p><?= ucfirst($competition['game_type']) ?> - <?= Competition::getFormatLabel($competition['format']) ?></p>
            </div>

            <form id="registerForm" class="form">
                <input type="hidden" name="competition_id" value="<?= $id ?>">
                <input type="hidden" name="action" value="register">

                <?php if (!$isSingles): ?>
                <div class="form-group">
                    <label for="team_name">Team Name (optional)</label>
                    <input type="text" id="team_name" name="team_name" placeholder="e.g., The Rolling Stones">
                </div>
                <?php endif; ?>

                <h3 class="section-title"><?= $isSingles ? 'Player' : 'Team Members' ?></h3>

                <?php foreach ($positions as $position): ?>
                <div class="form-group">
                    <label for="player_<?= $position ?>"><?= ucfirst($position) ?></label>
                    <select id="player_<?= $position ?>" name="players[<?= $position ?>]" required class="player-select">
                        <option value="">Select player...</option>
                        <?php foreach ($members as $member): ?>
                        <option value="<?= $member['id'] ?>" <?= $member['id'] == $playerId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($member['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>

                <button type="submit" class="btn-primary btn-block" id="submitBtn">Register</button>
            </form>
        </main>
    </div>

    <style>
    .competition-info {
        background: var(--surface);
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 1.5rem;
        text-align: center;
        border: 1px solid var(--border);
    }
    .competition-info h2 {
        margin: 0 0 0.25rem;
        font-size: 1.2rem;
    }
    .competition-info p {
        margin: 0;
        color: var(--text-secondary);
        font-size: 0.9rem;
    }
    .form-group { margin-bottom: 1.25rem; }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
    }
    .form-group select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 1rem;
        background: var(--surface);
    }
    .section-title {
        font-size: 0.9rem;
        color: var(--text-secondary);
        margin: 1.5rem 0 1rem;
        text-transform: uppercase;
    }
    .btn-block {
        width: 100%;
        padding: 1rem;
        margin-top: 1rem;
    }
    .player-select.duplicate {
        border-color: #c62828;
        background: #fff5f5;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('registerForm');
        const playerSelects = document.querySelectorAll('.player-select');

        // Check for duplicate player selections
        function checkDuplicates() {
            const values = [];
            let hasDuplicate = false;

            playerSelects.forEach(select => {
                select.classList.remove('duplicate');
                if (select.value && values.includes(select.value)) {
                    select.classList.add('duplicate');
                    hasDuplicate = true;
                }
                if (select.value) values.push(select.value);
            });

            return hasDuplicate;
        }

        playerSelects.forEach(select => {
            select.addEventListener('change', checkDuplicates);
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            if (checkDuplicates()) {
                document.getElementById('formError').textContent = 'Each player can only be selected once';
                document.getElementById('formError').style.display = 'block';
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Registering...';

            try {
                // Build players array
                const players = [];
                playerSelects.forEach(select => {
                    const position = select.name.match(/\[(\w+)\]/)[1];
                    players.push({
                        player_id: parseInt(select.value),
                        position: position
                    });
                });

                const data = {
                    action: 'register',
                    competition_id: <?= $id ?>,
                    players: players,
                    team_name: document.getElementById('team_name')?.value || null
                };

                const res = await fetch('../api/competition.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await res.json();

                if (result.success) {
                    window.location.href = 'view.php?id=<?= $id ?>';
                } else {
                    document.getElementById('formError').textContent = result.error || 'Registration failed';
                    document.getElementById('formError').style.display = 'block';
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Register';
                }
            } catch (err) {
                document.getElementById('formError').textContent = 'Network error';
                document.getElementById('formError').style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register';
            }
        });
    });
    </script>
</body>
</html>
