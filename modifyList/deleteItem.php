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

$iid = $_REQUEST["iid"];
$cid = $_REQUEST["cid"];
$iso = $_REQUEST["iso"];
$cname = $_REQUEST["cname"];
$referrer = $_REQUEST["referrer"];
if (array_key_exists("confirm", $_REQUEST)) {
    $confirm = $_REQUEST["confirm"];
} else {
    $confirm = "";
}

// check if either are null

if($confirm == "yes"){

  $result = deleteItem($iid, $userid, $_SESSION["fullname"]);
  
  if($result != "Success"){
    print $result;
  }
  else{
    header("Location: " . getFullPath("modifyList.php?cid=" . $cid . "&cname=" . $cname));
  }
}
else{

$query = "select items.iid as iid, items.title, items.addStar, items.description, subdesc, price, " .
       "quantity, link1, link1url, link2, link2url, link3, link3url, image " .
       "from items, categories " .
     "where items.cid=categories.cid " . 
     " and items.iid=" . $iid . 
     " and items.cid=" . $cid . 
     " and userid='" . $userid . "'";

   $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<BODY>
<table class="pagetable">
<tr>

<td valign="top" >

<?php
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Delete Item");
?>

<table width="100%" height="100%">
<tr><td valign=center align=center>

<table><tr><td align=left>
<h2>Delete Item</h2>
<p>

<?php
  if($row = mysqli_fetch_assoc($result)){ 
    printItem($row, 1, "", 0, 0);
?>
<p>
<table bgcolor="indianred">
<tr><td>
<h3>Are you sure you want to delete this item?</h3>
</td><td>
<form method="post" action="deleteItem.php">
<input type="hidden" name="iid" value="<?php echo $iid ?>">
<input type="hidden" name="iso" value="<?php echo $iso ?>">
<input type="hidden" name="cid" value="<?php echo $cid ?>">
<input type="hidden" name="cname" value="<?php echo $cname ?>">
<input type="hidden" name="confirm" value="yes">
<input type="hidden" name="referrer" value="<?php echo $referrer ?>">
<input type="submit" value="Yes" style="font-weight:bold">
</form>
</td><td>
<form method="post" action="<?php echo $referrer ?>">
<input type="hidden" name="iid" value="<?php echo $iid ?>">
<input type="hidden" name="iso" value="<?php echo $iso ?>">
<input type="hidden" name="cid" value="<?php echo $cid ?>">
<input type="hidden" name="cname" value="<?php echo $cname ?>">
<input type="submit" value="Cancel" style="font-weight:bold">
</form>
</td></tr></table>
</td></tr></table>

</td></tr></table>

<?php
}
else{
  print "<h1><font color=red>That item does not belong to you!</font></h1>";
}
}
?>
