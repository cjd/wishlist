<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$iid = (int)($_REQUEST["iid"] ?? 0);
$confirm = $_REQUEST["confirm"] ?? "";
$rawReferrer = basename((string)($_REQUEST["referrer"] ?? "modifyList.php"));
$referrer = in_array($rawReferrer, ["modifyList.php", "editCategory.php", "editItem.php"], true)
    ? $rawReferrer
    : "modifyList.php";

$stmt = mysqli_prepare($link, "SELECT items.*, categories.cid AS actual_cid, categories.name AS catName FROM items JOIN categories ON items.cid = categories.cid WHERE items.iid = ? AND categories.userid = ?");
mysqli_stmt_bind_param($stmt, "is", $iid, $userid);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm === "yes") {
    verifyCsrfOrDie(true);
    if (!$row) {
        http_response_code(403);
        die("Unauthorized: That item does not belong to you.");
    }

    $cid = (int)$row["actual_cid"];
    $cname = $row["catName"];
    $result = deleteItem($iid, $userid, $activeOwnerFullname, $base_dir);

    if ($result !== "Success") {
        http_response_code(400);
        echo escapeText($result);
        exit;
    }

    $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_m, "s", $userid);
    mysqli_stmt_execute($stmt_m);

    header("Location: " . getFullPath("modifyList.php?cid=" . $cid . "&cname=" . urlencode($cname)));
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
  <title>Delete Item</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Delete Item"); ?>

<table width="100%">
<tr><td valign="center" align="center">

<?php if ($row): ?>
  <?php
    $cid = (int)$row["actual_cid"];
    $iso = (int)$row["itemSortOrder"];
    $cname = $row["catName"];
  ?>
  <table><tr><td align="left">
    <h2>Delete Item</h2>
    <table border="0" cellpadding="2">
      <?php printItem($row, $activeOwnerFullname, (int)$row["quantity"], 0, ''); ?>
    </table>
    <p>
    <table bgcolor="indianred" cellpadding="6">
    <tr>
      <td><h3>Are you sure you want to delete this item?</h3></td>
      <td>
        <form method="post" action="deleteItem.php" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
          <input type="hidden" name="iid" value="<?php echo (int)$iid; ?>">
          <input type="hidden" name="iso" value="<?php echo (int)$iso; ?>">
          <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
          <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
          <input type="hidden" name="confirm" value="yes">
          <input type="hidden" name="referrer" value="<?php echo escapeText($referrer); ?>">
          <input type="submit" value="Yes" style="font-weight:bold">
        </form>
      </td>
      <td>
        <form method="get" action="<?php echo escapeText($referrer); ?>" style="margin:0;">
          <input type="hidden" name="iid" value="<?php echo (int)$iid; ?>">
          <input type="hidden" name="iso" value="<?php echo (int)$iso; ?>">
          <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
          <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
          <input type="submit" value="Cancel" style="font-weight:bold">
        </form>
      </td>
    </tr>
    </table>
  </td></tr></table>
<?php else: ?>
  <h2 style="color:#c00;">That item was not found or does not belong to you.</h2>
<?php endif; ?>

</td></tr></table>
</td></tr></table>
</body>
</html>
