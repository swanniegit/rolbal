/**
 * Centralized API Handler
 * Reduces duplication of fetch/error handling across all JS modules
 *
 * Security: Automatically includes CSRF tokens in state-changing requests
 */

const API = {
    /**
     * Get CSRF token from page meta tag or global variable
     * @returns {string} CSRF token
     */
    getCsrfToken() {
        // Try meta tag first
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        // Fall back to global variable
        if (typeof CSRF_TOKEN !== 'undefined') {
            return CSRF_TOKEN;
        }
        return '';
    },

    /**
     * POST request with FormData
     * Automatically includes CSRF token
     * @param {string} endpoint - API endpoint URL
     * @param {Object|FormData} data - Data to send
     * @returns {Promise<Object>} JSON response
     */
    async post(endpoint, data = {}) {
        let body;
        if (data instanceof FormData) {
            body = data;
            // Add CSRF token if not already present
            if (!body.has('csrf_token')) {
                body.append('csrf_token', this.getCsrfToken());
            }
        } else {
            body = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    body.append(key, value);
                }
            });
            // Add CSRF token
            if (!body.has('csrf_token')) {
                body.append('csrf_token', this.getCsrfToken());
            }
        }

        return this._fetch(endpoint, {
            method: 'POST',
            body: body
        });
    },

    /**
     * POST request with JSON body
     * Automatically includes CSRF token
     * @param {string} endpoint - API endpoint URL
     * @param {Object} data - Data to send as JSON
     * @returns {Promise<Object>} JSON response
     */
    async postJson(endpoint, data = {}) {
        // Add CSRF token to data
        const dataWithCsrf = { ...data, csrf_token: this.getCsrfToken() };
        return this._fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataWithCsrf)
        });
    },

    /**
     * PATCH request with JSON body
     * Automatically includes CSRF token
     * @param {string} endpoint - API endpoint URL
     * @param {Object} data - Data to send as JSON
     * @returns {Promise<Object>} JSON response
     */
    async patch(endpoint, data = {}) {
        // Add CSRF token to data
        const dataWithCsrf = { ...data, csrf_token: this.getCsrfToken() };
        return this._fetch(endpoint, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(dataWithCsrf)
        });
    },

    /**
     * DELETE request
     * Includes CSRF token in header
     * @param {string} endpoint - API endpoint URL
     * @returns {Promise<Object>} JSON response
     */
    async delete(endpoint) {
        return this._fetch(endpoint, {
            method: 'DELETE',
            headers: {
                'X-CSRF-Token': this.getCsrfToken()
            }
        });
    },

    /**
     * GET request
     * @param {string} endpoint - API endpoint URL
     * @returns {Promise<Object>} JSON response
     */
    async get(endpoint) {
        return this._fetch(endpoint, { method: 'GET' });
    },

    /**
     * Core fetch wrapper with error handling
     * @private
     */
    async _fetch(endpoint, options = {}) {
        try {
            const res = await fetch(endpoint, options);
            const data = await res.json();
            return data;
        } catch (err) {
            console.error('API error:', err);
            return { success: false, error: 'Network error. Please try again.' };
        }
    }
};

// Export for module systems, also available globally
if (typeof module !== 'undefined' && module.exports) {
    module.exports = API;
}
