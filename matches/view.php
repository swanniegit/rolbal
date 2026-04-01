<?php
/**
 * Match Viewer Page - Display live scoreboard with auto-refresh
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#2d5016">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - <?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?> vs <?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/styles.css">
    <style>
        .scoreboard-large {
            background: linear-gradient(135deg, var(--green-dark), #1a3d0c);
            color: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }
        .live-indicator {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .live-indicator.live {
            color: #ff4444;
        }
        .live-indicator.completed {
            color: #90EE90;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #ff4444;
            border-radius: 50%;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        .match-type-label {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 1rem;
        }
        .teams-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .team-block {
            flex: 1;
            text-align: center;
        }
        .team-block.left { text-align: left; }
        .team-block.right { text-align: right; }
        .team-label {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        .score-big {
            font-size: 4rem;
            font-weight: bold;
            line-height: 1;
        }
        .divider {
            font-size: 2rem;
            opacity: 0.5;
            padding: 0 1rem;
        }
        .end-display {
            text-align: center;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.2);
            font-size: 1rem;
        }
        .players-section {
            background: var(--white);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            gap: 1rem;
        }
        .players-col {
            flex: 1;
        }
        .players-col h4 {
            margin: 0 0 0.5rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .players-col.team-1 h4 { color: #3498db; }
        .players-col.team-2 h4 { color: #e74c3c; }
        .player-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        .player-row:last-child {
            border-bottom: none;
        }
        .player-position {
            color: var(--text-secondary);
            font-size: 0.7rem;
            text-transform: uppercase;
        }
        .ends-section {
            background: var(--white);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .ends-section h3 {
            margin: 0 0 1rem;
            font-size: 0.9rem;
            color: var(--green-dark);
        }
        .ends-table {
            width: 100%;
            overflow-x: auto;
        }
        .ends-row {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 0.25rem;
        }
        .ends-row.header .end-num {
            background: var(--green-light);
            color: var(--green-dark);
            font-weight: 600;
        }
        .end-num {
            min-width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .ends-row.team-1 .end-num { background: rgba(52, 152, 219, 0.15); }
        .ends-row.team-2 .end-num { background: rgba(231, 76, 60, 0.15); }
        .ends-row.team-1 .end-num.scored {
            background: #3498db;
            color: white;
            font-weight: 600;
        }
        .ends-row.team-2 .end-num.scored {
            background: #e74c3c;
            color: white;
            font-weight: 600;
        }
        .team-total {
            font-weight: bold;
            background: #333 !important;
            color: white !important;
        }
        .refresh-note {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: 1rem;
        }
        .refresh-note.updating {
            color: var(--green-dark);
        }
        .action-bar {
            margin-top: 1rem;
        }
        .action-bar a {
            display: block;
            text-align: center;
            padding: 0.75rem;
            background: var(--green-dark);
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header class="app-header compact">
            <a href="index.php?club=<?= $match['club_id'] ?>" class="back-btn">&larr;</a>
            <h1 class="app-title">Live Score</h1>
            <?php if ($canScore && $match['status'] === 'live'): ?>
            <a href="score.php?id=<?= $matchId ?>" class="header-action">Score</a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
        </header>

        <main class="main-content">
            <?php if ($flash): ?>
            <div class="flash flash-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Scoreboard -->
            <div class="scoreboard-large">
                <div class="live-indicator <?= $match['status'] ?>" id="liveIndicator">
                    <?php if ($match['status'] === 'live'): ?>
                    <span class="live-dot"></span>
                    <span>LIVE</span>
                    <?php elseif ($match['status'] === 'completed'): ?>
                    <span>FINAL</span>
                    <?php else: ?>
                    <span>SETUP</span>
                    <?php endif; ?>
                </div>

                <div class="match-type-label"><?= ucfirst($match['game_type']) ?> · <?= htmlspecialchars($club['name']) ?></div>

                <div class="teams-display">
                    <div class="team-block left">
                        <div class="team-label" id="team1Name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                        <div class="score-big" id="team1Score"><?= $match['team1_score'] ?></div>
                    </div>
                    <div class="divider">-</div>
                    <div class="team-block right">
                        <div class="team-label" id="team2Name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                        <div class="score-big" id="team2Score"><?= $match['team2_score'] ?></div>
                    </div>
                </div>

                <div class="end-display">
                    <?php if ($match['status'] === 'completed'): ?>
                    Match Complete
                    <?php else: ?>
                    End <span id="currentEnd"><?= $match['current_end'] ?></span> of <?= $match['total_ends'] ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Players -->
            <div class="players-section">
                <div class="players-col team-1">
                    <h4><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></h4>
                    <?php foreach ($match['teams'][0]['players'] ?? [] as $player): ?>
                    <div class="player-row">
                        <span><?= htmlspecialchars($player['player_name']) ?></span>
                        <span class="player-position"><?= ucfirst($player['position']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="players-col team-2">
                    <h4><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></h4>
                    <?php foreach ($match['teams'][1]['players'] ?? [] as $player): ?>
                    <div class="player-row">
                        <span><?= htmlspecialchars($player['player_name']) ?></span>
                        <span class="player-position"><?= ucfirst($player['position']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Ends Breakdown -->
            <div class="ends-section">
                <h3>Ends Breakdown</h3>
                <div class="ends-table" id="endsTable">
                    <!-- Header row -->
                    <div class="ends-row header">
                        <div class="end-num">End</div>
                        <?php for ($i = 1; $i <= min(count($match['ends']), $match['total_ends']); $i++): ?>
                        <div class="end-num"><?= $i ?></div>
                        <?php endfor; ?>
                        <div class="end-num team-total">Tot</div>
                    </div>
                    <!-- Team 1 row -->
                    <div class="ends-row team-1">
                        <div class="end-num"><?= htmlspecialchars(substr($match['teams'][0]['team_name'] ?? 'T1', 0, 3)) ?></div>
                        <?php
                        $t1Total = 0;
                        foreach ($match['ends'] as $end):
                            $isT1 = $end['scoring_team'] == 1;
                            $shots = $isT1 ? $end['shots'] : '-';
                            if ($isT1) $t1Total += $end['shots'];
                        ?>
                        <div class="end-num <?= $isT1 ? 'scored' : '' ?>"><?= $shots ?></div>
                        <?php endforeach; ?>
                        <div class="end-num team-total"><?= $t1Total ?></div>
                    </div>
                    <!-- Team 2 row -->
                    <div class="ends-row team-2">
                        <div class="end-num"><?= htmlspecialchars(substr($match['teams'][1]['team_name'] ?? 'T2', 0, 3)) ?></div>
                        <?php
                        $t2Total = 0;
                        foreach ($match['ends'] as $end):
                            $isT2 = $end['scoring_team'] == 2;
                            $shots = $isT2 ? $end['shots'] : '-';
                            if ($isT2) $t2Total += $end['shots'];
                        ?>
                        <div class="end-num <?= $isT2 ? 'scored' : '' ?>"><?= $shots ?></div>
                        <?php endforeach; ?>
                        <div class="end-num team-total"><?= $t2Total ?></div>
                    </div>
                </div>
            </div>

            <?php if ($match['status'] === 'live'): ?>
            <div class="refresh-note" id="refreshNote">Auto-refreshing every 5 seconds</div>
            <?php endif; ?>

            <?php if ($canScore && $match['status'] === 'live'): ?>
            <div class="action-bar">
                <a href="score.php?id=<?= $matchId ?>">Open Scorer</a>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <?php if ($match['status'] === 'live'): ?>
    <script>
    const MATCH_ID = <?= $matchId ?>;
    const TOTAL_ENDS = <?= $match['total_ends'] ?>;
    const TEAM1_ABBR = '<?= addslashes(substr($match['teams'][0]['team_name'] ?? 'T1', 0, 3)) ?>';
    const TEAM2_ABBR = '<?= addslashes(substr($match['teams'][1]['team_name'] ?? 'T2', 0, 3)) ?>';

    async function refreshScores() {
        const note = document.getElementById('refreshNote');
        note.textContent = 'Updating...';
        note.classList.add('updating');

        try {
            const res = await fetch('../api/match.php?action=scores&id=' + MATCH_ID);
            const data = await res.json();

            if (data.success) {
                updateDisplay(data);
            }
        } catch (err) {
            console.error('Refresh error:', err);
        }

        note.textContent = 'Auto-refreshing every 5 seconds';
        note.classList.remove('updating');
    }

    function updateDisplay(data) {
        // Update scores
        document.getElementById('team1Score').textContent = data.team1_score;
        document.getElementById('team2Score').textContent = data.team2_score;
        document.getElementById('currentEnd').textContent = data.current_end;

        // Check if match completed
        if (data.status === 'completed') {
            location.reload();
            return;
        }

        // Update ends table
        updateEndsTable(data.ends, data.team1_score, data.team2_score);
    }

    function updateEndsTable(ends, t1Total, t2Total) {
        const table = document.getElementById('endsTable');

        // Build header
        let headerHtml = '<div class="end-num">End</div>';
        for (let i = 1; i <= ends.length; i++) {
            headerHtml += '<div class="end-num">' + i + '</div>';
        }
        headerHtml += '<div class="end-num team-total">Tot</div>';

        // Build team 1 row
        let t1Html = '<div class="end-num">' + TEAM1_ABBR + '</div>';
        ends.forEach(end => {
            const isT1 = end.scoring_team == 1;
            t1Html += '<div class="end-num ' + (isT1 ? 'scored' : '') + '">' + (isT1 ? end.shots : '-') + '</div>';
        });
        t1Html += '<div class="end-num team-total">' + t1Total + '</div>';

        // Build team 2 row
        let t2Html = '<div class="end-num">' + TEAM2_ABBR + '</div>';
        ends.forEach(end => {
            const isT2 = end.scoring_team == 2;
            t2Html += '<div class="end-num ' + (isT2 ? 'scored' : '') + '">' + (isT2 ? end.shots : '-') + '</div>';
        });
        t2Html += '<div class="end-num team-total">' + t2Total + '</div>';

        table.innerHTML = `
            <div class="ends-row header">${headerHtml}</div>
            <div class="ends-row team-1">${t1Html}</div>
            <div class="ends-row team-2">${t2Html}</div>
        `;
    }

    // Poll every 5 seconds
    setInterval(refreshScores, 5000);
    </script>
    <?php endif; ?>
</body>
</html>
