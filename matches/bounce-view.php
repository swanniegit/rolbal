<?php
/**
 * Bounce Game Public View — no login required, share-token access
 */

require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/GameMatch.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    header('Location: ../index.php');
    exit;
}

$match = GameMatch::findBounceWithDetails($token);
if (!$match) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body><p>Game not found or link has expired.</p><a href="../index.php">Home</a></body></html>';
    exit;
}

$isLoggedIn  = Auth::check();
$matchTitle  = $match['match_name'] ? htmlspecialchars($match['match_name']) : 'Bounce Game';
$targetScore = $match['target_score'] ?? 21;
$isLive      = $match['status'] === 'live';
$isDone      = $match['status'] === 'completed';

// Build running totals for scoreboard table
$t1Shots = []; $t2Shots = [];
$t1Run   = []; $t2Run   = [];
$t1Total = 0;  $t2Total = 0;
foreach ($match['ends'] as $end) {
    $n = $end['end_number'];
    if ($end['scoring_team'] == 1) {
        $t1Shots[$n] = $end['shots']; $t2Shots[$n] = '-';
        $t1Total += $end['shots'];
    } else {
        $t2Shots[$n] = $end['shots']; $t1Shots[$n] = '-';
        $t2Total += $end['shots'];
    }
    $t1Run[$n] = $t1Total;
    $t2Run[$n] = $t2Total;
}
$numEnds = count($match['ends']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a3a6b">
    <meta property="og:title" content="<?= $matchTitle ?> – Live Score">
    <meta property="og:description" content="<?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?> vs <?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?> – Follow live">
    <title>BowlsTracker – <?= $matchTitle ?></title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/bounce.css">
</head>
<body>
    <div class="header">
        <a href="../index.php">&larr;</a>
        <h1><?= $matchTitle ?></h1>
        <?php if ($isLive): ?>
        <span class="badge-live">LIVE</span>
        <?php elseif ($isDone): ?>
        <span class="badge-done">FINAL</span>
        <?php else: ?>
        <span class="badge-setup">SETUP</span>
        <?php endif; ?>
    </div>

    <div class="content">

        <!-- Main scoreboard — big scores -->
        <div class="scoreboard" id="mainScoreboard">
            <div class="scoreboard-header">
                <span class="match-label">
                    <?= $match['players_per_team'] ?>v<?= $match['players_per_team'] ?> &middot;
                    <?= $match['bowls_per_player'] ?> bowls
                </span>
                <span style="font-size:0.8rem;opacity:0.8">
                    <?php if ($match['scoring_mode'] === 'first_to'): ?>
                    First to <?= $targetScore ?>
                    <?php else: ?>
                    <?= $targetScore ?> ends
                    <?php endif; ?>
                </span>
            </div>
            <div class="teams-row">
                <div class="team-col left">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?></div>
                    <div class="team-score" id="score1"><?= $match['team1_score'] ?></div>
                </div>
                <div class="team-col center" style="font-size:1.5rem;opacity:0.4">–</div>
                <div class="team-col right">
                    <div class="team-name"><?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?></div>
                    <div class="team-score" id="score2"><?= $match['team2_score'] ?></div>
                </div>
            </div>
            <div class="end-info">
                <?php if ($isDone): ?>
                Full time
                <?php else: ?>
                End <span id="currentEndLabel"><?= $match['current_end'] - 1 > 0 ? $match['current_end'] - 1 : 0 ?></span> played
                <?php if ($match['scoring_mode'] === 'ends'): ?> of <?= $targetScore ?><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Players -->
        <div class="players-card">
            <h4>Players</h4>
            <div class="players-teams">
                <?php foreach ([0, 1] as $ti): ?>
                <div>
                    <div class="players-team-name"><?= htmlspecialchars($match['teams'][$ti]['team_name'] ?? 'Team ' . ($ti + 1)) ?></div>
                    <?php foreach ($match['teams'][$ti]['players'] ?? [] as $p): ?>
                    <div class="player-row"><?= htmlspecialchars($p['player_name']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Ends grid — tap to expand detail -->
        <div class="ends-card" id="endsCard">
            <h4>Ends
                <span style="font-size:0.72rem;color:#bbb;font-weight:400;margin-left:0.4rem">tap to expand</span>
            </h4>
            <div class="ends-grid" id="endsGrid">
                <?php foreach ($match['ends'] as $end): ?>
                <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                <?php endforeach; ?>
                <?php if (empty($match['ends'])): ?>
                <span style="color:#bbb;font-size:0.82rem">Waiting for first end…</span>
                <?php endif; ?>
            </div>

            <!-- Detailed scoreboard table (hidden until tap) -->
            <div class="ends-detail" id="endsDetail">
                <div style="overflow-x:auto;margin-top:0.5rem">
                    <table id="detailTable" style="border-collapse:collapse;width:100%;font-size:0.78rem;min-width:300px">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:0.25rem 0.3rem;color:#888;font-weight:600">End</th>
                                <?php for ($i = 1; $i <= max($numEnds, 1); $i++): ?>
                                <th style="padding:0.25rem 0.3rem;color:#888;text-align:center"><?= $i ?></th>
                                <?php endfor; ?>
                                <th style="padding:0.25rem 0.5rem;color:#333;font-weight:700;text-align:center">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding:0.25rem 0.3rem;font-weight:700;color:#1d4ed8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80px">
                                    <?= htmlspecialchars($match['teams'][0]['team_name'] ?? 'Team 1') ?>
                                </td>
                                <?php for ($i = 1; $i <= max($numEnds, 1); $i++): ?>
                                <td style="text-align:center;padding:0.25rem 0.3rem;color:<?= isset($t1Shots[$i]) && $t1Shots[$i] !== '-' ? '#1d4ed8' : '#ccc' ?>">
                                    <?= $t1Shots[$i] ?? '-' ?>
                                </td>
                                <?php endfor; ?>
                                <td style="text-align:center;padding:0.25rem 0.5rem;font-weight:800;color:#1d4ed8" id="detailT1Total"><?= $t1Total ?></td>
                            </tr>
                            <tr>
                                <td style="padding:0.25rem 0.3rem;font-weight:700;color:#dc2626;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:80px">
                                    <?= htmlspecialchars($match['teams'][1]['team_name'] ?? 'Team 2') ?>
                                </td>
                                <?php for ($i = 1; $i <= max($numEnds, 1); $i++): ?>
                                <td style="text-align:center;padding:0.25rem 0.3rem;color:<?= isset($t2Shots[$i]) && $t2Shots[$i] !== '-' ? '#dc2626' : '#ccc' ?>">
                                    <?= $t2Shots[$i] ?? '-' ?>
                                </td>
                                <?php endfor; ?>
                                <td style="text-align:center;padding:0.25rem 0.5rem;font-weight:800;color:#dc2626" id="detailT2Total"><?= $t2Total ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($isLive && $isLoggedIn): ?>
        <div class="action-buttons">
            <a href="bounce-score.php?id=<?= $match['id'] ?>" class="btn-complete" style="text-decoration:none;display:flex;align-items:center;justify-content:center">
                Score this match
            </a>
        </div>
        <?php elseif ($isLive && !$isLoggedIn): ?>
        <div class="auth-prompt">
            <a href="../login.php">Log in</a> to score this match
        </div>
        <?php endif; ?>

        <?php if ($isLive): ?>
        <p class="refresh-note" id="refreshNote">Auto-refreshing every 5 seconds</p>
        <?php endif; ?>
    </div>

    <script>
    const SHARE_TOKEN = <?= json_encode($token) ?>;
    const IS_LIVE     = <?= $isLive ? 'true' : 'false' ?>;
    let prevScore1    = <?= (int)$match['team1_score'] ?>;
    let prevScore2    = <?= (int)$match['team2_score'] ?>;

    // Toggle end detail on tap
    document.getElementById('endsCard').addEventListener('click', () => {
        document.getElementById('endsDetail').classList.toggle('open');
    });

    async function refreshScores() {
        try {
            const res  = await fetch('../api/match.php?action=bounce_scores&token=' + SHARE_TOKEN);
            const data = await res.json();
            if (!data || data.success === false) return;

            const s1 = parseInt(data.team1_score, 10);
            const s2 = parseInt(data.team2_score, 10);

            // Flash effect on score change
            if (s1 !== prevScore1) flashScore('score1', s1 > prevScore1);
            if (s2 !== prevScore2) flashScore('score2', s2 > prevScore2);

            prevScore1 = s1;
            prevScore2 = s2;

            document.getElementById('score1').textContent = s1;
            document.getElementById('score2').textContent = s2;

            const endsPlayed = data.ends ? data.ends.length : 0;
            const endLabel = document.getElementById('currentEndLabel');
            if (endLabel) endLabel.textContent = endsPlayed;

            // Rebuild ends grid
            const grid = document.getElementById('endsGrid');
            if (data.ends && data.ends.length > 0) {
                grid.innerHTML = data.ends.map(e =>
                    `<div class="end-cell team-${e.scoring_team}">${e.shots}</div>`
                ).join('');
            } else {
                grid.innerHTML = '<span style="color:#bbb;font-size:0.82rem">Waiting for first end…</span>';
            }

            if (data.status === 'completed') {
                location.reload();
            }
        } catch (e) { /* silent */ }
    }

    function flashScore(id, increased) {
        const el = document.getElementById(id);
        if (!el) return;
        // Green flash (score went up) then red (settle) over 2s
        el.classList.remove('flash-up', 'flash-down');
        void el.offsetWidth; // reflow
        el.classList.add('flash-up');
        setTimeout(() => {
            el.classList.remove('flash-up');
            el.classList.add('flash-down');
        }, 1000);
        setTimeout(() => {
            el.classList.remove('flash-down');
        }, 2000);
    }

    if (IS_LIVE) setInterval(refreshScores, 5000);
    </script>
</body>
</html>
