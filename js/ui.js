/**
 * Centralized UI Utilities
 * Common helpers for flash messages, button states, toggles, etc.
 */

const UI = {
    /**
     * Show a flash message
     * @param {string} type - 'success', 'error', 'info', 'warning'
     * @param {string} message - Message to display
     * @param {number} duration - Auto-hide duration in ms (0 = no auto-hide)
     * @param {string} targetId - ID of the flash container element
     */
    showFlash(type, message, duration = 3000, targetId = 'formMessage') {
        let msgDiv = document.getElementById(targetId);

        if (!msgDiv) {
            msgDiv = document.createElement('div');
            msgDiv.id = targetId;
            const main = document.querySelector('.main-content');
            if (main) {
                main.insertBefore(msgDiv, main.firstChild);
            } else {
                document.body.insertBefore(msgDiv, document.body.firstChild);
            }
        }

        msgDiv.textContent = message;
        msgDiv.className = `flash flash-${type}`;
        msgDiv.classList.remove('hidden');

        // Scroll into view so user sees the message
        msgDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

        if (duration > 0) {
            setTimeout(() => msgDiv.classList.add('hidden'), duration);
        }

        return msgDiv;
    },

    /**
     * Hide a flash message
     * @param {string} targetId - ID of the flash container element
     */
    hideFlash(targetId = 'formMessage') {
        const msgDiv = document.getElementById(targetId);
        if (msgDiv) {
            msgDiv.classList.add('hidden');
        }
    },

    /**
     * Show form error (typically below form fields)
     * @param {string} targetId - ID of the error element
     * @param {string} message - Error message
     */
    showFormError(targetId, message) {
        const errorDiv = document.getElementById(targetId);
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.classList.remove('hidden');
        }
    },

    /**
     * Hide form error
     * @param {string} targetId - ID of the error element
     */
    hideFormError(targetId) {
        const errorDiv = document.getElementById(targetId);
        if (errorDiv) {
            errorDiv.classList.add('hidden');
        }
    },

    /**
     * Flash success animation on body
     * @param {number} duration - Animation duration in ms
     */
    flashSuccess(duration = 300) {
        document.body.classList.add('flash-success');
        setTimeout(() => document.body.classList.remove('flash-success'), duration);
    },

    /**
     * Set button loading state
     * @param {HTMLElement} btn - Button element
     * @param {boolean} loading - Whether loading
     * @param {string} originalText - Text to restore when not loading
     * @returns {HTMLElement} The button element
     */
    setButtonLoading(btn, loading, originalText = '') {
        if (!btn) return btn;

        btn.disabled = loading;
        if (loading) {
            btn._originalText = btn._originalText || btn.textContent;
            btn.textContent = 'Please wait...';
        } else {
            btn.textContent = originalText || btn._originalText || btn.textContent;
        }
        return btn;
    },

    /**
     * Initialize toggle button groups (data-field, data-value pattern)
     * @param {string} selector - CSS selector for toggle buttons
     */
    initToggleButtons(selector = '.btn-toggle') {
        document.querySelectorAll(selector).forEach(btn => {
            btn.addEventListener('click', function() {
                const field = this.dataset.field;
                const value = this.dataset.value;

                // Deactivate siblings
                document.querySelectorAll(`[data-field="${field}"]`)
                    .forEach(b => b.classList.remove('active'));

                // Activate this one
                this.classList.add('active');

                // Update hidden input
                const target = document.getElementById(field);
                if (target) target.value = value;
            });
        });
    },

    /**
     * Initialize password visibility toggles
     * @param {string} selector - CSS selector for toggle buttons
     */
    initPasswordToggles(selector = '.password-toggle') {
        document.querySelectorAll(selector).forEach(btn => {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                if (input) {
                    const isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                }
            });
        });
    },

    /**
     * Confirm action with dialog
     * @param {string} message - Confirmation message
     * @returns {boolean} Whether user confirmed
     */
    confirm(message = 'Are you sure?') {
        return window.confirm(message);
    },

    /**
     * Confirm delete with name typing
     * @param {string} itemName - Name that must be typed to confirm
     * @param {string} promptMessage - Message for the prompt
     * @returns {boolean} Whether user confirmed correctly
     */
    confirmDelete(itemName, promptMessage = null) {
        const msg = promptMessage || `Type "${itemName}" to confirm deletion:`;
        const confirmation = prompt(msg);

        if (confirmation === null) {
            return false;
        }

        if (confirmation !== itemName) {
            this.showFlash('error', 'Name did not match');
            return false;
        }

        return true;
    },

    /**
     * Remove element from DOM
     * @param {HTMLElement|string} element - Element or selector to remove
     */
    removeElement(element) {
        if (typeof element === 'string') {
            element = document.querySelector(element);
        }
        if (element) {
            element.remove();
        }
    },

    /**
     * Reload page
     */
    reload() {
        window.location.reload();
    },

    /**
     * Redirect to URL
     * @param {string} url - URL to redirect to
     */
    redirect(url) {
        window.location.href = url;
    }
};

// Export for module systems, also available globally
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UI;
}
