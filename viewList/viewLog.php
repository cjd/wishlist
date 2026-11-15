<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$viewerUserid = $_SESSION["userid"];
$recip = trim((string)($_REQUEST["recip"] ?? ""));
$name = trim((string)($_REQUEST["name"] ?? ""));

if ($recip === "") {
    header("Location: ../home.php");
    exit;
}

$access = canViewList($link, $recip, $viewerUserid);
if (!$access['allowed']) {
    header("Location: ../home.php");
    exit;
}

$stmt_p = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_p, "s", $recip);
mysqli_stmt_execute($stmt_p);
if ($prow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
    $name = trim($prow["firstname"] . " " . $prow["lastname"] . " " . ($prow["suffix"] ?? ""));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title><?php echo escapeText($name); ?>'s WishList Change Log</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php
createNavBar("../home.php:Home|viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name) . ":View List - " . $name . "|:View Log " . $name, false, "");
?>
<p>
<font size="2">
<?php
$query = "SELECT people.firstname, people.lastname, people.suffix, purchaseHistory.userid, purchaseHistory.boughtDate, purchaseHistory.quantity, purchaseHistory.splitWith, items.title, items.description, purchaseHistory.purchaseId
          FROM purchaseHistory
          JOIN items ON items.iid = purchaseHistory.iid
          JOIN categories ON categories.cid = items.cid
          JOIN people ON people.userid = purchaseHistory.userid
          WHERE categories.userid = ?
            AND categories.catSortOrder > -1
            AND (people.userid = ? OR people.userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1))
          ORDER BY purchaseHistory.boughtDate DESC";

$stmt_log = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt_log, "sss", $recip, $viewerUserid, $viewerUserid);
mysqli_stmt_execute($stmt_log);
$rs = mysqli_stmt_get_result($stmt_log);

if ($rs && mysqli_num_rows($rs) > 0) {
    while ($row = mysqli_fetch_assoc($rs)) {
        $buyerFull = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
        $qty = (int)$row["quantity"];
?>
  On <font color="indianred"><?php echo escapeText(parseDate($row["boughtDate"], 1)); ?></font>,
  <font color="navy"><?php echo escapeText($buyerFull); ?></font>
  <?php if (!empty($row["splitWith"])): ?>
    <font color="purple"><b>(Splitting with <?php echo escapeText($row["splitWith"]); ?>)</b></font>
  <?php endif; ?>
  made the following changes
  <b><a href="deletePurchase.php?recip=<?php echo urlencode($recip); ?>&amp;name=<?php echo urlencode($name); ?>&amp;purchaseId=<?php echo (int)$row["purchaseId"]; ?>&amp;return_to=viewLog">[Undo this purchase]</a></b>
  <br>
  <?php if ($qty !== 1): ?>
    <font color="red">Bought <?php echo $qty; ?></font>
  <?php endif; ?>
  <i><?php echo escapeText($row["title"]); ?></i>
  - <?php echo formatMultilineText($row["description"]); ?>
  <p>
<?php
    }
} else {
    echo "<p><i>No purchases recorded by you for this list.</i></p>";
}
?>
</font>
</td></tr></table>
</body>
</html>
