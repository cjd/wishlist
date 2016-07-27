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

$cid = $_REQUEST["cid"];
$cname = $_REQUEST["cname"];
if (get_magic_quotes_gpc()==1) {
   $cname = stripslashes($cname);
}

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<title>Add New Item</title>

<BODY>

<table class=pagetable>
<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Item");
?>

<center>
<p>&nbsp;
<form method="post" name="theForm" action="validate_addItem.php" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="10000000" />
<input type="hidden" size="30" name="cid" value="<?php echo $cid; ?>">

<table style="border-collapse: collapse;" id="AutoNumber1" border="1" bordercolor="#11111" cellpadding="2" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc"> <b><font size=3 color="#ffffff">
Add new item to "<?php echo $cname; ?>" category
</td></tr>

  <tr><td align="right">
    <b>Title</b>
  </td><td bgcolor="#eeeeee">
    <input type="text" style="width:100%;" name="title" value="">
  </td></tr>
  <tr><td align="right">
    <b>Desc.</b>
  </td><td bgcolor="#eeeeee">
    <input type="text" style="width:100%;" name="desc" value="">
  </td></tr>
  <tr><td align="right">
    <b>Price</b>
  </td><td bgcolor="#eeeeee">
    <input type="text" style="width:50%;" name="price" value="">
  </td></tr>
  <tr><td align="right">
    <b>Quantity</b>
  </td><td bgcolor="#eeeeee">
    <input type="text" style="width:50%;" name="quantity" value="1">
  </td></tr>
  <tr><td align="right">
    <b>Image</b>
  </td><td bgcolor="#eeeeee">
    <input type="file" style="width:100%;" name="image" value="">
  </td></tr>
  <tr><td align="right">
    <table>
      <tr>
        <td align=right>
          <b>URL</b>
        </td>
      </tr>
    </table>
  </td><td bgcolor="#eeeeee">
    <table width=100%>
      <tr><td>
        <input type="text" style="width:100%;" name="link1" value="link" hidden>
        <input type="text" style="width:100%;" name="link1url" value="">
      </td>
      </tr>
    </table>
  </td></tr>


  <tr><td align="right">
    <b>Extra<br>Info</b>
  </td><td bgcolor="#eeeeee">
    <textarea ROWS='3' COLS='20' name="subdesc" style="font-family: sans;width:100%;"></textarea>
  </td></tr>

  <tr><td align="center" colspan="2">
    <b>Allow people to check this item off</b>
    <input type="checkbox" name="allowCheck" checked>
  </td></tr>
  <tr><td align="center" colspan="2">
    <b>Add a star to this item</b>
    <input type="checkbox" name="addStar">
  </td></tr>

<tr><td colspan="2" bgcolor="#c0c0c0" align=center>
<input type="submit" value="Add" class="buttonstyle">
</td></tr></table>

</form>

</td></tr></table>
</body>
</html>
