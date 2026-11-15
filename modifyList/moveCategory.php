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
$cid = (int)($_POST["cid"] ?? 0);

if (!in_array($dir, ["up", "down"], true) || $cid <= 0) {
    header("Location: " . getFullPath("modifyList.php"));
    exit;
}

mysqli_begin_transaction($link);
try {
    $check = mysqli_prepare($link, "SELECT catSortOrder FROM categories WHERE cid = ? AND userid = ? AND catSortOrder >= 0 FOR UPDATE");
    mysqli_stmt_bind_param($check, "is", $cid, $userid);
    mysqli_stmt_execute($check);
    $cur_row = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
    if (!$cur_row) {
        mysqli_rollback($link);
        http_response_code(403);
        die("Unauthorized: Category does not belong to you.");
    }

    $cso = (int)$cur_row["catSortOrder"];
    $stmt_max = mysqli_prepare($link, "SELECT COALESCE(MAX(catSortOrder), 0) AS max_cso FROM categories WHERE userid = ? AND catSortOrder >= 0 FOR UPDATE");
    mysqli_stmt_bind_param($stmt_max, "s", $userid);
    mysqli_stmt_execute($stmt_max);
    $max_cso = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_max))["max_cso"] ?? 0);

    if ($dir === "down" && $cso < $max_cso) {
        $target_cso = $cso + 1;
        $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE catSortOrder = ? AND userid = ? AND catSortOrder >= 0");
        mysqli_stmt_bind_param($stmt, "iis", $cso, $target_cso, $userid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE cid = ? AND userid = ?");
        mysqli_stmt_bind_param($stmt, "iis", $target_cso, $cid, $userid);
        mysqli_stmt_execute($stmt);
    } elseif ($dir === "up" && $cso > 0) {
        $target_cso = $cso - 1;
        $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE catSortOrder = ? AND userid = ? AND catSortOrder >= 0");
        mysqli_stmt_bind_param($stmt, "iis", $cso, $target_cso, $userid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE cid = ? AND userid = ?");
        mysqli_stmt_bind_param($stmt, "iis", $target_cso, $cid, $userid);
        mysqli_stmt_execute($stmt);
    }

    $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_m, "s", $userid);
    mysqli_stmt_execute($stmt_m);

    mysqli_commit($link);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    error_log("moveCategory failed: " . $e->getMessage());
}

header("Location: " . getFullPath("modifyList.php"));
exit;
