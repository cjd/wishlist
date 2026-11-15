/**
 * Duplicate Item Detector
 * Warns users if an item with the same URL or title already exists in their wishlist
 */
(function() {
    var TRACKING_PARAM_RE = /^(?:utm_.*|fbclid|gclid|dclid|msclkid|mc_cid|mc_eid|ref|ref_|tag|affid|spm|igshid)$/i;

    function normalizeUrl(url) {
        if (!url || typeof url !== 'string') return '';
        var raw = url.trim();
        if (!raw) return '';

        try {
            var withProto = raw.indexOf('://') === -1 ? 'https://' + raw : raw;
            var parsed = new URL(withProto);
            var host = parsed.hostname.toLowerCase().replace(/^www\./, '');
            var path = parsed.pathname.replace(/\/+$/, '').toLowerCase();

            var keptParams = [];
            parsed.searchParams.forEach(function(val, key) {
                if (!TRACKING_PARAM_RE.test(key) && val !== '') {
                    keptParams.push(key.toLowerCase() + '=' + val.toLowerCase());
                }
            });
            keptParams.sort();

            return host + path + (keptParams.length ? '?' + keptParams.join('&') : '');
        } catch (e) {
            return raw.toLowerCase()
                .replace(/^https?:\/\//, '')
                .replace(/^www\./, '')
                .replace(/\/+$/, '');
        }
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

                if (urlVal && itemNormUrl && urlVal === itemNormUrl) {
                    match = item;
                    break;
                }

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
