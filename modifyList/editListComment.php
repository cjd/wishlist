<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST["delete"])) {
        verifyCsrfOrDie(true);
        $stmt = mysqli_prepare($link, "UPDATE categories SET catSubDescription = '' WHERE userid = ? AND catSortOrder = -1000");
        mysqli_stmt_bind_param($stmt, "s", $userid);
        mysqli_stmt_execute($stmt);

        $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_m, "s", $userid);
        mysqli_stmt_execute($stmt_m);

        header("Location: " . getFullPath("modifyList.php"));
        exit;
    }

    if (!empty($_POST["update"])) {
        verifyCsrfOrDie(true);
        $catSubDescription = trim((string)($_POST["catSubDescription"] ?? ''));
        $stmt = mysqli_prepare($link, "UPDATE categories SET catSubDescription = ? WHERE userid = ? AND catSortOrder = -1000");
        mysqli_stmt_bind_param($stmt, "ss", $catSubDescription, $userid);
        mysqli_stmt_execute($stmt);

        $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_m, "s", $userid);
        mysqli_stmt_execute($stmt_m);

        header("Location: " . getFullPath("modifyList.php"));
        exit;
    }

    if (!empty($_POST["add"])) {
        verifyCsrfOrDie(true);
        $catSubDescription = trim((string)($_POST["catSubDescription"] ?? ''));
        // Ensure we don't insert duplicate -1000 rows if one already exists
        $stmt_chk = mysqli_prepare($link, "SELECT cid FROM categories WHERE userid = ? AND catSortOrder = -1000");
        mysqli_stmt_bind_param($stmt_chk, "s", $userid);
        mysqli_stmt_execute($stmt_chk);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk))) {
            $stmt = mysqli_prepare($link, "UPDATE categories SET catSubDescription = ? WHERE userid = ? AND catSortOrder = -1000");
            mysqli_stmt_bind_param($stmt, "ss", $catSubDescription, $userid);
        } else {
            $stmt = mysqli_prepare($link, "INSERT INTO categories (userid, catSortOrder, name, description, linkname, linkurl, catSubDescription) VALUES (?, -1000, '', '', '', '', ?)");
            mysqli_stmt_bind_param($stmt, "ss", $userid, $catSubDescription);
        }
        mysqli_stmt_execute($stmt);

        $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_m, "s", $userid);
        mysqli_stmt_execute($stmt_m);

        header("Location: " . getFullPath("modifyList.php"));
        exit;
    }
}

$stmt = mysqli_prepare($link, "SELECT catSubDescription FROM categories WHERE userid = ? AND catSortOrder = -1000");
mysqli_stmt_bind_param($stmt, "s", $userid);
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
  <title>Edit List Comment</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Edit List Comment"); ?>

<center>
<p>&nbsp;</p>

<form method="post" action="editListComment.php">
<input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Edit List Comment</font></b>
</td></tr>

<?php if ($row): ?>
  <?php
    $rawText = preg_replace('/<br\s*\/?>/i', "\n", (string)($row["catSubDescription"] ?? ''));
    $decodedText = html_entity_decode($rawText, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  ?>
  <?php if (trim($decodedText) !== ''): ?>
  <tr><td colspan="2" align="center">
    <br>
    <?php echo formatMultilineText($row["catSubDescription"]); ?>
    <br>&nbsp;
  </td></tr>
  <?php endif; ?>

  <tr>
    <td align="right"><b>List Comment</b></td>
    <td bgcolor="#eeeeee">
      <textarea name="catSubDescription" cols="70" rows="5"><?php echo htmlspecialchars($decodedText, ENT_QUOTES, 'UTF-8'); ?></textarea>
    </td>
  </tr>
  <tr><td colspan="2" bgcolor="#c0c0c0">
    <table width="100%">
    <tr>
      <td align="center" valign="center">
        <input type="submit" name="update" value="Update List Comment" style="font-weight:bold">
      </td>
      <td align="center" valign="center">
        <input type="submit" name="delete" value="Delete List Comment" style="font-weight:bold; color:red">
      </td>
    </tr>
    </table>
  </td></tr>
<?php else: ?>
  <tr>
    <td align="right"><b>List Comment</b></td>
    <td bgcolor="#eeeeee">
      <textarea name="catSubDescription" cols="70" rows="5"></textarea>
    </td>
  </tr>
  <tr><td colspan="2" bgcolor="#c0c0c0" align="center">
    <input type="submit" name="add" value="Add List Comment" style="font-weight:bold">
  </td></tr>
<?php endif; ?>
</table>
</form>

</center>
</td></tr></table>
</body>
</html>
