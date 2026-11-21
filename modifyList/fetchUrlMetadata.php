<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
    @opcache_invalidate(__DIR__ . '/../funcLib.php', true);
}

include "../funcLib.php";

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token. Please refresh the page.']);
    exit;
}

$rawUrl = trim((string)($_POST['url'] ?? ''));
if ($rawUrl === '') {
    echo json_encode(['ok' => false, 'error' => 'Please paste a product link first.']);
    exit;
}
if (!preg_match('#^https?://#i', $rawUrl)) {
    $rawUrl = 'https://' . $rawUrl;
}
$cleanUrl = sanitizeHttpUrl($rawUrl, false);
if ($cleanUrl === '') {
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid http:// or https:// link.']);
    exit;
}

if (!empty($_SESSION['url_meta_cache_v4'][$cleanUrl])) {
    $cachedEntry = $_SESSION['url_meta_cache_v4'][$cleanUrl];
    if (is_array($cachedEntry) && isset($cachedEntry['ts'], $cachedEntry['payload']) && (time() - (int)$cachedEntry['ts']) < 300) {
        echo json_encode($cachedEntry['payload']);
        exit;
    }
    unset($_SESSION['url_meta_cache_v4'][$cleanUrl]);
}

$clientIp = getClientIp();
$actorUid = strtolower((string)($_SESSION['userid'] ?? $userid));
$rl = checkRateLimit($link, $clientIp, $actorUid, 'url_autofill', 25, 300);
if ($rl['blocked']) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many auto-fill requests. Please wait a moment and try again.']);
    exit;
}
recordRateLimitAttempt($link, $clientIp, $actorUid, 'url_autofill');

function cleanExtractedText($str, $maxLen = 255) {
    $decoded = html_entity_decode(strip_tags((string)$str), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $collapsed = trim(preg_replace('/\s+/u', ' ', $decoded));
    if ($collapsed === '') {
        return '';
    }
    if (function_exists('mb_substr') && mb_strlen($collapsed, 'UTF-8') > $maxLen) {
        return rtrim(mb_substr($collapsed, 0, $maxLen - 1, 'UTF-8')) . '…';
    }
    if (strlen($collapsed) > $maxLen) {
        return rtrim(substr($collapsed, 0, $maxLen - 3)) . '...';
    }
    return $collapsed;
}

function normalizePriceString($val) {
    if (is_int($val) || is_float($val)) {
        return $val > 0 ? number_format((float)$val, 2, '.', '') : '';
    }
    $raw = trim((string)$val);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/([0-9]{1,6}(?:,[0-9]{3})*(?:\.[0-9]{1,2})?)/', $raw, $m)) {
        $num = (float)str_replace(',', '', $m[1]);
        if ($num > 0 && $num < 1000000) {
            return number_format($num, 2, '.', '');
        }
    }
    return '';
}

function fetchShopifyJsonEndpoint($endpointUrl, $overrideUa = '', $forceHttp1 = false, &$diagOut = '') {
    if (!function_exists('curl_init')) {
        $diagOut = 'no_curl';
        return '';
    }
    $fallbackUa = 'Mozilla/5.0 (compatible; WishListMetadataPreview/1.0)';
    if ($overrideUa !== '') {
        $ua = $overrideUa;
    } else {
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $ua = preg_replace('/[\r\n\x00-\x1F\x7F]+/', ' ', $ua);
        if ($ua === '' || strcasecmp($ua, 'Mozilla/5.0') === 0) {
            $ua = $fallbackUa;
        } elseif (strlen($ua) > 512) {
            $ua = substr($ua, 0, 512);
        }
    }

    $currentUrl = $endpointUrl;
    $resolveEntries = [];
    for ($hop = 0; $hop <= 3; $hop++) {
        $parts = @parse_url($currentUrl);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            $diagOut = 'bad_url';
            return '';
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            $diagOut = 'bad_scheme';
            return '';
        }
        $host = strtolower(trim($parts['host'], "[] \t\n\r\0\x0B."));
        $port = isset($parts['port']) ? (int)$parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port !== 80 && $port !== 443) {
            $diagOut = 'bad_port';
            return '';
        }

        $ips = resolveSafePublicHostIps($host);
        if (empty($ips)) {
            $diagOut = 'dns_fail:' . $host;
            return '';
        }
        $pinnedIp = $ips[0];
        $resolveEntries[] = $host . ':' . $port . ':' . (strpos($pinnedIp, ':') !== false ? '[' . $pinnedIp . ']' : $pinnedIp);

        $redirectLocation = '';
        $ch = curl_init($currentUrl);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_RESOLVE => array_values(array_unique($resolveEntries)),
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_HEADERFUNCTION => function($ch, $headerLine) use (&$redirectLocation) {
                $len = strlen($headerLine);
                $colonPos = strpos($headerLine, ':');
                if ($colonPos !== false && strtolower(trim(substr($headerLine, 0, $colonPos))) === 'location') {
                    $redirectLocation = trim(substr($headerLine, $colonPos + 1));
                }
                return $len;
            }
        ];
        if ($forceHttp1) {
            $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }
        curl_setopt_array($ch, $opts);
        $body = @curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = $curlErrNo ? curl_error($ch) : '';
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        if (in_array($status, [301, 302, 303, 307, 308], true) && $redirectLocation !== '') {
            $nextUrl = resolveRelativeHttpUrl($currentUrl, $redirectLocation);
            if ($nextUrl === '') {
                $diagOut = 'bad_redir';
                return '';
            }
            $currentUrl = $nextUrl;
            continue;
        }

        if ($status === 403 && $overrideUa === '' && $ua !== $fallbackUa) {
            return fetchShopifyJsonEndpoint($currentUrl, $fallbackUa, $forceHttp1, $diagOut);
        }
        if ($status === 403 && !$forceHttp1) {
            return fetchShopifyJsonEndpoint($currentUrl, $fallbackUa, true, $diagOut);
        }
        if ($status >= 200 && $status < 300 && is_string($body) && $body !== '') {
            $diagOut = 'http_' . $status;
            return $body;
        }
        $diagOut = 'http_' . $status . ($curlErrNo ? '_err' . $curlErrNo . ':' . $curlErrMsg : '');
        return '';
    }
    $diagOut = 'max_redir';
    return '';
}

