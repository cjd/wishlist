<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$is_admin = (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1);
$requested_user = trim($_REQUEST['userid'] ?? '');

if ($requested_user !== '' && $requested_user !== $_SESSION['userid']) {
    if (!$is_admin) {
        http_response_code(403);
        die("Unauthorized: Only administrators can change another user's password.");
    }
    $target_user = $requested_user;
} elseif ($is_admin && !empty($_SESSION['euserid']) && $requested_user === '') {
    $target_user = $_SESSION['euserid'];
} else {
    $target_user = $_SESSION['userid'];
}

$require_current_password = ($target_user === $_SESSION['userid'] || !$is_admin);
$back_account_url = ($target_user !== $_SESSION['userid'])
    ? "updateAccount.php?target_userid=" . urlencode($target_user)
    : "updateAccount.php";

$error_msg = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    verifyCsrfOrDie(true);

    $new_pass = (string)($_POST['password'] ?? '');
    $confirm_pass = (string)($_POST['challenge'] ?? '');
    $current_pass = (string)($_POST['current_password'] ?? '');

    if ($new_pass !== $confirm_pass) {
        $error_msg = "Passwords do not match.";
    } elseif (strlen($new_pass) < 6 || trim($new_pass) === '') {
        $error_msg = "New password must be at least 6 characters and cannot be blank.";
    } else {
        $stmt_cur = mysqli_prepare($link, "SELECT password FROM people WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_cur, "s", $target_user);
        mysqli_stmt_execute($stmt_cur);
        $user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cur));

        if (!$user_row) {
            $error_msg = "Account not found.";
        } else {
            $current_ok = true;
            if ($require_current_password) {
                $stored = $user_row['password'];
                $current_ok = password_verify($current_pass, $stored)
                    || (strlen($stored) === 32 && ctype_xdigit($stored) && hash_equals(strtolower($stored), md5($current_pass)));
            }

            if (!$current_ok) {
                $error_msg = "Your current password is incorrect.";
            } else {
                $password_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($link, "UPDATE people SET password = ?, lastModDate = NOW() WHERE userid = ?");
                mysqli_stmt_bind_param($stmt, "ss", $password_hash, $target_user);
                mysqli_stmt_execute($stmt);
                $success = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Change Password</title>
  <script>
  function validate(form) {
      if (form.password.value.length < 6 || form.password.value.trim() === '') {
          alert("New password must be at least 6 characters.");
          return false;
      }
      if (form.password.value !== form.challenge.value) {
          alert("Passwords don't match.");
          return false;
      }
      return true;
  }
  </script>
</head>
<body onload="if(document.theForm && document.theForm.<?php echo $require_current_password ? 'current_password' : 'password'; ?>) document.theForm.<?php echo $require_current_password ? 'current_password' : 'password'; ?>.focus();">
<table class="pagetable">
<tr>
<td valign="top">
<?php
createNavBar("../home.php:Home|" . $back_account_url . ":Update Account|:Change Password for " . $target_user, true, "password");
?>
<div class="admin-dashboard">
  <?php if ($success): ?>
    <div class="admin-alert admin-alert-success">
      Password successfully changed for <code><?php echo escapeText($target_user); ?></code>.
    </div>
    <div style="text-align:center; margin-top:12px;">
      <a href="<?php echo escapeText($back_account_url); ?>" class="admin-nav-btn admin-nav-btn-primary">← Back to Account</a>
      <?php if ($is_admin && $target_user !== $_SESSION['userid']): ?>
        <a href="../admin.php" class="admin-nav-btn">← Back to Admin</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php if ($error_msg !== ''): ?>
      <div class="admin-alert admin-alert-error"><?php echo escapeText($error_msg); ?></div>
    <?php endif; ?>
    <form name="theForm" method="post" action="changePassword.php" onsubmit="return validate(this);" class="admin-modal-card">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
      <input type="hidden" name="userid" value="<?php echo escapeText($target_user); ?>">

      <div class="admin-modal-card-header">
        <span>Change Password</span>
        <span class="admin-family-count"><?php echo escapeText($target_user); ?></span>
      </div>

      <div class="admin-modal-card-body">
        <?php if ($require_current_password): ?>
          <div class="admin-form-row">
            <label for="cp_current">Current Password *</label>
            <input type="password" id="cp_current" name="current_password" autocomplete="current-password" required>
          </div>
        <?php endif; ?>
        <div class="admin-form-row">
          <label for="cp_new">New Password (min 6 chars) *</label>
          <input type="password" id="cp_new" name="password" minlength="6" autocomplete="new-password" required>
        </div>
        <div class="admin-form-row" style="margin-bottom:0;">
          <label for="cp_confirm">Re-enter New Password *</label>
          <input type="password" id="cp_confirm" name="challenge" minlength="6" autocomplete="new-password" required>
        </div>
      </div>

      <div class="admin-modal-card-footer">
        <a href="<?php echo escapeText($back_account_url); ?>" class="admin-nav-btn">Cancel</a>
        <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Update Password</button>
      </div>
    </form>
  <?php endif; ?>
</div>
</td>
</tr>
</table>
</body>
</html>
