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

$name = $_REQUEST["name"] ?? "";
$recip = $_REQUEST["recip"] ?? "";
$userid = $_SESSION["userid"];

$purchaseId = intval($_REQUEST["purchaseId"] ?? 0);
$confirm = $_REQUEST["confirm"] ?? "";

if ($confirm == "yes") {
  verifyCsrfOrDie();
  if ($purchaseId > 0) {
     $stmt = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE purchaseId = ? AND userid = ?");
     mysqli_stmt_bind_param($stmt, "is", $purchaseId, $userid);
     mysqli_stmt_execute($stmt);

     $stmt_email = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ? AND viewer != ?");
     mysqli_stmt_bind_param($stmt_email, "ss", $recip, $recip);
     mysqli_stmt_execute($stmt_email);
     $rs_email = mysqli_stmt_get_result($stmt_email);

     $to = "";
     while ($row = mysqli_fetch_assoc($rs_email)) {
       $to .= $row["viewer"] . ",";
     }
     $to = rtrim($to, ",");

     $from = "";
     $subject = $name . "'s WishList has been modified by " . 
       $_SESSION["fullname"];
     $desc = $_REQUEST["desc"] ?? "";
     $message = "<font color=indianred><b>" . $_SESSION["fullname"] . 
       "</b></font>" . 
       " has decided not to get the following purchase from " . 
       $name . "'s WishList" .
       "<br><dir>" . $desc . "</dir>";

     if (!empty($to)) {
       sendEmail($to, $from, $subject, $message, 0);
     }

     header("Location: " . getFullPath("../viewPurchases.php"));
     exit;
  }
}
else{
  $stmt = mysqli_prepare($link, "SELECT * FROM items JOIN purchaseHistory ON items.iid = purchaseHistory.iid WHERE purchaseHistory.userid = ? AND purchaseId = ?");
  mysqli_stmt_bind_param($stmt, "si", $userid, $purchaseId);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);

  if($row = mysqli_fetch_assoc($rs)){
    $desc = ($row["quantity"] != "1" ? "<font color=red>Bought " . $row["quantity"] . "</font> " : "") . "<i>" . $row["title"] . "</i> - " . $row["description"]; 

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<title>Delete Purchase</title>
<BODY>
<table cellspacing="0" cellpadding="5" width="100%" height="100%" bgcolor="#FFFFFF" nosave border="0" style="border: 1px solid rgb(128,255,128)">

<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|viewList.php?recip=" . $recip . "&name=" . $name . ":View List - " . $name . "|viewLog.php?recip=$recip&name=$name:View Log - $name|:Delete Purchase", false, "");
?>

<h2>Delete Purchase</h2>
<p>
  <?php echo $desc ?> 

<p>
<table bgcolor="violet">
<tr><td>
<h3>Are you sure you want to delete this purchase?</h3>
</td><td>
<form method="post" action="deletePurchase.php">
<input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
<input type="hidden" name="purchaseId" value="<?php echo intval($purchaseId); ?>">
<input type="hidden" name="desc" value="<?php echo htmlspecialchars($desc, ENT_QUOTES); ?>">
<input type="hidden" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
<input type="hidden" name="recip" value="<?php echo htmlspecialchars($recip, ENT_QUOTES); ?>">
<input type="hidden" name="confirm" value="yes">
<input type="submit" value="Yes">
</form>
</td><td>
<form method="post" action="viewLog.php">
<input type="hidden" name="name" value="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>">
<input type="hidden" name="recip" value="<?php echo htmlspecialchars($recip, ENT_QUOTES); ?>">
<input type="submit" value="No">
</form>
</td></tr>
<tr><td colspan=3 align=center>
<b>Note</b>: a message will be sent to everyone that can view <?php echo $name ?>'s WishList
</td></tr></table>


<?php
}
else{
  print "<h1><font color=red>That purchase does not belong to you!</font></h1>";
}

}

?>
