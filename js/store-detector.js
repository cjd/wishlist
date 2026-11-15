/**
 * Store Detector Utility
 * Auto-detects store name from URLs when adding or editing wishlist items
 */
(function() {
    var STORE_MAP = [
        { pattern: /amazon\.(com|co\.uk|ca|de|fr|es|it|co\.jp|com\.au)/i, name: 'Amazon' },
        { pattern: /target\.com/i, name: 'Target' },
        { pattern: /walmart\.com/i, name: 'Walmart' },
        { pattern: /bestbuy\.com/i, name: 'Best Buy' },
        { pattern: /apple\.com/i, name: 'Apple' },
        { pattern: /ebay\.(com|co\.uk|ca|de|com\.au)/i, name: 'eBay' },
        { pattern: /etsy\.com/i, name: 'Etsy' },
        { pattern: /costco\.com/i, name: 'Costco' },
        { pattern: /homedepot\.com/i, name: 'Home Depot' },
        { pattern: /lowes\.com/i, name: 'Lowe\'s' },
        { pattern: /ikea\.com/i, name: 'IKEA' },
        { pattern: /nike\.com/i, name: 'Nike' },
        { pattern: /sephora\.com/i, name: 'Sephora' },
        { pattern: /ulta\.com/i, name: 'Ulta' },
        { pattern: /wayfair\.com/i, name: 'Wayfair' },
        { pattern: /newegg\.com/i, name: 'Newegg' },
        { pattern: /gamestop\.com/i, name: 'GameStop' },
        { pattern: /barnesandnoble\.com/i, name: 'Barnes & Noble' },
        { pattern: /lego\.com/i, name: 'LEGO' },
        { pattern: /rei\.com/i, name: 'REI' },
        { pattern: /chewy\.com/i, name: 'Chewy' },
        { pattern: /steampowered\.com|steamcommunity\.com/i, name: 'Steam' },
        { pattern: /kohls\.com/i, name: 'Kohl\'s' },
        { pattern: /macys\.com/i, name: 'Macy\'s' },
        { pattern: /nordstrom\.com/i, name: 'Nordstrom' },
        { pattern: /gap\.com/i, name: 'Gap' },
        { pattern: /oldnavy\.gap\.com/i, name: 'Old Navy' },
        { pattern: /williams-sonoma\.com/i, name: 'Williams-Sonoma' },
        { pattern: /potterybarn\.com/i, name: 'Pottery Barn' },
        { pattern: /crateandbarrel\.com/i, name: 'Crate & Barrel' },
        { pattern: /microcenter\.com/i, name: 'Micro Center' },
        { pattern: /bhphotovideo\.com/i, name: 'B&H Photo' },
        { pattern: /dickssportinggoods\.com/i, name: 'Dick\'s Sporting Goods' }
    ];

    function extractStoreName(url) {
        if (!url || typeof url !== 'string') return '';
        var cleanUrl = url.trim();
        if (!cleanUrl) return '';

        // Match against known patterns
        for (var i = 0; i < STORE_MAP.length; i++) {
            if (STORE_MAP[i].pattern.test(cleanUrl)) {
                return STORE_MAP[i].name;
            }
        }

        // Generic fallback: extract hostname and capitalize
        try {
            var urlWithProto = cleanUrl.indexOf('://') === -1 ? 'https://' + cleanUrl : cleanUrl;
            var parsed = new URL(urlWithProto);
            var host = parsed.hostname.replace(/^www\./i, '');
            var parts = host.split('.');
            var domainName = parts.length >= 2 ? parts[parts.length - 2] : parts[0];
            if (domainName && domainName.length > 1) {
                return domainName.charAt(0).toUpperCase() + domainName.slice(1).toLowerCase();
            }
        } catch (e) {
            // Invalid URL format
        }
        return '';
    }

    function initStoreDetector() {
        var urlInput = document.querySelector('input[name="link1url"]');
        var nameInput = document.querySelector('input[name="link1"]');

        if (!urlInput || !nameInput) return;

        var lastAutoDetected = '';

        function onUrlChange() {
            var val = urlInput.value.trim();
            if (!val) return;

            var detected = extractStoreName(val);
            if (!detected) return;

            var currentName = nameInput.value.trim();
            // Auto-fill if name input is empty, matches default "link", or was previously auto-detected
            if (!currentName || currentName.toLowerCase() === 'link' || currentName === lastAutoDetected) {
                nameInput.value = detected;
                lastAutoDetected = detected;
            }
        }

        urlInput.addEventListener('input', onUrlChange);
        urlInput.addEventListener('paste', function() {
            setTimeout(onUrlChange, 50);
        });
        urlInput.addEventListener('change', onUrlChange);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStoreDetector);
    } else {
        initStoreDetector();
    }

    // Expose for external calls if needed
    window.extractStoreName = extractStoreName;
})();
