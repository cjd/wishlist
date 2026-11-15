<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Add New Category</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Category"); ?>

<center>
<p>&nbsp;</p>

<form method="post" action="validate_addCategory.php">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Add New Category</font></b>
</td></tr>

<tr>
  <td align="right"><b>Category Name</b></td>
  <td bgcolor="#eeeeee"><input type="text" size="40" name="cname" value="" required></td>
</tr>
<tr>
  <td align="right"><b>Category Description</b></td>
  <td bgcolor="#eeeeee"><input type="text" size="40" name="description" value=""></td>
</tr>
<tr>
  <td align="right">
    <table>
      <tr><td align="right"><b>Link Name</b></td></tr>
      <tr><td align="right"><b>URL</b></td></tr>
    </table>
  </td>
  <td bgcolor="#eeeeee">
    <input type="text" size="40" name="linkname" value=""><br>
    <input type="text" size="50" name="linkurl" value="" placeholder="https://...">
  </td>
</tr>
<tr>
  <td align="right"><b>Additional Information</b></td>
  <td bgcolor="#eeeeee">
    <textarea name="catSubDescription" cols="50" rows="5" style="font-family: sans-serif;"></textarea>
  </td>
</tr>
<tr>
  <td colspan="2" bgcolor="#c0c0c0" align="center">
    <input type="submit" value="Add Category" class="buttonstyle">
  </td>
</tr>
</table>
</form>

</center>
</td></tr></table>
</body>
</html>
