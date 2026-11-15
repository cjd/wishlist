/**
 * Duplicate Item Detector
 * Warns users if an item with the same URL or title already exists in their wishlist
 */
(function() {
    function normalizeUrl(url) {
        if (!url || typeof url !== 'string') return '';
        return url.trim().toLowerCase()
            .replace(/^https?:\/\//, '')
            .replace(/^www\./, '')
            .replace(/\/+$/, '')
            .split('?')[0]; // compare core product path
    }

    function initDuplicateDetector() {
        var existing = window.existingWishlistItems;
        if (!Array.isArray(existing) || existing.length === 0) return;

        var titleInput = document.querySelector('input[name="title"]');
        var urlInput = document.querySelector('input[name="link1url"]');
        var warningRow = document.getElementById('dupWarningRow');
        var titleSpan = document.getElementById('dupItemTitle');
        var catSpan = document.getElementById('dupItemCat');

        if (!titleInput || !warningRow || !titleSpan || !catSpan) return;

        function check() {
            var titleVal = (titleInput.value || '').trim().toLowerCase();
            var urlVal = urlInput ? normalizeUrl(urlInput.value || '') : '';

            if (!titleVal && !urlVal) {
                warningRow.style.display = 'none';
                return;
            }

            var match = null;
            for (var i = 0; i < existing.length; i++) {
                var item = existing[i];
                var itemNormUrl = normalizeUrl(item.url);
                var itemTitle = (item.title || '').trim().toLowerCase();

                // Check URL match first (high confidence)
                if (urlVal && itemNormUrl && urlVal === itemNormUrl) {
                    match = item;
                    break;
                }

                // Check exact title match (only if length >= 4 chars)
                if (titleVal && titleVal.length >= 4 && itemTitle === titleVal) {
                    match = item;
                    break;
                }
            }

            if (match) {
                titleSpan.textContent = '"' + match.title + '"';
                catSpan.textContent = match.cat || 'another category';
                warningRow.style.display = '';
            } else {
                warningRow.style.display = 'none';
            }
        }

        titleInput.addEventListener('input', check);
        if (urlInput) {
            urlInput.addEventListener('input', check);
            urlInput.addEventListener('paste', function() {
                setTimeout(check, 100);
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDuplicateDetector);
    } else {
        initDuplicateDetector();
    }
})();
