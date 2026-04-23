<?php
/**
 * Live Bounce Games — public scoreboard listing
 * Shows all live/setup bounce games from the last 3 days
 * No login required
 */

require_once __DIR__ . '/../includes/GameMatch.php';
require_once __DIR__ . '/../includes/Auth.php';

$matches    = GameMatch::getLiveBounceGames();
$isLoggedIn = Auth::check();

// Build filter buckets: club name => count, plus 'Open' for unlinked
$filterBuckets = [];
foreach ($matches as $m) {
    $key = $m['club_name'] ?: 'Open';
    $filterBuckets[$key] = ($filterBuckets[$key] ?? 0) + 1;
}
arsort($filterBuckets);
$showTabs = count($filterBuckets) >= 2;

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . $_SERVER['HTTP_HOST']
         . rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/') . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a3a6b">
    <title>BowlsTracker – Live Games</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/bounce.css">
</head>
<body>
    <div class="header">
        <a href="../index.php">&larr;</a>
        <h1>Live Games</h1>
        <?php if ($isLoggedIn): ?>
        <a href="bounce-create.php" class="header-action">+ New</a>
        <?php else: ?>
        <span></span>
        <?php endif; ?>
    </div>

    <div class="content">
        <?php if ($showTabs): ?>
        <div class="filter-tabs" id="filterTabs">
            <button class="filter-tab active" data-filter="all">All (<?= count($matches) ?>)</button>
            <?php foreach ($filterBuckets as $name => $count): ?>
            <button class="filter-tab" data-filter="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?> (<?= $count ?>)</button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($matches)): ?>
        <div class="empty-state">
            <div class="empty-icon">🎳</div>
            <p>No live games right now</p>
            <?php if ($isLoggedIn): ?>
            <a href="bounce-create.php">Start a Bounce Game</a>
            <?php else: ?>
            <p style="margin-top:0.75rem;font-size:0.85rem"><a href="../login.php" style="color:#1a3a6b;font-weight:600">Log in</a> to start a game</p>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="live-list" id="liveList">
            <?php foreach ($matches as $m): ?>
            <?php
                $gameId    = $m['id'];
                $token     = $m['share_token'];
                $name      = $m['match_name'] ?: 'Bounce Game';
                $t1        = htmlspecialchars($m['team1_name'] ?? 'Team 1');
                $t2        = htmlspecialchars($m['team2_name'] ?? 'Team 2');
                $s1        = (int)$m['team1_score'];
                $s2        = (int)$m['team2_score'];
                $endNum    = count($m['ends']);
                $isDone    = $m['status'] === 'completed';
                $winner    = $isDone ? ($s1 > $s2 ? $m['team1_name'] : ($s2 > $s1 ? $m['team2_name'] : null)) : null;

                if ($m['status'] === 'live')      $statusBadge = '<span class="badge-live">LIVE</span>';
                elseif ($m['status'] === 'setup') $statusBadge = '<span class="badge-setup">SETUP</span>';
                else                              $statusBadge = '<span class="badge-done">FINAL</span>';
            ?>
            <div class="live-card <?= $isDone ? 'live-card--done' : '' ?>"
                 data-id="<?= $gameId ?>"
                 data-token="<?= htmlspecialchars($token) ?>"
                 data-s1="<?= $s1 ?>"
                 data-s2="<?= $s2 ?>"
                 data-done="<?= $isDone ? '1' : '0' ?>"
                 data-club="<?= htmlspecialchars($m['club_name'] ?? '') ?>">
                <div class="live-card-header">
                    <span class="live-card-name"><?= htmlspecialchars($name) ?></span>
                    <?= $statusBadge ?>
                </div>

                <?php if ($winner): ?>
                <div class="winner-banner">🏆 <?= htmlspecialchars($winner) ?> wins</div>
                <?php endif; ?>

                <div class="live-card-score">
                    <div class="live-score-team <?= ($isDone && $s1 > $s2) ? 'team-winner' : '' ?>">
                        <div class="live-team-name"><?= $t1 ?></div>
                        <div class="live-team-score" id="ls1-<?= $gameId ?>"><?= $s1 ?></div>
                    </div>
                    <div class="live-score-sep">–</div>
                    <div class="live-score-team <?= ($isDone && $s2 > $s1) ? 'team-winner' : '' ?>">
                        <div class="live-team-name"><?= $t2 ?></div>
                        <div class="live-team-score" id="ls2-<?= $gameId ?>"><?= $s2 ?></div>
                    </div>
                </div>

                <div class="live-card-end">
                    <?php if ($m['status'] === 'live'): ?>
                    End <?= $endNum ?> played
                    <?php if ($m['scoring_mode'] === 'ends'): ?> of <?= $m['target_score'] ?><?php endif; ?>
                    <?php elseif ($isDone): ?>
                    Full time · <?= $endNum ?> ends
                    <?php else: ?>
                    Waiting to start
                    <?php endif; ?>
                    &middot; <?= $m['players_per_team'] ?>v<?= $m['players_per_team'] ?>, <?= $m['bowls_per_player'] ?> bowls
                </div>

                <!-- Expandable ends detail -->
                <div class="ends-detail" id="detail-<?= $gameId ?>">
                    <h5>End by end</h5>
                    <div class="ends-detail-grid" id="ends-<?= $gameId ?>">
                        <?php foreach ($m['ends'] as $end): ?>
                        <div class="end-cell team-<?= $end['scoring_team'] ?>"><?= $end['shots'] ?></div>
                        <?php endforeach; ?>
                        <?php if (empty($m['ends'])): ?>
                        <span style="color:#ccc;font-size:0.8rem">No ends yet</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
    const BASE_URL = <?= json_encode($baseUrl) ?>;

    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const filter = tab.dataset.filter;
            document.querySelectorAll('.live-card').forEach(card => {
                const club = card.dataset.club || '';
                const match = filter === 'all'
                    || card.dataset.club === filter
                    || (filter === 'Open' && !club);
                card.style.display = match ? '' : 'none';
            });
        });
    });

    // Click card: single click toggles ends detail, double-click opens full view
    document.querySelectorAll('.live-card').forEach(card => {
        let clickTimer = null;
        card.addEventListener('click', e => {
            clearTimeout(clickTimer);
            clickTimer = setTimeout(() => {
                const detail = document.getElementById('detail-' + card.dataset.id);
                if (detail) detail.classList.toggle('open');
            }, 220);
        });
        card.addEventListener('dblclick', e => {
            clearTimeout(clickTimer);
            const token = card.dataset.token;
            if (token) location.href = 'bounce-view.php?token=' + encodeURIComponent(token);
        });
    });

    // Flash the whole card background: green → red → white
    function flashCard(card) {
        card.classList.remove('flash-up', 'flash-down');
        void card.offsetWidth;
        card.classList.add('flash-up');
        setTimeout(() => {
            card.classList.remove('flash-up');
            card.classList.add('flash-down');
        }, 1000);
        setTimeout(() => {
            card.classList.remove('flash-down');
        }, 2000);
    }

    // Poll all live games every 5 seconds
    async function refreshAll() {
        try {
            const res  = await fetch('../api/match.php?action=bounce_list');
            const data = await res.json();
            if (!data || !data.matches) return;

            data.matches.forEach(m => {
                const card = document.querySelector(`.live-card[data-id="${m.id}"]`);
                if (!card) return;

                const s1El = document.getElementById('ls1-' + m.id);
                const s2El = document.getElementById('ls2-' + m.id);
                if (!s1El || !s2El) return;

                const newS1 = parseInt(m.team1_score, 10);
                const newS2 = parseInt(m.team2_score, 10);
                const oldS1 = parseInt(card.dataset.s1, 10);
                const oldS2 = parseInt(card.dataset.s2, 10);

                const isDone  = card.dataset.done === '1';
                const changed = newS1 !== oldS1 || newS2 !== oldS2;

                if (newS1 !== oldS1) { s1El.textContent = newS1; card.dataset.s1 = newS1; }
                if (newS2 !== oldS2) { s2El.textContent = newS2; card.dataset.s2 = newS2; }

                if (changed && !isDone) flashCard(card);

                // Refresh ends detail
                const endsEl = document.getElementById('ends-' + m.id);
                if (endsEl && m.ends) {
                    endsEl.innerHTML = m.ends.length
                        ? m.ends.map(e => `<div class="end-cell team-${e.scoring_team}">${e.shots}</div>`).join('')
                        : '<span style="color:#ccc;font-size:0.8rem">No ends yet</span>';
                }
            });
        } catch (e) { /* silent */ }
    }

    setInterval(refreshAll, 5000);
    </script>
</body>
</html>