function tryFetchShopifyProductMetadata($url, &$diagLog = '') {
    $parts = @parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host']) || empty($parts['path'])) {
        return null;
    }
    if (!preg_match('~^(?:(/[a-z]{2}(?:-[a-z]{2})?))?(?:/collections/[^/]+)?/products/([^/?#.]+)~i', $parts['path'], $m)) {
        return null;
    }
    $localePrefix = $m[1] ?? '';
    $handle = $m[2];
    $origin = strtolower($parts['scheme']) . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . (int)$parts['port'] : '');
    $baseProductPath = $origin . $localePrefix . '/products/' . $handle;

    $variantId = '';
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qParams);
        if (!empty($qParams['variant']) && is_scalar($qParams['variant'])) {
            $variantId = trim((string)$qParams['variant']);
        }
    }

    $diagParts = [];

    // 1. Try Shopify's Predictive Search API (/search/suggest.json) first — avoids /products/* local_rate_limited (HTTP 429)
    $queriesToTry = array_values(array_unique([
        str_replace(['-', '_'], ' ', $handle),
        $handle
    ]));

    foreach ($queriesToTry as $qTerm) {
        $suggestUrl = $origin . $localePrefix . '/search/suggest.json?q=' . rawurlencode($qTerm) . '&resources%5Btype%5D=product';
        $d = '';
        $suggestBody = fetchShopifyJsonEndpoint($suggestUrl, '', false, $d);
        if ($suggestBody === '') {
            $diagParts[] = 'suggest=' . $d;
            continue;
        }
        $decoded = @json_decode($suggestBody, true);
        $products = $decoded['resources']['results']['products'] ?? null;
        if ((!is_array($products) || empty($products))) {
            // Retry once with preview UA in case store search engine ignored browser UA
            $d2 = '';
            $suggestBody2 = fetchShopifyJsonEndpoint($suggestUrl, 'Mozilla/5.0 (compatible; WishListMetadataPreview/1.0)', true, $d2);
            if ($suggestBody2 !== '') {
                $decoded2 = @json_decode($suggestBody2, true);
                $products2 = $decoded2['resources']['results']['products'] ?? null;
                if (is_array($products2) && !empty($products2)) {
                    $products = $products2;
                }
            }
        }
        if (!is_array($products) || empty($products)) {
            $diagParts[] = 'suggest=' . $d . '_empty_products';
            continue;
        }
        if (is_array($products) && !empty($products)) {
            $matchedProd = null;
            foreach ($products as $cand) {
                if (is_array($cand) && isset($cand['handle']) && strcasecmp((string)$cand['handle'], $handle) === 0) {
                    $matchedProd = $cand;
                    break;
                }
            }
            if ($matchedProd === null && is_array($products[0])) {
                $matchedProd = $products[0];
            }
            if (is_array($matchedProd)) {
                $sTitle = cleanExtractedText($matchedProd['title'] ?? '', 255);
                $sDesc = cleanExtractedText($matchedProd['body'] ?? '', 200);
                $sPrice = normalizePriceString($matchedProd['price'] ?? ($matchedProd['price_min'] ?? ''));
                $sImg = '';
                if (!empty($matchedProd['image']) && is_string($matchedProd['image'])) {
                    $sImg = $matchedProd['image'];
                } elseif (!empty($matchedProd['featured_image']['url']) && is_string($matchedProd['featured_image']['url'])) {
                    $sImg = $matchedProd['featured_image']['url'];
                }

                if (!empty($matchedProd['variants']) && is_array($matchedProd['variants'])) {
                    $chosenVar = null;
                    if ($variantId !== '') {
                        foreach ($matchedProd['variants'] as $v) {
                            if (is_array($v) && isset($v['id']) && (string)$v['id'] === $variantId) {
                                $chosenVar = $v;
                                break;
                            }
                        }
                    }
                    if ($chosenVar === null && is_array($matchedProd['variants'][0] ?? null)) {
                        $chosenVar = $matchedProd['variants'][0];
                    }
                    if (is_array($chosenVar)) {
                        $varPrice = normalizePriceString($chosenVar['price'] ?? '');
                        if ($varPrice !== '') {
                            $sPrice = $varPrice;
                        }
                        if (!empty($chosenVar['image']) && is_string($chosenVar['image'])) {
                            $sImg = $chosenVar['image'];
                        } elseif (!empty($chosenVar['featured_image']['src']) && is_string($chosenVar['featured_image']['src'])) {
                            $sImg = $chosenVar['featured_image']['src'];
                        } elseif (!empty($chosenVar['featured_image']['url']) && is_string($chosenVar['featured_image']['url'])) {
                            $sImg = $chosenVar['featured_image']['url'];
                        }
                        if ($sTitle !== '' && !empty($chosenVar['title']) && is_string($chosenVar['title']) && strcasecmp(trim($chosenVar['title']), 'Default Title') !== 0) {
                            $sTitle = cleanExtractedText($sTitle . ' - ' . trim($chosenVar['title']), 255);
                        }
                    }
                }

                if ($sImg !== '') {
                    $sImg = sanitizeHttpUrl(resolveRelativeHttpUrl($url, $sImg), false);
                }

                if ($sTitle !== '' || $sPrice !== '' || $sImg !== '') {
                    $host = strtolower($parts['host']);
                    return [
                        'title' => $sTitle,
                        'description' => $sDesc,
                        'price' => $sPrice,
                        'image' => $sImg,
                        'store' => preg_replace('/^www\./i', '', $host),
                        'final_url' => $url
                    ];
                }
            }
        }
    }

    // 2. Try Shopify's /products/{handle}.json endpoint
    $dJson = '';
    $jsonBody = fetchShopifyJsonEndpoint($baseProductPath . '.json', '', false, $dJson);
    if ($jsonBody !== '') {
        $decoded = @json_decode($jsonBody, true);
        if (is_array($decoded) && !empty($decoded['product']) && is_array($decoded['product'])) {
            $prod = $decoded['product'];
            $sTitle = cleanExtractedText($prod['title'] ?? '', 255);
            $sDesc = cleanExtractedText($prod['body_html'] ?? '', 200);
            $sPrice = '';
            $sImg = '';
            $variantImgId = null;

            if (!empty($prod['variants']) && is_array($prod['variants'])) {
                $chosenVar = $prod['variants'][0];
                if ($variantId !== '') {
                    foreach ($prod['variants'] as $v) {
                        if (is_array($v) && isset($v['id']) && (string)$v['id'] === $variantId) {
                            $chosenVar = $v;
                            break;
                        }
                    }
                }
                if (is_array($chosenVar)) {
                    $sPrice = normalizePriceString($chosenVar['price'] ?? '');
                    if (!empty($chosenVar['image_id'])) {
                        $variantImgId = (string)$chosenVar['image_id'];
                    }
                    if ($sTitle !== '' && !empty($chosenVar['title']) && is_string($chosenVar['title']) && strcasecmp(trim($chosenVar['title']), 'Default Title') !== 0) {
                        $sTitle = cleanExtractedText($sTitle . ' - ' . trim($chosenVar['title']), 255);
                    }
                }
            }

            if ($variantImgId !== null && !empty($prod['images']) && is_array($prod['images'])) {
                foreach ($prod['images'] as $imgObj) {
                    if (is_array($imgObj) && isset($imgObj['id']) && (string)$imgObj['id'] === $variantImgId && !empty($imgObj['src'])) {
                        $sImg = (string)$imgObj['src'];
                        break;
                    }
                }
            }
            if ($sImg === '' && !empty($prod['image']['src']) && is_string($prod['image']['src'])) {
                $sImg = $prod['image']['src'];
            }
            if ($sImg === '' && !empty($prod['images'][0]['src']) && is_string($prod['images'][0]['src'])) {
                $sImg = $prod['images'][0]['src'];
            }
            if ($sImg !== '') {
                $sImg = sanitizeHttpUrl(resolveRelativeHttpUrl($url, $sImg), false);
            }

            if ($sTitle !== '' || $sPrice !== '' || $sImg !== '') {
                $host = strtolower($parts['host']);
                return [
                    'title' => $sTitle,
                    'description' => $sDesc,
                    'price' => $sPrice,
                    'image' => $sImg,
                    'store' => preg_replace('/^www\./i', '', $host),
                    'final_url' => $url
                ];
            }
        }
        $diagParts[] = 'json=' . $dJson . '_no_product';
    } else {
        $diagParts[] = 'json=' . $dJson;
    }

    $diagLog = implode(', ', $diagParts);
    return null;
}

