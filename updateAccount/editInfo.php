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
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Edit Personal Information</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|" . $back_url . ":Update Account|:Edit Personal Info", true, "changeInfo"); ?>

<div class="admin-dashboard">

  <?php if ($error_code === 'invalid_birthday'): ?>
    <div class="admin-alert admin-alert-error">Please select a valid calendar date for the birthday (or leave both Month and Day blank).</div>
  <?php elseif ($error_code === 'missing_name'): ?>
    <div class="admin-alert admin-alert-error">First Name and Last Name are required.</div>
  <?php endif; ?>

  <form method="post" action="validate_editInfo.php" class="admin-modal-card admin-modal-card-wide">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <input type="hidden" name="target_userid" value="<?php echo escapeText($target_userid); ?>">

    <div class="admin-modal-card-header">
      <span>Edit Personal Information</span>
      <span class="admin-family-count"><?php echo escapeText($target_userid); ?></span>
    </div>

    <div class="admin-modal-card-body">
      <div class="account-form-grid-2">
        <div class="admin-form-row">
          <label for="ei_firstname">First Name *</label>
          <input type="text" id="ei_firstname" name="firstname" value="<?php echo escapeText($rs['firstname'] ?? ''); ?>" required>
        </div>
        <div class="admin-form-row">
          <label for="ei_lastname">Last Name *</label>
          <input type="text" id="ei_lastname" name="lastname" value="<?php echo escapeText($rs['lastname'] ?? ''); ?>" required>
        </div>
      </div>

      <div class="account-form-grid-2">
        <div class="admin-form-row">
          <label for="ei_suffix">Suffix</label>
          <input type="text" id="ei_suffix" name="suffix" value="<?php echo escapeText($rs['suffix'] ?? ''); ?>" placeholder="Jr., III, etc.">
        </div>
        <div class="admin-form-row">
          <label for="ei_email">Email</label>
          <input type="email" id="ei_email" name="email" value="<?php echo escapeText($rs['email'] ?? ''); ?>">
        </div>
      </div>

      <div class="admin-form-row">
        <label for="ei_street">Street Address</label>
        <input type="text" id="ei_street" name="street" value="<?php echo escapeText($rs['street'] ?? ''); ?>">
      </div>

      <div class="account-form-grid-2">
        <div class="admin-form-row">
          <label for="ei_city">City / Suburb</label>
          <input type="text" id="ei_city" name="city" value="<?php echo escapeText($rs['city'] ?? ''); ?>">
        </div>
        <div class="admin-form-row">
          <label for="ei_state">State</label>
          <input type="text" id="ei_state" name="state" value="<?php echo escapeText($rs['state'] ?? ''); ?>">
        </div>
      </div>

      <div class="account-form-grid-2">
        <div class="admin-form-row">
          <label for="ei_zip">Post Code</label>
          <input type="text" id="ei_zip" maxlength="20" name="zip" value="<?php echo escapeText((($rs['zip'] ?? '') !== '0') ? ($rs['zip'] ?? '') : ''); ?>">
        </div>
        <div class="admin-form-row">
          <label>Birthday</label>
          <div class="account-bday-selects">
            <select name="bmonth" aria-label="Birth Month">
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
            <select name="bday" aria-label="Birth Day">
              <option value="">-- Day --</option>
              <?php
              $curDay = !empty($rs['bday']) ? (int)$rs['bday'] : 0;
              for ($i = 1; $i <= 31; $i++) {
                  $sel = ($i === $curDay) ? ' selected' : '';
                  echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
              }
              ?>
            </select>
          </div>
        </div>
      </div>

      <div class="account-form-grid-2">
        <div class="admin-form-row">
          <label for="ei_phone">Home Phone</label>
          <input type="text" id="ei_phone" name="phone" value="<?php echo escapeText($rs['phone'] ?? ''); ?>">
        </div>
        <div class="admin-form-row">
          <label for="ei_mobilephone">Mobile Phone</label>
          <input type="text" id="ei_mobilephone" name="mobilephone" value="<?php echo escapeText($rs['mobilephone'] ?? ''); ?>">
        </div>
      </div>

      <div class="admin-form-row" style="margin-bottom:0;">
        <label for="ei_url">Homepage URL</label>
        <input type="text" id="ei_url" name="url" value="<?php echo escapeText($rs['url'] ?? ''); ?>" placeholder="https://...">
      </div>
    </div>

    <div class="admin-modal-card-footer">
      <a href="<?php echo escapeText($back_url); ?>" class="admin-nav-btn">Cancel</a>
      <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Save Changes</button>
    </div>
  </form>

</div>
</td></tr></table>
</body>
</html>
