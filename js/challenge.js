// Challenge Game Logic

document.addEventListener('DOMContentLoaded', () => {
    const startForm = document.getElementById('startForm');
    const attemptId = document.getElementById('attemptId')?.value;

    if (startForm) {
        initStartForm();
    } else if (attemptId) {
        initChallengeGame(attemptId);
    }
});

// Handle start form submission
function initStartForm() {
    const form = document.getElementById('startForm');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(form);
        const json = await API.post('../api/challenge.php', formData);

        if (json.success) {
            // Reload page with attempt ID
            UI.redirect(`play.php?id=${formData.get('challenge_id')}&attempt=${json.attempt_id}`);
        } else {
            UI.showFlash('error', json.error || 'Failed to start challenge');
        }
    });
}

// Main challenge game logic
function initChallengeGame(attemptId) {
    const sequences = JSON.parse(document.getElementById('sequencesJson').value);
    const totalBowls = parseInt(document.getElementById('totalBowls').value);
    const maxScore = parseInt(document.getElementById('maxScore').value);
    const scoringType = document.getElementById('scoringType')?.value || 'standard';

    let rollCount = parseInt(document.getElementById('currentRollCount').value);
    let totalScore = parseInt(document.getElementById('currentTotalScore').value);
    let toucher = 0;
    let lastSequenceIndex = -1;

    // UI Elements
    const toucherBtn = document.getElementById('toucherBtn');
    const undoBtn = document.getElementById('undoBtn');
    const quitBtn = document.getElementById('quitBtn');
    const scorePopup = document.getElementById('scorePopup');

    // Calculate current position in sequences
    function getCurrentPosition() {
        let bowlsProcessed = 0;

        for (let i = 0; i < sequences.length; i++) {
            const seq = sequences[i];
            const seqBowls = parseInt(seq.bowl_count);

            if (bowlsProcessed + seqBowls > rollCount) {
                return {
                    sequenceIndex: i,
                    bowlInSequence: rollCount - bowlsProcessed + 1,
                    sequence: seq,
                    totalBowlsInSequence: seqBowls
                };
            }
            bowlsProcessed += seqBowls;
        }

        // All complete
        return {
            sequenceIndex: sequences.length - 1,
            bowlInSequence: sequences[sequences.length - 1].bowl_count,
            sequence: sequences[sequences.length - 1],
            complete: true
        };
    }

    // Update UI to reflect current position
    function updateUI() {
        const pos = getCurrentPosition();

        // Check if sequence changed - show modal dialog
        if (lastSequenceIndex !== -1 && pos.sequenceIndex !== lastSequenceIndex) {
            showSequenceChangeModal(pos);
        }
        lastSequenceIndex = pos.sequenceIndex;

        // Update sequence info
        document.getElementById('currentSeqNum').textContent = pos.sequenceIndex + 1;

        // Update end length badge
        const endLengthBadge = document.getElementById('endLengthBadge');
        const endLengthNames = { 9: 'Long End', 10: 'Middle End', 11: 'Short End' };
        endLengthBadge.textContent = endLengthNames[pos.sequence.end_length] || '';

        // Update delivery badge
        const deliveryBadge = document.getElementById('deliveryBadge');
        const deliveryNames = { 13: 'Backhand', 14: 'Forehand' };
        deliveryBadge.textContent = deliveryNames[pos.sequence.delivery] || '';
        deliveryBadge.className = 'delivery-indicator ' +
            (pos.sequence.delivery == 14 ? 'delivery-forehand' : 'delivery-backhand');

        // Update bowl header
        document.getElementById('bowlHeader').textContent = `Bowl ${pos.bowlInSequence}`;
        document.getElementById('currentBowlNum').textContent = pos.bowlInSequence;

        // Update score
        document.getElementById('scoreDisplay').textContent = totalScore;
        document.getElementById('totalScore').textContent = totalScore;

        // Update progress
        const percent = (rollCount / totalBowls) * 100;
        document.getElementById('progressFill').style.width = `${percent}%`;
        document.getElementById('rollCountDisplay').textContent = `${rollCount}/${totalBowls} total`;

        // Update undo button
        undoBtn.disabled = rollCount === 0;
    }

    // Show score popup animation
    function showScorePopup(score) {
        scorePopup.textContent = `+${score}`;
        scorePopup.classList.add('show');
        setTimeout(() => scorePopup.classList.remove('show'), 600);
    }

    // Show sequence change modal
    function showSequenceChangeModal(pos) {
        const endLengthNames = { 9: 'Long End', 10: 'Middle End', 11: 'Short End' };
        const deliveryNames = { 13: 'Backhand', 14: 'Forehand' };

        const endLength = endLengthNames[pos.sequence.end_length] || '';
        const delivery = deliveryNames[pos.sequence.delivery] || '';

        // Create modal if it doesn't exist
        let modal = document.getElementById('sequenceModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'sequenceModal';
            modal.className = 'sequence-modal-overlay';
            modal.innerHTML = `
                <div class="sequence-modal">
                    <h2>New Sequence</h2>
                    <div class="sequence-modal-content">
                        <div class="sequence-modal-number">Sequence <span id="modalSeqNum"></span></div>
                        <div class="sequence-modal-details">
                            <span class="sequence-modal-length" id="modalEndLength"></span>
                            <span class="sequence-modal-delivery" id="modalDelivery"></span>
                        </div>
                        <div class="sequence-modal-bowls"><span id="modalBowlCount"></span> bowls</div>
                    </div>
                    <button class="btn btn-primary sequence-modal-btn" id="modalAcceptBtn">Ready</button>
                </div>
            `;
            document.body.appendChild(modal);

            document.getElementById('modalAcceptBtn').addEventListener('click', () => {
                modal.classList.remove('show');
            });
        }

        // Update modal content
        document.getElementById('modalSeqNum').textContent = `${pos.sequenceIndex + 1}/${sequences.length}`;
        document.getElementById('modalEndLength').textContent = endLength;
        document.getElementById('modalEndLength').className = 'sequence-modal-length';
        document.getElementById('modalDelivery').textContent = delivery;
        document.getElementById('modalDelivery').className = 'sequence-modal-delivery ' +
            (pos.sequence.delivery == 14 ? 'delivery-forehand' : 'delivery-backhand');
        document.getElementById('modalBowlCount').textContent = pos.sequence.bowl_count;

        // Show modal
        modal.classList.add('show');
    }

    // Record a roll
    async function saveRoll(result) {
        const pos = getCurrentPosition();

        if (pos.complete) {
            return;
        }

        const json = await API.post('../api/challenge.php', {
            action: 'roll',
            attempt_id: attemptId,
            end_length: pos.sequence.end_length,
            delivery: pos.sequence.delivery,
            result: result,
            toucher: toucher
        });

        if (json.success) {
            // Update state
            rollCount = json.progress.roll_count;
            totalScore = json.progress.total_score;

            // Show score animation
            showScorePopup(json.roll.score);

            // Flash success
            UI.flashSuccess();

            // Reset toucher
            toucher = 0;
            toucherBtn.classList.remove('active');

            // Check if complete
            if (json.progress.is_complete) {
                UI.redirect(`results.php?attempt=${attemptId}`);
                return;
            }

            // Update UI
            updateUI();
        } else {
            UI.showFlash('error', json.error || 'Failed to save roll');
        }
    }

    // Undo last roll
    async function undoLastRoll() {
        if (rollCount === 0) return;

        const json = await API.delete(`../api/challenge.php?attempt_id=${attemptId}&undo=1`);

        if (json.success) {
            rollCount = json.progress.roll_count;
            totalScore = json.progress.total_score;
            updateUI();
        } else {
            UI.showFlash('error', json.error || 'Cannot undo');
        }
    }

    // Quit challenge
    function quitChallenge() {
        if (UI.confirm('Are you sure you want to quit this challenge? Your progress will be saved.')) {
            UI.redirect('index.php');
        }
    }

    if (scoringType === 'trail_rest') {
        // Trail & Rest drill: big radio-style buttons
        document.querySelectorAll('.btn-trail-rest').forEach(btn => {
            btn.addEventListener('click', () => {
                saveRoll(parseInt(btn.dataset.value));
            });
        });
    } else {
        // Standard grid
        document.querySelectorAll('.btn-pos').forEach(btn => {
            btn.addEventListener('click', () => {
                saveRoll(parseInt(btn.dataset.value));
            });
        });

        document.querySelectorAll('.btn-miss').forEach(btn => {
            btn.addEventListener('click', () => {
                saveRoll(parseInt(btn.dataset.value));
            });
        });

        toucherBtn?.addEventListener('click', () => {
            toucher = toucher ? 0 : 1;
            toucherBtn.classList.toggle('active', toucher === 1);
        });
    }

    undoBtn?.addEventListener('click', undoLastRoll);
    quitBtn?.addEventListener('click', quitChallenge);

    // Initial UI update
    updateUI();
}
