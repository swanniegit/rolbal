/**
 * Match Scorer Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const teamBtns = document.querySelectorAll('.team-btn');
    const shotBtns = document.querySelectorAll('.shot-btn');
    const submitBtn = document.getElementById('submitEndBtn');
    const undoBtn = document.getElementById('undoBtn');
    const startBtn = document.getElementById('startMatchBtn');
    const completeBtn = document.getElementById('completeBtn');
    const deleteBtn = document.getElementById('deleteBtn');

    let selectedTeam = null;
    let selectedShots = null;

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

            UI.setButtonLoading(submitBtn, true);

            const data = await API.post('../api/match.php', {
                action: 'end',
                match_id: MATCH_ID,
                end_number: currentEnd,
                scoring_team: selectedTeam,
                shots: selectedShots
            });

            if (data.success !== false) {
                updateScoreboard(data);
                clearSelection();
            } else {
                UI.showFlash('error', data.error || 'Failed to record end');
            }

            UI.setButtonLoading(submitBtn, false, 'Submit End');
            updateSubmitBtn();
        });
    }

    // Undo
    if (undoBtn) {
        undoBtn.addEventListener('click', async () => {
            if (!UI.confirm('Undo last end?')) return;

            undoBtn.disabled = true;

            const data = await API.post('../api/match.php', {
                action: 'undo',
                match_id: MATCH_ID
            });

            if (data.success !== false) {
                updateScoreboard(data);
                clearSelection();
            } else {
                UI.showFlash('error', data.error || 'Nothing to undo');
            }

            undoBtn.disabled = false;
        });
    }

    // Start match
    if (startBtn) {
        startBtn.addEventListener('click', async () => {
            UI.setButtonLoading(startBtn, true);

            const data = await API.post('../api/match.php', {
                action: 'start',
                match_id: MATCH_ID
            });

            if (data.success !== false) {
                UI.reload();
            } else {
                UI.showFlash('error', data.error || 'Failed to start match');
                UI.setButtonLoading(startBtn, false, 'Start Match');
            }
        });
    }

    // Complete match
    if (completeBtn) {
        completeBtn.addEventListener('click', async () => {
            if (!UI.confirm('End this match? This cannot be undone.')) return;

            UI.setButtonLoading(completeBtn, true);

            const data = await API.post('../api/match.php', {
                action: 'complete',
                match_id: MATCH_ID
            });

            if (data.success !== false) {
                UI.redirect('view.php?id=' + MATCH_ID);
            } else {
                UI.showFlash('error', data.error || 'Failed to end match');
                UI.setButtonLoading(completeBtn, false, 'End Match');
            }
        });
    }

    // Delete match
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async () => {
            if (!UI.confirm('Delete this match? This cannot be undone.')) return;

            UI.setButtonLoading(deleteBtn, true);

            const data = await API.delete('../api/match.php?id=' + MATCH_ID);

            if (data.success !== false) {
                UI.redirect('index.php?club=' + CLUB_ID);
            } else {
                UI.showFlash('error', data.error || 'Failed to delete match');
                UI.setButtonLoading(deleteBtn, false, 'Delete Match');
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
