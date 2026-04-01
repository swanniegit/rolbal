// Session helpers - visibility toggle, delete confirmation

function initVisibilityToggles() {
    document.querySelectorAll('.visibility-toggle').forEach(btn => {
        btn.addEventListener('click', async function() {
            const sessionId = this.dataset.sessionId;

            try {
                const res = await fetch('api/session.php', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: parseInt(sessionId), action: 'toggle_visibility' })
                });
                const data = await res.json();

                if (data.success) {
                    this.dataset.public = data.is_public ? '1' : '0';
                    this.querySelector('.visibility-icon').textContent = data.is_public ? '👁' : '👁‍🗨';
                    this.title = data.is_public ? 'Public' : 'Private';
                } else {
                    console.error('Toggle failed:', data.error);
                }
            } catch (err) {
                console.error('Failed to toggle visibility:', err);
            }
        });
    });
}

function initDeleteButtons() {
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Delete this game?')) return;

            const id = this.dataset.id;
            try {
                const res = await fetch(`api/session.php?id=${id}`, { method: 'DELETE' });
                const json = await res.json();
                if (json.success) {
                    const card = this.closest('.session-card-wrap') || this.closest('.session-item');
                    if (card) card.remove();
                } else {
                    alert(json.error || 'Failed to delete');
                }
            } catch (err) {
                alert('Error: ' + err.message);
            }
        });
    });
}

// Auto-init on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    initVisibilityToggles();
    initDeleteButtons();
});
