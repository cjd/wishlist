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

$dir = $_POST["dir"] ?? "";
$iid = (int)($_POST["iid"] ?? 0);

if (!in_array($dir, ["up", "down"], true) || $iid <= 0) {
    header("Location: " . getFullPath("modifyList.php"));
    exit;
}

mysqli_begin_transaction($link);
try {
    $check = mysqli_prepare($link, "SELECT items.cid, items.itemSortOrder FROM items JOIN categories ON items.cid = categories.cid WHERE items.iid = ? AND categories.userid = ? FOR UPDATE");
    mysqli_stmt_bind_param($check, "is", $iid, $userid);
    mysqli_stmt_execute($check);
    $item_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    if (!$item_row) {
        mysqli_rollback($link);
        http_response_code(403);
        die("Unauthorized: That item does not belong to you.");
    }

    $cid = (int)$item_row["cid"];
    $iso = (int)$item_row["itemSortOrder"];

    $stmt_max = mysqli_prepare($link, "SELECT COALESCE(MAX(itemSortOrder), 0) AS max_iso FROM items WHERE cid = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_max, "i", $cid);
    mysqli_stmt_execute($stmt_max);
    $max_iso = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_max))["max_iso"] ?? 0);

    if ($dir === "down" && $iso < $max_iso) {
        $target_iso = $iso + 1;
        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = ? WHERE itemSortOrder = ? AND cid = ?");
        mysqli_stmt_bind_param($stmt, "iii", $iso, $target_iso, $cid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt, "ii", $target_iso, $iid);
        mysqli_stmt_execute($stmt);
    } elseif ($dir === "up" && $iso > 0) {
        $target_iso = $iso - 1;
        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = ? WHERE itemSortOrder = ? AND cid = ?");
        mysqli_stmt_bind_param($stmt, "iii", $iso, $target_iso, $cid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt, "ii", $target_iso, $iid);
        mysqli_stmt_execute($stmt);
    }

    $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_m, "s", $userid);
    mysqli_stmt_execute($stmt_m);

    mysqli_commit($link);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    error_log("moveItem failed: " . $e->getMessage());
}

header("Location: " . getFullPath("modifyList.php"));
exit;
