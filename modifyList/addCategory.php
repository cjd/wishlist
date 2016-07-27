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

<BODY>
<table class=pagetable>
<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Category");
?>

<center>
<p>&nbsp;
<p>&nbsp;

<form method="post" action="validate_addCategory.php">

<table style="border-collapse: collapse;" id="AutoNumber1" border="1" bordercolor="#11111" cellpadding="2" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc"> <b><font size=3 color="#ffffff">
<b>Add New Category</b>
</font></td></tr>


<tr><td align="right">
<b>Category Name</b>
</td><td bgcolor="#eeeeee">
<input type="text" size="30" name="cname" value="">
</td></tr>
<tr><td align="right">  
<b>Category Description</b>
</td><td bgcolor="#eeeeee">
<input type="text" size="30" name="description" value="">
</td></tr>
<tr><td align="right">
  <table>
    <tr><td align="right">
      <b>Link Name</b>
    </tr>
    <tr>
    <td  align="right">
      <b>URL</b>
    </td></tr>
  </table
</td><td bgcolor="#eeeeee">
<input type="text" size="30" name="linkname" value="">
<br>
<input type="text" size="30" name="linkurl" value="">
</td></tr>
<tr><td align="right">
<b>Additional Information</b>
</td>
<td bgcolor="#eeeeee">
<textarea name="catSubDescription" cols="35" rows="5" style="font-family: sans-serif;"></textarea>
</td></tr>
<tr><td colspan="2" bgcolor="#c0c0c0" align=center>
<input type="submit" value="Add Category" class="buttonstyle">
</td></tr></table>

</form>

</center>
</td></tr></table>
</body>
</html>
