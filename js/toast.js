/**
 * Floating Toast Notification Utility
 */
function showToast(message, type, duration) {
    type = type || 'success';
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

    var icon = (type === 'error') ? '⚠️ ' : (type === 'info' ? 'ℹ️ ' : '✓ ');
    toast.innerHTML = '<span class="toast-icon">' + icon + '</span><span class="toast-text">' + message + '</span>';

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
