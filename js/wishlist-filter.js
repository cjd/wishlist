/**
 * Real-time filter for wishlist items and categories
 */
function filterWishlist(query) {
    var filter = (query || '').toLowerCase().trim();
    var clearBtn = document.getElementById('wishlistSearchClear');
    if (clearBtn) {
        clearBtn.style.display = filter.length > 0 ? 'inline-block' : 'none';
    }

    var categories = document.querySelectorAll('.wishlist-category-block');
    var totalMatches = 0;

    categories.forEach(function(cat) {
        var items = cat.querySelectorAll('.sortable-item');
        var headerEl = cat.querySelector('.categoryHeader');
        var headerText = headerEl ? headerEl.textContent.toLowerCase() : '';
        var catHeaderMatches = filter !== '' && headerText.indexOf(filter) !== -1;
        var catHasVisible = false;

        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            if (filter === '' || catHeaderMatches || text.indexOf(filter) !== -1) {
                item.style.display = '';
                catHasVisible = true;
                totalMatches++;
            } else {
                item.style.display = 'none';
            }
        });

        // Hide category while filtering unless its header or at least one item matched
        if (filter !== '' && !catHasVisible && !catHeaderMatches) {
            cat.style.display = 'none';
        } else {
            cat.style.display = '';
        }
    });

    var countBadge = document.getElementById('wishlistSearchCount');
    if (countBadge) {
        if (filter.length > 0) {
            countBadge.textContent = totalMatches + ' item' + (totalMatches === 1 ? '' : 's') + ' found';
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.style.display = 'none';
        }
    }
}

function clearWishlistFilter() {
    var input = document.getElementById('wishlistSearch');
    if (input) {
        input.value = '';
        filterWishlist('');
        input.focus();
    }
}
