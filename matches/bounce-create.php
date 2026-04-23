<?php
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/Template.php';
require_once __DIR__ . '/../includes/ClubMember.php';

if (!Auth::check()) {
    header('Location: ../login.php');
    exit;
}

$flash      = Auth::getFlash();
$csrfToken  = Auth::generateCsrfToken();
$userClubs  = ClubMember::getPlayerClubs(Auth::id());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#1a3a6b">
    <title>BowlsTracker – New Bounce Game</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../css/pages/bounce.css">
</head>
<body>
    <div class="header">
        <a href="../index.php">&larr;</a>
        <h1>New Bounce Game</h1>
        <span></span>
    </div>

    <div class="content">
        <?php Template::flash($flash); ?>

        <form id="bounceForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Game name -->
            <div class="form-card">
                <div class="form-group">
                    <label>Game Name <span style="font-weight:400;color:#aaa;text-transform:none">(optional)</span></label>
                    <input type="text" name="match_name" id="matchName" placeholder="e.g. Saturday Pairs, Club Fours">
                </div>

                <!-- Teams -->
                <div class="form-group">
                    <label>Team Names <span style="color:#e53e3e;font-weight:700">*</span></label>
                    <div class="teams-grid" style="margin-bottom:0">
                        <div class="team-col-form">
                            <input type="text" name="team1_name" class="player-input" placeholder="Team 1 name" required autocomplete="off">
                        </div>
                        <div class="team-col-form">
                            <input type="text" name="team2_name" class="player-input" placeholder="Team 2 name" required autocomplete="off">
                        </div>
                    </div>
                    <div style="font-size:0.75rem;color:#999;margin-top:0.35rem">Both team names are required</div>
                </div>
            </div>

            <!-- Club link (optional) -->
            <?php if (!empty($userClubs)): ?>
            <div class="form-card">
                <div class="form-group">
                    <label>Link to Club <span style="font-weight:400;color:#aaa;text-transform:none">(optional)</span></label>
                    <select name="club_id" id="clubSelect" class="player-input">
                        <option value="">Open game (no club)</option>
                        <?php foreach ($userClubs as $club): ?>
                        <option value="<?= (int)$club['id'] ?>"><?= htmlspecialchars($club['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:0.75rem;color:#999;margin-top:0.35rem">Games linked to a club can be filtered on the live board</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Format -->
            <div class="form-card">
                <div class="form-group">
                    <label>Players per Team</label>
                    <div class="stepper">
                        <button type="button" id="decPlayers" disabled>−</button>
                        <span class="stepper-value" id="playersVal">1</span>
                        <button type="button" id="incPlayers">+</button>
                    </div>
                    <input type="hidden" name="players_per_team" id="playersPerTeam" value="1">
                </div>

                <div class="form-group">
                    <label>Bowls per Player</label>
                    <div class="btn-group" id="bowlsGroup">
                        <button type="button" class="btn-toggle" data-val="1">1</button>
                        <button type="button" class="btn-toggle" data-val="2">2</button>
                        <button type="button" class="btn-toggle" data-val="3">3</button>
                        <button type="button" class="btn-toggle active" data-val="4">4</button>
                        <button type="button" class="btn-toggle" data-val="5">5</button>
                        <button type="button" class="btn-toggle" data-val="6">6</button>
                    </div>
                    <input type="hidden" name="bowls_per_player" id="bowlsPerPlayer" value="4">
                </div>

                <div class="form-group">
                    <label>Scoring</label>
                    <div class="scoring-row">
                        <button type="button" class="btn-toggle active" data-mode="ends" id="modeEnds">Play X Ends</button>
                        <button type="button" class="btn-toggle" data-mode="first_to" id="modeFirst">First to X</button>
                    </div>
                    <input type="hidden" name="scoring_mode" id="scoringMode" value="ends">
                    <div class="target-row">
                        <label id="targetLabel">Number of ends:</label>
                        <input type="number" name="target_score" id="targetScore" value="21" min="1" max="50">
                    </div>
                </div>
            </div>

            <!-- Player names -->
            <div class="form-card" id="playersCard">
                <span class="section-label">Player Names</span>
                <div class="teams-grid" id="playersGrid">
                    <!-- filled by JS -->
                </div>
            </div>

            <button type="submit" class="btn-primary-full" id="submitBtn">Create Bounce Game</button>
        </form>
    </div>

    <!-- Share modal shown after creation -->
    <div id="shareModal" class="victory-modal hidden">
        <div class="victory-content" style="max-width:360px">
            <div class="victory-trophy">🎳</div>
            <div class="victory-winner">Game Created!</div>
            <div style="font-size:0.9rem;color:#555;margin-bottom:1.25rem">Share this link so others can follow the live score</div>

            <div class="share-card" style="text-align:left;margin-bottom:0.75rem">
                <h4>Live Score Link</h4>
                <div class="share-row">
                    <div class="share-url" id="shareUrlDisplay"></div>
                    <button class="btn-copy" onclick="copyLink()">Copy</button>
                </div>
                <a class="btn-whatsapp" id="waShareBtn" href="#" target="_blank">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Share on WhatsApp
                </a>
            </div>

            <button class="btn-view-result" id="goToScoreBtn">Open Scorer</button>
        </div>
    </div>

    <script>
    const CSRF = <?= json_encode($csrfToken) ?>;
    let createdMatchId   = null;
    let createdToken     = null;
    let playersPerTeam   = 1;
    const MAX_PLAYERS    = 6;

    // ── Stepper ──
    const decBtn   = document.getElementById('decPlayers');
    const incBtn   = document.getElementById('incPlayers');
    const valSpan  = document.getElementById('playersVal');
    const hidInput = document.getElementById('playersPerTeam');

    function updateStepper() {
        valSpan.textContent   = playersPerTeam;
        hidInput.value        = playersPerTeam;
        decBtn.disabled       = playersPerTeam <= 1;
        incBtn.disabled       = playersPerTeam >= MAX_PLAYERS;
        renderPlayerFields();
    }

    decBtn.addEventListener('click', () => { if (playersPerTeam > 1) { playersPerTeam--; updateStepper(); } });
    incBtn.addEventListener('click', () => { if (playersPerTeam < MAX_PLAYERS) { playersPerTeam++; updateStepper(); } });

    // ── Player name fields ──
    function renderPlayerFields() {
        const grid = document.getElementById('playersGrid');
        const t1col = document.createElement('div');
        const t2col = document.createElement('div');
        t1col.className = 'team-col-form';
        t2col.className = 'team-col-form';

        const t1name = document.querySelector('input[name="team1_name"]').value || 'Team 1';
        const t2name = document.querySelector('input[name="team2_name"]').value || 'Team 2';

        t1col.innerHTML = `<h5>${t1name}</h5>`;
        t2col.innerHTML = `<h5>${t2name}</h5>`;

        for (let i = 1; i <= playersPerTeam; i++) {
            const oldT1 = grid.querySelector(`input[name="team1_player_${i}"]`);
            const oldT2 = grid.querySelector(`input[name="team2_player_${i}"]`);

            const in1 = document.createElement('input');
            in1.type = 'text'; in1.name = `team1_player_${i}`;
            in1.className = 'player-input';
            in1.placeholder = `Player ${i}`;
            if (oldT1) in1.value = oldT1.value;

            const in2 = document.createElement('input');
            in2.type = 'text'; in2.name = `team2_player_${i}`;
            in2.className = 'player-input';
            in2.placeholder = `Player ${i}`;
            if (oldT2) in2.value = oldT2.value;

            t1col.appendChild(in1);
            t2col.appendChild(in2);
        }

        grid.innerHTML = '';
        grid.appendChild(t1col);
        grid.appendChild(t2col);
    }

    // Update player headers when team names change
    document.querySelectorAll('input[name="team1_name"], input[name="team2_name"]').forEach(el => {
        el.addEventListener('input', renderPlayerFields);
    });

    // ── Bowls toggle ──
    document.querySelectorAll('#bowlsGroup .btn-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#bowlsGroup .btn-toggle').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('bowlsPerPlayer').value = btn.dataset.val;
        });
    });

    // ── Scoring mode ──
    const modeEnds  = document.getElementById('modeEnds');
    const modeFirst = document.getElementById('modeFirst');
    const targetLbl = document.getElementById('targetLabel');

    modeEnds.addEventListener('click', () => {
        modeEnds.classList.add('active'); modeFirst.classList.remove('active');
        document.getElementById('scoringMode').value = 'ends';
        targetLbl.textContent = 'Number of ends:';
        document.getElementById('targetScore').value = 21;
    });

    modeFirst.addEventListener('click', () => {
        modeFirst.classList.add('active'); modeEnds.classList.remove('active');
        document.getElementById('scoringMode').value = 'first_to';
        targetLbl.textContent = 'First to (points):';
        document.getElementById('targetScore').value = 21;
    });

    // ── Submit ──
    document.getElementById('bounceForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating…';

        const data = {};
        new FormData(e.target).forEach((v, k) => { data[k] = v; });

        try {
            const res  = await fetch('../api/match.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...data, action: 'bounce_create' })
            });
            const json = await res.json();

            if (json.success) {
                createdMatchId = json.match_id;
                createdToken   = json.share_token;
                showShareModal();
            } else {
                alert(json.error || 'Failed to create game');
                btn.disabled = false;
                btn.textContent = 'Create Bounce Game';
            }
        } catch (err) {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Create Bounce Game';
        }
    });

    function showShareModal() {
        const base    = location.origin + location.pathname.replace(/\/matches\/[^/]*$/, '');
        const viewUrl = base + '/matches/bounce-view.php?token=' + createdToken;
        document.getElementById('shareUrlDisplay').textContent = viewUrl;
        document.getElementById('waShareBtn').href =
            'https://wa.me/?text=' + encodeURIComponent('Follow the live score: ' + viewUrl);
        document.getElementById('goToScoreBtn').addEventListener('click', () => {
            location.href = 'bounce-score.php?id=' + createdMatchId;
        });
        document.getElementById('shareModal').classList.remove('hidden');
    }

    window.copyLink = function() {
        const url = document.getElementById('shareUrlDisplay').textContent;
        navigator.clipboard.writeText(url).then(() => {
            const btn = document.querySelector('.btn-copy');
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1800);
        });
    };

    // Init
    updateStepper();
    </script>
</body>
</html>
