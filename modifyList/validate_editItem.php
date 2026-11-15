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

$iid = (int)($_POST["iid"] ?? 0);
$movecid = (int)($_POST["movecid"] ?? 0);

// Fetch authoritative item state from DB
$check_item = mysqli_prepare($link, "SELECT items.iid, items.cid, items.itemSortOrder, items.price, items.image FROM items JOIN categories ON items.cid = categories.cid WHERE items.iid = ? AND categories.userid = ?");
mysqli_stmt_bind_param($check_item, "is", $iid, $userid);
mysqli_stmt_execute($check_item);
$cur_item = mysqli_fetch_assoc(mysqli_stmt_get_result($check_item));
if (!$cur_item) {
    http_response_code(403);
    die("Unauthorized: That item does not belong to you.");
}

$cid = (int)$cur_item["cid"];
$iso = (int)$cur_item["itemSortOrder"];
$oldPrice = (float)($cur_item["price"] ?? 0);
$oldImage = !empty($cur_item["image"]) ? basename($cur_item["image"]) : "";

$check_move = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($check_move, "is", $movecid, $userid);
mysqli_stmt_execute($check_move);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_move))) {
    http_response_code(403);
    die("Unauthorized: Destination category does not belong to you.");
}

$title = trim((string)($_POST["title"] ?? ''));
if ($title === '') {
    header("Location: " . getFullPath("editItem.php?iid=" . $iid . "&cid=" . $cid));
    exit;
}

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
        $cleanUrl = sanitizeHttpUrl($urlIn, false);
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

mysqli_begin_transaction($link);
try {
    if ($cid !== $movecid) {
        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE itemSortOrder > ? AND cid = ?");
        mysqli_stmt_bind_param($stmt, "ii", $iso, $cid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "SELECT COALESCE(MAX(itemSortOrder), -1) + 1 AS next_iso FROM items WHERE cid = ?");
        mysqli_stmt_bind_param($stmt, "i", $movecid);
        mysqli_stmt_execute($stmt);
        $row_iso = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        $iso = max(0, (int)($row_iso["next_iso"] ?? 0));
    }

    $stmt = mysqli_prepare($link, "UPDATE items SET title = ?, description = ?, quantity = ?, subdesc = ?, price = ?, allowCheck = ?, addStar = ?, link1 = ?, link1url = ?, link2 = ?, link2url = ?, link3 = ?, link3url = ?, itemSortOrder = ?, cid = ? WHERE iid = ?");
    mysqli_stmt_bind_param($stmt, "ssisdssssssssiii", $title, $description, $quantity, $subdesc, $price, $allowCheck, $addStar, $link1, $link1url, $link2, $link2url, $link3, $link3url, $iso, $movecid, $iid);
    mysqli_stmt_execute($stmt);

    if (abs($price - $oldPrice) >= 0.005) {
        $stmt_ph = mysqli_prepare($link, "INSERT INTO itemPriceHistory (iid, price) VALUES (?, ?)");
        if ($stmt_ph) {
            mysqli_stmt_bind_param($stmt_ph, "id", $iid, $price);
            mysqli_stmt_execute($stmt_ph);
        }
    }

    mysqli_commit($link);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    error_log("validate_editItem failed: " . $e->getMessage());
    header("Location: " . getFullPath("editItem.php?iid=" . $iid . "&cid=" . $cid . "&error=1"));
    exit;
}

// Non-destructive image upload / removal handling
$uploadDir = $base_dir . '/uploads/';
$hasNewUpload = (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK);

if ($hasNewUpload) {
    $finalFile = "image-" . $iid . ".jpg";
    $tempBase = "image-" . $iid . "-tmp-" . bin2hex(random_bytes(4));
    $tempFile = $tempBase . ".jpg";
    $tempThumb = $tempBase . "_thumb.jpg";
    $finalThumb = "image-" . $iid . "_thumb.jpg";

    if (process_image_upload('image', $uploadDir . $tempFile)) {
        // Remove legacy differently-named image if any
        if ($oldImage !== "" && $oldImage !== $finalFile && is_file($uploadDir . $oldImage)) {
            @unlink($uploadDir . $oldImage);
        }
        @rename($uploadDir . $tempFile, $uploadDir . $finalFile);
        if (is_file($uploadDir . $tempThumb)) {
            @rename($uploadDir . $tempThumb, $uploadDir . $finalThumb);
        }
        $stmt_img = mysqli_prepare($link, "UPDATE items SET image = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt_img, "si", $finalFile, $iid);
        mysqli_stmt_execute($stmt_img);
    } else {
        // Clean up any partial temp files while keeping existing image intact!
        if (is_file($uploadDir . $tempFile)) @unlink($uploadDir . $tempFile);
        if (is_file($uploadDir . $tempThumb)) @unlink($uploadDir . $tempThumb);
    }
} elseif (!empty($_POST['remove_image']) && $oldImage !== "") {
    if (is_file($uploadDir . $oldImage)) {
        @unlink($uploadDir . $oldImage);
    }
    $oldThumb = preg_replace('/\.[^.]+$/', '', $oldImage) . '_thumb.jpg';
    if (is_file($uploadDir . $oldThumb)) {
        @unlink($uploadDir . $oldThumb);
    }
    $stmt_img = mysqli_prepare($link, "UPDATE items SET image = NULL WHERE iid = ?");
    mysqli_stmt_bind_param($stmt_img, "i", $iid);
    mysqli_stmt_execute($stmt_img);
}

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));
exit;