function extractTitleFromUrlSlug($url) {
    $path = (string)(parse_url($url, PHP_URL_PATH) ?? '');
    if ($path === '' || $path === '/') {
        return '';
    }
    $segments = array_values(array_filter(explode('/', trim($path, '/')), function($s) {
        return $s !== '';
    }));
    if (empty($segments)) {
        return '';
    }

    $candidate = '';
    $count = count($segments);

    // Amazon: /<Product-Slug>/dp/<ASIN>
    for ($i = 0; $i < $count; $i++) {
        if (strtolower($segments[$i]) === 'dp' && $i > 0) {
            $candidate = $segments[$i - 1];
            break;
        }
    }
    // Target: /p/<product-slug>/-/A-<id>
    if ($candidate === '' && strtolower($segments[0]) === 'p' && isset($segments[1])) {
        $candidate = $segments[1];
    }
    // Walmart: /ip/<Product-Slug>/<id>
    if ($candidate === '' && strtolower($segments[0]) === 'ip' && isset($segments[1]) && !ctype_digit($segments[1])) {
        $candidate = $segments[1];
    }
    // Etsy: /listing/<id>/<product-slug>
    if ($candidate === '') {
        for ($i = 0; $i < $count - 1; $i++) {
            if (strtolower($segments[$i]) === 'listing' && isset($segments[$i + 2])) {
                $candidate = $segments[$i + 2];
                break;
            }
        }
    }
    // Best Buy / Generic: pick the longest hyphenated non-ID segment
    if ($candidate === '') {
        $bestScore = 0;
        foreach ($segments as $seg) {
            $segClean = preg_replace('/\.(?:html?|php|p|jsp|aspx?)$/i', '', $seg);
            if (preg_match('/^(?:dp|gp|product|products|ip|itm|site|p|s|b|c|ref=[a-z0-9_]+|A-[0-9]+|[0-9]+|[B0-9][A-Z0-9]{9})$/i', $segClean)) {
                continue;
            }
            $parts = preg_split('/[-_]+/', $segClean, -1, PREG_SPLIT_NO_EMPTY);
            if (count($parts) >= 2 && strlen($segClean) > $bestScore) {
                $bestScore = strlen($segClean);
                $candidate = $segClean;
            }
        }
    }

    if ($candidate === '') {
        return '';
    }

    $decoded = rawurldecode($candidate);
    $decoded = preg_replace('/\.(?:html?|php|p|jsp|aspx?)$/i', '', $decoded);
    // Strip trailing SKU/ID tokens if present
    $decoded = preg_replace('/[-_]+(?:A-[0-9]{5,}|[0-9]{6,})$/i', '', $decoded);
    $words = preg_split('/[-_\s]+/', trim($decoded), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) < 2) {
        return '';
    }

    $formattedWords = array_map(function($w) {
        if (preg_match('/^[A-Z0-9]{2,5}$/', $w)) {
            return $w;
        }
        return ucfirst(strtolower($w));
    }, $words);

    return trim(implode(' ', $formattedWords));
}

