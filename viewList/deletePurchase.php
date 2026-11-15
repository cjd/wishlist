<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$viewerUserid = $_SESSION["userid"];
$name = trim((string)($_REQUEST["name"] ?? ""));
$recip = trim((string)($_REQUEST["recip"] ?? ""));
$purchaseId = (int)($_REQUEST["purchaseId"] ?? 0);
$confirm = $_REQUEST["confirm"] ?? "";
$returnTo = $_REQUEST["return_to"] ?? "";

// Fetch purchase + item details from DB, verifying primary buyer or allowEdit=1 spouse/proxy
$sql_fetch = "SELECT ph.purchaseId, ph.userid AS buyer_userid, ph.quantity, ph.splitUsers, ph.splitWith,
                     i.title, i.description, c.userid AS owner_userid,
                     p.firstname AS owner_first, p.lastname AS owner_last, p.suffix AS owner_suffix
              FROM purchaseHistory ph
              JOIN items i ON i.iid = ph.iid
              JOIN categories c ON c.cid = i.cid
              JOIN people p ON p.userid = c.userid
              WHERE ph.purchaseId = ?
                AND (ph.userid = ? OR ph.userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1))";
$stmt = mysqli_prepare($link, $sql_fetch);
mysqli_stmt_bind_param($stmt, "iss", $purchaseId, $viewerUserid, $viewerUserid);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if ($row) {
    $recip = $row["owner_userid"];
    $name = trim($row["owner_first"] . " " . $row["owner_last"] . " " . ($row["owner_suffix"] ?? ""));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm === "yes") {
    verifyCsrfOrDie(true);

    if ($row) {
        $coBuyersToNotify = trim((string)($row["splitUsers"] ?? ""));
        $qty = (int)$row["quantity"];
        $safeDesc = ($qty !== 1 ? "<font color=red>Bought " . $qty . "</font> " : "")
            . "<i>" . escapeText($row["title"]) . "</i> - " . escapeText($row["description"]);

        $stmt_del = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE purchaseId = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $purchaseId);
        mysqli_stmt_execute($stmt_del);

        if ($coBuyersToNotify !== "") {
            $subject = "Group Gift Cancelled: " . $name . "'s WishList";
            $message = "<font color=indianred><b>" . escapeText($_SESSION["fullname"]) .
                "</b></font> has cancelled the group gift purchase from <b>" .
                escapeText($name) . "'s</b> WishList:<br><dir>" . $safeDesc . "</dir>";
            sendEmail($coBuyersToNotify, $viewerUserid, $subject, $message, 0);
        }
    }

    if ($returnTo === 'viewLog') {
        header("Location: " . getFullPath("viewLog.php?recip=" . urlencode($recip) . "&name=" . urlencode($name)));
    } else {
        header("Location: " . getFullPath("../viewPurchases.php"));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Delete Purchase</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php
createNavBar("../home.php:Home|viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name) . ":View List - " . $name . "|viewLog.php?recip=" . urlencode($recip) . "&name=" . urlencode($name) . ":View Log - " . $name . "|:Delete Purchase", false, "");
?>

<?php if ($row): ?>
  <?php
  $qty = (int)$row["quantity"];
  $displayDesc = ($qty !== 1 ? "<font color=\"red\">Bought " . $qty . "</font> " : "")
      . "<i>" . escapeText($row["title"]) . "</i> - " . formatMultilineText($row["description"]);
  ?>
  <h2>Delete Purchase</h2>
  <p><?php echo $displayDesc; ?></p>

  <table bgcolor="violet" cellpadding="6">
  <tr>
    <td><h3>Are you sure you want to undo this purchase?</h3></td>
    <td>
      <form method="post" action="deletePurchase.php" style="margin:0;">
        <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
        <input type="hidden" name="purchaseId" value="<?php echo (int)$purchaseId; ?>">
        <input type="hidden" name="name" value="<?php echo escapeText($name); ?>">
        <input type="hidden" name="recip" value="<?php echo escapeText($recip); ?>">
        <input type="hidden" name="return_to" value="<?php echo escapeText($returnTo); ?>">
        <input type="hidden" name="confirm" value="yes">
        <input type="submit" value="Yes" class="buttonstyle">
      </form>
    </td>
    <td>
      <?php if ($returnTo === 'viewPurchases'): ?>
        <button type="button" class="buttonstyle" onclick="location.href='../viewPurchases.php'">No</button>
      <?php else: ?>
        <form method="get" action="viewLog.php" style="margin:0;">
          <input type="hidden" name="name" value="<?php echo escapeText($name); ?>">
          <input type="hidden" name="recip" value="<?php echo escapeText($recip); ?>">
          <input type="submit" value="No" class="buttonstyle">
        </form>
      <?php endif; ?>
    </td>
  </tr>
  </table>
<?php else: ?>
  <h2 style="color:#c00;">That purchase was not found or does not belong to you.</h2>
<?php endif; ?>

</td></tr></table>
</body>
</html>
