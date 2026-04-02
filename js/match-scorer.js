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
                    window.location.href = 'index.php?club=' + CLUB_ID;
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
