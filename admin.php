<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

$config_exists = file_exists(__DIR__ . "/config.php");

if ($config_exists) {
  include __DIR__ . "/funcLib.php";
  if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    http_response_code(403);
    die("Unauthorized access.");
  }
  if (isset($_REQUEST['setup'])) {
    createConfig();
    exit;
  }
} else {
  // First-time setup when config.php does not exist yet
  createConfig();
  exit;
}

$action = $_REQUEST["action"] ?? "";
$message = "<h2>Admin Page</h2>";

if ($action === "commitDelete") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);
  $delUserid = trim($_POST["deleteUserid"] ?? $_POST["userid"] ?? "");
  if (($_POST["confirm"] ?? '') === "Yes" && $delUserid !== "") {
    if ($delUserid === $_SESSION['userid']) {
      $message = "<h2 style='color:#c00;'>Error: You cannot delete your own account while logged in.</h2>";
    } else {
      // Check if target is the last remaining admin
      $stmt_chk = mysqli_prepare($link, "SELECT admin FROM people WHERE userid = ?");
      mysqli_stmt_bind_param($stmt_chk, "s", $delUserid);
      mysqli_stmt_execute($stmt_chk);
      $res_chk = mysqli_stmt_get_result($stmt_chk);
      $target_row = mysqli_fetch_assoc($res_chk);

      if (!$target_row) {
        $message = "<h2 style='color:#c00;'>Error: User not found.</h2>";
      } else {
        $can_delete = true;
        if ((int)$target_row['admin'] === 1) {
          $res_admins = mysqli_query($link, "SELECT COUNT(*) AS cnt FROM people WHERE admin = 1");
          $admin_cnt = (int)(mysqli_fetch_assoc($res_admins)['cnt'] ?? 0);
          if ($admin_cnt <= 1) {
            $can_delete = false;
            $message = "<h2 style='color:#c00;'>Error: Cannot delete the last administrator account.</h2>";
          }
        }

        if ($can_delete) {
          // Delete items first (suppressing 20-day buyer notifications during account deletion)
          $stmt_items = mysqli_prepare($link, "SELECT items.iid FROM items JOIN categories ON items.cid = categories.cid WHERE categories.userid = ?");
          mysqli_stmt_bind_param($stmt_items, "s", $delUserid);
          mysqli_stmt_execute($stmt_items);
          $res_items = mysqli_stmt_get_result($stmt_items);
          while ($row_item = mysqli_fetch_assoc($res_items)) {
            deleteItem((int)$row_item["iid"], $delUserid, "", $base_dir, true);
          }

          mysqli_begin_transaction($link);
          try {
            $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? OR viewer = ?");
            mysqli_stmt_bind_param($stmt, "ss", $delUserid, $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE userid = ? OR comment_userid = ?");
            mysqli_stmt_bind_param($stmt, "ss", $delUserid, $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE userid = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
            mysqli_stmt_execute($stmt);

            // Clean deleted user from any splitUsers lists
            $res_splits = mysqli_query($link, "SELECT purchaseId, splitUsers FROM purchaseHistory WHERE splitUsers IS NOT NULL AND splitUsers != ''");
            if ($res_splits) {
              $stmt_upd_split = mysqli_prepare($link, "UPDATE purchaseHistory SET splitUsers = ? WHERE purchaseId = ?");
              while ($sp_row = mysqli_fetch_assoc($res_splits)) {
                $parts = array_filter(array_map('trim', explode(',', $sp_row['splitUsers'])), function ($u) use ($delUserid) {
                  return $u !== '' && $u !== $delUserid;
                });
                $new_split = implode(',', $parts);
                if ($new_split !== $sp_row['splitUsers']) {
                  $pid_int = (int)$sp_row['purchaseId'];
                  mysqli_stmt_bind_param($stmt_upd_split, "si", $new_split, $pid_int);
                  mysqli_stmt_execute($stmt_upd_split);
                }
              }
            }

            $stmt = mysqli_prepare($link, "DELETE FROM messages WHERE recipient_id = ? OR sender_id = ?");
            mysqli_stmt_bind_param($stmt, "ss", $delUserid, $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM accessRequests WHERE requesterId = ? OR targetId = ?");
            mysqli_stmt_bind_param($stmt, "ss", $delUserid, $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM favorites WHERE userid = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM passwordResetRequests WHERE target_userid = ? OR approver_userid = ?");
            mysqli_stmt_bind_param($stmt, "ss", $delUserid, $delUserid);
            mysqli_stmt_execute($stmt);

            $loginKey = 'user:' . strtolower($delUserid);
            $stmt = mysqli_prepare($link, "DELETE FROM loginAttempts WHERE identifier = ?");
            mysqli_stmt_bind_param($stmt, "s", $loginKey);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM categories WHERE userid = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
            mysqli_stmt_execute($stmt);

            $stmt = mysqli_prepare($link, "DELETE FROM people WHERE userid = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
            mysqli_stmt_execute($stmt);

            mysqli_commit($link);

            if (isset($_SESSION['euserid']) && $_SESSION['euserid'] === $delUserid) {
              unset($_SESSION['euserid']);
            }
            $message = "<h2>Person deleted</h2>";
          } catch (mysqli_sql_exception $e) {
            mysqli_rollback($link);
            error_log("Admin delete user failed: " . $e->getMessage());
            $message = "<h2 style='color:#c00;'>Error deleting user. Please check server logs.</h2>";
          }
        }
      }
    }
  }
} elseif ($action === "commitEdit") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);
  $oldUserid = trim($_POST["oldUserid"] ?? "");
  $newUserid = trim($_POST["newUserid"] ?? "");

  if ($oldUserid === "" || $newUserid === "") {
    $message = "<h2 style='color:#c00;'>Error: Both old and new UserIDs are required.</h2>";
  } elseif ($oldUserid === $newUserid) {
    $message = "<h2>UserID unchanged.</h2>";
  } elseif (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $newUserid) || strlen($newUserid) > 50) {
    $message = "<h2 style='color:#c00;'>Error: New UserID may only contain letters, numbers, underscores, dots, and hyphens (max 50 chars).</h2>";
  } else {
    $stmt_old = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_old, "s", $oldUserid);
    mysqli_stmt_execute($stmt_old);
    $old_exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_old));

    $stmt_new = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_new, "s", $newUserid);
    mysqli_stmt_execute($stmt_new);
    $new_exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_new));

    if (!$old_exists) {
      $message = "<h2 style='color:#c00;'>Error: User '" . escapeText($oldUserid) . "' was not found.</h2>";
    } elseif ($new_exists) {
      $message = "<h2 style='color:#c00;'>Error: UserID '" . escapeText($newUserid) . "' already exists. Choose a different UserID.</h2>";
    } else {
      mysqli_begin_transaction($link);
      try {
        $updates = [
          ["UPDATE viewList SET pid = ? WHERE pid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE viewList SET viewer = ? WHERE viewer = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE comments SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE comments SET comment_userid = ? WHERE comment_userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE purchaseHistory SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE messages SET sender_id = ? WHERE sender_id = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE messages SET recipient_id = ? WHERE recipient_id = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE accessRequests SET requesterId = ? WHERE requesterId = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE accessRequests SET targetId = ? WHERE targetId = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE favorites SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE passwordResetRequests SET target_userid = ? WHERE target_userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE passwordResetRequests SET approver_userid = ? WHERE approver_userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE categories SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
          ["UPDATE people SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
        ];

        foreach ($updates as [$sql, $types, $params]) {
          $stmt = mysqli_prepare($link, $sql);
          mysqli_stmt_bind_param($stmt, $types, ...$params);
          mysqli_stmt_execute($stmt);
        }

        // Update co-buyer splitUsers CSV references
        $res_splits = mysqli_query($link, "SELECT purchaseId, splitUsers FROM purchaseHistory WHERE splitUsers IS NOT NULL AND splitUsers != ''");
        if ($res_splits) {
          $stmt_upd_split = mysqli_prepare($link, "UPDATE purchaseHistory SET splitUsers = ? WHERE purchaseId = ?");
          while ($sp_row = mysqli_fetch_assoc($res_splits)) {
            $parts = array_map(function ($u) use ($oldUserid, $newUserid) {
              $trimmed = trim($u);
              return $trimmed === $oldUserid ? $newUserid : $trimmed;
            }, explode(',', $sp_row['splitUsers']));
            $new_split = implode(',', $parts);
            if ($new_split !== $sp_row['splitUsers']) {
              $pid_int = (int)$sp_row['purchaseId'];
              mysqli_stmt_bind_param($stmt_upd_split, "si", $new_split, $pid_int);
              mysqli_stmt_execute($stmt_upd_split);
            }
          }
        }

        $oldKey = 'user:' . strtolower($oldUserid);
        $newKey = 'user:' . strtolower($newUserid);
        $stmt_la = mysqli_prepare($link, "UPDATE loginAttempts SET identifier = ? WHERE identifier = ?");
        mysqli_stmt_bind_param($stmt_la, "ss", $newKey, $oldKey);
        mysqli_stmt_execute($stmt_la);

        mysqli_commit($link);

        if ($_SESSION['userid'] === $oldUserid) {
          $_SESSION['userid'] = $newUserid;
        }
        if (isset($_SESSION['euserid']) && $_SESSION['euserid'] === $oldUserid) {
          $_SESSION['euserid'] = $newUserid;
        }

        $message = "<h2>User " . escapeText($oldUserid) . " has been changed to " . escapeText($newUserid) . "</h2>";
      } catch (mysqli_sql_exception $e) {
        mysqli_rollback($link);
        error_log("Admin rename user failed: " . $e->getMessage());
        $message = "<h2 style='color:#c00;'>Error renaming user. Please check server logs.</h2>";
      }
    }
  }
} elseif ($action === "commitAdd") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);
  $fname = trim($_POST["fname"] ?? "");
  $lname = trim($_POST["lname"] ?? "");
  $suffix = trim($_POST["suffix"] ?? "");
  $desiredUserid = trim($_POST["userid"] ?? "");
  $email = "";

  if ($desiredUserid === "") {
    $message = "<h2 style='color:#c00;'>The userid cannot be empty!</h2>";
  } elseif (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $desiredUserid) || strlen($desiredUserid) > 50) {
    $message = "<h2 style='color:#c00;'>Userid may only contain letters, numbers, underscores, dots, and hyphens (max 50 chars).</h2>";
  } elseif ($fname === "" || $lname === "") {
    $message = "<h2 style='color:#c00;'>First Name and Last Name are required.</h2>";
  } else {
    $stmt_dup = mysqli_prepare($link, "SELECT 1 FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_dup, "s", $desiredUserid);
    mysqli_stmt_execute($stmt_dup);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dup))) {
      $message = "<h2 style='color:#c00;'>Error: UserID '" . escapeText($desiredUserid) . "' already exists!</h2>";
    } else {
      $submittedPassword = $_POST['password'] ?? '';
      if (trim($submittedPassword) !== '') {
        $password = $submittedPassword;
      } else {
        $password = generateRandomPassword(10);
      }

      $password_hash = password_hash($password, PASSWORD_DEFAULT);

      mysqli_begin_transaction($link);
      try {
        $stmt = mysqli_prepare($link, "INSERT INTO people (userid, firstname, lastname, suffix, email, password, lastLoginDate, lastModDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
        mysqli_stmt_bind_param($stmt, "ssssss", $desiredUserid, $fname, $lname, $suffix, $email, $password_hash);
        mysqli_stmt_execute($stmt);

        // Special categories
        $stmt = mysqli_prepare($link, "INSERT INTO categories (catSortOrder, userid, name, catSubDescription) VALUES (-10000, ?, 'Items Under Consideration', 'You are the only person who can view items in this category. Use it for items you are thinking of adding to your list')");
        mysqli_stmt_bind_param($stmt, "s", $desiredUserid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "INSERT INTO categories (catSortOrder, userid, name, description, linkname, linkurl, catSubDescription) VALUES (-1000, ?, '', '', '', '', '')");
        mysqli_stmt_bind_param($stmt, "s", $desiredUserid);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (1, 0, 0, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ss", $desiredUserid, $desiredUserid);
        mysqli_stmt_execute($stmt);

        mysqli_commit($link);

        $subject = "WishList Account Created";
        $email_message = "<b>WishList Account Created</b><br><br><b>Userid:</b> " . escapeText($desiredUserid) . "<br><b>Password:</b> " . escapeText($password) . "<br><br>In order to change your password, login to the WishList site with the password provided above. Once logged in, click on the <b><font color=navy>Update Your Account</font></b> button located at the bottom of the homepage. On the page that opens, click on the <b><font color=navy>Change Password</font></b> button.";
        sendEmail($desiredUserid, $_SESSION['userid'], $subject, $email_message, 0);
        $message = "<h2>Account created - Password = <code>" . escapeText($password) . "</code></h2><p>A message has been sent to the user with their login details.</p>";
      } catch (mysqli_sql_exception $e) {
        mysqli_rollback($link);
        error_log("Admin add user failed: " . $e->getMessage());
        $message = "<h2 style='color:#c00;'>Could not create user. Please check server logs.</h2>";
      }
    }
  }
} elseif ($action === "changeAdmin") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);

  if (!empty($_POST["grantAdmin"]) && is_array($_POST["grantAdmin"])) {
    $grantAdmin = array_values(array_filter(array_map('strval', $_POST["grantAdmin"]), function ($v) {
      return $v !== '';
    }));

    if (empty($grantAdmin)) {
      $message = "<h2 style='color:#c00;'>Error: You cannot remove all administrators. At least one admin must remain.</h2>";
    } else {
      $placeholders = implode(',', array_fill(0, count($grantAdmin), '?'));
      $types = str_repeat('s', count($grantAdmin));

      // Verify at least one selected user actually exists
      $stmt_verify = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM people WHERE userid IN ($placeholders)");
      mysqli_stmt_bind_param($stmt_verify, $types, ...$grantAdmin);
      mysqli_stmt_execute($stmt_verify);
      $valid_cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['cnt'] ?? 0);

      if ($valid_cnt < 1) {
        $message = "<h2 style='color:#c00;'>Error: At least one valid administrator must remain selected.</h2>";
      } else {
        mysqli_begin_transaction($link);
        try {
          $stmt = mysqli_prepare($link, "UPDATE people SET admin = 1 WHERE userid IN ($placeholders)");
          mysqli_stmt_bind_param($stmt, $types, ...$grantAdmin);
          mysqli_stmt_execute($stmt);

          $stmt = mysqli_prepare($link, "UPDATE people SET admin = 0 WHERE userid NOT IN ($placeholders)");
          mysqli_stmt_bind_param($stmt, $types, ...$grantAdmin);
          mysqli_stmt_execute($stmt);

          mysqli_commit($link);
          $_SESSION['admin'] = in_array($_SESSION['userid'], $grantAdmin, true) ? 1 : 0;
          $message = "<h2>Admin privileges updated</h2>";
        } catch (mysqli_sql_exception $e) {
          mysqli_rollback($link);
          error_log("Admin privilege update failed: " . $e->getMessage());
          $message = "<h2 style='color:#c00;'>Error updating admin privileges.</h2>";
        }
      }
    }
  } else {
    $message = "<h2 style='color:#c00;'>Error: You cannot remove all administrators. At least one admin must remain.</h2>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css" type="text/css">
  <link rel="manifest" href="manifest.json">
  <title>WishList Admin Page</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|:Admin Page"); ?>
<center>
<?php
print $message;

if ($action === "add") {
?>
<form method="post" action="admin.php">
<input type="hidden" name="action" value="commitAdd">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc"><font size="3" color="white"><b>Add New User</b></font></td></tr>
<tr>
<td align="right"><b>First Name</b></td>
<td><input type="text" name="fname" size="20" required></td>
</tr>
<tr>
<td align="right"><b>Last Name</b></td>
<td><input type="text" name="lname" size="20" required></td>
</tr>
<tr>
<td align="right"><b>Suffix (optional)</b></td>
<td><input type="text" name="suffix" size="10" placeholder="Jr., III, etc."></td>
</tr>
<tr>
<td align="right"><b>Desired Login Name</b></td>
<td><input type="text" name="userid" size="20" maxlength="50" required></td>
</tr>
<tr>
<td align="right"><b>Password</b></td>
<td><input type="password" name="password" size="20" placeholder="Blank = random"></td>
</tr>
<tr><td align="center" colspan="2" bgcolor="#c0c0c0"><input type="submit" value="Add User" style="font-weight:bold"></td>
</tr></table>
</form>
<br>
<?php
} elseif ($action === "edit") {
  $editUserid = $_REQUEST["userid"] ?? "";
  print "<form method=\"post\" action=\"admin.php\">";
  print "<input type=\"hidden\" name=\"action\" value=\"commitEdit\">";
  print "<input type=\"hidden\" name=\"csrf_token\" value=\"" . escapeText(getCsrfToken()) . "\">";
  print "<input type=\"hidden\" name=\"oldUserid\" value=\"" . escapeText($editUserid) . "\">";
  print "<table style=\"border-collapse: collapse;\" border=\"1\" bordercolor=\"#111111\" cellpadding=\"4\" cellspacing=\"0\" bgcolor=\"lightYellow\">";
  print "<tr><td colspan=\"2\" align=\"center\" bgcolor=\"#6702cc\"><font size=\"3\" color=\"white\"><b>Change Userid</b></font></td></tr>";
  print "<tr><td><b>Old Userid</b></td><td>" . escapeText($editUserid) . "</td></tr>";
  print "<tr><td><b>New Userid</b></td><td><input type=\"text\" name=\"newUserid\" maxlength=\"50\" required></td></tr>";
  print "<tr><td align=\"center\" colspan=\"2\" bgcolor=\"#c0c0c0\"><input type=\"submit\" value=\"Update\" style=\"font-weight:bold\"></td></tr>";
  print "</table></form><br>";
} elseif ($action === "delete") {
  $deleteUserid = $_REQUEST["userid"] ?? "";
  print "<form method=\"post\" action=\"admin.php\">";
  print "<input type=\"hidden\" name=\"action\" value=\"commitDelete\">";
  print "<input type=\"hidden\" name=\"csrf_token\" value=\"" . escapeText(getCsrfToken()) . "\">";
  print "<input type=\"hidden\" name=\"deleteUserid\" value=\"" . escapeText($deleteUserid) . "\">";
  print "<table style=\"border-collapse: collapse;\" border=\"1\" bordercolor=\"#111111\" cellpadding=\"4\" cellspacing=\"0\" bgcolor=\"lightYellow\">";
  print "<tr><td colspan=\"2\" align=\"center\" bgcolor=\"#6702cc\"><font size=\"3\" color=\"white\"><b>Confirm Delete</b></font></td></tr>";
  print "<tr><td colspan=\"2\">Are you sure you want to delete <b>" . escapeText($deleteUserid) . "</b>?<p>Everything relating to this user will be permanently deleted.</p></td></tr>";
  print "<tr><td align=\"center\" colspan=\"2\" bgcolor=\"#c0c0c0\"><input type=\"submit\" name=\"confirm\" value=\"Yes\" style=\"font-weight:bold\"> <input type=\"submit\" name=\"confirm\" value=\"No\" style=\"font-weight:bold\"></td></tr>";
  print "</table></form><br>";
}
?>

<form method="post" action="admin.php">
<input type="hidden" name="action" value="changeAdmin">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr><td colspan="4" align="center" bgcolor="#6702cc">
<font size="3" color="white"><b>Current Users</b></font>
</td></tr>
<tr bgcolor="lightYellow">
  <td align="center"><b>UserId</b></td>
  <td align="center" valign="top"><b>Name</b></td>
  <td align="center"><b>Last Login</b></td>
  <td align="center"><b>Admin</b></td>
</tr>
<?php
$result = mysqli_query($link, "SELECT userid, firstname, lastname, suffix, lastLoginDate, admin FROM people ORDER BY lastname, firstname");
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $u_escaped = escapeText($row["userid"]);
    $u_encoded = urlencode($row["userid"]);
    $fullName = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
    print "<tr>";
    print "<td><a href=\"admin.php?action=edit&amp;userid=" . $u_encoded . "\">[edit]</a> <a href=\"admin.php?action=delete&amp;userid=" . $u_encoded . "\">[del]</a> <a href=\"./updateAccount/changePassword.php?userid=" . $u_encoded . "\">[pwd]</a> <a href=\"./updateAccount/updateAccount.php?target_userid=" . $u_encoded . "\">[account]</a> " . $u_escaped . "</td>";
    print "<td>" . escapeText($fullName) . "</td>";
    print "<td><font face=\"Courier\">" . escapeText(parseDate($row["lastLoginDate"], 1)) . "</font></td>";
    print "<td align=\"center\"><input type=\"checkbox\" name=\"grantAdmin[]\" value=\"" . $u_escaped . "\" " . ($row["admin"] == 1 ? "checked" : "") . "></td>";
    print "</tr>";
  }
}
?>
<tr><td align="center" colspan="4" bgcolor="#c0c0c0">
<input type="submit" value="Update Admin Status" class="buttonstyle">
</td></tr></table>
</form>

