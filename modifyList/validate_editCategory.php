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
$stmt_chk = mysqli_prepare($link, "SELECT catSortOrder, name FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($stmt_chk, "is", $cid, $userid);
mysqli_stmt_execute($stmt_chk);
$cat_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));
if (!$cat_row) {
    http_response_code(403);
    die("Category not found or access denied.");
}

$name = ((int)$cat_row['catSortOrder'] < 0)
    ? $cat_row['name']
    : trim((string)($_POST["name"] ?? ''));

if ($name === '') {
    header("Location: " . getFullPath("editCategory.php?cid=" . $cid));
    exit;
}

$description = trim((string)($_POST["description"] ?? ''));
$linkname = trim((string)($_POST["linkname"] ?? ''));
$linkurl_in = trim((string)($_POST["linkurl"] ?? ''));
$catSubDescription = trim((string)($_POST["catSubDescription"] ?? ''));

$linkurl = '';
if ($linkurl_in !== '') {
    if (!preg_match('#^https?://#i', $linkurl_in)) {
        $linkurl_in = 'https://' . $linkurl_in;
    }
    $linkurl = sanitizeHttpUrl($linkurl_in, false);
}
if ($linkurl === '') {
    $linkname = '';
}

$stmt = mysqli_prepare($link, "UPDATE categories SET name = ?, description = ?, linkname = ?, linkurl = ?, catSubDescription = ? WHERE cid = ? AND userid = ?");
mysqli_stmt_bind_param($stmt, "sssssis", $name, $description, $linkname, $linkurl, $catSubDescription, $cid, $userid);
mysqli_stmt_execute($stmt);

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));
exit;
