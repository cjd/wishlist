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
  <link rel="stylesheet" href="../style.css" type="text/css">
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
createNavBar("../home.php:Home|" . $back_account_url . ":Update Account|:Change Password for " . $target_user, false, "password");
?>
<center>
<?php if ($success): ?>
  <p><h2>Password Successfully Changed</h2></p>
  <p><b><a href="<?php echo escapeText($back_account_url); ?>">Back to Account</a></b></p>
<?php else: ?>
  <?php if ($error_msg !== ''): ?>
    <p style="color:#c00; font-weight:bold;"><?php echo escapeText($error_msg); ?></p>
  <?php endif; ?>
  <form name="theForm" method="post" action="changePassword.php" onsubmit="return validate(this);">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <input type="hidden" name="userid" value="<?php echo escapeText($target_user); ?>">
    <p><b>Changing password for: <code><?php echo escapeText($target_user); ?></code></b></p>

    <table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
      <tr>
        <td colspan="2" align="center" bgcolor="#6702cc">
          <font size="3" color="white"><b>Enter New Password</b></font>
        </td>
      </tr>
      <?php if ($require_current_password): ?>
      <tr>
        <td align="right"><b>Current Password</b></td>
        <td><input type="password" name="current_password" autocomplete="current-password" required></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td align="right"><b>New Password (min 6 chars)</b></td>
        <td><input type="password" name="password" minlength="6" autocomplete="new-password" required></td>
      </tr>
      <tr>
        <td align="right"><b>Re-enter New Password</b></td>
        <td><input type="password" name="challenge" minlength="6" autocomplete="new-password" required></td>
      </tr>
      <tr>
        <td align="center" colspan="2" bgcolor="#c0c0c0">
          <input type="submit" value="Submit" style="font-weight:bold">
        </td>
      </tr>
    </table>
  </form>
<?php endif; ?>
</center>
</td>
</tr>
</table>
</body>
</html>
