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

        const data = await API.post('../api/club.php', formData);

        if (data.success) {
            UI.showFlash('success', 'Club updated successfully');
            if (data.club.slug !== clubSlug) {
                UI.redirect('manage.php?slug=' + data.club.slug);
            }
        } else {
            UI.showFlash('error', data.error || 'Update failed');
        }
    });

    // Icon upload
    document.getElementById('iconForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const fileInput = document.getElementById('iconFile');
        if (!fileInput.files[0]) {
            UI.showFlash('error', 'Please select an image');
            return;
        }

        const formData = new FormData(this);
        const btn = document.getElementById('uploadIconBtn');
        UI.setButtonLoading(btn, true);

        const data = await API.post('../api/club.php', formData);

        if (data.success) {
            UI.showFlash('success', 'Icon updated');
            UI.reload();
        } else {
            UI.showFlash('error', data.error || 'Upload failed');
        }

        UI.setButtonLoading(btn, false, 'Upload Icon');
    });

    // Remove icon
    const removeIconBtn = document.getElementById('removeIconBtn');
    if (removeIconBtn) {
        removeIconBtn.addEventListener('click', async function() {
            if (!UI.confirm('Remove club icon?')) return;

            const data = await API.post('../api/club.php', {
                action: 'remove_icon',
                club_id: clubId
            });

            if (data.success) {
                UI.reload();
            } else {
                UI.showFlash('error', data.error || 'Failed to remove icon');
            }
        });
    }

    // Role change
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', async function() {
            const memberId = this.dataset.memberId;
            const role = this.value;

            const data = await API.post('../api/club.php', {
                action: 'update_member_role',
                club_id: clubId,
                member_id: memberId,
                role: role
            });

            if (data.success) {
                UI.showFlash('success', 'Role updated');
            } else {
                UI.showFlash('error', data.error || 'Failed to update role');
                UI.reload();
            }
        });
    });

    // Remove member
    document.querySelectorAll('.remove-member').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!UI.confirm('Remove this member from the club?')) return;

            const memberId = this.dataset.memberId;
            const memberItem = this.closest('.manage-member-item');

            const data = await API.post('../api/club.php', {
                action: 'remove_member',
                club_id: clubId,
                member_id: memberId
            });

            if (data.success) {
                UI.removeElement(memberItem);
                UI.showFlash('success', 'Member removed');
            } else {
                UI.showFlash('error', data.error || 'Failed to remove member');
            }
        });
    });

    // Save WhatsApp number
    document.querySelectorAll('.save-whatsapp').forEach(btn => {
        btn.addEventListener('click', async function() {
            const memberId = this.dataset.memberId;
            const input = document.querySelector(`.whatsapp-input[data-member-id="${memberId}"]`);
            const whatsappNumber = input.value.trim();

            UI.setButtonLoading(this, true);

            const data = await API.post('../api/club.php', {
                action: 'update_member_whatsapp',
                club_id: clubId,
                member_id: memberId,
                whatsapp_number: whatsappNumber
            });

            if (data.success) {
                UI.showFlash('success', whatsappNumber ? 'WhatsApp number saved' : 'WhatsApp number removed');
                if (data.whatsapp_number) {
                    input.value = data.whatsapp_number;
                }
            } else {
                UI.showFlash('error', data.error || 'Failed to save WhatsApp number');
            }

            UI.setButtonLoading(this, false, 'Save');
        });
    });

    // Delete club
    const deleteBtn = document.getElementById('deleteClubBtn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', async function() {
            if (!UI.confirmDelete(clubName)) return;

            UI.setButtonLoading(this, true);

            const data = await API.delete(`../api/club.php?id=${clubId}`);

            if (data.success) {
                UI.redirect('index.php');
            } else {
                UI.showFlash('error', data.error || 'Failed to delete club');
                UI.setButtonLoading(this, false, 'Delete Club');
            }
        });
    }
});