$shopifyDiag = '';
$shopifyMeta = tryFetchShopifyProductMetadata($cleanUrl, $shopifyDiag);
if (is_array($shopifyMeta)) {
    $responsePayload = [
        'ok' => true,
        'title' => $shopifyMeta['title'],
        'description' => $shopifyMeta['description'],
        'price' => $shopifyMeta['price'],
        'image' => $shopifyMeta['image'],
        'store' => $shopifyMeta['store'],
        'final_url' => $shopifyMeta['final_url']
    ];
    if (!isset($_SESSION['url_meta_cache_v4']) || !is_array($_SESSION['url_meta_cache_v4'])) {
        $_SESSION['url_meta_cache_v4'] = [];
    }
    if (count($_SESSION['url_meta_cache_v4']) >= 20) {
        array_shift($_SESSION['url_meta_cache_v4']);
    }
    $_SESSION['url_meta_cache_v4'][$cleanUrl] = [
        'ts' => time(),
        'payload' => $responsePayload
    ];
    echo json_encode($responsePayload);
    exit;
}

$fetched = fetchSafePublicUrl($cleanUrl, 1572864, 4, false);
$finalUrl = (string)($fetched['final_url'] ?? $cleanUrl);
$httpStatus = (int)($fetched['status'] ?? 0);
$fetchWarning = '';

