<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$confirm = $_REQUEST["confirm"] ?? "";
$referrer = "modifyList.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm === "yes") {
    verifyCsrfOrDie(true);

    // Only delete purchasable items in visible categories whose purchased quantity meets or exceeds requested quantity
    $sql = "SELECT items.iid
            FROM items
            JOIN categories ON categories.cid = items.cid
            JOIN purchaseHistory ON purchaseHistory.iid = items.iid
            WHERE categories.userid = ?
              AND categories.catSortOrder > -1
              AND items.allowCheck = 'true'
            GROUP BY items.iid, items.quantity
            HAVING SUM(purchaseHistory.quantity) >= items.quantity";
    $stmt = mysqli_prepare($link, $sql);
    mysqli_stmt_bind_param($stmt, "s", $userid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $deletedAny = false;
    while ($row = mysqli_fetch_assoc($result)) {
        deleteItem((int)$row["iid"], $userid, $activeOwnerFullname, $base_dir);
        $deletedAny = true;
    }

    if ($deletedAny) {
        $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_m, "s", $userid);
        mysqli_stmt_execute($stmt_m);
    }

    header("Location: " . getFullPath("modifyList.php"));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Remove Crossed Off Items</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Remove Crossed Off Items"); ?>

<table width="100%">
<tr><td valign="center" align="center">

<table><tr><td align="left">
  <h2>Remove Crossed Off Items</h2>
  <p>This removes items on your list that have been completely purchased by viewers (items only partially fulfilled will be kept).</p>
  <table bgcolor="indianred" cellpadding="6">
  <tr>
    <td><h3>Are you sure you want to remove all fully purchased items?</h3></td>
    <td>
      <form method="post" action="removeCrossedItems.php" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
        <input type="hidden" name="confirm" value="yes">
        <input type="submit" value="Yes" style="font-weight:bold">
      </form>
    </td>
    <td>
      <form method="get" action="modifyList.php" style="margin:0;">
        <input type="submit" value="Cancel" style="font-weight:bold">
      </form>
    </td>
  </tr>
  </table>
</td></tr></table>

</td></tr></table>
</td></tr></table>
</body>
</html>
