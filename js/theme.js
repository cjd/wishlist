/**
 * Theme Manager for WishList Site
 * Supports Light, Dark, and System modes with localStorage persistence.
 */
(function() {
    var savedTheme = localStorage.getItem('wishlist_theme');
    if (savedTheme) {
        document.documentElement.setAttribute('data-theme', savedTheme);
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.setAttribute('data-theme', 'dark');
    }

    // Listen for system theme changes if no manual override
    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            if (!localStorage.getItem('wishlist_theme')) {
                document.documentElement.setAttribute('data-theme', e.matches ? 'dark' : 'light');
                updateThemeToggleLabel();
            }
        });
    }
})();

function toggleTheme() {
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var newTheme = isDark ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('wishlist_theme', newTheme);
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

// Register Service Worker for PWA installability and offline list caching
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        var swPath = (typeof base_url !== 'undefined' ? base_url : '') + '/service-worker.js';
        navigator.serviceWorker.register(swPath).catch(function() {
            // Silently fallback if non-HTTPS or service workers not supported
        });
    });
}

