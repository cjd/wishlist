<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . getFullPath("modifyList.php"));
    exit;
}

verifyCsrfOrDie(true);

$cid = (int)($_POST["cid"] ?? 0);
$title = trim((string)($_POST["title"] ?? ''));
$description = trim((string)($_POST["desc"] ?? ''));
$priceRaw = str_replace([',', '$'], '', trim((string)($_POST["price"] ?? '')));
$price = is_numeric($priceRaw) ? max(0.0, round((float)$priceRaw, 2)) : 0.0;

$qtyRaw = $_POST["quantity"] ?? 1;
$quantity = is_numeric($qtyRaw) ? max(1, (int)$qtyRaw) : 1;

$subdesc = trim((string)($_POST["subdesc"] ?? ''));

$parseLinkPair = function($nameKey, $urlKey) {
    $nameVal = trim((string)($_POST[$nameKey] ?? ''));
    $urlIn = trim((string)($_POST[$urlKey] ?? ''));
    $cleanUrl = '';
    if ($urlIn !== '') {
        if (!preg_match('#^https?://#i', $urlIn)) {
            $urlIn = 'https://' . $urlIn;
        }
        $cleanUrl = sanitizeHttpUrl($urlIn);
    }
    if ($cleanUrl === '') {
        return ['', ''];
    }
    if ($nameVal === '' || strtolower($nameVal) === 'link') {
        $host = parse_url($cleanUrl, PHP_URL_HOST);
        $nameVal = $host ? preg_replace('/^www\./i', '', $host) : 'Link';
    }
    return [$nameVal, $cleanUrl];
};

$rawPairs = [
    $parseLinkPair('link1', 'link1url'),
    $parseLinkPair('link2', 'link2url'),
    $parseLinkPair('link3', 'link3url')
];
$filledPairs = array_values(array_filter($rawPairs, function($p) {
    return $p[1] !== '';
}));
while (count($filledPairs) < 3) {
    $filledPairs[] = ['', ''];
}
[$link1, $link1url] = $filledPairs[0];
[$link2, $link2url] = $filledPairs[1];
[$link3, $link3url] = $filledPairs[2];

$allowCheck = !empty($_POST["allowCheck"]) ? "true" : "false";
$addStar = !empty($_POST["addStar"]) ? "1" : "0";

if ($title === '') {
    header("Location: " . getFullPath("addItem.php?cid=" . $cid));
    exit;
}

$check_cat = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($check_cat, "is", $cid, $userid);
mysqli_stmt_execute($check_cat);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_cat))) {
    http_response_code(403);
    die("Unauthorized: Category does not belong to you.");
}

$stmt = mysqli_prepare($link, "SELECT COALESCE(MAX(itemSortOrder), -1) + 1 AS next_iso FROM items WHERE cid = ?");
mysqli_stmt_bind_param($stmt, "i", $cid);
mysqli_stmt_execute($stmt);
$row_iso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$iso = max(0, (int)($row_iso["next_iso"] ?? 0));

$stmt = mysqli_prepare($link, "INSERT INTO items (cid, addStar, title, description, price, quantity, link1, link1url, link2, link2url, link3, link3url, subdesc, allowCheck, itemSortOrder, createDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "isssdissssssssi", $cid, $addStar, $title, $description, $price, $quantity, $link1, $link1url, $link2, $link2url, $link3, $link3url, $subdesc, $allowCheck, $iso);
mysqli_stmt_execute($stmt);
$new_iid = (int)mysqli_insert_id($link);

if ($new_iid > 0) {
    $uploadDir = $base_dir . '/uploads/';
    $uploadFile = "image-" . $new_iid . ".jpg";
    $savedImage = false;

    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        if (process_image_upload('image', $uploadDir . $uploadFile)) {
            $savedImage = true;
        }
    } elseif (!empty($_POST['fetched_image_url'])) {
        if (import_remote_image_to_thumbnail((string)$_POST['fetched_image_url'], $uploadDir . $uploadFile)) {
            $savedImage = true;
        }
    }

    if ($savedImage) {
        $stmt_img = mysqli_prepare($link, "UPDATE items SET image = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt_img, "si", $uploadFile, $new_iid);
        mysqli_stmt_execute($stmt_img);
    }
}

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));
exit;
