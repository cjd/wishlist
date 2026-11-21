/**
 * Product URL Auto-Fill Helper
 * Queries modifyList/fetchUrlMetadata.php to populate Title, Price, Description, Store & Thumbnail.
 * On Edit Item pages (data-confirm-changes="1"), displays a confirmation modal comparing
 * current vs. fetched values with per-field checkboxes before applying or saving changes.
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
        var removeImgCheckbox = document.getElementById('removeImageCheckbox');

        if (!btn || !urlInput || !csrfInput) return;

        var confirmMode = btn.getAttribute('data-confirm-changes') === '1';

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

        function applyFetchedImage(imgUrl) {
            if (!fetchedImgInput || !imgUrl) return;
            if (fileInput) {
                try { fileInput.value = ''; } catch (e) {}
            }
            if (removeImgCheckbox) {
                removeImgCheckbox.checked = false;
            }
            fetchedImgInput.value = imgUrl;
            if (previewImg && previewBox) {
                previewImg.src = imgUrl;
                previewBox.style.display = 'flex';
            }
        }

        function resolveProposedStore(data, rawUrl) {
            var detected = (typeof window.extractStoreName === 'function')
                ? window.extractStoreName((data && data.final_url) || rawUrl)
                : '';
            var fetched = (data && data.store) ? String(data.store).trim() : '';
            if (detected && (!fetched || (/^[a-z0-9.-]+\.[a-z]{2,}$/i.test(fetched) && detected.toLowerCase() !== fetched.toLowerCase()))) {
                return detected;
            }
            return fetched || detected;
        }

        function buildEditDiffList(data, rawUrl) {
            var diffs = [];

            var proposedTitle = (data.title || '').trim();
            if (proposedTitle && titleInput) {
                var curTitle = titleInput.value.trim();
                if (curTitle !== proposedTitle) {
                    diffs.push({
                        key: 'title',
                        label: 'Title',
                        currentText: curTitle,
                        newText: proposedTitle,
                        apply: function() {
                            titleInput.value = proposedTitle;
                            titleInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }
                    });
                }
            }

            var proposedDesc = (data.description || '').trim();
            if (proposedDesc && descInput) {
                var curDesc = descInput.value.trim();
                if (curDesc !== proposedDesc) {
                    diffs.push({
                        key: 'description',
                        label: 'Description',
                        currentText: curDesc,
                        newText: proposedDesc,
                        apply: function() {
                            descInput.value = proposedDesc;
                        }
                    });
                }
            }

            var proposedPrice = (data.price || '').trim();
            if (proposedPrice && priceInput) {
                var curPriceRaw = priceInput.value.trim();
                var curNum = parseFloat(curPriceRaw.replace(/[$,]/g, ''));
                var propNum = parseFloat(proposedPrice.replace(/[$,]/g, ''));
                var isSamePrice = (!isNaN(curNum) && !isNaN(propNum) && Math.abs(curNum - propNum) < 0.005);
                var isCurZeroOrEmpty = (curPriceRaw === '' || (!isNaN(curNum) && curNum === 0));
                if (!isSamePrice) {
                    diffs.push({
                        key: 'price',
                        label: 'Price',
                        currentText: isCurZeroOrEmpty ? '' : ('$' + (isNaN(curNum) ? curPriceRaw : curNum.toFixed(2))),
                        newText: '$' + (isNaN(propNum) ? proposedPrice : propNum.toFixed(2)),
                        apply: function() {
                            priceInput.value = proposedPrice;
                        }
                    });
                }
            }

            var proposedStore = resolveProposedStore(data, rawUrl);
            var detectedStore = (typeof window.extractStoreName === 'function')
                ? window.extractStoreName((data && data.final_url) || rawUrl)
                : '';
            if (proposedStore && storeInput) {
                var curStore = storeInput.value.trim();
                var matchesCurrent = curStore !== '' && (
                    curStore.toLowerCase() === proposedStore.toLowerCase() ||
                    (detectedStore && curStore.toLowerCase() === detectedStore.toLowerCase())
                );
                if (!matchesCurrent) {
                    diffs.push({
                        key: 'store',
                        label: 'Store / Name',
                        currentText: curStore,
                        newText: proposedStore,
                        apply: function() {
                            storeInput.value = proposedStore;
                        }
                    });
                }
            }

            var proposedImg = (data.image || '').trim();
            if (proposedImg && fetchedImgInput) {
                var curFetchedImg = fetchedImgInput.value.trim();
                var existingSavedImgEl = document.getElementById('existingSavedImageEl');
                var existingSavedSrc = existingSavedImgEl ? (existingSavedImgEl.getAttribute('src') || '') : '';
                var curPreviewSrc = (curFetchedImg && previewImg) ? (previewImg.getAttribute('src') || curFetchedImg) : existingSavedSrc;
                if (proposedImg !== curFetchedImg) {
                    diffs.push({
                        key: 'image',
                        label: 'Photo',
                        isImage: true,
                        currentImg: curPreviewSrc,
                        newImg: proposedImg,
                        apply: function() {
                            applyFetchedImage(proposedImg);
                        }
                    });
                }
            }

            return diffs;
        }

        var dialogEl = null;

        function ensureConfirmDialog() {
            if (dialogEl) return dialogEl;
            dialogEl = document.createElement('dialog');
            dialogEl.className = 'autofill-confirm-dialog';
            dialogEl.setAttribute('aria-labelledby', 'autofillConfirmTitle');
            document.body.appendChild(dialogEl);

            dialogEl.addEventListener('click', function(e) {
                if (e.target === dialogEl) {
                    closeConfirmDialog();
                    setStatus('Auto-fill cancelled (no changes applied).', false);
                }
            });
            return dialogEl;
        }

        function closeConfirmDialog() {
            if (!dialogEl) return;
            if (typeof dialogEl.close === 'function' && dialogEl.open) {
                dialogEl.close();
            } else {
                dialogEl.removeAttribute('open');
                dialogEl.style.display = 'none';
            }
        }

        function openConfirmDialog(diffs, warningNote) {
            var dlg = ensureConfirmDialog();
            dlg.innerHTML = '';

            var header = document.createElement('div');
            header.className = 'autofill-confirm-header';
            var titleH3 = document.createElement('h3');
            titleH3.id = 'autofillConfirmTitle';
            titleH3.textContent = '✨ Confirm Auto-Fill Changes';
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'autofill-confirm-close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.textContent = '×';
            closeBtn.addEventListener('click', function() {
                closeConfirmDialog();
                setStatus('Auto-fill cancelled (no changes applied).', false);
            });
            header.appendChild(titleH3);
            header.appendChild(closeBtn);

            var body = document.createElement('div');
            body.className = 'autofill-confirm-body';

            if (warningNote) {
                var warnBanner = document.createElement('p');
                warnBanner.className = 'autofill-status autofill-status-warn';
                warnBanner.style.marginTop = '0';
                warnBanner.style.marginBottom = '10px';
                warnBanner.textContent = '⚠️ ' + warningNote;
                body.appendChild(warnBanner);
            }

            var intro = document.createElement('p');
            intro.className = 'autofill-confirm-intro';
            intro.textContent = 'Choose which product details from the link you want to apply to this item:';
            body.appendChild(intro);

            var listWrap = document.createElement('div');
            listWrap.className = 'autofill-diff-list';

            var checkboxes = [];

            diffs.forEach(function(diff) {
                var rowLabel = document.createElement('label');
                rowLabel.className = 'autofill-diff-row';

                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.className = 'autofill-diff-checkbox';
                cb.checked = true;
                checkboxes.push({ cb: cb, diff: diff });

                var contentCol = document.createElement('div');
                contentCol.className = 'autofill-diff-content';

                var fieldTitle = document.createElement('div');
                fieldTitle.className = 'autofill-diff-label';
                fieldTitle.textContent = diff.label;
                contentCol.appendChild(fieldTitle);

                var compareGrid = document.createElement('div');
                compareGrid.className = 'autofill-diff-compare';

                var curBox = document.createElement('div');
                curBox.className = 'autofill-diff-cell autofill-diff-old';
                var curTag = document.createElement('span');
                curTag.className = 'autofill-diff-tag';
                curTag.textContent = 'Current:';
                curBox.appendChild(curTag);

                if (diff.isImage) {
                    if (diff.currentImg) {
                        var oldImg = document.createElement('img');
                        oldImg.src = diff.currentImg;
                        oldImg.alt = 'Current photo';
                        oldImg.className = 'autofill-diff-thumb';
                        oldImg.setAttribute('referrerpolicy', 'no-referrer');
                        curBox.appendChild(oldImg);
                    } else {
                        var noImgSpan = document.createElement('span');
                        noImgSpan.className = 'autofill-diff-empty';
                        noImgSpan.textContent = '(No photo)';
                        curBox.appendChild(noImgSpan);
                    }
                } else {
                    var curValSpan = document.createElement('span');
                    if (diff.currentText) {
                        curValSpan.className = 'autofill-diff-val';
                        curValSpan.textContent = diff.currentText;
                    } else {
                        curValSpan.className = 'autofill-diff-empty';
                        curValSpan.textContent = '(empty)';
                    }
                    curBox.appendChild(curValSpan);
                }

                var arrowBox = document.createElement('div');
                arrowBox.className = 'autofill-diff-arrow';
                arrowBox.textContent = '→';

                var newBox = document.createElement('div');
                newBox.className = 'autofill-diff-cell autofill-diff-new';
                var newTag = document.createElement('span');
                newTag.className = 'autofill-diff-tag';
                newTag.textContent = 'New from link:';
                newBox.appendChild(newTag);

                if (diff.isImage) {
                    var newImg = document.createElement('img');
                    newImg.src = diff.newImg;
                    newImg.alt = 'New product photo';
                    newImg.className = 'autofill-diff-thumb';
                    newImg.setAttribute('referrerpolicy', 'no-referrer');
                    newBox.appendChild(newImg);
                } else {
                    var newValSpan = document.createElement('span');
                    newValSpan.className = 'autofill-diff-val';
                    newValSpan.textContent = diff.newText;
                    newBox.appendChild(newValSpan);
                }

                compareGrid.appendChild(curBox);
                compareGrid.appendChild(arrowBox);
                compareGrid.appendChild(newBox);
                contentCol.appendChild(compareGrid);

                rowLabel.appendChild(cb);
                rowLabel.appendChild(contentCol);
                listWrap.appendChild(rowLabel);
            });

            body.appendChild(listWrap);

            var footer = document.createElement('div');
            footer.className = 'autofill-confirm-footer';

            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'actionButton autofill-cancel-btn';
            cancelBtn.textContent = 'Cancel';
            cancelBtn.addEventListener('click', function() {
                closeConfirmDialog();
                setStatus('Auto-fill cancelled (no changes applied).', false);
            });

            function applySelectedChanges() {
                var applied = [];
                checkboxes.forEach(function(entry) {
                    if (entry.cb.checked) {
                        entry.diff.apply();
                        applied.push(entry.diff.label);
                    }
                });
                return applied;
            }

            var applyBtn = document.createElement('button');
            applyBtn.type = 'button';
            applyBtn.className = 'actionButton autofill-btn';
            applyBtn.textContent = '✓ Apply Selected to Form';
            applyBtn.addEventListener('click', function() {
                var applied = applySelectedChanges();
                closeConfirmDialog();
                if (applied.length > 0) {
                    setStatus('✨ Applied: ' + applied.join(', ') + ' — click "Update Item" below to save.', false);
                } else {
                    setStatus('No fields were selected to update.', false);
                }
            });

            var applyAndSaveBtn = document.createElement('button');
            applyAndSaveBtn.type = 'button';
            applyAndSaveBtn.className = 'actionButton autofill-save-btn';
            applyAndSaveBtn.textContent = '💾 Apply & Save Item';
            applyAndSaveBtn.addEventListener('click', function() {
                var applied = applySelectedChanges();
                closeConfirmDialog();
                if (applied.length === 0) {
                    setStatus('No fields were selected to update.', true);
                    return;
                }
                var form = btn.form || document.querySelector('form[action="validate_editItem.php"]');
                if (form) {
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit();
                    } else {
                        form.submit();
                    }
                }
            });

            footer.appendChild(cancelBtn);
            footer.appendChild(applyBtn);
            footer.appendChild(applyAndSaveBtn);

            dlg.appendChild(header);
            dlg.appendChild(body);
            dlg.appendChild(footer);

            if (typeof dlg.showModal === 'function') {
                dlg.showModal();
            } else {
                dlg.setAttribute('open', 'open');
                dlg.style.display = 'flex';
            }
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

                if (confirmMode) {
                    var diffs = buildEditDiffList(data, rawUrl);
                    if (diffs.length === 0) {
                        setStatus(data.warning ? data.warning : '✓ Link checked — all fetched details already match current item fields.', !!data.warning);
                        return;
                    }
                    setStatus('Review proposed changes in the confirmation window...', false);
                    openConfirmDialog(diffs, data.warning || '');
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
                    applyFetchedImage(data.image);
                    filledParts.push('Photo');
                }

                if (filledParts.length > 0) {
                    var msg = '✨ Auto-filled: ' + filledParts.join(', ');
                    if (data.warning) {
                        msg += ' (' + data.warning + ')';
                    }
                    setStatus(msg, false);
                } else {
                    setStatus(data.warning ? data.warning : 'Link checked (existing fields kept as-is).', !!data.warning);
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