if (empty($fetched['ok']) || empty($fetched['body'])) {
    // If redirected to a Shopify /products/ URL, try Shopify JSON on the redirected finalUrl
    if ($finalUrl !== $cleanUrl) {
        $shopifyRedirectMeta = tryFetchShopifyProductMetadata($finalUrl);
        if (is_array($shopifyRedirectMeta)) {
            $responsePayload = [
                'ok' => true,
                'title' => $shopifyRedirectMeta['title'],
                'description' => $shopifyRedirectMeta['description'],
                'price' => $shopifyRedirectMeta['price'],
                'image' => $shopifyRedirectMeta['image'],
                'store' => $shopifyRedirectMeta['store'],
                'final_url' => $shopifyRedirectMeta['final_url']
            ];
            echo json_encode($responsePayload);
            exit;
        }
    }
    $slugTitle = extractTitleFromUrlSlug($finalUrl);
    $host = strtolower(parse_url($finalUrl, PHP_URL_HOST) ?? '');
    $fallbackStore = $host !== '' ? preg_replace('/^www\./i', '', $host) : '';
    if ($slugTitle !== '') {
        $statusNote = $httpStatus > 0 ? " (HTTP {$httpStatus})" : '';
        $diagSuffix = $shopifyDiag !== '' ? " [{$shopifyDiag}]" : '';
        echo json_encode([
            'ok' => true,
            'title' => $slugTitle,
            'description' => '',
            'price' => '',
            'image' => '',
            'store' => $fallbackStore,
            'final_url' => $finalUrl,
            'warning' => "Store rate-limited or blocked full page preview{$statusNote} — Title & Store were inferred from the link URL.{$diagSuffix}"
        ]);
        exit;
    }
    echo json_encode([
        'ok' => false,
        'error' => $fetched['error'] ?? 'Could not read details from that page. You can still type the item details manually.'
    ]);
    exit;
}

$html = (string)$fetched['body'];

function extractImageFromLdField($imgField) {
    if (is_string($imgField)) {
        return trim($imgField);
    }
    if (is_array($imgField)) {
        if (!empty($imgField['url']) && is_string($imgField['url'])) {
            return trim($imgField['url']);
        }
        if (!empty($imgField['contentUrl']) && is_string($imgField['contentUrl'])) {
            return trim($imgField['contentUrl']);
        }
        foreach ($imgField as $entry) {
            $found = extractImageFromLdField($entry);
            if ($found !== '') {
                return $found;
            }
        }
    }
    return '';
}

function extractPriceFromLdOffers($offers) {
    if (!is_array($offers)) {
        return '';
    }
    foreach (['price', 'lowPrice', 'highPrice'] as $pk) {
        if (isset($offers[$pk]) && (is_scalar($offers[$pk]))) {
            $p = normalizePriceString($offers[$pk]);
            if ($p !== '') {
                return $p;
            }
        }
    }
    if (!empty($offers['offers']) && is_array($offers['offers'])) {
        $nested = extractPriceFromLdOffers($offers['offers']);
        if ($nested !== '') {
            return $nested;
        }
    }
    foreach ($offers as $item) {
        if (is_array($item)) {
            $sub = extractPriceFromLdOffers($item);
            if ($sub !== '') {
                return $sub;
            }
        }
    }
    return '';
}

