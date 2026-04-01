<?php
/**
 * Create Match Page - Setup new match form
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';

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

// Verify permissions
if (!GameMatch::canCreate($playerId, $clubId)) {
    Auth::flash('error', 'Only club admins can create matches');
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
$members = Club::getMembers($clubId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - New Match</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .form-section {
            background: var(--white);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .form-section h3 {
            margin: 0 0 1rem;
            font-size: 1rem;
            color: var(--green-dark);
        }
        .form-row {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }
        .form-group {
            flex: 1;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.9rem;
        }
        .team-section {
            border: 2px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .team-section.team-1 { border-color: #3498db; }
        .team-section.team-2 { border-color: #e74c3c; }
        .team-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .team-header h4 {
            margin: 0;
            font-size: 0.9rem;
        }
        .team-1 .team-header h4 { color: #3498db; }
        .team-2 .team-header h4 { color: #e74c3c; }
        .position-row {
            margin-bottom: 0.5rem;
        }
        .position-row:last-child {
            margin-bottom: 0;
        }
        .position-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 0.25rem;
        }
        .hidden { display: none; }
        .game-type-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        .game-type-btn {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--white);
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .game-type-btn:hover {
            border-color: var(--green-medium);
        }
        .game-type-btn.active {
            border-color: var(--green-dark);
            background: var(--green-light);
        }
        .game-type-btn .name {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        .game-type-btn .desc {
            font-size: 0.7rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }
        .btn-start {
            width: 100%;
            padding: 1rem;
            font-size: 1rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php?club=<?= $clubId ?>" class="back-btn">&larr;</a>
            <h1 class="app-title">New Match</h1>
            <span></span>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <form id="matchForm">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="club_id" value="<?= $clubId ?>">
                <input type="hidden" name="game_type" id="gameTypeInput" value="singles">

                <!-- Game Type Selection -->
                <div class="form-section">
                    <h3>Game Type</h3>
                    <div class="game-type-grid">
                        <div class="game-type-btn active" data-type="singles">
                            <div class="name">Singles</div>
                            <div class="desc">1v1 · 4 bowls</div>
                        </div>
                        <div class="game-type-btn" data-type="pairs">
                            <div class="name">Pairs</div>
                            <div class="desc">2v2 · 3-4 bowls</div>
                        </div>
                        <div class="game-type-btn" data-type="trips">
                            <div class="name">Trips</div>
                            <div class="desc">3v3 · 2-3 bowls</div>
                        </div>
                        <div class="game-type-btn" data-type="fours">
                            <div class="name">Fours</div>
                            <div class="desc">4v4 · 2 bowls</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Bowls per Player</label>
                            <select name="bowls_per_player" id="bowlsSelect">
                                <option value="4">4 bowls</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Number of Ends</label>
                            <select name="total_ends">
                                <option value="15">15 ends</option>
                                <option value="18">18 ends</option>
                                <option value="21" selected>21 ends</option>
                                <option value="25">25 ends</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Team 1 -->
                <div class="team-section team-1">
                    <div class="team-header">
                        <h4>Team 1</h4>
                    </div>
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label>Team Name</label>
                        <input type="text" name="team1_name" placeholder="Team 1">
                    </div>
                    <div class="position-row" data-position="skip">
                        <div class="position-label">Skip</div>
                        <input type="text" name="team1_skip" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="third">
                        <div class="position-label">Third</div>
                        <input type="text" name="team1_third" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="second">
                        <div class="position-label">Second</div>
                        <input type="text" name="team1_second" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="lead">
                        <div class="position-label">Lead</div>
                        <input type="text" name="team1_lead" placeholder="Player name" list="memberList">
                    </div>
                </div>

                <!-- Team 2 -->
                <div class="team-section team-2">
                    <div class="team-header">
                        <h4>Team 2</h4>
                    </div>
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label>Team Name</label>
                        <input type="text" name="team2_name" placeholder="Team 2">
                    </div>
                    <div class="position-row" data-position="skip">
                        <div class="position-label">Skip</div>
                        <input type="text" name="team2_skip" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="third">
                        <div class="position-label">Third</div>
                        <input type="text" name="team2_third" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="second">
                        <div class="position-label">Second</div>
                        <input type="text" name="team2_second" placeholder="Player name" list="memberList">
                    </div>
                    <div class="position-row hidden" data-position="lead">
                        <div class="position-label">Lead</div>
                        <input type="text" name="team2_lead" placeholder="Player name" list="memberList">
                    </div>
                </div>

                <!-- Member datalist for autocomplete -->
                <datalist id="memberList">
                    <?php foreach ($members as $member): ?>
                    <option value="<?= htmlspecialchars($member['name']) ?>">
                    <?php endforeach; ?>
                </datalist>

                <button type="submit" class="btn-primary btn-start" id="startBtn">Create Match</button>
            </form>
        </main>
    </div>

    <script>
    const GAME_TYPES = <?= json_encode($gameTypes) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('matchForm');
        const gameTypeInput = document.getElementById('gameTypeInput');
        const bowlsSelect = document.getElementById('bowlsSelect');
        const gameTypeBtns = document.querySelectorAll('.game-type-btn');

        function updateFormForGameType(type) {
            const config = GAME_TYPES[type];
            if (!config) return;

            // Update game type input
            gameTypeInput.value = type;

            // Update active button
            gameTypeBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.type === type);
            });

            // Update bowls options
            bowlsSelect.innerHTML = '';
            config.allowed_bowls.forEach(bowls => {
                const opt = document.createElement('option');
                opt.value = bowls;
                opt.textContent = bowls + ' bowls';
                if (bowls === config.default_bowls) opt.selected = true;
                bowlsSelect.appendChild(opt);
            });

            // Show/hide position rows
            const allPositions = ['skip', 'third', 'second', 'lead'];
            document.querySelectorAll('.position-row').forEach(row => {
                const pos = row.dataset.position;
                const visible = config.positions.includes(pos);
                row.classList.toggle('hidden', !visible);
            });
        }

        // Game type button click
        gameTypeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                updateFormForGameType(btn.dataset.type);
            });
        });

        // Form submit
        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('startBtn');
            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const formData = new FormData(form);

                const res = await fetch('../api/match.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'score.php?id=' + data.match_id;
                } else {
                    alert(data.error || 'Failed to create match');
                    btn.disabled = false;
                    btn.textContent = 'Create Match';
                }
            } catch (err) {
                alert('Network error');
                btn.disabled = false;
                btn.textContent = 'Create Match';
            }
        });

        // Initialize
        updateFormForGameType('singles');
    });
    </script>
</body>
</html>
