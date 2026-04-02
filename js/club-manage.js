/**
 * Club Management Page JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    const clubId = window.CLUB_ID;
    const clubSlug = window.CLUB_SLUG;
    const clubName = window.CLUB_NAME;

    // Edit club form
    document.getElementById('editClubForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        if (!this.querySelector('[name="is_public"]').checked) {
            formData.set('is_public', '0');
        }

        try {
            const res = await fetch('../api/club.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showMessage('success', 'Club updated successfully');
                if (data.club.slug !== clubSlug) {
                    window.location.href = 'manage.php?slug=' + data.club.slug;
                }
            } else {
                showMessage('error', data.error || 'Update failed');
            }
        } catch (err) {
            showMessage('error', 'Network error');
        }
    });

    // Icon upload
    document.getElementById('iconForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('iconFile');
        if (!fileInput.files[0]) {
            showMessage('error', 'Please select an image');
            return;
        }

        const formData = new FormData(this);
        const btn = document.getElementById('uploadIconBtn');
        btn.disabled = true;
        btn.textContent = 'Uploading...';

        try {
            const res = await fetch('../api/club.php', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                showMessage('success', 'Icon updated');
                location.reload();
            } else {
                showMessage('error', data.error || 'Upload failed');
            }
        } catch (err) {
            showMessage('error', 'Network error');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Upload Icon';
        }
    });

    // Remove icon
    const removeIconBtn = document.getElementById('removeIconBtn');
    if (removeIconBtn) {
        removeIconBtn.addEventListener('click', async function() {
            if (!confirm('Remove club icon?')) return;

            const formData = new FormData();
            formData.append('action', 'remove_icon');
            formData.append('club_id', clubId);

            try {
                const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    location.reload();
                } else {
                    showMessage('error', data.error || 'Failed to remove icon');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            }
        });
    }

    // Role change
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', async function() {
            const memberId = this.dataset.memberId;
            const role = this.value;

            const formData = new FormData();
            formData.append('action', 'update_member_role');
            formData.append('club_id', clubId);
            formData.append('member_id', memberId);
            formData.append('role', role);

            try {
                const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    showMessage('success', 'Role updated');
                } else {
                    showMessage('error', data.error || 'Failed to update role');
                    location.reload();
                }
            } catch (err) {
                showMessage('error', 'Network error');
            }
        });
    });

    // Remove member
    document.querySelectorAll('.remove-member').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Remove this member from the club?')) return;

            const memberId = this.dataset.memberId;
            const memberItem = this.closest('.manage-member-item');

            const formData = new FormData();
            formData.append('action', 'remove_member');
            formData.append('club_id', clubId);
            formData.append('member_id', memberId);

            try {
                const res = await fetch('../api/club.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    memberItem.remove();
                    showMessage('success', 'Member removed');
                } else {
                    showMessage('error', data.error || 'Failed to remove member');
                }
            } catch (err) {
                showMessage('error', 'Network error');
            }
        });
    });

    // Delete club
    const deleteBtn = document.getElementById('deleteClubBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function() {
            const confirmation = prompt(`Type "${clubName}" to confirm deletion:`);

            if (confirmation !== clubName) {
                if (confirmation !== null) {
                    showMessage('error', 'Club name did not match');
                }
                return;
            }

            this.disabled = true;
            this.textContent = 'Deleting...';

            try {
                const res = await fetch(`../api/club.php?id=${clubId}`, { method: 'DELETE' });
                const data = await res.json();

                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    showMessage('error', data.error || 'Failed to delete club');
                    this.disabled = false;
                    this.textContent = 'Delete Club';
                }
            } catch (err) {
                showMessage('error', 'Network error');
                this.disabled = false;
                this.textContent = 'Delete Club';
            }
        });
    }

    function showMessage(type, message) {
        const msgDiv = document.getElementById('formMessage');
        msgDiv.textContent = message;
        msgDiv.className = 'flash flash-' + type;
        msgDiv.classList.remove('hidden');
        setTimeout(() => msgDiv.classList.add('hidden'), 3000);
    }
});
