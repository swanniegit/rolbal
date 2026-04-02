/**
 * Centralized API Handler
 * Reduces duplication of fetch/error handling across all JS modules
 */

const API = {
    /**
     * POST request with FormData
     * @param {string} endpoint - API endpoint URL
     * @param {Object|FormData} data - Data to send
     * @returns {Promise<Object>} JSON response
     */
    async post(endpoint, data = {}) {
        let body;
        if (data instanceof FormData) {
            body = data;
        } else {
            body = new FormData();
            Object.entries(data).forEach(([key, value]) => {
                if (value !== undefined && value !== null) {
                    body.append(key, value);
                }
            });
        }

        return this._fetch(endpoint, {
            method: 'POST',
            body: body
        });
    },

    /**
     * POST request with JSON body
     * @param {string} endpoint - API endpoint URL
     * @param {Object} data - Data to send as JSON
     * @returns {Promise<Object>} JSON response
     */
    async postJson(endpoint, data = {}) {
        return this._fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },

    /**
     * PATCH request with JSON body
     * @param {string} endpoint - API endpoint URL
     * @param {Object} data - Data to send as JSON
     * @returns {Promise<Object>} JSON response
     */
    async patch(endpoint, data = {}) {
        return this._fetch(endpoint, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    },

    /**
     * DELETE request
     * @param {string} endpoint - API endpoint URL
     * @returns {Promise<Object>} JSON response
     */
    async delete(endpoint) {
        return this._fetch(endpoint, { method: 'DELETE' });
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
