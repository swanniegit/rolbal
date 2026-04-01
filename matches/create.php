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
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .form-section h3 {
            margin: 0 0 1rem;
            font-size: 1rem;
            color: #2d5016;
            font-weight: 600;
        }
        .game-type-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .game-type-btn {
            padding: 1rem 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #fff;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .game-type-btn:hover {
            border-color: #81c784;
            background: #f5fff5;
        }
        .game-type-btn.active {
            border-color: #2d5016;
            background: #e8f5e9;
        }
        .game-type-btn .name {
            font-weight: 700;
            font-size: 1rem;
            color: #333;
            display: block;
        }
        .game-type-btn .desc {
            font-size: 0.75rem;
            color: #888;
            margin-top: 0.25rem;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 0;
        }
        .form-group {
            margin-bottom: 0.75rem;
        }
        .form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: #666;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            background: #fff;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #2d5016;
        }
        .team-section {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .team-section.team-1 {
            border-left: 4px solid #3498db;
        }
        .team-section.team-2 {
            border-left: 4px solid #e74c3c;
        }
        .team-section h4 {
            margin: 0 0 1rem;
            font-size: 1rem;
            font-weight: 700;
        }
        .team-1 h4 { color: #3498db; }
        .team-2 h4 { color: #e74c3c; }
        .position-row {
            margin-bottom: 0.75rem;
        }
        .position-row:last-child {
            margin-bottom: 0;
        }
        .position-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 0.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .position-row input {
            width: 100%;
            padding: 0.65rem 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
        }
        .position-row input:focus {
            outline: none;
            border-color: #2d5016;
        }
        .hidden { display: none !important; }
        .btn-start {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 0.5rem;
            border-radius: 10px;
            background: #2d5016;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-start:hover {
            background: #1e3a0f;
        }
        .btn-start:disabled {
            background: #ccc;
            cursor: not-allowed;
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
                <input type="hidden" name="scoring_mode" id="scoringModeInput" value="first_to">

                <!-- Game Type Selection -->
                <div class="form-section">
                    <h3>Game Type</h3>
                    <div class="game-type-grid">
                        <div class="game-type-btn active" data-type="singles" data-mode="first_to">
                            <span class="name">Singles</span>
                            <span class="desc">1v1 · 4 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="pairs" data-mode="ends">
                            <span class="name">Pairs</span>
                            <span class="desc">2v2 · 3-4 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="trips" data-mode="ends">
                            <span class="name">Trips</span>
                            <span class="desc">3v3 · 2-3 bowls</span>
                        </div>
                        <div class="game-type-btn" data-type="fours" data-mode="ends">
                            <span class="name">Fours</span>
                            <span class="desc">4v4 · 2 bowls</span>
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
                            <label id="targetLabel">First to</label>
                            <select name="target_score" id="targetSelect">
                                <option value="21" selected>21 points</option>
                                <option value="25">25 points</option>
                                <option value="31">31 points</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Team 1 -->
                <div class="team-section team-1">
                    <h4>Team 1</h4>
                    <div class="form-group">
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
                    <h4>Team 2</h4>
                    <div class="form-group">
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

                <button type="submit" class="btn-start" id="startBtn">Create Match</button>
            </form>
        </main>
    </div>

    <script>
    const GAME_TYPES = <?= json_encode($gameTypes) ?>;

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('matchForm');
        const gameTypeInput = document.getElementById('gameTypeInput');
        const scoringModeInput = document.getElementById('scoringModeInput');
        const bowlsSelect = document.getElementById('bowlsSelect');
        const targetSelect = document.getElementById('targetSelect');
        const targetLabel = document.getElementById('targetLabel');
        const gameTypeBtns = document.querySelectorAll('.game-type-btn');

        function updateFormForGameType(type, mode) {
            const config = GAME_TYPES[type];
            if (!config) return;

            // Update hidden inputs
            gameTypeInput.value = type;
            scoringModeInput.value = mode;

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

            // Update target label and options based on scoring mode
            if (mode === 'first_to') {
                targetLabel.textContent = 'First to';
                targetSelect.innerHTML = `
                    <option value="21">21 points</option>
                    <option value="25">25 points</option>
                    <option value="31">31 points</option>
                `;
            } else {
                targetLabel.textContent = 'Number of Ends';
                targetSelect.innerHTML = `
                    <option value="15">15 ends</option>
                    <option value="18">18 ends</option>
                    <option value="21" selected>21 ends</option>
                    <option value="25">25 ends</option>
                `;
            }

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
                updateFormForGameType(btn.dataset.type, btn.dataset.mode);
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
        updateFormForGameType('singles', 'first_to');
    });
    </script>
</body>
</html>
