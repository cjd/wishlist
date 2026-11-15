<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$cid = (int)($_REQUEST["cid"] ?? 0);
$confirm = $_REQUEST["confirm"] ?? '';
$rawReferrer = basename((string)($_REQUEST["referrer"] ?? 'modifyList.php'));
$referrer = in_array($rawReferrer, ["modifyList.php", "editCategory.php"], true)
    ? $rawReferrer
    : "modifyList.php";

$stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE cid = ? AND userid = ?");
mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
mysqli_stmt_execute($stmt);
$cat_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$cat_row) {
    http_response_code(403);
    die("Unauthorized: That category does not belong to you.");
}

$cname = $cat_row["name"];
$actual_cso = (int)$cat_row["catSortOrder"];

if ($actual_cso < 0) {
    http_response_code(400);
    die("Special built-in categories cannot be deleted.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $confirm === "yes") {
    verifyCsrfOrDie(true);

    $stmt_items = mysqli_prepare($link, "SELECT iid FROM items WHERE cid = ?");
    mysqli_stmt_bind_param($stmt_items, "i", $cid);
    mysqli_stmt_execute($stmt_items);
    $result = mysqli_stmt_get_result($stmt_items);

    while ($row = mysqli_fetch_assoc($result)) {
        $exit = deleteItem((int)$row["iid"], $userid, $activeOwnerFullname, $base_dir);
        if ($exit !== "Success") {
            http_response_code(403);
            die("An item in this category doesn't belong to you.");
        }
    }

    mysqli_begin_transaction($link);
    try {
        $stmt_del = mysqli_prepare($link, "DELETE FROM categories WHERE cid = ? AND userid = ? AND catSortOrder >= 0");
        mysqli_stmt_bind_param($stmt_del, "is", $cid, $userid);
        mysqli_stmt_execute($stmt_del);

        $stmt_shift = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder - 1 WHERE catSortOrder > ? AND userid = ? AND catSortOrder >= 0");
        mysqli_stmt_bind_param($stmt_shift, "is", $actual_cso, $userid);
        mysqli_stmt_execute($stmt_shift);

        $stmt_mod = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_mod, "s", $userid);
        mysqli_stmt_execute($stmt_mod);

        mysqli_commit($link);
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($link);
        error_log("deleteCategory failed: " . $e->getMessage());
    }

    header("Location: " . getFullPath("modifyList.php"));
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Delete Category</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Delete Category"); ?>

<h2>Delete "<?php echo escapeText($cname); ?>" Category</h2>
<p>
<?php printCategory($cat_row, $activeOwnerFullname, 0, 1, 0, -1, -1); ?>
<p>
<table bgcolor="violet" cellpadding="6">
<tr>
  <td><h3>Are you sure you want to delete this category and all its items?</h3></td>
  <td>
    <form method="post" action="deleteCategory.php" style="margin:0;">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
      <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
      <input type="hidden" name="cso" value="<?php echo (int)$actual_cso; ?>">
      <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
      <input type="hidden" name="referrer" value="<?php echo escapeText($referrer); ?>">
      <input type="hidden" name="confirm" value="yes">
      <input type="submit" value="Yes" style="font-weight:bold">
    </form>
  </td>
  <td>
    <form method="get" action="<?php echo escapeText($referrer); ?>" style="margin:0;">
      <input type="hidden" name="cid" value="<?php echo (int)$cid; ?>">
      <input type="hidden" name="cso" value="<?php echo (int)$actual_cso; ?>">
      <input type="hidden" name="cname" value="<?php echo escapeText($cname); ?>">
      <input type="submit" value="No" style="font-weight:bold">
    </form>
  </td>
</tr>
</table>
</td></tr></table>
</body>
</html>
