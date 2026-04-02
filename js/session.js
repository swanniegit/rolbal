// Session helpers - visibility toggle, delete confirmation

function initVisibilityToggles() {
    document.querySelectorAll('.visibility-toggle').forEach(btn => {
        btn.addEventListener('click', async function() {
            const sessionId = this.dataset.sessionId;

            const data = await API.patch('api/session.php', {
                id: parseInt(sessionId),
                action: 'toggle_visibility'
            });

            if (data.success) {
                this.dataset.public = data.is_public ? '1' : '0';
                this.querySelector('.visibility-icon').textContent = data.is_public ? '👁' : '👁‍🗨';
                this.title = data.is_public ? 'Public' : 'Private';
            } else {
                console.error('Toggle failed:', data.error);
            }
        });
    });
}

function initDeleteButtons() {
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!UI.confirm('Delete this game?')) return;

            const id = this.dataset.id;
            const json = await API.delete(`api/session.php?id=${id}`);

            if (json.success) {
                const card = this.closest('.session-card-wrap') || this.closest('.session-item');
                UI.removeElement(card);
            } else {
                UI.showFlash('error', json.error || 'Failed to delete');
            }
        });
    });
}

// Auto-init on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    initVisibilityToggles();
    initDeleteButtons();
});
