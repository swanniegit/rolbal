// Rolbal Game Logic

// Track free games for anonymous users
function incrementFreeGamesCounter() {
    const cookieName = 'rolbal_free_games';
    const currentMonth = new Date().toISOString().slice(0, 7); // YYYY-MM

    let data = { month: currentMonth, count: 0 };

    // Read existing cookie
    const existing = document.cookie.split('; ').find(c => c.startsWith(cookieName + '='));
    if (existing) {
        try {
            const parsed = JSON.parse(decodeURIComponent(existing.split('=')[1]));
            if (parsed.month === currentMonth) {
                data = parsed;
            }
        } catch (e) {}
    }

    // Increment count
    data.count++;

    // Set cookie for 60 days
    const expires = new Date();
    expires.setDate(expires.getDate() + 60);
    document.cookie = `${cookieName}=${encodeURIComponent(JSON.stringify(data))}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
}

document.addEventListener('DOMContentLoaded', () => {
    const sessionForm = document.getElementById('sessionForm');
    const sessionId = document.getElementById('sessionId')?.value;

    if (sessionForm) {
        initSessionForm();
    } else if (sessionId) {
        initRollRecording(sessionId);
    }
});

// Session Form
function initSessionForm() {
    const form = document.getElementById('sessionForm');
    const dateInput = document.getElementById('sessionDate');

    // Default to today
    dateInput.value = new Date().toISOString().split('T')[0];

    // Toggle buttons
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('click', () => {
            const field = btn.dataset.field;
            const value = btn.dataset.value;

            document.querySelectorAll(`.btn-toggle[data-field="${field}"]`).forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(field).value = value;
        });
    });

    // Submit
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const hand = document.getElementById('hand').value;
        if (!hand) {
            alert('Please select a hand');
            return;
        }

        const data = new FormData(form);

        try {
            const res = await fetch('api/session.php', {
                method: 'POST',
                body: data
            });
            const json = await res.json();

            if (json.success) {
                // Increment free games counter for anonymous users
                const isLoggedIn = document.getElementById('isLoggedIn')?.value === '1';
                if (!isLoggedIn) {
                    incrementFreeGamesCounter();
                }
                window.location.href = `game.php?id=${json.id}`;
            } else {
                alert(json.error || 'Failed to create session');
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    });
}

// Roll Recording
function initRollRecording(sessionId) {
    const bowlsPerEnd = parseInt(document.getElementById('bowlsPerEnd').value);
    const totalEnds = parseInt(document.getElementById('totalEnds').value);
    let totalRolls = parseInt(document.getElementById('totalRolls').value);

    let currentEndLength = null;
    let toucher = 0;

    const stepEndLength = document.getElementById('stepEndLength');
    const stepResult = document.getElementById('stepResult');
    const toucherBtn = document.getElementById('toucherBtn');

    // End length selection
    stepEndLength.querySelectorAll('.btn-choice').forEach(btn => {
        btn.addEventListener('click', () => {
            currentEndLength = parseInt(btn.dataset.value);

            stepEndLength.querySelectorAll('.btn-choice').forEach(b => b.classList.remove('selected'));
            btn.classList.add('selected');

            setTimeout(() => {
                stepEndLength.classList.add('hidden');
                stepResult.classList.remove('hidden');
                updateBowlHeader();
            }, 150);
        });
    });

    // Result position selection
    stepResult.querySelectorAll('.btn-pos').forEach(btn => {
        btn.addEventListener('click', () => saveRoll(parseInt(btn.dataset.value)));
    });

    // Toucher toggle
    toucherBtn?.addEventListener('click', () => {
        toucher = toucher ? 0 : 1;
        toucherBtn.classList.toggle('active', toucher === 1);
    });

    // Undo
    document.getElementById('undoBtn')?.addEventListener('click', undoLastRoll);

    function updateBowlHeader() {
        const currentBowl = (totalRolls % bowlsPerEnd) + 1;
        stepResult.querySelector('h2').textContent = `Bowl ${currentBowl}`;
    }

    async function saveRoll(result) {
        const currentEnd = Math.floor(totalRolls / bowlsPerEnd) + 1;

        const data = new FormData();
        data.append('session_id', sessionId);
        data.append('end_number', currentEnd);
        data.append('end_length', currentEndLength);
        data.append('result', result);
        data.append('toucher', toucher);

        try {
            const res = await fetch('api/roll.php', {
                method: 'POST',
                body: data
            });
            const json = await res.json();

            if (json.success) {
                totalRolls++;

                // Update UI
                document.getElementById('rollCount').textContent = totalRolls;
                document.getElementById('undoBtn').disabled = false;

                // Update progress
                updateProgress();

                // Flash success
                document.body.classList.add('flash-success');
                setTimeout(() => document.body.classList.remove('flash-success'), 300);

                // Reset toucher
                toucher = 0;
                toucherBtn?.classList.remove('active');

                // Check if end complete or game complete
                const totalBowls = totalEnds * bowlsPerEnd;

                if (totalRolls >= totalBowls) {
                    // Game complete - reload to show stats link
                    window.location.reload();
                } else if (totalRolls % bowlsPerEnd === 0) {
                    // End complete, show end length selection
                    currentEndLength = null;
                    stepResult.classList.add('hidden');
                    stepEndLength.classList.remove('hidden');
                    stepEndLength.querySelector('h2').textContent = `End ${Math.floor(totalRolls / bowlsPerEnd) + 1} - Length`;
                    stepEndLength.querySelectorAll('.btn-choice').forEach(b => b.classList.remove('selected'));
                } else {
                    // Next bowl in same end
                    updateBowlHeader();
                }
            } else {
                alert(json.error || 'Failed to save roll');
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }

    function updateProgress() {
        const currentEnd = Math.floor(totalRolls / bowlsPerEnd) + 1;
        const currentBowl = (totalRolls % bowlsPerEnd) + 1;
        const totalBowls = totalEnds * bowlsPerEnd;
        const percent = (totalRolls / totalBowls) * 100;

        document.getElementById('currentEnd').textContent = Math.min(currentEnd, totalEnds);
        document.getElementById('currentBowl').textContent = totalRolls % bowlsPerEnd === 0 ? bowlsPerEnd : (totalRolls % bowlsPerEnd);
        document.getElementById('progressFill').style.width = `${percent}%`;
    }

    async function undoLastRoll() {
        try {
            const res = await fetch(`api/roll.php?session_id=${sessionId}&undo=1`, {
                method: 'DELETE'
            });
            const json = await res.json();

            if (json.success) {
                // Reload to recalculate state
                window.location.reload();
            }
        } catch (err) {
            alert('Error: ' + err.message);
        }
    }
}
