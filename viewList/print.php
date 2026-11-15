<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$viewerUserid = $_SESSION["userid"];
$recip = trim((string)($_REQUEST["recip"] ?? ""));
$name = trim((string)($_REQUEST["name"] ?? ""));

$access = canViewList($link, $recip, $viewerUserid);
if (!$access['allowed']) {
    http_response_code(403);
    die("You do not have permission to print this wishlist.");
}

$stmt_p = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_p, "s", $recip);
mysqli_stmt_execute($stmt_p);
if ($prow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
    $name = trim($prow["firstname"] . " " . $prow["lastname"] . " " . ($prow["suffix"] ?? ""));
}

// Hide purchases if the list owner prints their own list
$displayPurchases = ($recip === $viewerUserid) ? 0 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="stylesheet" href="../print.css" type="text/css" media="print">
  <title><?php echo escapeText($name); ?>'s WishList</title>
</head>
<body style="background-color: white;">
<font size="5"><b><?php echo escapeText($name); ?>'s WishList</b></font>
<p>
<?php printList2($recip, $viewerUserid, $name, $displayPurchases); ?>
</body>
</html>
