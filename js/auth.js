// Auth form helpers - delegates to UI utilities

function initPasswordToggles() {
    UI.initPasswordToggles('.password-toggle');
}

function initButtonToggles() {
    UI.initToggleButtons('.btn-toggle');
}

function showFormError(elementId, message) {
    UI.showFormError(elementId, message);
}

function hideFormError(elementId) {
    UI.hideFormError(elementId);
}

function setButtonLoading(btn, loading, originalText) {
    UI.setButtonLoading(btn, loading, originalText);
}

// Auto-init on DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    initPasswordToggles();
    initButtonToggles();
});