function findProductNodeInJsonLd($node) {
    if (!is_array($node)) {
        return null;
    }
    if (isset($node['@type'])) {
        $types = is_array($node['@type']) ? $node['@type'] : [$node['@type']];
        foreach ($types as $t) {
            if (is_string($t) && preg_match('/(?:^|:|\/)Product$/i', trim($t))) {
                return $node;
            }
        }
    }
    foreach ($node as $child) {
        if (is_array($child)) {
            $found = findProductNodeInJsonLd($child);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

$title = '';
$description = '';
$price = '';
$imageUrl = '';
$siteName = '';

// 1. Parse JSON-LD <script type="application/ld+json"> blocks first
if (preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $ldMatches)) {
    foreach ($ldMatches[1] as $jsonRaw) {
        $decoded = @json_decode(trim($jsonRaw), true);
        if (!is_array($decoded)) {
            continue;
        }
        $prod = findProductNodeInJsonLd($decoded);
        if ($prod !== null) {
            if ($title === '' && !empty($prod['name']) && is_string($prod['name'])) {
                $title = cleanExtractedText($prod['name'], 255);
            }
            if ($description === '' && !empty($prod['description']) && is_string($prod['description'])) {
                $description = cleanExtractedText($prod['description'], 200);
            }
            if ($imageUrl === '' && !empty($prod['image'])) {
                $imageUrl = extractImageFromLdField($prod['image']);
            }
            if ($price === '' && !empty($prod['offers'])) {
                $price = extractPriceFromLdOffers($prod['offers']);
            }
            if ($title !== '' && $price !== '' && $imageUrl !== '') {
                break;
            }
        }
    }
}

// 2. Parse OpenGraph, Product meta tags, itemprop, and <title> via DOMDocument
$prevLibxml = libxml_use_internal_errors(true);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
$xpath = new DOMXPath($dom);

$metaMap = [];
foreach ($xpath->query('//meta') as $metaEl) {
    if (!($metaEl instanceof DOMElement)) {
        continue;
    }
    $content = trim($metaEl->getAttribute('content'));
    if ($content === '') {
        continue;
    }
    foreach (['property', 'name', 'itemprop'] as $attr) {
        $key = strtolower(trim($metaEl->getAttribute($attr)));
        if ($key !== '' && !isset($metaMap[$key])) {
            $metaMap[$key] = $content;
        }
    }
}

if ($siteName === '' && !empty($metaMap['og:site_name'])) {
    $siteName = cleanExtractedText($metaMap['og:site_name'], 80);
}

// Detect anti-bot / CAPTCHA gate pages so we never auto-fill "Robot Check" or "Amazon.com" as the item title
$isBotChallenge = (
    stripos($html, 'errors/validateCaptcha') !== false ||
    stripos($html, 'api-services-support@amazon.com') !== false
);

if ($title === '') {
    foreach (['og:title', 'twitter:title', 'name'] as $tk) {
        if (!empty($metaMap[$tk])) {
            $title = cleanExtractedText($metaMap[$tk], 255);
            if ($title !== '') break;
        }
    }
}

// 3. DOM element fallbacks (Amazon & general e-commerce product pages without OpenGraph/JSON-LD)
if ($title === '') {
    foreach (['//span[@id="productTitle"]', '//span[@id="ebooksProductTitle"]', '//h1[@id="title"]'] as $titleQuery) {
        $nodes = $xpath->query($titleQuery);
        if ($nodes && $nodes->length > 0) {
            $candidate = cleanExtractedText($nodes->item(0)->textContent, 255);
            if ($candidate !== '') {
                $title = $candidate;
                break;
            }
        }
    }
}

if ($title === '') {
    $titleNodes = $xpath->query('//title');
    if ($titleNodes && $titleNodes->length > 0) {
        $rawPageTitle = cleanExtractedText($titleNodes->item(0)->textContent, 255);
        $rawPageTitle = preg_replace('/^Amazon\.[a-z.]+\s*:\s*/i', '', $rawPageTitle);
        $rawPageTitle = preg_replace('/\s*:\s*Amazon\.[a-z.]+.*$/i', '', $rawPageTitle);
        if (!preg_match('/^(?:Amazon\.[a-z.]+|Robot Check|Page Not Found)$/i', trim($rawPageTitle))) {
            $title = trim($rawPageTitle);
        }
    }
}

if ($isBotChallenge && $title === '') {
    libxml_clear_errors();
    libxml_use_internal_errors($prevLibxml);
    $slugTitle = extractTitleFromUrlSlug($finalUrl);
    $host = strtolower(parse_url($finalUrl, PHP_URL_HOST) ?? '');
    $fallbackStore = $host !== '' ? preg_replace('/^www\./i', '', $host) : '';
    if ($slugTitle !== '') {
        echo json_encode([
            'ok' => true,
            'title' => $slugTitle,
            'description' => '',
            'price' => '',
            'image' => '',
            'store' => $fallbackStore,
            'final_url' => $finalUrl,
            'warning' => 'Store blocked full automated preview with a bot check — Title & Store were inferred from the link URL.'
        ]);
        exit;
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Store page blocked automated preview with a bot check. Please enter the item details manually.'
    ]);
    exit;
}

if ($description === '') {
    foreach (['og:description', 'twitter:description', 'description'] as $dk) {
        if (!empty($metaMap[$dk])) {
            $candDesc = cleanExtractedText($metaMap[$dk], 200);
            if ($candDesc !== '' && !preg_match('/^Amazon\.[a-z.]+\s*:/i', $candDesc)) {
                $description = $candDesc;
                break;
            }
        }
    }
}

if ($description === '') {
    $bulletNodes = $xpath->query('//*[@id="feature-bullets"]//ul//li//span[contains(@class, "a-list-item")]');
    if ($bulletNodes && $bulletNodes->length > 0) {
        $bullets = [];
        foreach ($bulletNodes as $bNode) {
            $bText = cleanExtractedText($bNode->textContent, 120);
            if ($bText !== '' && stripos($bText, 'Make sure this fits') === false) {
                $bullets[] = $bText;
                if (count($bullets) >= 2) break;
            }
        }
        if (!empty($bullets)) {
            $description = cleanExtractedText(implode(' • ', $bullets), 200);
        }
    }
}

if ($price === '') {
    foreach (['product:price:amount', 'og:price:amount', 'price', 'twitter:data1'] as $pk) {
        if (!empty($metaMap[$pk])) {
            // Only accept twitter:data1 if it actually starts with a currency symbol or looks like a price
            if ($pk === 'twitter:data1' && !preg_match('/^\s*[$£€]|^\s*[0-9]+\.[0-9]{2}\b/', $metaMap[$pk])) {
                continue;
            }
            $p = normalizePriceString($metaMap[$pk]);
            if ($p !== '') {
                $price = $p;
                break;
            }
        }
    }
}

// Amazon & Microdata DOM price fallbacks
if ($price === '') {
    $priceInputQueries = [
        '//input[@id="twister-plus-price-data-price"]/@value',
        '//input[@id="attach-base-product-price"]/@value',
        '//input[@id="priceValue"]/@value',
        '//*[@itemprop="price"]/@content'
    ];
    foreach ($priceInputQueries as $pq) {
        $pNodes = $xpath->query($pq);
        if ($pNodes && $pNodes->length > 0) {
            $p = normalizePriceString($pNodes->item(0)->nodeValue);
            if ($p !== '') {
                $price = $p;
                break;
            }
        }
    }
}

if ($price === '') {
    $priceTextQueries = [
        '//*[@id="corePriceDisplay_desktop_feature_div"]//*[contains(concat(" ", normalize-space(@class), " "), " a-price ")]/*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]',
        '//*[@id="corePrice_feature_div"]//*[contains(concat(" ", normalize-space(@class), " "), " a-price ")]/*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]',
        '//*[@id="corePrice_desktop"]//*[contains(concat(" ", normalize-space(@class), " "), " a-price ")]/*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]',
        '//*[@id="apex_desktop"]//*[contains(concat(" ", normalize-space(@class), " "), " a-price ")]/*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]',
        '//*[@id="tp_price_block_total_price_ww"]//*[contains(concat(" ", normalize-space(@class), " "), " a-offscreen ")]',
        '//*[@id="price_inside_buybox"]',
        '//*[@id="newBuyBoxPrice"]',
        '//*[@id="priceblock_ourprice"]',
        '//*[@id="priceblock_dealprice"]',
        '//*[@id="priceblock_saleprice"]',
        '//*[@id="kindle-price"]',
        '//*[@itemprop="price"]'
    ];
    foreach ($priceTextQueries as $pq) {
        $pNodes = $xpath->query($pq);
        if ($pNodes && $pNodes->length > 0) {
            foreach ($pNodes as $pNode) {
                $p = normalizePriceString($pNode->textContent);
                if ($p !== '') {
                    $price = $p;
                    break 2;
                }
            }
        }
    }
}

if ($price === '') {
    $wholeNodes = $xpath->query('//*[contains(@id, "corePrice")]//*[contains(@class, "a-price-whole")]');
    if ($wholeNodes && $wholeNodes->length > 0) {
        $wholeRaw = preg_replace('/[^0-9]/', '', $wholeNodes->item(0)->textContent);
        $fracNodes = $xpath->query('//*[contains(@id, "corePrice")]//*[contains(@class, "a-price-fraction")]');
        $fracRaw = ($fracNodes && $fracNodes->length > 0)
            ? preg_replace('/[^0-9]/', '', $fracNodes->item(0)->textContent)
            : '00';
        if ($wholeRaw !== '') {
            $price = normalizePriceString($wholeRaw . '.' . substr($fracRaw . '00', 0, 2));
        }
    }
}

if ($imageUrl === '') {
    foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src', 'image'] as $ik) {
        if (!empty($metaMap[$ik])) {
            $imageUrl = $metaMap[$ik];
            break;
        }
    }
}

