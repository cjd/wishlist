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
$link1 = trim((string)($_POST["link1"] ?? ''));
$link1url_in = trim((string)($_POST["link1url"] ?? ''));

$link1url = '';
if ($link1url_in !== '') {
    if (!preg_match('#^https?://#i', $link1url_in)) {
        $link1url_in = 'https://' . $link1url_in;
    }
    $link1url = sanitizeHttpUrl($link1url_in, false);
}

if ($link1url === '') {
    $link1 = '';
} elseif ($link1 === '' || strtolower($link1) === 'link') {
    $host = parse_url($link1url, PHP_URL_HOST);
    $link1 = $host ? preg_replace('/^www\./i', '', $host) : 'Link';
}

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

$stmt = mysqli_prepare($link, "INSERT INTO items (cid, addStar, title, description, price, quantity, link1, link1url, subdesc, allowCheck, itemSortOrder, createDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "isssdissssi", $cid, $addStar, $title, $description, $price, $quantity, $link1, $link1url, $subdesc, $allowCheck, $iso);
mysqli_stmt_execute($stmt);
$new_iid = (int)mysqli_insert_id($link);

if ($new_iid > 0 && $price > 0) {
    $stmt_ph = mysqli_prepare($link, "INSERT INTO itemPriceHistory (iid, price) VALUES (?, ?)");
    if ($stmt_ph) {
        mysqli_stmt_bind_param($stmt_ph, "id", $new_iid, $price);
        mysqli_stmt_execute($stmt_ph);
    }
}

if ($new_iid > 0 && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = $base_dir . '/uploads/';
    $uploadFile = "image-" . $new_iid . ".jpg";
    if (process_image_upload('image', $uploadDir . $uploadFile)) {
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