<button class="buttonstyle" onclick="location.href='admin.php?action=add'">Add New User</button><br>
<button class="buttonstyle" onclick="location.href='admin/accessRequests.php'">Manage Access Requests</button><br>
<button class="buttonstyle" onclick="location.href='admin.php?setup=true'">Change Settings</button>

</center>
</td>
</tr>
</table>
</body>
</html>
<?php

function createConfig() {
  global $base_dir, $base_url, $admin_email, $db_loc, $db_name, $db_userid, $db_pass, $doingSetup, $ignoreSession;

  $config_file = __DIR__ . "/config.php";
  $has_existing_config = file_exists($config_file);

  if ($has_existing_config) {
    include_once __DIR__ . '/funcLib.php';
    if (!isset($_SESSION["admin"]) || $_SESSION["admin"] != 1) {
      http_response_code(403);
      die("Unauthorized access.");
    }
  } else {
    $doingSetup = true;
    $ignoreSession = true;
    include_once __DIR__ . '/funcLib.php';
  }

  $error = '';
  $allowUpdate = false;
  $show_root_form = false;

  $is_update_post = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateValues']));
  if ($is_update_post) {
    verifyCsrfOrDie(true);
    $allowUpdate = true;

    $new_base_dir = trim($_POST['base_dir'] ?? '');
    $new_base_url = trim($_POST['base_url'] ?? '');
    $new_admin_email = trim($_POST['admin_email'] ?? '');
    $new_db_loc = trim($_POST['db_loc'] ?? '');
    $new_db_name = trim($_POST['db_name'] ?? '');
    $new_db_userid = trim($_POST['db_userid'] ?? '');

    $submitted_db_pass = $_POST['db_pass'] ?? '';
    if ($submitted_db_pass !== '') {
      $new_db_pass = $submitted_db_pass;
    } elseif (!empty($_POST['use_pending_pass']) && isset($_SESSION['_setup_pending_db_pass'])) {
      $new_db_pass = $_SESSION['_setup_pending_db_pass'];
    } elseif ($has_existing_config && isset($db_pass) && $db_pass !== '') {
      $new_db_pass = $db_pass;
    } else {
      $new_db_pass = '';
    }
  } else {
    $new_base_dir = $base_dir ?? '';
    $new_base_url = $base_url ?? '';
    $new_admin_email = $admin_email ?? '';
    $new_db_loc = $db_loc ?? '';
    $new_db_name = $db_name ?? '';
    $new_db_userid = $db_userid ?? '';
    $new_db_pass = $db_pass ?? '';
  }

  // Handle optional root credential submission to create DB/user
  if ($is_update_post && !empty($_POST['db_root_pass'])) {
    $root_user = trim($_POST['db_root_user'] ?? 'root');
    $root_pass = $_POST['db_root_pass'];

    try {
      $root_link = mysqli_connect($new_db_loc, $root_user, $root_pass);

      $escaped_db_name = str_replace('`', '``', $new_db_name);
      $escaped_db_userid = mysqli_real_escape_string($root_link, $new_db_userid);
      $escaped_db_pass = mysqli_real_escape_string($root_link, $new_db_pass);

      $query_create_db = "CREATE DATABASE IF NOT EXISTS `$escaped_db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
      if (!mysqli_query($root_link, $query_create_db)) {
        throw new Exception("Could not create database: " . mysqli_error($root_link));
      }

      @mysqli_query($root_link, "DROP USER IF EXISTS '$escaped_db_userid'@'%'");

      $create_user_query = "CREATE USER '$escaped_db_userid'@'%' IDENTIFIED BY '$escaped_db_pass'";
      if (!mysqli_query($root_link, $create_user_query)) {
        throw new Exception("Could not create user: " . mysqli_error($root_link));
      }

      $query_grant = "GRANT ALL PRIVILEGES ON `$escaped_db_name`.* TO '$escaped_db_userid'@'%'";
      if (!mysqli_query($root_link, $query_grant)) {
        throw new Exception("Could not grant privileges: " . mysqli_error($root_link));
      }

      @mysqli_query($root_link, "FLUSH PRIVILEGES");
      mysqli_close($root_link);
    } catch (mysqli_sql_exception | Exception $e) {
      $error .= "ERROR during root operation: " . escapeText($e->getMessage()) . "<br>";
      $show_root_form = true;
    }
  }

  // Test DB connection when updating
  if ($is_update_post && $new_db_loc !== '' && $new_db_name !== '' && $new_db_userid !== '' && $new_db_pass !== '' && !$show_root_form) {
    try {
      $test_link = mysqli_connect($new_db_loc, $new_db_userid, $new_db_pass);
      if (!mysqli_select_db($test_link, $new_db_name)) {
        $error .= "INFO: Could not select database '" . escapeText($new_db_name) . "'. It might not exist or user '" . escapeText($new_db_userid) . "' lacks permissions.<br>Please provide root credentials to create the database and grant access.<br>";
        $allowUpdate = false;
        $show_root_form = true;
      }
      mysqli_close($test_link);
    } catch (mysqli_sql_exception $e) {
      $error .= "ERROR connecting as '" . escapeText($new_db_userid) . "': " . escapeText($e->getMessage()) . "<br>";
      $error .= "If the user or database needs to be created, use the root credential form below.<br>";
      $allowUpdate = false;
      $show_root_form = true;
    }
  } elseif ($is_update_post && ($new_base_dir === '' || $new_base_url === '' || $new_admin_email === '' || $new_db_loc === '' || $new_db_name === '' || $new_db_userid === '' || $new_db_pass === '')) {
    $error .= "All required fields (including Database Password on initial setup) must be completed.<br>";
    $allowUpdate = false;
  }

  if ($show_root_form) {
    $_SESSION['_setup_pending_db_pass'] = $new_db_pass;
  } else {
    unset($_SESSION['_setup_pending_db_pass']);
  }

  // Write config file
  if ($allowUpdate && $error === '' &&
      $new_base_dir !== '' && $new_base_url !== '' &&
      $new_admin_email !== '' && $new_db_loc !== '' && $new_db_name !== '' &&
      $new_db_userid !== '' && $new_db_pass !== '') {

    $fh = @fopen($config_file, 'w');
    if ($fh) {
      fwrite($fh, "<?php\n");
      fwrite($fh, "\$base_dir = " . var_export(rtrim($new_base_dir, '/'), true) . ";\n");
      fwrite($fh, "\$base_url = " . var_export(rtrim($new_base_url, '/'), true) . ";\n\n");
      fwrite($fh, "\$admin_email = " . var_export($new_admin_email, true) . ";\n\n");
      fwrite($fh, "\$db_loc = " . var_export($new_db_loc, true) . ";\n");
      fwrite($fh, "\$db_name = " . var_export($new_db_name, true) . ";\n");
      fwrite($fh, "\$db_userid = " . var_export($new_db_userid, true) . ";\n");
      fwrite($fh, "\$db_pass = " . var_export($new_db_pass, true) . ";\n");
      fclose($fh);

      @chmod($config_file, 0640);
      unset($_SESSION['_setup_pending_db_pass']);
      header("Location: admin.php");
      exit;
    } else {
      $error .= "Couldn't write config file!";
    }
  }

  $safeHost = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
  $scheme = isHttpsRequest() ? 'https://' : 'http://';
  $defaultUrl = $new_base_url !== '' ? $new_base_url : ($scheme . $safeHost);
  $defaultDir = $new_base_dir !== '' ? $new_base_dir : __DIR__;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css" type="text/css">
  <title>WishList Setup</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|admin.php:Admin Page|:Change WishList Settings"); ?>
<center>
<?php
if ($error !== '') {
  print "<h2 style='color:#c00;'>" . $error . "</h2>";
}
?>
<p>
<form method="post" action="admin.php">
<input type="hidden" name="setup" value="true">
<input type="hidden" name="updateValues" value="true">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc"><font size="3" color="white"><b>WishList Setup</b></font></td></tr>
<tr>
<td align="right">* Base Dir</td>
<td bgcolor="white"><input type="text" name="base_dir" value="<?php echo escapeText($defaultDir); ?>" size="50" required></td>
</tr>
<tr>
<td align="right">* Base URL</td>
<td bgcolor="white"><input type="text" name="base_url" value="<?php echo escapeText($defaultUrl); ?>" size="50" required></td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
<td align="right">* Primary Admin Email</td>
<td><input type="email" name="admin_email" value="<?php echo escapeText($new_admin_email); ?>" size="50" required></td>
</tr>
<tr><td colspan="2">&nbsp;</td></tr>
<tr>
<td align="right">* Database Server</td>
<td><input type="text" name="db_loc" value="<?php echo escapeText($new_db_loc); ?>" size="50" required></td>
</tr>
<tr>
<td align="right">* Database Name</td>
<td><input type="text" name="db_name" value="<?php echo escapeText($new_db_name); ?>" size="50" required></td>
</tr>
<tr>
<td align="right">* Database Userid</td>
<td><input type="text" name="db_userid" value="<?php echo escapeText($new_db_userid); ?>" size="50" required></td>
</tr>
<tr>
<td align="right"><?php echo $has_existing_config ? 'Database Password' : '* Database Password'; ?></td>
<td><input type="password" name="db_pass" value="" size="50" autocomplete="new-password" placeholder="<?php echo $has_existing_config ? 'Leave blank to keep existing password' : 'Required'; ?>"></td>
</tr>
</table>
<p>
<input type="submit" value="Submit" class="buttonstyle">
</form>
* = required field

<?php if ($show_root_form): ?>
  <hr>
  <form method="post" action="admin.php">
    <input type="hidden" name="setup" value="true">
    <input type="hidden" name="updateValues" value="true">
    <input type="hidden" name="use_pending_pass" value="1">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <input type="hidden" name="base_dir" value="<?php echo escapeText($new_base_dir); ?>">
    <input type="hidden" name="base_url" value="<?php echo escapeText($new_base_url); ?>">
    <input type="hidden" name="admin_email" value="<?php echo escapeText($new_admin_email); ?>">
    <input type="hidden" name="db_loc" value="<?php echo escapeText($new_db_loc); ?>">
    <input type="hidden" name="db_name" value="<?php echo escapeText($new_db_name); ?>">
    <input type="hidden" name="db_userid" value="<?php echo escapeText($new_db_userid); ?>">

    <table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
    <tr><td colspan="2" align="center" bgcolor="#cc0202"><font size="3" color="white"><b>Database Root Credentials</b></font></td></tr>
    <tr>
      <td align="right">Root User</td>
      <td><input type="text" name="db_root_user" value="root" size="50" required></td>
    </tr>
    <tr>
      <td align="right">Root Password</td>
      <td><input type="password" name="db_root_pass" value="" size="50" autocomplete="off" required></td>
    </tr>
    </table>
    <p>
    <input type="submit" value="Create Database &amp; Grant Privileges" class="buttonstyle">
  </form>
<?php endif; ?>
</center>
</td>
</tr>
</table>
</body>
</html>
<?php
}
