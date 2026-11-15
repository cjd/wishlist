<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$iid = (int)($_REQUEST["iid"] ?? 0);

$stmt_item = mysqli_prepare($link, "SELECT items.*, categories.name AS catName, categories.cid AS actual_cid FROM items JOIN categories ON items.cid = categories.cid WHERE items.iid = ? AND categories.userid = ?");
mysqli_stmt_bind_param($stmt_item, "is", $iid, $userid);
mysqli_stmt_execute($stmt_item);
$item_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_item));

if (!$item_row) {
    http_response_code(403);
    die("Item not found or access denied.");
}

$cid = (int)$item_row["actual_cid"];
$iso = (int)$item_row["itemSortOrder"];
$cname = $item_row["catName"];
$csrf_token = getCsrfToken();

$existingItems = [];
$stmt_exist = mysqli_prepare($link, "SELECT items.iid, items.title, items.link1url, categories.name AS catName FROM items JOIN categories ON items.cid = categories.cid WHERE categories.userid = ? AND items.iid != ?");
mysqli_stmt_bind_param($stmt_exist, "si", $userid, $iid);
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

$rawSubdesc = preg_replace('/<br\s*\/?>/i', "\n", (string)($item_row["subdesc"] ?? ''));
$decodedSubdesc = html_entity_decode($rawSubdesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
$safeImgBase = !empty($item_row["image"]) ? basename($item_row["image"]) : '';
$imgVer = ($safeImgBase !== '' && is_file($base_dir . "/uploads/" . $safeImgBase))
    ? ('?v=' . filemtime($base_dir . "/uploads/" . $safeImgBase))
    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Edit Item</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Edit Item", false, "deleteItem"); ?>

<p>&nbsp;</p>
<center>
<form method="post" action="validate_editItem.php" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
<input type="hidden" name="MAX_FILE_SIZE" value="16777216">
<input type="hidden" name="iid" value="<?php echo (int)$iid; ?>">
<input type="hidden" name="iso" value="<?php echo (int)$iso; ?>">
<input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
<input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Edit Item from "<?php echo escapeText($cname); ?>" Category</font></b>
</td></tr>

<tr id="dupWarningRow" style="display:none;">
  <td colspan="2" bgcolor="#fffbeb" style="padding: 8px 12px; border: 2px solid #f59e0b;">
    <div style="color: #b45309; font-family: arial, sans-serif; font-size: 13px; display: flex; align-items: center; gap: 8px;">
      <span style="font-size: 18px;">⚠️</span>
      <div>
        <b>Possible Duplicate Detected:</b> You already have another item <i id="dupItemTitle"></i> in category <b id="dupItemCat"></b>.
      </div>
    </div>
  </td>
</tr>

<tr>
  <td align="right"><b>Move to Category</b></td>
  <td bgcolor="#eeeeee">
    <select name="movecid">
    <?php
    $stmt_cats = mysqli_prepare($link, "SELECT cid, name FROM categories WHERE catSortOrder != -1000 AND userid = ? ORDER BY name");
    mysqli_stmt_bind_param($stmt_cats, "s", $userid);
    mysqli_stmt_execute($stmt_cats);
    $rs_cats = mysqli_stmt_get_result($stmt_cats);
    while ($crow = mysqli_fetch_assoc($rs_cats)) {
        $sel = ((int)$crow["cid"] === $cid) ? ' selected' : '';
        echo '<option value="' . (int)$crow["cid"] . '"' . $sel . '>' . escapeText($crow["name"]) . '</option>';
    }
    ?>
    </select>
  </td>
</tr>

<tr>
  <td align="right"><b>Title</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="80" style="width:100%;box-sizing:border-box;" name="title" value="<?php echo escapeText($item_row["title"] ?? ''); ?>" required>
  </td>
</tr>
<tr>
  <td align="right"><b>Description</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="80" style="width:100%;box-sizing:border-box;" name="desc" value="<?php echo escapeText($item_row["description"] ?? ''); ?>">
  </td>
</tr>
<tr>
  <td align="right"><b>Price</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="10" name="price" value="<?php echo escapeText($item_row["price"] ?? ''); ?>">
  </td>
</tr>
<tr>
  <td align="right"><b>Quantity</b></td>
  <td bgcolor="#eeeeee">
    <input type="number" min="1" step="1" size="7" name="quantity" value="<?php echo max(1, (int)($item_row["quantity"] ?? 1)); ?>" required>
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
    <input type="text" size="50" name="link1" id="link1" value="<?php echo escapeText($item_row["link1"] ?? ''); ?>"><br>
    <input type="text" size="80" style="width:100%;box-sizing:border-box;" name="link1url" id="link1url" value="<?php echo escapeText($item_row["link1url"] ?? ''); ?>">
  </td>
</tr>

<tr>
  <td align="right"><b>Image</b></td>
  <td bgcolor="#eeeeee">
    <?php if ($safeImgBase !== ''): ?>
      <img src="<?php echo escapeText($base_url . "/uploads/" . rawurlencode($safeImgBase) . $imgVer); ?>" alt="" style="max-width:200px; height:auto; display:block; margin-bottom:6px;">
      <label style="font-size:13px;"><input type="checkbox" name="remove_image" value="1"> Remove current image</label><br><br>
    <?php endif; ?>
    <input type="file" style="width:100%;" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
  </td>
</tr>

<tr>
  <td align="right"><b>Additional<br>Information</b></td>
  <td bgcolor="#eeeeee">
    <textarea rows="3" cols="75" style="width:100%;box-sizing:border-box;" name="subdesc"><?php echo htmlspecialchars($decodedSubdesc, ENT_QUOTES, 'UTF-8'); ?></textarea>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Allow people to check this item off</b>
    <input type="checkbox" name="allowCheck" <?php echo ($item_row["allowCheck"] === "true") ? "checked" : ""; ?>>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Add a star to this item</b>
    <input type="checkbox" name="addStar" <?php echo ((string)$item_row["addStar"] === "1") ? "checked" : ""; ?>>
  </td>
</tr>

<tr><td colspan="2" bgcolor="#c0c0c0">
  <table width="100%">
  <tr>
    <td align="center" valign="center">
      <input type="submit" value="Update Item" style="font-weight:bold">
    </td>
    <td align="center" valign="center">
      <button type="submit" form="deleteItemForm" style="font-weight:bold; color:red">Delete Item</button>
    </td>
  </tr>
  </table>
</td></tr>
</table>
</form>

<form id="deleteItemForm" method="post" action="deleteItem.php">
  <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
  <input type="hidden" name="iso" value="<?php echo (int)$iso; ?>">
  <input type="hidden" name="iid" value="<?php echo (int)$iid; ?>">
  <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
  <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
  <input type="hidden" name="referrer" value="editItem.php">
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
