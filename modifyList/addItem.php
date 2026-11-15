<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$cid = (int)($_REQUEST["cid"] ?? 0);
$stmt = mysqli_prepare($link, "SELECT name FROM categories WHERE cid = ? AND userid = ? AND catSortOrder != -1000");
mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($res)) {
    $cname = $row["name"];
} else {
    http_response_code(403);
    die("Category not found or access denied.");
}

$existingItems = [];
$stmt_exist = mysqli_prepare($link, "SELECT items.iid, items.title, items.link1url, categories.name AS catName FROM items JOIN categories ON items.cid = categories.cid WHERE categories.userid = ?");
mysqli_stmt_bind_param($stmt_exist, "s", $userid);
mysqli_stmt_execute($stmt_exist);
$res_exist = mysqli_stmt_get_result($stmt_exist);
while ($row_exist = mysqli_fetch_assoc($res_exist)) {
    $existingItems[] = [
        'iid' => (int)$row_exist['iid'],
        'title' => html_entity_decode((string)$row_exist['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'url' => html_entity_decode((string)($row_exist['link1url'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        'cat' => html_entity_decode((string)$row_exist['catName'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Add New Item</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Item"); ?>

<center>
<p>&nbsp;</p>
<form method="post" name="theForm" action="validate_addItem.php" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
<input type="hidden" name="MAX_FILE_SIZE" value="16777216">
<input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Add new item to "<?php echo escapeText($cname); ?>" category</font></b>
</td></tr>

<tr id="dupWarningRow" style="display:none;">
  <td colspan="2" bgcolor="#fffbeb" style="padding: 8px 12px; border: 2px solid #f59e0b;">
    <div style="color: #b45309; font-family: arial, sans-serif; font-size: 13px; display: flex; align-items: center; gap: 8px;">
      <span style="font-size: 18px;">⚠️</span>
      <div>
        <b>Possible Duplicate Detected:</b> You already have an item <i id="dupItemTitle"></i> in category <b id="dupItemCat"></b>.
      </div>
    </div>
  </td>
</tr>

<tr>
  <td align="right"><b>Title</b></td>
  <td bgcolor="#eeeeee"><input type="text" style="width:100%;" name="title" value="" required></td>
</tr>
<tr>
  <td align="right"><b>Desc.</b></td>
  <td bgcolor="#eeeeee"><input type="text" style="width:100%;" name="desc" value=""></td>
</tr>
<tr>
  <td align="right"><b>Price</b></td>
  <td bgcolor="#eeeeee"><input type="text" style="width:50%;" name="price" value="" placeholder="0.00"></td>
</tr>
<tr>
  <td align="right"><b>Quantity</b></td>
  <td bgcolor="#eeeeee"><input type="number" min="1" step="1" style="width:50%;" name="quantity" value="1" required></td>
</tr>
<tr>
  <td align="right"><b>Image</b></td>
  <td bgcolor="#eeeeee"><input type="file" style="width:100%;" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></td>
</tr>
<tr>
  <td align="right">
    <table>
      <tr><td align="right"><b>Store / Name</b></td></tr>
      <tr><td align="right"><b>URL</b></td></tr>
    </table>
  </td>
  <td bgcolor="#eeeeee">
    <table width="100%">
      <tr><td>
        <input type="text" style="width:100%;" name="link1" id="link1" placeholder="Auto-detected from URL, or type store name" value="">
      </td></tr>
      <tr><td>
        <input type="text" style="width:100%;" name="link1url" id="link1url" placeholder="Paste item link (https://...)" value="">
      </td></tr>
    </table>
  </td>
</tr>
<tr>
  <td align="right"><b>Extra<br>Info</b></td>
  <td bgcolor="#eeeeee">
    <textarea rows="3" cols="20" name="subdesc" style="font-family: sans-serif; width:100%;"></textarea>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Allow people to check this item off</b>
    <input type="checkbox" name="allowCheck" checked>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Add a star to this item</b>
    <input type="checkbox" name="addStar">
  </td>
</tr>
<tr>
  <td colspan="2" bgcolor="#c0c0c0" align="center">
    <input type="submit" value="Add" class="buttonstyle">
  </td>
</tr>
</table>
</form>
</center>
</td></tr></table>

<script>
window.existingWishlistItems = <?php echo json_encode($existingItems, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script src="../js/store-detector.js"></script>
<script src="../js/duplicate-detector.js"></script>
</body>
</html>
