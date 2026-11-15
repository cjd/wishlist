/**
 * Back to Top Floating Button
 * Smooth scroll-to-top button that appears when user scrolls down
 */
(function() {
    function initBackToTop() {
        if (document.getElementById('backToTopBtn')) return;

        var btn = document.createElement('button');
        btn.id = 'backToTopBtn';
        btn.className = 'back-to-top-btn';
        btn.setAttribute('type', 'button');
        btn.setAttribute('aria-label', 'Back to top');
        btn.setAttribute('title', 'Back to top');
        btn.innerHTML = '&#8593;'; // ↑ arrow

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        document.body.appendChild(btn);

        var ticking = false;
        window.addEventListener('scroll', function() {
            if (!ticking) {
                window.requestAnimationFrame(function() {
                    if (window.scrollY > 280) {
                        btn.classList.add('visible');
                    } else {
                        btn.classList.remove('visible');
                    }
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBackToTop);
    } else {
        initBackToTop();
    }
})();
