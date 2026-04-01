<?php
/**
 * Match Viewer Page - Traditional Scoreboard Layout
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

// Verify permissions
if (!GameMatch::canView($playerId, $matchId)) {
    Auth::flash('error', 'Not authorized to view this match');
    header('Location: ../clubs/index.php');
    exit;
}

$match = GameMatch::findWithDetails($matchId);
if (!$match) {
    Auth::flash('error', 'Match not found');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($match['club_id']);
$canScore = GameMatch::canScore($playerId, $matchId);

// Build running totals
$t1Shots = [];
$t2Shots = [];
$t1Running = [];
$t2Running = [];
$t1Total = 0;
$t2Total = 0;

foreach ($match['ends'] as $end) {
    $endNum = $end['end_number'];
    if ($end['scoring_team'] == 1) {
        $t1Shots[$endNum] = $end['shots'];
        $t2Shots[$endNum] = '-';
        $t1Total += $end['shots'];
    } else {
        $t1Shots[$endNum] = '-';
        $t2Shots[$endNum] = $end['shots'];
        $t2Total += $end['shots'];
    }
    $t1Running[$endNum] = $t1Total;
    $t2Running[$endNum] = $t2Total;
}

$totalEnds = $match['target_score'] ?? $match['total_ends'] ?? 21;
$scoringMode = $match['scoring_mode'] ?? 'ends';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Scorecard</title>
    <link rel="manifest" href="../manifest.json">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        .header {
            background: #2d5016;
            color: white;
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header a { color: white; text-decoration: none; }
        .header h1 { font-size: 1.1rem; }
        .live-badge {
            background: #ff4444;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
            animation: pulse 2s infinite;
        }
        .completed-badge {
            background: #4CAF50;
            padding: 0.25rem 0.6rem;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .scoreboard {
            background: white;
            margin: 1rem;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .team-section {
            border-bottom: 2px solid #333;
        }
        .team-section:last-child {
            border-bottom: none;
        }
        .team-header {
            background: #f8f9fa;
            padding: 0.75rem;
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid #ddd;
        }
        .team-name {
            font-weight: 700;
            font-size: 1rem;
        }
        .team-1 .team-name { color: #1565c0; }
        .team-2 .team-name { color: #c62828; }
        .players {
            font-size: 0.75rem;
            color: #666;
        }
        .score-row {
            display: flex;
            border-bottom: 1px solid #eee;
        }
        .score-row:last-child {
            border-bottom: none;
        }
        .row-label {
            width: 50px;
            padding: 0.5rem;
            background: #f8f9fa;
            font-size: 0.7rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            display: flex;
            align-items: center;
        }
        .score-cells {
            display: flex;
            flex: 1;
            overflow-x: auto;
        }
        .score-cell {
            min-width: 32px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-right: 1px solid #eee;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .score-cell:last-child {
            border-right: none;
        }
        .ends-row {
            background: #333;
            color: white;
        }
        .ends-row .row-label {
            background: #222;
            color: white;
        }
        .ends-row .score-cell {
            border-right-color: #444;
            font-weight: 700;
            font-size: 0.75rem;
        }
        .shots-row .score-cell {
            color: #333;
        }
        .shots-row .score-cell.dash {
            color: #ccc;
        }
        .total-row {
            background: #fafafa;
        }
        .total-row .score-cell {
            font-weight: 700;
        }
        .team-1 .total-row .score-cell { color: #1565c0; }
        .team-2 .total-row .score-cell { color: #c62828; }
        .final-score {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            gap: 1rem;
        }
        .final-team {
            text-align: center;
            flex: 1;
        }
        .final-team-name {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .final-team-score {
            font-size: 3rem;
            font-weight: 800;
        }
        .team-1 .final-team-score { color: #1565c0; }
        .team-2 .final-team-score { color: #c62828; }
        .final-vs {
            font-size: 1.5rem;
            color: #ccc;
        }
        .match-info {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            font-size: 0.8rem;
            color: #666;
        }
        .refresh-note {
            text-align: center;
            padding: 1rem;
            font-size: 0.75rem;
            color: #999;
        }
        .action-bar {
            padding: 0 1rem 1rem;
        }
        .action-bar a {
            display: block;
            text-align: center;
            padding: 0.75rem;
            background: #2d5016;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php?club=<?= $match['club_id'] ?>">&larr; Back</a>
        <h1>Scorecard</h1>
        <?php if ($match['status'] === 'live'): ?>
        <span class="live-badge">LIVE</span>
        <?php elseif ($match['status'] === 'completed'): ?>
        <span class="completed-badge">FINAL</span>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
    </div>

    <!-- Final Score Summary -->
    <div class="scoreboard">
        <div class="final-score">
            <div class="final-team team-1">
                <div class="final-team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                <div class="final-team-score" id="finalScore1"><?= $match['team1_score'] ?></div>
            </div>
            <div class="final-vs">-</div>
            <div class="final-team team-2">
                <div class="final-team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                <div class="final-team-score" id="finalScore2"><?= $match['team2_score'] ?></div>
            </div>
        </div>
        <div class="match-info">
            <?= ucfirst($match['game_type']) ?> ·
            <?php if ($scoringMode === 'first_to'): ?>
                First to <?= $totalEnds ?>
            <?php else: ?>
                End <?= $match['current_end'] ?> of <?= $totalEnds ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Traditional Scoreboard -->
    <div class="scoreboard" id="scoreboard">
        <!-- Team 1 -->
        <div class="team-section team-1">
            <div class="team-header">
                <span class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></span>
                <span class="players">
                    <?php
                    $players1 = array_map(fn($p) => $p['player_name'], $match['teams'][0]['players'] ?? []);
                    echo htmlspecialchars(implode(', ', $players1));
                    ?>
                </span>
            </div>
            <div class="score-row shots-row" id="t1Shots">
                <div class="row-label">Shots</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell <?= ($t1Shots[$i] ?? '-') === '-' ? 'dash' : '' ?>">
                        <?= $t1Shots[$i] ?? '-' ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="score-row total-row" id="t1Total">
                <div class="row-label">Total</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell"><?= $t1Running[$i] ?? '-' ?></div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Ends Row -->
        <div class="score-row ends-row" id="endsRow">
            <div class="row-label">Ends</div>
            <div class="score-cells">
                <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                <div class="score-cell"><?= $i ?></div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Team 2 -->
        <div class="team-section team-2">
            <div class="score-row shots-row" id="t2Shots">
                <div class="row-label">Shots</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell <?= ($t2Shots[$i] ?? '-') === '-' ? 'dash' : '' ?>">
                        <?= $t2Shots[$i] ?? '-' ?>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="score-row total-row" id="t2Total">
                <div class="row-label">Total</div>
                <div class="score-cells">
                    <?php for ($i = 1; $i <= max(count($match['ends']), 1); $i++): ?>
                    <div class="score-cell"><?= $t2Running[$i] ?? '-' ?></div>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="team-header">
                <span class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></span>
                <span class="players">
                    <?php
                    $players2 = array_map(fn($p) => $p['player_name'], $match['teams'][1]['players'] ?? []);
                    echo htmlspecialchars(implode(', ', $players2));
                    ?>
                </span>
            </div>
        </div>
    </div>

    <?php if ($match['status'] === 'live'): ?>
    <div class="refresh-note">Auto-refreshing every 5 seconds</div>
    <?php endif; ?>

    <?php if ($canScore && $match['status'] === 'live'): ?>
    <div class="action-bar">
        <a href="score.php?id=<?= $matchId ?>">Open Scorer</a>
    </div>
    <?php endif; ?>

    <?php if ($match['status'] === 'live'): ?>
    <script>
    const MATCH_ID = <?= $matchId ?>;

    async function refreshScores() {
        try {
            const res = await fetch('../api/match.php?action=scores&id=' + MATCH_ID);
            const data = await res.json();
            if (data.success !== false) {
                updateScoreboard(data);
            }
        } catch (err) {
            console.error('Refresh error:', err);
        }
    }

    function updateScoreboard(data) {
        if (data.status === 'completed') {
            location.reload();
            return;
        }

        // Build running totals
        let t1Total = 0, t2Total = 0;
        let t1Shots = {}, t2Shots = {}, t1Running = {}, t2Running = {};

        data.ends.forEach(end => {
            const num = end.end_number;
            if (end.scoring_team == 1) {
                t1Shots[num] = end.shots;
                t2Shots[num] = '-';
                t1Total += end.shots;
            } else {
                t1Shots[num] = '-';
                t2Shots[num] = end.shots;
                t2Total += end.shots;
            }
            t1Running[num] = t1Total;
            t2Running[num] = t2Total;
        });

        // Update final scores
        document.getElementById('finalScore1').textContent = t1Total;
        document.getElementById('finalScore2').textContent = t2Total;

        // Update rows
        const numEnds = data.ends.length || 1;

        let t1ShotsHtml = '<div class="row-label">Shots</div><div class="score-cells">';
        let t1TotalHtml = '<div class="row-label">Total</div><div class="score-cells">';
        let endsHtml = '<div class="row-label">Ends</div><div class="score-cells">';
        let t2ShotsHtml = '<div class="row-label">Shots</div><div class="score-cells">';
        let t2TotalHtml = '<div class="row-label">Total</div><div class="score-cells">';

        for (let i = 1; i <= numEnds; i++) {
            const t1s = t1Shots[i] || '-';
            const t2s = t2Shots[i] || '-';
            t1ShotsHtml += `<div class="score-cell ${t1s === '-' ? 'dash' : ''}">${t1s}</div>`;
            t1TotalHtml += `<div class="score-cell">${t1Running[i] || '-'}</div>`;
            endsHtml += `<div class="score-cell">${i}</div>`;
            t2ShotsHtml += `<div class="score-cell ${t2s === '-' ? 'dash' : ''}">${t2s}</div>`;
            t2TotalHtml += `<div class="score-cell">${t2Running[i] || '-'}</div>`;
        }

        t1ShotsHtml += '</div>';
        t1TotalHtml += '</div>';
        endsHtml += '</div>';
        t2ShotsHtml += '</div>';
        t2TotalHtml += '</div>';

        document.getElementById('t1Shots').innerHTML = t1ShotsHtml;
        document.getElementById('t1Total').innerHTML = t1TotalHtml;
        document.getElementById('endsRow').innerHTML = endsHtml;
        document.getElementById('t2Shots').innerHTML = t2ShotsHtml;
        document.getElementById('t2Total').innerHTML = t2TotalHtml;
    }

    setInterval(refreshScores, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
