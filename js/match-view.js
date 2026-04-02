/**
 * Match View Page JavaScript - Auto-refresh scoreboard
 */

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

// Start auto-refresh
setInterval(refreshScores, 5000);
