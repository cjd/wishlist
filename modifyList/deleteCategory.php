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

$cid = intval($_REQUEST["cid"] ?? 0);
$cname = $_REQUEST["cname"] ?? '';
$confirm = $_REQUEST["confirm"] ?? '';
$cso = intval($_REQUEST["cso"] ?? 0);
$referrer = $_REQUEST["referrer"] ?? 'modifyList.php';
// Restrict referrer to local script name to prevent open redirect
$referrer = basename($referrer);
if (empty($referrer)) {
    $referrer = "modifyList.php";
}

// check if either are null

if($confirm == "yes"){
  verifyCsrfOrDie();
  $check = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ?");
  mysqli_stmt_bind_param($check, "is", $cid, $userid);
  mysqli_stmt_execute($check);
  $res = mysqli_stmt_get_result($check);
  if (mysqli_num_rows($res) == 0) {
    die("Unauthorized: That category does not belong to you.");
  }

  $stmt = mysqli_prepare($link, "SELECT iid FROM items WHERE cid = ?");
  mysqli_stmt_bind_param($stmt, "i", $cid);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  while($row = mysqli_fetch_assoc($result)){ 
    // individually delete each item in the category
    $exit = deleteItem($row["iid"], $userid, $_SESSION["fullname"], $base_dir);

    if($exit != "Success"){
      print "An item in this category doesn't belong to you!";
      return;
    }
  }
  
  $stmt = mysqli_prepare($link, "DELETE FROM categories WHERE cid = ? AND userid = ?");
  mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
  mysqli_stmt_execute($stmt);
  
  $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder - 1 WHERE catSortOrder > ? AND userid = ?");
  mysqli_stmt_bind_param($stmt, "is", $cso, $userid);
  mysqli_stmt_execute($stmt);
  
  $stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);

  header("Location: " . getFullPath("modifyList.php"));
  exit;
  
}
else{
   $stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE cid = ? AND userid = ? ORDER BY catSortOrder");
   mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
   mysqli_stmt_execute($stmt);
   $rs = mysqli_stmt_get_result($stmt);

   if ($cat_row = mysqli_fetch_assoc($rs)) {
       $cname = $cat_row["name"];
   } else {
       die("Unauthorized: That category does not belong to you.");
   }
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

<h2>Delete <?php echo htmlspecialchars($cname); ?> Category</h2>
<p>

<?php 
  printCategory($cat_row, "", 0, 1, 0, -1, -1);
?>


<p>
<table bgcolor="violet">
<tr><td>
<h3>Are you sure you want to delete this category and all its items?</h3>
</td><td>
<form method="post" action="deleteCategory.php">
<input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
<input type="hidden" name="cid" value="<?php echo htmlspecialchars($cid); ?>">
<input type="hidden" name="cso" value="<?php echo htmlspecialchars($cso); ?>">
<input type="hidden" name="cname" value="<?php echo htmlspecialchars($cname); ?>">
<input type="hidden" name="referrer" value="<?php echo htmlspecialchars($referrer); ?>">
<input type="hidden" name="confirm" value="yes">
<input type="submit" value="Yes" style="font-weight:bold">
</form>
</td><td>
<form method="post" action="<?php echo htmlspecialchars($referrer); ?>">
<input type="hidden" name="cid" value="<?php echo htmlspecialchars($cid); ?>">
<input type="hidden" name="cso" value="<?php echo htmlspecialchars($cso); ?>">
<input type="hidden" name="cname" value="<?php echo htmlspecialchars($cname); ?>">
<input type="submit" value="No" style="font-weight:bold">
</form>
</td></tr></table>
</body>
</html>
<?php
}
?>
