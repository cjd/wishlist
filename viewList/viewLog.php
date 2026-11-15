<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include "../funcLib.php";

$recip = $_REQUEST["recip"] ?? "";
$name = $_REQUEST["name"] ?? "";
$userid = $_SESSION["userid"] ?? "";

// Check authorization to view this log: must be list owner, admin, or have view permissions
if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != 1) {
    $stmt_perm = mysqli_prepare($link, "SELECT 1 FROM viewList WHERE viewer = ? AND pid = ?");
    mysqli_stmt_bind_param($stmt_perm, "ss", $userid, $recip);
    mysqli_stmt_execute($stmt_perm);
    $perm_res = mysqli_stmt_get_result($stmt_perm);
    if (!mysqli_fetch_row($perm_res) && $recip != $userid) {
        header("Location: ../home.php");
        exit;
    }
}

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<title><?php echo htmlspecialchars($name); ?>'s WishList Change Log</title>
<BODY>
<table cellspacing="0" cellpadding="5" width="100%" height="100%" bgcolor="#FFFFFF" nosave border="0" style="border: 1px solid rgb(128,255,128)">
<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name) . ":View List - " . htmlspecialchars($name) . "|:View Log " . htmlspecialchars($name), false, "");
?>

<p>
<font size="2">

<?php

$query = "SELECT firstname, lastname, suffix, purchaseHistory.userid, purchaseHistory.boughtDate, purchaseHistory.quantity, purchaseHistory.splitWith, title, items.description, purchaseId FROM items, categories, people, purchaseHistory WHERE items.iid=purchaseHistory.iid AND categories.cid=items.cid AND people.userid=purchaseHistory.userid AND categories.userid = ? AND (people.userid = ? OR people.userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit=1)) ORDER BY purchaseHistory.boughtDate DESC";

$stmt_log = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt_log, "sss", $recip, $userid, $userid);
mysqli_stmt_execute($stmt_log);
$rs = mysqli_stmt_get_result($stmt_log);
 
while($row = mysqli_fetch_assoc($rs)){
?>

  On <font color="indianred"><?php echo parseDate($row["boughtDate"],1) ?></font>, 
  <font color="navy"><?php echo htmlspecialchars($row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"]) ?></font>
  <?php if (!empty($row["splitWith"])) { ?>
    <font color="purple"><b>(Splitting with <?php echo htmlspecialchars($row["splitWith"]); ?>)</b></font>
  <?php } ?>
  made the following changes
<?php
  if($row["userid"] == $userid){ 
?>    <b><a href="deletePurchase.php?recip=<?php echo urlencode($recip); ?>&name=<?php echo urlencode($name); ?>&purchaseId=<?php echo intval($row["purchaseId"]); ?>">[Undo this purchase]</a></b>
<?php
}
?>
  <br>
  <?php 
    print $row["quantity"] != "1" ? "<font color=red>Bought " . htmlspecialchars($row["quantity"]) . "</font> " : "" ?><i><?php echo htmlspecialchars($row["title"]); ?></i> 
  - <?php echo cleanString($row["description"]); ?> 

  <p>
<?php
}
?>
</font>
</body>
</html>


