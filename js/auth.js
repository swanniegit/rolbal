// Auth form helpers - password toggle, form validation

function initPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = document.getElementById(this.dataset.target);
            const isPassword = target.type === 'password';
            target.type = isPassword ? 'text' : 'password';
            this.querySelector('.eye-icon').textContent = isPassword ? '👁‍🗨' : '👁';
        });
    });
}

function initButtonToggles() {
    document.querySelectorAll('.btn-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const field = this.dataset.field;
            document.querySelectorAll(`[data-field="${field}"]`).forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(field).value = this.dataset.value;
        });
    });
}

function showFormError(elementId, message) {
    const errorEl = document.getElementById(elementId);
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function hideFormError(elementId) {
    const errorEl = document.getElementById(elementId);
    if (errorEl) {
        errorEl.classList.add('hidden');
    }
}

function setButtonLoading(btn, loading, originalText) {
    btn.disabled = loading;
    btn.textContent = loading ? 'Please wait...' : originalText;
}

// Auto-init on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    initPasswordToggles();
    initButtonToggles();
});
