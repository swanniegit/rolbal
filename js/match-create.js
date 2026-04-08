/**
 * Match Create Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('matchForm');
    const gameTypeInput = document.getElementById('gameTypeInput');
    const scoringModeInput = document.getElementById('scoringModeInput');
    const bowlsSelect = document.getElementById('bowlsSelect');
    const targetSelect = document.getElementById('targetSelect');
    const targetLabel = document.getElementById('targetLabel');
    const gameTypeBtns = document.querySelectorAll('.game-type-btn');

    function updateFormForGameType(type, mode) {
        const config = GAME_TYPES[type];
        if (!config) return;

        // Update hidden inputs
        gameTypeInput.value = type;
        scoringModeInput.value = mode;

        // Update active button
        gameTypeBtns.forEach(btn => {
            btn.classList.toggle('active', btn.dataset.type === type);
        });

        // Update bowls options
        bowlsSelect.innerHTML = '';
        config.allowed_bowls.forEach(bowls => {
            const opt = document.createElement('option');
            opt.value = bowls;
            opt.textContent = bowls + ' bowls';
            if (bowls === config.default_bowls) opt.selected = true;
            bowlsSelect.appendChild(opt);
        });

        // Update target label and options based on scoring mode
        if (mode === 'first_to') {
            targetLabel.textContent = 'First to';
            targetSelect.innerHTML = `
                <option value="21">21 points</option>
                <option value="25">25 points</option>
                <option value="31">31 points</option>
            `;
        } else {
            targetLabel.textContent = 'Number of Ends';
            targetSelect.innerHTML = `
                <option value="15">15 ends</option>
                <option value="18">18 ends</option>
                <option value="21" selected>21 ends</option>
                <option value="25">25 ends</option>
            `;
        }

        // Show/hide position rows
        document.querySelectorAll('.position-row').forEach(row => {
            const pos = row.dataset.position;
            const visible = config.positions.includes(pos);
            row.classList.toggle('hidden', !visible);
        });
    }

    // Game type button click
    gameTypeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            updateFormForGameType(btn.dataset.type, btn.dataset.mode);
        });
    });

    // Form submit
    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('startBtn');
        btn.disabled = true;
        btn.textContent = 'Creating...';

        const formData = new FormData(form);
        const data = await API.post('../api/match.php', formData);

        if (data.success) {
            window.location.href = 'score.php?id=' + data.match_id;
        } else {
            UI.showFlash('error', data.error || 'Failed to create match');
            btn.disabled = false;
            btn.textContent = 'Create Match';
        }
    });

    // Initialize
    updateFormForGameType('singles', 'first_to');
});
