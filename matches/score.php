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
$targetScore = $match['target_score'] ?? $match['total_ends'] ?? 21;
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            color: #333;
        }
        .header {
            background: #2d5016;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header a {
            color: white;
            text-decoration: none;
            font-size: 1.25rem;
        }
        .header h1 {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .header-action {
            background: rgba(255,255,255,0.2);
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .content {
            padding: 1rem;
            max-width: 500px;
            margin: 0 auto;
        }
        .flash {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .flash-success { background: #d4edda; color: #155724; }
        .flash-error { background: #f8d7da; color: #721c24; }
        .scoreboard {
            background: linear-gradient(135deg, #2d5016, #1e3a0f);
            color: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .scoreboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .match-type {
            font-size: 0.85rem;
            opacity: 0.9;
            text-transform: capitalize;
        }
        .status-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .status-badge.setup { background: #ff9800; color: white; }
        .status-badge.live { background: #f44336; color: white; animation: pulse 2s infinite; }
        .status-badge.completed { background: #4caf50; color: white; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .teams-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }
        .team-col {
            flex: 1;
        }
        .team-col.left { text-align: left; }
        .team-col.center { text-align: center; }
        .team-col.right { text-align: right; }
        .team-name {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.25rem;
        }
        .team-score {
            font-size: 2.75rem;
            font-weight: 800;
            line-height: 1;
        }
        .vs-col {
            font-size: 1.5rem;
            opacity: 0.5;
            padding: 0 0.5rem;
        }
        .end-info {
            text-align: center;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.2);
            font-size: 0.9rem;
            opacity: 0.85;
        }
        .ends-history {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .ends-history h4 {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .ends-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
        }
        .end-cell {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            background: #f0f0f0;
            color: #666;
        }
        .end-cell.team-1 { background: #e3f2fd; color: #1565c0; }
        .end-cell.team-2 { background: #ffebee; color: #c62828; }
        .score-section {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }
        .score-section h3 {
            text-align: center;
            color: #2d5016;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .team-select {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        .team-btn {
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            background: white;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .team-btn:hover { border-color: #aaa; }
        .team-btn.team-1.active {
            border-color: #1565c0;
            background: #e3f2fd;
            color: #1565c0;
        }
        .team-btn.team-2.active {
            border-color: #c62828;
            background: #ffebee;
            color: #c62828;
        }
        .shots-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        .shot-btn {
            padding: 1rem;
            border: 2px solid #ddd;
            border-radius: 10px;
            background: white;
            font-size: 1.25rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .shot-btn:hover { border-color: #aaa; }
        .shot-btn.active {
            border-color: #2d5016;
            background: #e8f5e9;
            color: #2d5016;
        }
        .submit-row {
            display: flex;
            gap: 0.5rem;
        }
        .btn-undo {
            padding: 1rem;
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 1.25rem;
            cursor: pointer;
        }
        .btn-undo:hover { border-color: #aaa; }
        .btn-submit {
            flex: 1;
            padding: 1rem;
            background: #2d5016;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-submit:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .btn-start {
            width: 100%;
            padding: 1rem;
            background: #2d5016;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 1rem;
        }
        .btn-start:hover { background: #1e3a0f; }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        .btn-complete {
            flex: 1;
            padding: 0.85rem;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-delete {
            flex: 1;
            padding: 0.85rem;
            background: white;
            color: #c62828;
            border: 2px solid #c62828;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
        }
        .hidden { display: none !important; }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php?club=<?= $match['club_id'] ?>">&larr;</a>
        <h1>Score Match</h1>
        <a href="view.php?id=<?= $matchId ?>" class="header-action">View</a>
    </div>

    <div class="content">
        <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <!-- Scoreboard -->
        <div class="scoreboard">
            <div class="scoreboard-header">
                <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                <span class="status-badge <?= $match['status'] ?>"><?= strtoupper($match['status']) ?></span>
            </div>
            <div class="teams-row">
                <div class="team-col left">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                    <div class="team-score" id="team1Score"><?= $match['team1_score'] ?></div>
                </div>
                <div class="team-col center vs-col">-</div>
                <div class="team-col right">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                    <div class="team-score" id="team2Score"><?= $match['team2_score'] ?></div>
                </div>
            </div>
            <div class="end-info">
                End <span id="currentEnd"><?= $match['current_end'] ?></span> of <?= $targetScore ?>
            </div>
        </div>

        <!-- Ends History -->
        <div class="ends-history">
            <h4>Ends</h4>
            <div class="ends-grid" id="endsGrid">
                <?php foreach ($match['ends'] as $end): ?>
                <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                <?php endforeach; ?>
                <?php if (empty($match['ends'])): ?>
                <span style="color: #999; font-size: 0.85rem;">No ends recorded yet</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Start Match (when in setup) -->
        <?php if ($match['status'] === 'setup'): ?>
        <button type="button" class="btn-start" id="startMatchBtn">Start Match</button>
        <div class="action-buttons">
            <button type="button" class="btn-delete" id="deleteBtn">Delete Match</button>
        </div>
        <?php endif; ?>

        <!-- Score Input (when live) -->
        <?php if ($match['status'] === 'live'): ?>
        <div class="score-section">
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
                <button type="button" class="btn-submit" id="submitEndBtn" disabled>Submit End</button>
            </div>
        </div>

        <div class="action-buttons">
            <button type="button" class="btn-complete" id="completeBtn">End Match</button>
        </div>
        <?php endif; ?>
    </div>

    <script>
    const MATCH_ID = <?= $matchId ?>;
    const TOTAL_ENDS = <?= $targetScore ?>;
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
            if (submitBtn) submitBtn.disabled = !(selectedTeam && selectedShots);
        }

        // Submit end
        if (submitBtn) {
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

                    if (data.success !== false) {
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
        }

        // Undo
        if (undoBtn) {
            undoBtn.addEventListener('click', async () => {
                if (!confirm('Undo last end?')) return;

                undoBtn.disabled = true;

                try {
                    const formData = new FormData();
                    formData.append('action', 'undo');
                    formData.append('match_id', MATCH_ID);

                    const res = await fetch('../api/match.php', { method: 'POST', body: formData });
                    const data = await res.json();

                    if (data.success !== false) {
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
        }

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

                    if (data.success !== false) {
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

                    if (data.success !== false) {
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

                    if (data.success !== false) {
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
            if (data.ends.length === 0) {
                grid.innerHTML = '<span style="color: #999; font-size: 0.85rem;">No ends recorded yet</span>';
            } else {
                grid.innerHTML = data.ends.map(end =>
                    `<div class="end-cell team-${end.scoring_team}">${end.shots}</div>`
                ).join('');
            }

            // Update section title
            const h3 = document.querySelector('.score-section h3');
            if (h3) h3.textContent = 'Record End ' + currentEnd;
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
