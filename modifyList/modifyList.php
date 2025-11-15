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

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>
<script type="text/javascript" src="../js/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="../js/jquery.vanillabox-0.1.6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<link rel=stylesheet href="../js/theme/bitter/vanillabox.css">
<script type="text/javascript">
$(document).ready(function() {
    $('#single-image').vanillabox();
});
</script>

<head>
</head>

<title>Modify List</title>
<BODY>

<table class=pagetable>
<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|:Modify WishList");
?>

<table width="100%"><tr><td>
<font face=arial size=5 color=red><b><?php if (!array_key_exists("recip",$_REQUEST) || $_REQUEST["recip"] == "") { echo "Your "; } else { echo $_REQUEST["recip"];} ?> List</b></font> - 
<font size=-1 face=arial><b>All check marks have been removed</b></font>
</td></tr></table>

<?php

  $stmt = mysqli_prepare($link, "SELECT catSubDescription FROM categories WHERE userid = ? AND catSortOrder=-1000");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
  $mrs = mysqli_stmt_get_result($stmt);
  $mrow = mysqli_fetch_assoc($mrs);

print "<table border=0 cellpadding=2 cellspacing=0 width=100%><tr>";
if(isset($mrow["catSubDescription"]) && $mrow["catSubDescription"] != ""){

  print "<td NOWRAP bgcolor=#99ff99><form method=\"post\" action=\"editListComment.php\"><input type=\"submit\" value=\"✏️\" class=\"actionButton\"></form><img width=26px height=13px
src=\"../images/space.GIF\"></td><td width=99% bgcolor=#ffffcc> "
. $mrow["catSubDescription"];

} else{ print "<td NOWRAP width=\"100px\" bgcolor=#99ff99><form method=\"post\" action=\"editListComment.php\"><input type=\"submit\" value=\"➕\" class=\"actionButton\"></form><img width=26px height=13px
src=\"../images/space.GIF\"></td><td width=99% bgcolor=#ffffcc> Click
the link to the left to add a comment to the top of your list for
others to see"; }

print "</td></tr></table><p>";
?>

<?php 

printModifyList($userid);

?>
<table width=100%>
<tr><td colspan="3" align="center">
<form method="post" action="addCategory.php">
<input type="submit" value="Add New Category" class="buttonstyle">
</form>
</td>
</tr>
</table>

<p>

<?php
  $stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE userid = ? AND catSortOrder=-10000");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
  $mrs = mysqli_stmt_get_result($stmt);
  $mrow = mysqli_fetch_assoc($mrs);

  printCategory($mrow, $_SESSION["fullname"], 1, 0, 0, 1, 0, 0, 1);
?>

<center>
<input type="button" value="Edit Account" class="buttonstyle" onclick="location.href='../updateAccount/updateAccount.php'">
<input type="button" value="Send Update Notification" class="buttonstyle" onclick="javascript:open('../sendEmail.php?action=emailRecipViewers&recip=<?php print $_SESSION["userid"] ?>','WishList_com','height=575,width=850,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes');">
<form method="post" action="removeCrossedItems.php">
<input type="submit" value="Remove Crossed Off Items" class="buttonstyle">
</form>
</center>

</td>
</tr>
</table>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var lists = document.querySelectorAll('.sortable-list');
    lists.forEach(function (list) {
        new Sortable(list, {
            group: 'shared', // set both lists to same group
            animation: 150,
            handle: '.drag-handle',
            onAdd: function (evt) {
                var placeholder = evt.to.querySelector('.empty-category-placeholder');
                if (placeholder) {
                    placeholder.remove();
                }
            },
            onEnd: function (evt) {
                var itemEl = evt.item;
                var itemId = itemEl.dataset.itemId;
                var fromCategoryId = evt.from.dataset.categoryId;
                var toCategoryId = evt.to.dataset.categoryId;
                var newIndex = evt.newIndex;

                $.ajax({
                    url: 'updateItemOrder.php',
                    type: 'POST',
                    data: {
                        itemId: itemId,
                        fromCategoryId: fromCategoryId,
                        toCategoryId: toCategoryId,
                        newIndex: newIndex
                    },
                    success: function(response) {
                        // You can add a success message here if you want
                        console.log(response);
                    },
                    error: function() {
                        // You can add an error message here if you want
                        console.error('Error updating item order');
                    }
                });
            }
        });
    });
});
</script>
</body>
</html>
