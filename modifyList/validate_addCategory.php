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

$cname = trim((string)($_POST["cname"] ?? ''));
if ($cname === '') {
    header("Location: " . getFullPath("addCategory.php"));
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

$stmt = mysqli_prepare($link, "SELECT COALESCE(MAX(catSortOrder), -1) + 1 AS next_cso FROM categories WHERE userid = ? AND catSortOrder >= 0");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
$cso = max(0, (int)($row["next_cso"] ?? 0));

$stmt = mysqli_prepare($link, "INSERT INTO categories (userid, name, description, linkname, linkurl, catSortOrder, catSubDescription) VALUES (?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sssssis", $userid, $cname, $description, $linkname, $linkurl, $cso, $catSubDescription);
mysqli_stmt_execute($stmt);

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));
exit;
