/**
 * Match Scoreboard Page JavaScript - Auto-refresh all live matches
 */

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
