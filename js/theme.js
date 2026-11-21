/**
 * Theme Manager for WishList Site
 * Supports Light, Dark, and System modes with localStorage persistence.
 */
(function() {
    var savedTheme = null;
    try {
        savedTheme = localStorage.getItem('wishlist_theme');
    } catch (e) {}

    if (savedTheme === 'dark' || savedTheme === 'light') {
        document.documentElement.setAttribute('data-theme', savedTheme);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    if (window.matchMedia) {
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onSystemChange = function(e) {
            var currentSaved = null;
            try { currentSaved = localStorage.getItem('wishlist_theme'); } catch (err) {}
            if (currentSaved !== 'dark' && currentSaved !== 'light') {
                document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                updateThemeToggleLabel();
            }
        };
        if (typeof mq.addEventListener === 'function') {
            mq.addEventListener('change', onSystemChange);
        }
    }
    if (typeof window.WISHLIST_TEXT_SIZE === 'string') {
        applyTextSize(window.WISHLIST_TEXT_SIZE);
    }
})();

function applyTextSize(size) {
    if (size === 'large' || size === 'xlarge') {
        document.documentElement.setAttribute('data-text-size', size);
    } else {
        document.documentElement.removeAttribute('data-text-size');
    }
}

function toggleTheme() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var newTheme = isDark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    try {
        localStorage.setItem('wishlist_theme', newTheme);
    } catch (e) {}
    updateThemeToggleLabel();
}

function updateThemeToggleLabel() {
    var btn = document.getElementById('themeToggleBtn');
    if (btn) {
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        btn.textContent = isDark ? '☀️' : '🌙';
        btn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
    }
}

document.addEventListener('DOMContentLoaded', updateThemeToggleLabel);

// Register Service Worker for PWA installability and static asset caching
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        var basePath = '';
        if (typeof window.WISHLIST_BASE_URL === 'string' && window.WISHLIST_BASE_URL !== '') {
            try {
                basePath = new URL(window.WISHLIST_BASE_URL, window.location.origin).pathname.replace(/\/+$/, '');
            } catch (e) {
                basePath = window.WISHLIST_BASE_URL.replace(/^https?:\/\/[^/]+/i, '').replace(/\/+$/, '');
            }
        }
        var swPath = basePath + '/service-worker.js';
        navigator.serviceWorker.register(swPath, { updateViaCache: 'none' }).catch(function() {
            // Silently fallback if non-HTTPS or service workers not supported
        });
    });
}
