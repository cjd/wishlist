/**
 * Lightweight Native Image Lightbox
 * Dependency-free replacement for Featherlight using HTML <dialog>.
 */
(function() {
    var dialog = null;
    var imgEl = null;
    var captionEl = null;

    function isSafeImageSrc(url) {
        if (!url || typeof url !== 'string') return false;
        var trimmed = url.trim();
        if (!trimmed) return false;
        if (/^(?:javascript|data|vbscript):/i.test(trimmed)) return false;
        return true;
    }

    function ensureDialog() {
        if (dialog) return dialog;
        dialog = document.createElement('dialog');
        dialog.className = 'wl-lightbox-dialog';

        var wrapper = document.createElement('div');
        wrapper.className = 'wl-lightbox-inner';

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'wl-lightbox-close';
        closeBtn.setAttribute('aria-label', 'Close image preview');
        closeBtn.textContent = '✕';
        closeBtn.addEventListener('click', closeLightbox);

        imgEl = document.createElement('img');
        imgEl.className = 'wl-lightbox-img';
        imgEl.alt = '';

        captionEl = document.createElement('div');
        captionEl.className = 'wl-lightbox-caption';

        wrapper.appendChild(closeBtn);
        wrapper.appendChild(imgEl);
        wrapper.appendChild(captionEl);
        dialog.appendChild(wrapper);

        dialog.addEventListener('click', function(e) {
            if (e.target === dialog) {
                closeLightbox();
            }
        });

        document.body.appendChild(dialog);
        return dialog;
    }

    function openLightbox(src, title) {
        if (!isSafeImageSrc(src)) return;
        var dlg = ensureDialog();
        imgEl.src = src;
        imgEl.alt = title || '';
        captionEl.textContent = title || '';
        captionEl.style.display = title ? 'block' : 'none';

        if (typeof dlg.showModal === 'function') {
            if (!dlg.open) {
                dlg.showModal();
            }
        } else {
            dlg.setAttribute('open', '');
        }
    }

    function closeLightbox() {
        if (!dialog) return;
        if (typeof dialog.close === 'function' && dialog.open) {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        if (imgEl) {
            imgEl.removeAttribute('src');
        }
    }

    document.addEventListener('click', function(e) {
        var trigger = e.target && e.target.closest ? e.target.closest('[data-lightbox], [data-featherlight]') : null;
        if (!trigger) return;
        var src = trigger.getAttribute('data-lightbox') || trigger.getAttribute('data-featherlight') || trigger.getAttribute('href');
        if (!isSafeImageSrc(src)) return;
        e.preventDefault();
        openLightbox(src, trigger.getAttribute('title') || '');
    });
})();
