<?php
/**
 * Multi-Match Scoreboard - Display all live matches for a club
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Club.php';
require_once __DIR__ . '/../includes/ClubMember.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$isLoggedIn = Auth::check();
$playerId = Auth::id();

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

// Verify club membership
if (!ClubMember::isMember($clubId, $playerId)) {
    Auth::flash('error', 'You must be a club member to view matches');
    header('Location: ../clubs/index.php');
    exit;
}

$club = Club::find($clubId);
if (!$club) {
    Auth::flash('error', 'Club not found');
    header('Location: ../clubs/index.php');
    exit;
}

$liveMatches = GameMatch::getLiveMatchesForClub($clubId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Rolbal - Live Scoreboard</title>
    <link rel="manifest" href="../manifest.json">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            color: #fff;
        }
        .header {
            background: rgba(0,0,0,0.3);
            padding: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 1.25rem;
            font-weight: 600;
        }
        .header .club-name {
            font-size: 0.85rem;
            opacity: 0.7;
        }
        .live-badge {
            background: #ff4444;
            color: white;
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            animation: pulse 2s infinite;
        }
        .live-dot {
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .matches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }
        .match-card {
            background: rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 1.25rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            opacity: 0.7;
        }
        .match-type {
            text-transform: capitalize;
        }
        .match-end {
            background: rgba(255,255,255,0.15);
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
        }
        .score-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        .team-block {
            flex: 1;
            text-align: center;
        }
        .team-block.left { text-align: left; }
        .team-block.right { text-align: right; }
        .team-name {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .team-score {
            font-size: 3rem;
            font-weight: 800;
            line-height: 1;
        }
        .team-1 .team-score { color: #64b5f6; }
        .team-2 .team-score { color: #ef5350; }
        .vs {
            font-size: 1.5rem;
            opacity: 0.4;
            padding: 0 0.75rem;
        }
        .ends-row {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .end-chip {
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .end-chip.team-1 {
            background: rgba(100, 181, 246, 0.3);
            color: #64b5f6;
        }
        .end-chip.team-2 {
            background: rgba(239, 83, 80, 0.3);
            color: #ef5350;
        }
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            opacity: 0.6;
        }
        .empty-state h2 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }
        .empty-state p {
            font-size: 0.9rem;
        }
        .back-link {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 1.5rem;
        }
        .refresh-note {
            text-align: center;
            font-size: 0.75rem;
            opacity: 0.5;
            padding: 1rem;
        }
        .scorer-info {
            font-size: 0.75rem;
            opacity: 0.6;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <a href="index.php?club=<?= $clubId ?>" class="back-link">&larr;</a>
        </div>
        <div style="text-align: center;">
            <h1>Live Scoreboard</h1>
            <div class="club-name"><?= htmlspecialchars($club['name']) ?></div>
        </div>
        <div class="live-badge">
            <span class="live-dot"></span>
            LIVE
        </div>
    </div>

    <div class="matches-grid" id="matchesGrid">
        <?php if (empty($liveMatches)): ?>
        <div class="empty-state">
            <h2>No Live Matches</h2>
            <p>There are no matches in progress right now.</p>
        </div>
        <?php else: ?>
            <?php foreach ($liveMatches as $match): ?>
            <div class="match-card" data-match-id="<?= $match['id'] ?>">
                <div class="match-header">
                    <span class="match-type"><?= htmlspecialchars($match['game_type']) ?></span>
                    <span class="match-end">End <?= $match['current_end'] ?></span>
                </div>
                <div class="score-display">
                    <div class="team-block left team-1">
                        <div class="team-name"><?= htmlspecialchars($match['team1_name'] ?: 'Team 1') ?></div>
                        <div class="team-score" id="t1-<?= $match['id'] ?>"><?= $match['team1_score'] ?></div>
                    </div>
                    <div class="vs">-</div>
                    <div class="team-block right team-2">
                        <div class="team-name"><?= htmlspecialchars($match['team2_name'] ?: 'Team 2') ?></div>
                        <div class="team-score" id="t2-<?= $match['id'] ?>"><?= $match['team2_score'] ?></div>
                    </div>
                </div>
                <div class="ends-row" id="ends-<?= $match['id'] ?>">
                    <?php foreach ($match['ends'] as $end): ?>
                    <div class="end-chip team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                    <?php endforeach; ?>
                </div>
                <?php if ($match['scorer_name']): ?>
                <div class="scorer-info">Scorer: <?= htmlspecialchars($match['scorer_name']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="refresh-note">Auto-refreshing every 5 seconds</div>

    <script>
    const CLUB_ID = <?= $clubId ?>;

    async function refreshMatches() {
        try {
            const res = await fetch('../api/match.php?action=live_all&club_id=' + CLUB_ID);
            const data = await res.json();

            if (data.success && data.matches) {
                updateDisplay(data.matches);
            }
        } catch (err) {
            console.error('Refresh error:', err);
        }
    }

    function updateDisplay(matches) {
        const grid = document.getElementById('matchesGrid');

        if (matches.length === 0) {
            grid.innerHTML = `
                <div class="empty-state">
                    <h2>No Live Matches</h2>
                    <p>There are no matches in progress right now.</p>
                </div>
            `;
            return;
        }

        // Update existing matches or add new ones
        matches.forEach(match => {
            const card = document.querySelector(`[data-match-id="${match.id}"]`);
            if (card) {
                // Update scores
                document.getElementById('t1-' + match.id).textContent = match.team1_score;
                document.getElementById('t2-' + match.id).textContent = match.team2_score;

                // Update end number
                card.querySelector('.match-end').textContent = 'End ' + match.current_end;

                // Update ends row
                const endsRow = document.getElementById('ends-' + match.id);
                endsRow.innerHTML = match.ends.map(end =>
                    `<div class="end-chip team-${end.scoring_team}">${end.shots}</div>`
                ).join('');
            } else {
                // New match - reload page to get full HTML
                location.reload();
            }
        });

        // Remove completed matches
        document.querySelectorAll('.match-card').forEach(card => {
            const matchId = parseInt(card.dataset.matchId);
            if (!matches.find(m => m.id === matchId)) {
                card.remove();
            }
        });
    }

    // Poll every 5 seconds
    setInterval(refreshMatches, 5000);
    </script>
</body>
</html>
