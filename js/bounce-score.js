/**
 * Bounce Game Scorer JS
 */

document.addEventListener('DOMContentLoaded', function () {
    const teamBtns   = document.querySelectorAll('.team-btn');
    const shotBtns   = document.querySelectorAll('.shot-btn');
    const submitBtn  = document.getElementById('submitEndBtn');
    const undoBtn    = document.getElementById('undoBtn');
    const startBtn   = document.getElementById('startMatchBtn');
    const completeBtn = document.getElementById('completeBtn');
    const deleteBtn  = document.getElementById('deleteBtn');

    let selectedTeam  = null;
    let selectedShots = null;

    // ── Team selection ──
    teamBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            teamBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedTeam = parseInt(btn.dataset.team);
            updateSubmit();
        });
    });

    // ── Shots selection ──
    shotBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            shotBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            selectedShots = parseInt(btn.dataset.shots);
            updateSubmit();
        });
    });

    function updateSubmit() {
        if (submitBtn) submitBtn.disabled = !(selectedTeam && selectedShots);
    }

    // ── Submit end ──
    if (submitBtn) {
        submitBtn.addEventListener('click', async () => {
            if (!selectedTeam || !selectedShots) return;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving…';

            const data = await apiPost({
                action:       'end',
                match_id:     MATCH_ID,
                end_number:   currentEnd,
                scoring_team: selectedTeam,
                shots:        selectedShots
            });

            if (data.success !== false) {
                updateScoreboard(data);
                clearSelection();
            } else {
                alert(data.error || 'Failed to record end');
            }

            submitBtn.textContent = 'Submit End';
            updateSubmit();
        });
    }

    // ── Undo ──
    if (undoBtn) {
        undoBtn.addEventListener('click', async () => {
            if (!confirm('Undo last end?')) return;
            undoBtn.disabled = true;

            const data = await apiPost({ action: 'undo', match_id: MATCH_ID });

            if (data.success !== false) {
                updateScoreboard(data);
                clearSelection();
            } else {
                alert(data.error || 'Nothing to undo');
            }
            undoBtn.disabled = false;
        });
    }

    // ── Start ──
    if (startBtn) {
        startBtn.addEventListener('click', async () => {
            startBtn.disabled = true;
            startBtn.textContent = 'Starting…';

            const data = await apiPost({ action: 'start', match_id: MATCH_ID });

            if (data.success !== false) {
                location.reload();
            } else {
                alert(data.error || 'Failed to start match');
                startBtn.disabled = false;
                startBtn.textContent = 'Start Match';
            }
        });
    }

    // ── Complete ──
    if (completeBtn) {
        completeBtn.addEventListener('click', async () => {
            if (!confirm('End this match? This cannot be undone.')) return;
            completeBtn.disabled = true;
            completeBtn.textContent = 'Ending…';

            const data = await apiPost({ action: 'complete', match_id: MATCH_ID });

            if (data.success !== false) {
                location.href = 'bounce-view.php?token=' + encodeURIComponent(SHARE_TOKEN);
            } else {
                alert(data.error || 'Failed to end match');
                completeBtn.disabled = false;
                completeBtn.textContent = 'End Match';
            }
        });
    }

    // ── Delete ──
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!confirm('Delete this game? This cannot be undone.')) return;
            deleteBtn.disabled = true;

            const data = await apiDelete();

            if (data.success !== false) {
                location.href = '../index.php';
            } else {
                alert(data.error || 'Failed to delete game');
                deleteBtn.disabled = false;
            }
        });
    }

    // ── DOM update ──
    function updateScoreboard(data) {
        const s1El = document.getElementById('team1Score');
        const s2El = document.getElementById('team2Score');

        if (s1El) s1El.textContent = data.team1_score;
        if (s2El) s2El.textContent = data.team2_score;

        const endEl = document.getElementById('currentEnd');
        const lblEl = document.getElementById('endLabel');
        currentEnd = data.current_end;
        if (endEl) endEl.textContent = currentEnd;
        if (lblEl) lblEl.textContent = currentEnd;

        const grid = document.getElementById('endsGrid');
        if (grid) {
            grid.innerHTML = data.ends && data.ends.length
                ? data.ends.map(e => `<div class="end-cell team-${e.scoring_team}">${e.shots}</div>`).join('')
                : '<span style="color:#bbb;font-size:0.82rem">No ends recorded yet</span>';
        }

        if (data.status === 'completed') {
            showVictory(data.team1_score, data.team2_score);
        }
    }

    function showVictory(s1, s2) {
        const winner = s1 >= s2 ? TEAM1_NAME : TEAM2_NAME;
        const el = document.getElementById('victoryModal');
        if (!el) return;
        document.getElementById('victoryWinner').textContent = winner + ' wins!';
        document.getElementById('victoryScore').textContent  = s1 + ' – ' + s2;
        el.classList.remove('hidden');
        document.querySelectorAll('.team-btn,.shot-btn,#submitEndBtn,#undoBtn,#completeBtn').forEach(b => { if (b) b.disabled = true; });
    }

    function clearSelection() {
        selectedTeam = selectedShots = null;
        teamBtns.forEach(b => b.classList.remove('active'));
        shotBtns.forEach(b => b.classList.remove('active'));
    }

    // ── API helpers ──
    async function apiPost(body) {
        try {
            const res = await fetch('../api/match.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...body, csrf_token: CSRF_TOKEN })
            });
            return await res.json();
        } catch (e) { return { success: false, error: 'Network error' }; }
    }

    async function apiDelete() {
        try {
            const res = await fetch('../api/match.php?id=' + MATCH_ID, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            });
            return await res.json();
        } catch (e) { return { success: false, error: 'Network error' }; }
    }
});

// Copy share link
function copyShareLink() {
    const url = document.getElementById('shareUrlBox').textContent;
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.querySelector('.btn-copy');
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1800);
    });
}
