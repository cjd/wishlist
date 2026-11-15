<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$target_userid = $userid;
if (!empty($_REQUEST['target_userid'])) {
    $req_u = trim((string)$_REQUEST['target_userid']);
    if ($req_u === $_SESSION['userid'] || (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1)) {
        $target_userid = $req_u;
    } else {
        $stmt_perm = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($stmt_perm, "ss", $req_u, $_SESSION['userid']);
        mysqli_stmt_execute($stmt_perm);
        $prow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_perm));
        if ($prow && $prow['allowEdit'] == '1') {
            $target_userid = $req_u;
        }
    }
}

$stmt = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $target_userid);
mysqli_stmt_execute($stmt);
$rs = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?? [];

$back_url = ($target_userid !== $_SESSION['userid'])
    ? "updateAccount.php?target_userid=" . urlencode($target_userid)
    : "updateAccount.php";
$error_code = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Edit Personal Information</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|" . $back_url . ":Update Account|:Edit Personal Info", false, "changeInfo"); ?>
<center>
<p>&nbsp;</p>

<?php if ($error_code === 'invalid_birthday'): ?>
  <p style="color:#c00; font-weight:bold;">Please select a valid calendar date for the birthday (or leave both Month and Day blank).</p>
<?php elseif ($error_code === 'missing_name'): ?>
  <p style="color:#c00; font-weight:bold;">First Name and Last Name are required.</p>
<?php endif; ?>

<form method="post" action="validate_editInfo.php">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
<input type="hidden" name="target_userid" value="<?php echo escapeText($target_userid); ?>">
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Information on File</font></b>
</td></tr>

<tr><td class="lightYellow" align="right"><b>First Name</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="firstname" value="<?php echo escapeText($rs['firstname'] ?? ''); ?>" required>
</td></tr>

<tr><td class="lightYellow" align="right"><b>Last Name</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="lastname" value="<?php echo escapeText($rs['lastname'] ?? ''); ?>" required>
</td></tr>

<tr><td class="lightYellow" align="right"><b>Suffix</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="suffix" value="<?php echo escapeText($rs['suffix'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Email</b></td>
<td bgcolor="#eeeeee">
  <input type="email" size="30" name="email" value="<?php echo escapeText($rs['email'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Street</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="street" value="<?php echo escapeText($rs['street'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>City</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="city" value="<?php echo escapeText($rs['city'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>State</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="30" name="state" value="<?php echo escapeText($rs['state'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Post Code</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="12" maxlength="20" name="zip" value="<?php echo escapeText((($rs['zip'] ?? '') !== '0') ? ($rs['zip'] ?? '') : ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Home Phone</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="20" name="phone" value="<?php echo escapeText($rs['phone'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Mobile Phone</b></td>
<td bgcolor="#eeeeee">
  <input type="text" size="20" name="mobilephone" value="<?php echo escapeText($rs['mobilephone'] ?? ''); ?>">
</td></tr>

<tr><td class="lightYellow" align="right"><b>Birthday</b></td>
<td bgcolor="#eeeeee">
  <select name="bmonth" size="1">
    <option value="">-- Month --</option>
    <?php
    $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    $curMonth = $rs['bmonth'] ?? '';
    foreach ($months as $m) {
        $sel = ($m === $curMonth) ? ' selected' : '';
        echo '<option value="' . escapeText($m) . '"' . $sel . '>' . escapeText($m) . '</option>';
    }
    ?>
  </select>

  <select name="bday" size="1">
    <option value="">-- Day --</option>
    <?php
    $curDay = !empty($rs['bday']) ? (int)$rs['bday'] : 0;
    for ($i = 1; $i <= 31; $i++) {
        $sel = ($i === $curDay) ? ' selected' : '';
        echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
    }
    ?>
  </select>
</td></tr>

<tr><td class="lightYellow" align="right"><b>HomePage URL</b></td>
<td bgcolor="#eeeeee">
  <input type="text" name="url" size="30" value="<?php echo escapeText($rs['url'] ?? ''); ?>" placeholder="https://...">
</td></tr>

<tr><td colspan="2" align="center" bgcolor="#c0c0c0">
  <input type="submit" value="Update" class="buttonstyle">
</td></tr>
</table>
<br>
</form>
</center>
</td></tr></table>
</body>
</html>