// Amazon & DOM product image fallbacks (#landingImage, #imgBlkFront, etc.)
if ($imageUrl === '') {
    $imgNodeQueries = [
        '//img[@id="landingImage"]',
        '//img[@id="imgBlkFront"]',
        '//img[@id="ebooksImgBlkFront"]',
        '//img[@id="main-image"]',
        '//*[@id="imgTagWrapperId"]//img'
    ];
    foreach ($imgNodeQueries as $iq) {
        $imgNodes = $xpath->query($iq);
        if ($imgNodes && $imgNodes->length > 0) {
            $imgEl = $imgNodes->item(0);
            if ($imgEl instanceof DOMElement) {
                $hiRes = trim($imgEl->getAttribute('data-old-hires'));
                if ($hiRes !== '' && preg_match('#^https?://#i', $hiRes)) {
                    $imageUrl = $hiRes;
                    break;
                }
                $dynJson = trim($imgEl->getAttribute('data-a-dynamic-image'));
                if ($dynJson !== '') {
                    $dynMap = @json_decode(html_entity_decode($dynJson, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
                    if (is_array($dynMap) && !empty($dynMap)) {
                        $bestUrl = '';
                        $bestArea = -1;
                        foreach ($dynMap as $u => $dims) {
                            if (!is_string($u) || !preg_match('#^https?://#i', $u)) continue;
                            $area = (is_array($dims) && isset($dims[0], $dims[1]))
                                ? ((int)$dims[0] * (int)$dims[1])
                                : 0;
                            if ($area > $bestArea) {
                                $bestArea = $area;
                                $bestUrl = $u;
                            }
                        }
                        if ($bestUrl !== '') {
                            $imageUrl = $bestUrl;
                            break;
                        }
                    }
                }
                $srcAttr = trim($imgEl->getAttribute('src'));
                if ($srcAttr !== '' && !preg_match('/^(?:data:|.*grey-pixel|.*transparent-pixel)/i', $srcAttr)) {
                    $imageUrl = $srcAttr;
                    break;
                }
            }
        }
    }
}

if ($imageUrl === '' && preg_match('/"hiRes"\s*:\s*"(https:\/\/m\.media-amazon\.com\/images\/I\/[^"\\\\]+)"/i', $html, $amzImgMatch)) {
    $imageUrl = $amzImgMatch[1];
} elseif ($imageUrl === '' && preg_match('/"large"\s*:\s*"(https:\/\/m\.media-amazon\.com\/images\/I\/[^"\\\\]+)"/i', $html, $amzImgMatch)) {
    $imageUrl = $amzImgMatch[1];
}

libxml_clear_errors();
libxml_use_internal_errors($prevLibxml);

if ($imageUrl !== '') {
    $resolvedImg = resolveRelativeHttpUrl($finalUrl, html_entity_decode($imageUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $imageUrl = sanitizeHttpUrl($resolvedImg, false);
}

// Clean redundant description if identical to title
if ($description !== '' && strcasecmp($description, $title) === 0) {
    $description = '';
}

$host = strtolower(parse_url($finalUrl, PHP_URL_HOST) ?? '');
$store = $siteName !== '' ? $siteName : ($host !== '' ? preg_replace('/^www\./i', '', $host) : '');

if ($title === '') {
    $slugTitle = extractTitleFromUrlSlug($finalUrl);
    if ($slugTitle !== '') {
        $title = $slugTitle;
    }
}

if ($title === '' && $price === '' && $imageUrl === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'No product metadata was found on that page. Please fill in the item fields manually.'
    ]);
    exit;
}

$responsePayload = [
    'ok' => true,
    'title' => $title,
    'description' => $description,
    'price' => $price,
    'image' => $imageUrl,
    'store' => $store,
    'final_url' => $finalUrl
];

if (!isset($_SESSION['url_meta_cache_v4']) || !is_array($_SESSION['url_meta_cache_v4'])) {
    $_SESSION['url_meta_cache_v4'] = [];
}
if (count($_SESSION['url_meta_cache_v4']) >= 20) {
    array_shift($_SESSION['url_meta_cache_v4']);
}
$_SESSION['url_meta_cache_v4'][$cleanUrl] = [
    'ts' => time(),
    'payload' => $responsePayload
];

echo json_encode($responsePayload);
