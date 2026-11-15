<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

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

$clientIp = getClientIp();
$actorUid = strtolower((string)($_SESSION['userid'] ?? $userid));
$rl = checkRateLimit($link, $clientIp, $actorUid, 'url_autofill', 25, 300);
if ($rl['blocked']) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many auto-fill requests. Please wait a moment and try again.']);
    exit;
}
recordRateLimitAttempt($link, $clientIp, $actorUid, 'url_autofill');

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

$fetched = fetchSafePublicUrl($cleanUrl, 1572864, 4, false);
if (empty($fetched['ok']) || empty($fetched['body'])) {
    echo json_encode([
        'ok' => false,
        'error' => $fetched['error'] ?? 'Could not read details from that page. You can still type the item details manually.'
    ]);
    exit;
}

$html = (string)$fetched['body'];
$finalUrl = (string)($fetched['final_url'] ?? $cleanUrl);

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

if ($title === '') {
    foreach (['og:title', 'twitter:title', 'name'] as $tk) {
        if (!empty($metaMap[$tk])) {
            $title = cleanExtractedText($metaMap[$tk], 255);
            if ($title !== '') break;
        }
    }
}
if ($title === '') {
    $titleNodes = $xpath->query('//title');
    if ($titleNodes && $titleNodes->length > 0) {
        $rawPageTitle = cleanExtractedText($titleNodes->item(0)->textContent, 255);
        $rawPageTitle = preg_replace('/^Amazon\.[a-z.]+\s*:\s*/i', '', $rawPageTitle);
        $title = $rawPageTitle;
    }
}

if ($description === '') {
    foreach (['og:description', 'twitter:description', 'description'] as $dk) {
        if (!empty($metaMap[$dk])) {
            $description = cleanExtractedText($metaMap[$dk], 200);
            if ($description !== '') break;
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

if ($imageUrl === '') {
    foreach (['og:image:secure_url', 'og:image', 'twitter:image', 'twitter:image:src', 'image'] as $ik) {
        if (!empty($metaMap[$ik])) {
            $imageUrl = $metaMap[$ik];
            break;
        }
    }
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

if ($title === '' && $price === '' && $imageUrl === '') {
    echo json_encode([
        'ok' => false,
        'error' => 'No product metadata was found on that page. Please fill in the item fields manually.'
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'title' => $title,
    'description' => $description,
    'price' => $price,
    'image' => $imageUrl,
    'store' => $store,
    'final_url' => $finalUrl
]);
