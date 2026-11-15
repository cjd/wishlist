<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$cid = (int)($_REQUEST["cid"] ?? 0);
$stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Edit Category</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Edit Category"); ?>

<center>
<p>&nbsp;</p>

<?php if ($row): ?>
<?php
  $cname = $row["name"];
  $cso = (int)$row["catSortOrder"];
  $isSpecialCategory = ($cso < 0);
  $safeLinkUrl = sanitizeHttpUrl($row["linkurl"] ?? '', false);
  $rawSubDesc = preg_replace('/<br\s*\/?>/i', "\n", (string)($row["catSubDescription"] ?? ''));
  $decodedSubDesc = html_entity_decode($rawSubDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
?>
<form method="post" action="validate_editCategory.php" id="editCategoryForm">
<input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
<input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Modify Category Heading</font></b>
</td></tr>

<tr><td colspan="2" align="center">
  <br>
  <b><?php echo escapeText($row["name"]); ?></b>
  <?php echo escapeText($row["description"] ?? ''); ?>
  <?php if ($safeLinkUrl !== ''): ?>
    <a target="_blank" rel="noopener noreferrer" href="<?php echo escapeText($safeLinkUrl); ?>"><?php echo escapeText(($row["linkname"] ?? '') !== '' ? $row["linkname"] : $safeLinkUrl); ?></a>
  <?php endif; ?>
  <p><br>
</td></tr>

<tr>
  <td align="right"><b>Category Name</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="name" value="<?php echo escapeText($row["name"]); ?>" <?php echo $isSpecialCategory ? 'readonly' : 'required'; ?>>
  </td>
</tr>
<tr>
  <td align="right"><b>Category Description</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="description" value="<?php echo escapeText($row["description"] ?? ''); ?>">
  </td>
</tr>
<tr>
  <td align="right">
    <table>
      <tr><td align="right"><b>Link Name</b></td></tr>
      <tr><td align="right"><b>URL</b></td></tr>
    </table>
  </td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="linkname" value="<?php echo escapeText($row["linkname"] ?? ''); ?>"><br>
    <input type="text" size="80" name="linkurl" value="<?php echo escapeText($row["linkurl"] ?? ''); ?>" placeholder="https://...">
  </td>
</tr>
<tr>
  <td align="right"><b>Additional Information</b></td>
  <td bgcolor="#eeeeee">
    <textarea name="catSubDescription" cols="70" rows="5"><?php echo htmlspecialchars($decodedSubDesc, ENT_QUOTES, 'UTF-8'); ?></textarea>
  </td>
</tr>

<tr><td colspan="2" bgcolor="#c0c0c0">
  <table width="100%">
  <tr>
    <td align="center" valign="center">
      <input type="submit" value="Update Category" style="font-weight:bold">
    </td>
    <?php if (!$isSpecialCategory): ?>
    <td align="center" valign="center">
      <button type="submit" form="deleteCategoryForm" style="font-weight:bold; color:red">Delete Category</button>
    </td>
    <?php endif; ?>
  </tr>
  </table>
</td></tr>
</table>
</form>

<?php if (!$isSpecialCategory): ?>
<form id="deleteCategoryForm" method="post" action="deleteCategory.php">
  <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
  <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
  <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
  <input type="hidden" name="cso" value="<?php echo (int)$cso; ?>">
  <input type="hidden" name="referrer" value="editCategory.php">
</form>
<?php endif; ?>

<?php else: ?>
  <h2 style="color:#c00;">Category not found or access denied.</h2>
<?php endif; ?>

</center>
</td></tr></table>
</body>
</html>
