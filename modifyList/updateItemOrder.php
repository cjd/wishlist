<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

verifyCsrfOrDie(true);

if (!isset($_POST['itemId'], $_POST['toCategoryId'], $_POST['newIndex'])) {
    http_response_code(400);
    echo "Invalid request.";
    exit;
}

$itemId = (int)$_POST['itemId'];
$toCategoryId = (int)$_POST['toCategoryId'];
$newIndex = max(0, (int)$_POST['newIndex']);

$check_to = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($check_to, "is", $toCategoryId, $userid);
mysqli_stmt_execute($check_to);
if (!mysqli_fetch_assoc(mysqli_stmt_get_result($check_to))) {
    http_response_code(403);
    echo "Unauthorized: Destination category does not belong to you.";
    exit;
}

mysqli_begin_transaction($link);
try {
    // Read authoritative source category and sort order from DB
    $stmt_cur = mysqli_prepare($link, "SELECT items.cid, items.itemSortOrder FROM items JOIN categories ON items.cid = categories.cid WHERE items.iid = ? AND categories.userid = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_cur, "is", $itemId, $userid);
    mysqli_stmt_execute($stmt_cur);
    $cur_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cur));

    if (!$cur_row) {
        mysqli_rollback($link);
        http_response_code(403);
        echo "Unauthorized: That item does not belong to you.";
        exit;
    }

    $fromCategoryId = (int)$cur_row['cid'];
    $oldSortOrder = (int)$cur_row['itemSortOrder'];

    // Clamp newIndex to valid range in destination category
    $stmt_cnt = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM items WHERE cid = ? AND iid != ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_cnt, "ii", $toCategoryId, $itemId);
    mysqli_stmt_execute($stmt_cnt);
    $otherCount = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cnt))['cnt'] ?? 0);
    if ($newIndex > $otherCount) {
        $newIndex = $otherCount;
    }

    // Temporarily move item out of the way
    $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = -1 WHERE iid = ?");
    mysqli_stmt_bind_param($stmt, "i", $itemId);
    mysqli_stmt_execute($stmt);

    if ($fromCategoryId === $toCategoryId) {
        if ($newIndex > $oldSortOrder) {
            $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE cid = ? AND itemSortOrder > ? AND itemSortOrder <= ?");
            mysqli_stmt_bind_param($stmt, "iii", $fromCategoryId, $oldSortOrder, $newIndex);
            mysqli_stmt_execute($stmt);
        } elseif ($newIndex < $oldSortOrder) {
            $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE cid = ? AND itemSortOrder >= ? AND itemSortOrder < ?");
            mysqli_stmt_bind_param($stmt, "iii", $fromCategoryId, $newIndex, $oldSortOrder);
            mysqli_stmt_execute($stmt);
        }
    } else {
        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE cid = ? AND itemSortOrder > ?");
        mysqli_stmt_bind_param($stmt, "ii", $fromCategoryId, $oldSortOrder);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE cid = ? AND itemSortOrder >= ?");
        mysqli_stmt_bind_param($stmt, "ii", $toCategoryId, $newIndex);
        mysqli_stmt_execute($stmt);
    }

    $stmt = mysqli_prepare($link, "UPDATE items SET cid = ?, itemSortOrder = ? WHERE iid = ?");
    mysqli_stmt_bind_param($stmt, "iii", $toCategoryId, $newIndex, $itemId);
    mysqli_stmt_execute($stmt);

    $stmt_mod = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_mod, "s", $userid);
    mysqli_stmt_execute($stmt_mod);

    mysqli_commit($link);
    echo "OK";
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($link);
    error_log("updateItemOrder failed: " . $exception->getMessage());
    http_response_code(500);
    echo "Error updating item order.";
}
