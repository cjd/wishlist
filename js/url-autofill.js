/**
 * Product URL Auto-Fill Helper
 * Queries modifyList/fetchUrlMetadata.php to populate Title, Price, Description, Store & Thumbnail
 */
(function() {
    function initUrlAutofill() {
        var btn = document.getElementById('autofillUrlBtn');
        var urlInput = document.getElementById('link1url');
        var statusEl = document.getElementById('autofillStatus');
        var csrfInput = document.querySelector('input[name="csrf_token"]');
        var titleInput = document.querySelector('input[name="title"]');
        var descInput = document.querySelector('input[name="desc"]');
        var priceInput = document.querySelector('input[name="price"]');
        var storeInput = document.getElementById('link1');
        var fileInput = document.querySelector('input[type="file"][name="image"]');
        var fetchedImgInput = document.getElementById('fetchedImageUrl');
        var previewBox = document.getElementById('fetchedImagePreviewBox');
        var previewImg = document.getElementById('fetchedImagePreview');
        var clearImgBtn = document.getElementById('clearFetchedImageBtn');

        if (!btn || !urlInput || !csrfInput) return;

        function setStatus(msg, isError) {
            if (!statusEl) return;
            statusEl.textContent = msg || '';
            statusEl.className = isError ? 'autofill-status autofill-status-warn' : 'autofill-status autofill-status-ok';
            statusEl.style.display = msg ? 'block' : 'none';
        }

        function clearFetchedImage() {
            if (fetchedImgInput) fetchedImgInput.value = '';
            if (previewImg) previewImg.removeAttribute('src');
            if (previewBox) previewBox.style.display = 'none';
        }

        if (clearImgBtn) {
            clearImgBtn.addEventListener('click', clearFetchedImage);
        }
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files.length > 0) {
                    clearFetchedImage();
                }
            });
        }

        btn.addEventListener('click', function() {
            var rawUrl = (urlInput.value || '').trim();
            if (!rawUrl) {
                setStatus('Paste a product link into the URL box first.', true);
                urlInput.focus();
                return;
            }

            btn.disabled = true;
            var origLabel = btn.textContent;
            btn.textContent = '⏳ Reading link...';
            setStatus('Fetching product details...', false);

            var body = new URLSearchParams();
            body.append('csrf_token', csrfInput.value);
            body.append('url', rawUrl);

            fetch('fetchUrlMetadata.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: body.toString()
            })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = origLabel;

                if (!data || !data.ok) {
                    setStatus((data && data.error) ? data.error : 'Could not auto-fill from that link. You can enter details manually.', true);
                    return;
                }

                var filledParts = [];

                if (data.title && titleInput && !titleInput.value.trim()) {
                    titleInput.value = data.title;
                    titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                    filledParts.push('Title');
                }
                if (data.price && priceInput && !priceInput.value.trim()) {
                    priceInput.value = data.price;
                    filledParts.push('Price');
                }
                if (data.description && descInput && !descInput.value.trim()) {
                    descInput.value = data.description;
                    filledParts.push('Description');
                }

                // Trigger store-detector & duplicate-detector first
                urlInput.dispatchEvent(new Event('input', { bubbles: true }));
                if (data.store && storeInput && !storeInput.value.trim()) {
                    storeInput.value = data.store;
                }

                var hasLocalFile = fileInput && fileInput.files && fileInput.files.length > 0;
                if (data.image && fetchedImgInput && !hasLocalFile) {
                    fetchedImgInput.value = data.image;
                    if (previewImg && previewBox) {
                        previewImg.src = data.image;
                        previewBox.style.display = 'flex';
                    }
                    filledParts.push('Photo');
                }

                if (filledParts.length > 0) {
                    setStatus('✨ Auto-filled: ' + filledParts.join(', '), false);
                } else {
                    setStatus('Link checked (existing fields kept as-is).', false);
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = origLabel;
                setStatus('Could not reach auto-fill service. You can still enter details manually.', true);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUrlAutofill);
    } else {
        initUrlAutofill();
    }
})();
