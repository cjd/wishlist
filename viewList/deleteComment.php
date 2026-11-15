<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../home.php");
    exit;
}

verifyCsrfOrDie(true);

$viewerUserid = $_SESSION["userid"];
$name = trim((string)($_POST["name"] ?? ""));
$recip = trim((string)($_POST["recip"] ?? ""));
$commentId = (int)($_POST["commentId"] ?? 0);
$returnTo = $_POST["return_to"] ?? "";

if ($commentId > 0) {
    // Only the comment author (or an admin for someone else's list) may delete a comment;
    // list owners can never blind-delete comments on their own wishlist.
    if (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1) {
        $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE commentId = ? AND (comment_userid = ? OR userid != ?)");
        mysqli_stmt_bind_param($stmt, "iss", $commentId, $viewerUserid, $viewerUserid);
    } else {
        $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE commentId = ? AND comment_userid = ? AND (claimed_by IS NULL OR claimed_by = '' OR claimed_by = ?)");
        mysqli_stmt_bind_param($stmt, "iss", $commentId, $viewerUserid, $viewerUserid);
    }
    mysqli_stmt_execute($stmt);
}

if ($returnTo === 'viewPurchases') {
    header("Location: " . getFullPath("../viewPurchases.php"));
} else {
    header("Location: " . getFullPath("viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name)));
}
exit;
