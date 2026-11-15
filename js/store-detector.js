/**
 * Store Detector Utility
 * Auto-detects store name from URL hostnames when adding or editing wishlist items
 */
(function() {
    var STORE_MAP = [
        { pattern: /(?:^|\.)oldnavy\.gap\.com$/i, name: 'Old Navy' },
        { pattern: /(?:^|\.)gap\.com$/i, name: 'Gap' },
        { pattern: /(?:^|\.)amazon\.(?:com|co\.uk|ca|de|fr|es|it|co\.jp|com\.au)$/i, name: 'Amazon' },
        { pattern: /(?:^|\.)target\.com(?:\.au)?$/i, name: 'Target' },
        { pattern: /(?:^|\.)walmart\.com$/i, name: 'Walmart' },
        { pattern: /(?:^|\.)bestbuy\.com$/i, name: 'Best Buy' },
        { pattern: /(?:^|\.)apple\.com$/i, name: 'Apple' },
        { pattern: /(?:^|\.)ebay\.(?:com|co\.uk|ca|de|com\.au)$/i, name: 'eBay' },
        { pattern: /(?:^|\.)etsy\.com$/i, name: 'Etsy' },
        { pattern: /(?:^|\.)costco\.com(?:\.au)?$/i, name: 'Costco' },
        { pattern: /(?:^|\.)homedepot\.com$/i, name: 'Home Depot' },
        { pattern: /(?:^|\.)lowes\.com$/i, name: 'Lowe\'s' },
        { pattern: /(?:^|\.)ikea\.com$/i, name: 'IKEA' },
        { pattern: /(?:^|\.)nike\.com$/i, name: 'Nike' },
        { pattern: /(?:^|\.)sephora\.com(?:\.au)?$/i, name: 'Sephora' },
        { pattern: /(?:^|\.)ulta\.com$/i, name: 'Ulta' },
        { pattern: /(?:^|\.)wayfair\.com$/i, name: 'Wayfair' },
        { pattern: /(?:^|\.)newegg\.com$/i, name: 'Newegg' },
        { pattern: /(?:^|\.)gamestop\.com$/i, name: 'GameStop' },
        { pattern: /(?:^|\.)barnesandnoble\.com$/i, name: 'Barnes & Noble' },
        { pattern: /(?:^|\.)lego\.com$/i, name: 'LEGO' },
        { pattern: /(?:^|\.)rei\.com$/i, name: 'REI' },
        { pattern: /(?:^|\.)chewy\.com$/i, name: 'Chewy' },
        { pattern: /(?:^|\.)(?:steampowered|steamcommunity)\.com$/i, name: 'Steam' },
        { pattern: /(?:^|\.)kohls\.com$/i, name: 'Kohl\'s' },
        { pattern: /(?:^|\.)macys\.com$/i, name: 'Macy\'s' },
        { pattern: /(?:^|\.)nordstrom\.com$/i, name: 'Nordstrom' },
        { pattern: /(?:^|\.)williams-sonoma\.com$/i, name: 'Williams-Sonoma' },
        { pattern: /(?:^|\.)potterybarn\.com$/i, name: 'Pottery Barn' },
        { pattern: /(?:^|\.)crateandbarrel\.com$/i, name: 'Crate & Barrel' },
        { pattern: /(?:^|\.)microcenter\.com$/i, name: 'Micro Center' },
        { pattern: /(?:^|\.)bhphotovideo\.com$/i, name: 'B&H Photo' },
        { pattern: /(?:^|\.)dickssportinggoods\.com$/i, name: 'Dick\'s Sporting Goods' },
        { pattern: /(?:^|\.)bigw\.com\.au$/i, name: 'Big W' },
        { pattern: /(?:^|\.)kmart\.com(?:\.au)?$/i, name: 'Kmart' },
        { pattern: /(?:^|\.)ebgames\.(?:com\.au|co\.nz|ca)$/i, name: 'EB Games' },
        { pattern: /(?:^|\.)jbhifi\.com(?:\.au)?$/i, name: 'JB Hi-Fi' },
        { pattern: /(?:^|\.)bunnings\.com(?:\.au)?$/i, name: 'Bunnings' },
        { pattern: /(?:^|\.)officeworks\.com\.au$/i, name: 'Officeworks' },
        { pattern: /(?:^|\.)myer\.com\.au$/i, name: 'Myer' }
    ];

    var MULTI_PART_TLDS = /^(?:com\.au|net\.au|org\.au|co\.uk|org\.uk|co\.nz|co\.jp|com\.br|com\.sg)$/i;

    function extractStoreName(url) {
        if (!url || typeof url !== 'string') return '';
        var cleanUrl = url.trim();
        if (!cleanUrl) return '';

        try {
            var urlWithProto = cleanUrl.indexOf('://') === -1 ? 'https://' + cleanUrl : cleanUrl;
            var parsed = new URL(urlWithProto);
            var host = parsed.hostname.replace(/^www\./i, '').toLowerCase();
            if (!host) return '';

            for (var i = 0; i < STORE_MAP.length; i++) {
                if (STORE_MAP[i].pattern.test(host)) {
                    return STORE_MAP[i].name;
                }
            }

            var parts = host.split('.');
            var domainName = parts[0];
            if (parts.length >= 3) {
                var lastTwo = parts.slice(-2).join('.');
                if (MULTI_PART_TLDS.test(lastTwo)) {
                    domainName = parts[parts.length - 3];
                } else {
                    domainName = parts[parts.length - 2];
                }
            } else if (parts.length === 2) {
                domainName = parts[0];
            }

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

    window.extractStoreName = extractStoreName;
})();
