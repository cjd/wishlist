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

$cid = mysqli_escape_string($link,$_REQUEST["cid"]);
$cname = $_REQUEST["cname"];
$confirm = mysqli_escape_string($link,$_REQUEST["confirm"]);
$cso = mysqli_escape_string($link,$_REQUEST["cso"]);
$referrer = mysqli_escape_string($link,$_REQUEST["referrer"]);

// check if either are null

if($confirm == "yes"){
  $query = "select iid from items where cid=" . $cid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  while($row = mysqli_fetch_assoc($result)){ 
    // individually delete each item in the category
    $exit = deleteItem($row["iid"], $userid, $_SESSION["fullname"]);

    if($exit != "Success"){
      print "An item in this category doesn't belong to you!";
      return;
    }
  }
  
  $query = "delete from categories where cid=" . $cid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
  $query = "update categories set catSortOrder = catSortOrder - 1 where catSortOrder > " . $cso . " and userid='" . $userid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
  header("Location: " . getFullPath("modifyList.php"));
  
  $query = "update people set lastModDate=NOW() where userid='" . $userid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
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
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Delete Category");
?>

<h2>Delete <?php echo str_replace("\\", "", $cname); ?> Category</h2>
<p>

<?php 
   $query = "select * from categories where cid=" . $cid . " order by catSortOrder";
  
  $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  while($row = mysqli_fetch_assoc($rs)){
    printCategory($row, "", 1, 0, 1, 0, -1, -1);
  }

?>


<p>
<table bgcolor="violet">
<tr><td>
<h3>Are you sure you want to delete this category and all its items?</h3>
</td><td>
<form method="post" action="deleteCategory.php">
<input type="hidden" name="cid" value="<?php echo $cid ?>">
<input type="hidden" name="cso" value="<?php echo $cso ?>">
<input type="hidden" name="cname" value="<?php echo $cname ?>">
<input type="hidden" name="referrer" value="<?php echo $referrer ?>">
<input type="hidden" name="confirm" value="yes">
<input type="submit" value="Yes" style="font-weight:bold">
</form>
</td><td>
<form method="post" action="<?php echo $referrer ?>">
<input type="hidden" name="cid" value="<?php echo $cid ?>">
<input type="hidden" name="cso" value="<?php echo $cso ?>">
<input type="hidden" name="cname" value="<?php echo $cname ?>">
<input type="submit" value="No" style="font-weight:bold">
</form>
</td></tr></table>
</body>
</html>
<?php
}
?>
