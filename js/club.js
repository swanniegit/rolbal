/**
 * Club JavaScript utilities
 */

const ClubUtils = {
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    async apiCall(endpoint, options = {}) {
        try {
            const res = await fetch(endpoint, options);
            return await res.json();
        } catch (err) {
            console.error('API call failed:', err);
            return { success: false, error: 'Network error' };
        }
    },

    async joinClub(clubId) {
        const formData = new FormData();
        formData.append('action', 'join');
        formData.append('club_id', clubId);

        return this.apiCall('../api/club.php', {
            method: 'POST',
            body: formData
        });
    },

    async leaveClub(clubId) {
        const formData = new FormData();
        formData.append('action', 'leave');
        formData.append('club_id', clubId);

        return this.apiCall('../api/club.php', {
            method: 'POST',
            body: formData
        });
    },

    async setPrimaryClub(clubId) {
        const formData = new FormData();
        formData.append('action', 'set_primary');
        formData.append('club_id', clubId || '');

        return this.apiCall('../api/club.php', {
            method: 'POST',
            body: formData
        });
    },

    renderClubIcon(club, size = 'medium') {
        if (club.icon_filename) {
            return `<img src="../assets/club-icons/${this.escapeHtml(club.icon_filename)}" alt="" class="club-icon">`;
        }
        return `<span class="club-icon-placeholder">${club.name.charAt(0).toUpperCase()}</span>`;
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = ClubUtils;
}
