<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include "../funcLib.php";

$confirm = $_REQUEST["confirm"];
$referrer = "modifyList.php";

// check if either are null

if($confirm == "yes"){

  $query="SELECT items.iid as iid FROM items,categories,purchaseHistory WHERE categories.userid='".$userid."' and categories.cid=items.cid and purchaseHistory.iid=items.iid";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  while ($row = mysqli_fetch_assoc($result)) {
    deleteItem($row["iid"], $userid, $_SESSION["fullname"], $base_dir);
  }
  header("Location: " . getFullPath("modifyList.php"));
}
else{

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<BODY>
<table class="pagetable">
<tr>

<td valign="top" >

<?php
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Remove Crossed Off Items");
?>

<table width="100%" height="100%">
<tr><td valign=center align=center>

<table><tr><td align=left>
<h2>Remove Crossed Off Items</h2>
<p>

<table bgcolor="indianred">
<tr><td>
<h3>Are you sure you want to remove all the crosses off items?</h3>

<?php print "DELETE FROM items,categories,purchaseHistory WHERE categories.userid='".$userid."' and categories.cid=items.cid and purchaseHistory.iid=items.iid"; ?>
</td><td>
<form method="post" action="removeCrossedItems.php">
<input type="hidden" name="confirm" value="yes">
<input type="hidden" name="referrer" value="<?php echo $referrer ?>">
<input type="submit" value="Yes" style="font-weight:bold">
</form>
</td><td>
<form method="post" action="<?php echo $referrer ?>">
<input type="submit" value="Cancel" style="font-weight:bold">
</form>
</td></tr></table>
</td></tr></table>

</td></tr></table>

<?php
}
?>
