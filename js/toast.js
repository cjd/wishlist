/**
 * Floating Toast Notification Utility
 */
function showToast(message, type, duration) {
    var allowedTypes = { success: true, error: true, info: true };
    type = (type && allowedTypes[type]) ? type : 'success';
    duration = duration || 3000;

    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    var toast = document.createElement('div');
    toast.className = 'toast-pill toast-' + type;

    var iconSpan = document.createElement('span');
    iconSpan.className = 'toast-icon';
    iconSpan.textContent = (type === 'error') ? '⚠️ ' : (type === 'info' ? 'ℹ️ ' : '✓ ');

    var textSpan = document.createElement('span');
    textSpan.className = 'toast-text';
    textSpan.textContent = String(message == null ? '' : message);

    toast.appendChild(iconSpan);
    toast.appendChild(textSpan);
    container.appendChild(toast);

    requestAnimationFrame(function() {
        toast.classList.add('toast-show');
    });

    setTimeout(function() {
        toast.classList.remove('toast-show');
        toast.classList.add('toast-hide');
        setTimeout(function() {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, duration);
}
