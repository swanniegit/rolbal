<?php
/**
 * Match Scorer Page - Interface for recording end scores
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();
$flash = Auth::getFlash();

if (!$isLoggedIn) {
    header('Location: ../login.php');
    exit;
}

$matchId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$matchId) {
    Auth::flash('error', 'Match ID required');
    header('Location: ../clubs/index.php');
    exit;
}

// Handle claim request
if (isset($_GET['claim'])) {
    if (GameMatch::canClaimScorer($playerId, $matchId)) {
        GameMatch::claimScorer($matchId, $playerId);
        Auth::flash('success', 'You are now the scorer for this match');
        header('Location: score.php?id=' . $matchId);
        exit;
    } else {
        Auth::flash('error', 'Cannot claim scorer - must be paid member and scorer not already claimed');
        header('Location: index.php?club=' . GameMatch::find($matchId)['club_id']);
        exit;
    }
}

// Verify permissions
if (!GameMatch::canScore($playerId, $matchId)) {
    Auth::flash('error', 'Not authorized to score this match');
    header('Location: view.php?id=' . $matchId);
    exit;
}

$match = GameMatch::findWithDetails($matchId);
if (!$match) {
    Auth::flash('error', 'Match not found');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($match['club_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Score Match</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .scoreboard {
            background: var(--green-dark);
            color: white;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .scoreboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-weight: 600;
        }
        .status-badge.setup { background: #ffa500; }
        .status-badge.live { background: #ff4444; animation: pulse 2s infinite; }
        .status-badge.completed { background: var(--green-light); color: var(--green-dark); }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        .teams-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .team-col {
            flex: 1;
            text-align: center;
        }
        .team-col.left { text-align: left; }
        .team-col.right { text-align: right; }
        .team-name {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
        }
        .team-score {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .vs-col {
            padding: 0 1rem;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        .end-info {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.85rem;
            opacity: 0.8;
        }
        .score-input-section {
            background: var(--white);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        .score-input-section h3 {
            margin: 0 0 1rem;
            text-align: center;
            color: var(--green-dark);
        }
        .team-select {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .team-btn {
            flex: 1;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--white);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .team-btn:hover {
            border-color: var(--green-medium);
        }
        .team-btn.active {
            border-color: var(--green-dark);
            background: var(--green-light);
        }
        .team-btn.team-1.active {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.1);
        }
        .team-btn.team-2.active {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }
        .shots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .shot-btn {
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--white);
            font-size: 1.25rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
        }
        .shot-btn:hover {
            border-color: var(--green-medium);
        }
        .shot-btn.active {
            border-color: var(--green-dark);
            background: var(--green-light);
            color: var(--green-dark);
        }
        .submit-row {
            display: flex;
            gap: 0.5rem;
        }
        .btn-submit-end {
            flex: 1;
            padding: 1rem;
            font-size: 1rem;
        }
        .btn-undo {
            padding: 1rem;
            background: var(--white);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
        }
        .btn-undo:hover {
            border-color: var(--green-medium);
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .action-buttons button {
            flex: 1;
            padding: 0.75rem;
        }
        .btn-start-match {
            background: var(--green-dark);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            font-size: 1rem;
            width: 100%;
            cursor: pointer;
        }
        .btn-complete {
            background: #27ae60;
        }
        .btn-delete {
            background: #e74c3c;
        }
        .hidden { display: none; }
        .ends-history {
            background: var(--white);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
        }
        .ends-history h4 {
            margin: 0 0 0.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .ends-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        .end-cell {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 4px;
            background: #f0f0f0;
        }
        .end-cell.team-1 { background: rgba(52, 152, 219, 0.2); color: #2980b9; }
        .end-cell.team-2 { background: rgba(231, 76, 60, 0.2); color: #c0392b; }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php?club=<?= $match['club_id'] ?>" class="back-btn">&larr;</a>
            <h1 class="app-title">Score Match</h1>
            <a href="view.php?id=<?= $matchId ?>" class="header-action">View</a>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Scoreboard -->
            <div class="scoreboard">
                <div class="scoreboard-header">
                    <span class="match-type"><?= ucfirst($match['game_type']) ?></span>
                    <span class="status-badge <?= $match['status'] ?>" id="statusBadge"><?= strtoupper($match['status']) ?></span>
                </div>
                <div class="teams-row">
                    <div class="team-col left">
                        <div class="team-name" id="team1Name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                        <div class="team-score" id="team1Score"><?= $match['team1_score'] ?></div>
                    </div>
                    <div class="vs-col">-</div>
                    <div class="team-col right">
                        <div class="team-name" id="team2Name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                        <div class="team-score" id="team2Score"><?= $match['team2_score'] ?></div>
                    </div>
                </div>
                <div class="end-info">
                    End <span id="currentEnd"><?= $match['current_end'] ?></span> of <?= $match['target_score'] ?? $match['total_ends'] ?? 21 ?>
                </div>
            </div>

            <!-- Ends History -->
            <div class="ends-history" id="endsHistory">
                <h4>Ends</h4>
                <div class="ends-grid" id="endsGrid">
                    <?php foreach ($match['ends'] as $end): ?>
                    <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Start Match (when in setup) -->
            <?php if ($match['status'] === 'setup'): ?>
            <button type="button" class="btn-start-match" id="startMatchBtn">Start Match</button>
            <?php endif; ?>

            <!-- Score Input (when live) -->
            <div class="score-input-section <?= $match['status'] !== 'live' ? 'hidden' : '' ?>" id="scoreSection">
                <h3>Record End <?= $match['current_end'] ?></h3>

                <div class="team-select">
                    <button type="button" class="team-btn team-1" data-team="1"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></button>
                    <button type="button" class="team-btn team-2" data-team="2"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></button>
                </div>

                <div class="shots-grid">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                    <button type="button" class="shot-btn" data-shots="<?= $i ?>"><?= $i ?></button>
                    <?php endfor; ?>
                </div>

                <div class="submit-row">
                    <button type="button" class="btn-undo" id="undoBtn" title="Undo last end">&#8630;</button>
                    <button type="button" class="btn-primary btn-submit-end" id="submitEndBtn" disabled>Submit End</button>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons <?= $match['status'] !== 'live' ? 'hidden' : '' ?>" id="actionSection">
                <button type="button" class="btn-primary btn-complete" id="completeBtn">End Match</button>
            </div>

            <?php if ($match['status'] === 'setup'): ?>
            <div class="action-buttons">
                <button type="button" class="btn-secondary btn-delete" id="deleteBtn">Delete Match</button>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    const MATCH_ID = <?= $matchId ?>;
    const TOTAL_ENDS = <?= $match['target_score'] ?? $match['total_ends'] ?? 21 ?>;
    let currentEnd = <?= $match['current_end'] ?>;
    let selectedTeam = null;
    let selectedShots = null;

    document.addEventListener('DOMContentLoaded', function() {
        const teamBtns = document.querySelectorAll('.team-btn');
        const shotBtns = document.querySelectorAll('.shot-btn');
        const submitBtn = document.getElementById('submitEndBtn');
        const undoBtn = document.getElementById('undoBtn');
        const startBtn = document.getElementById('startMatchBtn');
        const completeBtn = document.getElementById('completeBtn');
        const deleteBtn = document.getElementById('deleteBtn');

        // Team selection
        teamBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                teamBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedTeam = parseInt(btn.dataset.team);
                updateSubmitBtn();
            });
        });

        // Shots selection
        shotBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                shotBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                selectedShots = parseInt(btn.dataset.shots);
                updateSubmitBtn();
            });
        });

        function updateSubmitBtn() {
            submitBtn.disabled = !(selectedTeam && selectedShots);
        }

        // Submit end
        submitBtn.addEventListener('click', async () => {
            if (!selectedTeam || !selectedShots) return;

            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';

            try {
                const formData = new FormData();
                formData.append('action', 'end');
                formData.append('match_id', MATCH_ID);
                formData.append('end_number', currentEnd);
                formData.append('scoring_team', selectedTeam);
                formData.append('shots', selectedShots);

                const res = await fetch('../api/match.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    updateScoreboard(data);
                    clearSelection();
                } else {
                    alert(data.error || 'Failed to record end');
                }
            } catch (err) {
                alert('Network error');
            }

            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit End';
            updateSubmitBtn();
        });

        // Undo
        undoBtn.addEventListener('click', async () => {
            if (!confirm('Undo last end?')) return;

            undoBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('action', 'undo');
                formData.append('match_id', MATCH_ID);

                const res = await fetch('../api/match.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    updateScoreboard(data);
                    clearSelection();
                } else {
                    alert(data.error || 'Nothing to undo');
                }
            } catch (err) {
                alert('Network error');
            }

            undoBtn.disabled = false;
        });

        // Start match
        if (startBtn) {
            startBtn.addEventListener('click', async () => {
                startBtn.disabled = true;
                startBtn.textContent = 'Starting...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'start');
                    formData.append('match_id', MATCH_ID);

                    const res = await fetch('../api/match.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to start match');
                        startBtn.disabled = false;
                        startBtn.textContent = 'Start Match';
                    }
                } catch (err) {
                    alert('Network error');
                    startBtn.disabled = false;
                    startBtn.textContent = 'Start Match';
                }
            });
        }

        // Complete match
        if (completeBtn) {
            completeBtn.addEventListener('click', async () => {
                if (!confirm('End this match? This cannot be undone.')) return;

                completeBtn.disabled = true;
                completeBtn.textContent = 'Ending...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'complete');
                    formData.append('match_id', MATCH_ID);

                    const res = await fetch('../api/match.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success) {
                        window.location.href = 'view.php?id=' + MATCH_ID;
                    } else {
                        alert(data.error || 'Failed to end match');
                        completeBtn.disabled = false;
                        completeBtn.textContent = 'End Match';
                    }
                } catch (err) {
                    alert('Network error');
                    completeBtn.disabled = false;
                    completeBtn.textContent = 'End Match';
                }
            });
        }

        // Delete match
        if (deleteBtn) {
            deleteBtn.addEventListener('click', async () => {
                if (!confirm('Delete this match? This cannot be undone.')) return;

                deleteBtn.disabled = true;
                deleteBtn.textContent = 'Deleting...';

                try {
                    const res = await fetch('../api/match.php?id=' + MATCH_ID, { method: 'DELETE' });
                    const data = await res.json();

                    if (data.success) {
                        window.location.href = 'index.php?club=<?= $match['club_id'] ?>';
                    } else {
                        alert(data.error || 'Failed to delete match');
                        deleteBtn.disabled = false;
                        deleteBtn.textContent = 'Delete Match';
                    }
                } catch (err) {
                    alert('Network error');
                    deleteBtn.disabled = false;
                    deleteBtn.textContent = 'Delete Match';
                }
            });
        }

        function updateScoreboard(data) {
            document.getElementById('team1Score').textContent = data.team1_score;
            document.getElementById('team2Score').textContent = data.team2_score;
            document.getElementById('currentEnd').textContent = data.current_end;
            currentEnd = data.current_end;

            // Update ends grid
            const grid = document.getElementById('endsGrid');
            grid.innerHTML = '';
            data.ends.forEach(end => {
                const cell = document.createElement('div');
                cell.className = 'end-cell team-' + end.scoring_team;
                cell.textContent = end.shots;
                grid.appendChild(cell);
            });

            // Update section title
            document.querySelector('.score-input-section h3').textContent = 'Record End ' + currentEnd;
        }

        function clearSelection() {
            selectedTeam = null;
            selectedShots = null;
            teamBtns.forEach(b => b.classList.remove('active'));
            shotBtns.forEach(b => b.classList.remove('active'));
        }
    });
    </script>
</body>
</html>
