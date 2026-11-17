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
$alert_html = "";
$alert_type = "success";

if ($action === "commitDelete") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);
  $delUserid = trim($_POST["deleteUserid"] ?? $_POST["userid"] ?? "");
  if (($_POST["confirm"] ?? '') === "Yes" && $delUserid !== "") {
    if ($delUserid === $_SESSION['userid']) {
      $alert_type = "error";
      $alert_html = "Error: You cannot delete your own account while logged in.";
    } else {
      // Check if target is the last remaining admin
      $stmt_chk = mysqli_prepare($link, "SELECT admin FROM people WHERE userid = ?");
      mysqli_stmt_bind_param($stmt_chk, "s", $delUserid);
      mysqli_stmt_execute($stmt_chk);
      $res_chk = mysqli_stmt_get_result($stmt_chk);
      $target_row = mysqli_fetch_assoc($res_chk);

      if (!$target_row) {
        $alert_type = "error";
        $alert_html = "Error: User not found.";
      } else {
        $can_delete = true;
        if ((int)$target_row['admin'] === 1) {
          $res_admins = mysqli_query($link, "SELECT COUNT(*) AS cnt FROM people WHERE admin = 1");
          $admin_cnt = (int)(mysqli_fetch_assoc($res_admins)['cnt'] ?? 0);
          if ($admin_cnt <= 1) {
            $can_delete = false;
            $alert_type = "error";
            $alert_html = "Error: Cannot delete the last administrator account.";
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

            $stmt = mysqli_prepare($link, "UPDATE comments SET claimed_by = NULL, claimed_date = NULL, split_users = '', split_with = '' WHERE claimed_by = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
            mysqli_stmt_execute($stmt);

            $res_csplits = mysqli_query($link, "SELECT commentId, split_users FROM comments WHERE split_users IS NOT NULL AND split_users != ''");
            if ($res_csplits) {
              $stmt_upd_csplit = mysqli_prepare($link, "UPDATE comments SET split_users = ? WHERE commentId = ?");
              while ($csp_row = mysqli_fetch_assoc($res_csplits)) {
                $cparts = array_filter(array_map('trim', explode(',', $csp_row['split_users'])), function ($u) use ($delUserid) {
                  return $u !== '' && $u !== $delUserid;
                });
                $new_csplit = implode(',', $cparts);
                if ($new_csplit !== $csp_row['split_users']) {
                  $cid_int = (int)$csp_row['commentId'];
                  mysqli_stmt_bind_param($stmt_upd_csplit, "si", $new_csplit, $cid_int);
                  mysqli_stmt_execute($stmt_upd_csplit);
                }
              }
            }

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

            $stmt = mysqli_prepare($link, "DELETE FROM loginAttempts WHERE userid = ?");
            mysqli_stmt_bind_param($stmt, "s", $delUserid);
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
            $alert_type = "success";
            $alert_html = "User <code>" . escapeText($delUserid) . "</code> has been deleted.";
          } catch (mysqli_sql_exception $e) {
            mysqli_rollback($link);
            error_log("Admin delete user failed: " . $e->getMessage());
            $alert_type = "error";
            $alert_html = "Error deleting user. Please check server logs.";
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
  $wantAdmin = !empty($_POST["is_admin"]) ? 1 : 0;

  if ($oldUserid === "" || $newUserid === "") {
    $alert_type = "error";
    $alert_html = "Error: Both old and new UserIDs are required.";
  } elseif (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $newUserid) || strlen($newUserid) > 50) {
    $alert_type = "error";
    $alert_html = "Error: New UserID may only contain letters, numbers, underscores, dots, and hyphens (max 50 chars).";
  } else {
    $stmt_old = mysqli_prepare($link, "SELECT userid, admin FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_old, "s", $oldUserid);
    mysqli_stmt_execute($stmt_old);
    $old_exists = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_old));

    if (!$old_exists) {
      $alert_type = "error";
      $alert_html = "Error: User '<code>" . escapeText($oldUserid) . "</code>' was not found.";
    } else {
      $oldAdmin = (int)($old_exists['admin'] ?? 0);
      $can_proceed = true;

      if ($oldAdmin === 1 && $wantAdmin === 0) {
        $res_admins = mysqli_query($link, "SELECT COUNT(*) AS cnt FROM people WHERE admin = 1");
        $admin_cnt = (int)(mysqli_fetch_assoc($res_admins)['cnt'] ?? 0);
        if ($admin_cnt <= 1) {
          $can_proceed = false;
          $alert_type = "error";
          $alert_html = "Error: You cannot remove administrator privileges from the last remaining admin.";
        }
      }

      if ($can_proceed && $oldUserid !== $newUserid) {
        $stmt_new = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_new, "s", $newUserid);
        mysqli_stmt_execute($stmt_new);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_new))) {
          $can_proceed = false;
          $alert_type = "error";
          $alert_html = "Error: UserID '<code>" . escapeText($newUserid) . "</code>' already exists. Choose a different UserID.";
        }
      }

      if ($can_proceed) {
        if ($oldUserid === $newUserid) {
          if ($oldAdmin !== $wantAdmin) {
            $stmt_adm = mysqli_prepare($link, "UPDATE people SET admin = ? WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_adm, "is", $wantAdmin, $oldUserid);
            mysqli_stmt_execute($stmt_adm);
            if ($_SESSION['userid'] === $oldUserid) {
              $_SESSION['admin'] = $wantAdmin;
            }
            $alert_type = "success";
            $alert_html = "Administrator status updated for <code>" . escapeText($oldUserid) . "</code>.";
          } else {
            $alert_type = "success";
            $alert_html = "No changes made for <code>" . escapeText($oldUserid) . "</code>.";
          }
        } else {
          mysqli_begin_transaction($link);
          try {
            $updates = [
              ["UPDATE viewList SET pid = ? WHERE pid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE viewList SET viewer = ? WHERE viewer = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE comments SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE comments SET comment_userid = ? WHERE comment_userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE comments SET claimed_by = ? WHERE claimed_by = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE purchaseHistory SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE messages SET sender_id = ? WHERE sender_id = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE messages SET recipient_id = ? WHERE recipient_id = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE accessRequests SET requesterId = ? WHERE requesterId = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE accessRequests SET targetId = ? WHERE targetId = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE favorites SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE passwordResetRequests SET target_userid = ? WHERE target_userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE passwordResetRequests SET approver_userid = ? WHERE approver_userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE categories SET userid = ? WHERE userid = ?", "ss", [$newUserid, $oldUserid]],
              ["UPDATE people SET userid = ?, admin = ? WHERE userid = ?", "sis", [$newUserid, $wantAdmin, $oldUserid]],
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

            $res_csplits = mysqli_query($link, "SELECT commentId, split_users FROM comments WHERE split_users IS NOT NULL AND split_users != ''");
            if ($res_csplits) {
              $stmt_upd_csplit = mysqli_prepare($link, "UPDATE comments SET split_users = ? WHERE commentId = ?");
              while ($csp_row = mysqli_fetch_assoc($res_csplits)) {
                $cparts = array_map(function ($u) use ($oldUserid, $newUserid) {
                  $trimmed = trim($u);
                  return $trimmed === $oldUserid ? $newUserid : $trimmed;
                }, explode(',', $csp_row['split_users']));
                $new_csplit = implode(',', $cparts);
                if ($new_csplit !== $csp_row['split_users']) {
                  $cid_int = (int)$csp_row['commentId'];
                  mysqli_stmt_bind_param($stmt_upd_csplit, "si", $new_csplit, $cid_int);
                  mysqli_stmt_execute($stmt_upd_csplit);
                }
              }
            }

            $stmt_la = mysqli_prepare($link, "UPDATE loginAttempts SET userid = ? WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_la, "ss", $newUserid, $oldUserid);
            mysqli_stmt_execute($stmt_la);

            mysqli_commit($link);

            if ($_SESSION['userid'] === $oldUserid) {
              $_SESSION['userid'] = $newUserid;
              $_SESSION['admin'] = $wantAdmin;
            }
            if (isset($_SESSION['euserid']) && $_SESSION['euserid'] === $oldUserid) {
              $_SESSION['euserid'] = $newUserid;
            }

            $alert_type = "success";
            if ($oldAdmin !== $wantAdmin) {
              $alert_html = "UserID <code>" . escapeText($oldUserid) . "</code> changed to <code>" . escapeText($newUserid) . "</code> and administrator status updated.";
            } else {
              $alert_html = "UserID <code>" . escapeText($oldUserid) . "</code> has been changed to <code>" . escapeText($newUserid) . "</code>.";
            }
          } catch (mysqli_sql_exception $e) {
            mysqli_rollback($link);
            error_log("Admin rename user failed: " . $e->getMessage());
            $alert_type = "error";
            $alert_html = "Error updating user. Please check server logs.";
          }
        }
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
    $alert_type = "error";
    $alert_html = "The UserID cannot be empty.";
  } elseif (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $desiredUserid) || strlen($desiredUserid) > 50) {
    $alert_type = "error";
    $alert_html = "UserID may only contain letters, numbers, underscores, dots, and hyphens (max 50 chars).";
  } elseif ($fname === "" || $lname === "") {
    $alert_type = "error";
    $alert_html = "First Name and Last Name are required.";
  } else {
    $stmt_dup = mysqli_prepare($link, "SELECT 1 FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_dup, "s", $desiredUserid);
    mysqli_stmt_execute($stmt_dup);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dup))) {
      $alert_type = "error";
      $alert_html = "Error: UserID '<code>" . escapeText($desiredUserid) . "</code>' already exists!";
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
        $alert_type = "success";
        $alert_html = "Account <code>" . escapeText($desiredUserid) . "</code> created — Temporary Password: <code>" . escapeText($password) . "</code><p>An internal welcome message has been sent to the user with their login details.</p>";
      } catch (mysqli_sql_exception $e) {
        mysqli_rollback($link);
        error_log("Admin add user failed: " . $e->getMessage());
        $alert_type = "error";
        $alert_html = "Could not create user. Please check server logs.";
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
      $alert_type = "error";
      $alert_html = "Error: You cannot remove all administrators. At least one admin must remain.";
    } else {
      $placeholders = implode(',', array_fill(0, count($grantAdmin), '?'));
      $types = str_repeat('s', count($grantAdmin));

      // Verify at least one selected user actually exists
      $stmt_verify = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM people WHERE userid IN ($placeholders)");
      mysqli_stmt_bind_param($stmt_verify, $types, ...$grantAdmin);
      mysqli_stmt_execute($stmt_verify);
      $valid_cnt = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_verify))['cnt'] ?? 0);

      if ($valid_cnt < 1) {
        $alert_type = "error";
        $alert_html = "Error: At least one valid administrator must remain selected.";
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
          $alert_type = "success";
          $alert_html = "Administrator privileges updated.";
        } catch (mysqli_sql_exception $e) {
          mysqli_rollback($link);
          error_log("Admin privilege update failed: " . $e->getMessage());
          $alert_type = "error";
          $alert_html = "Error updating admin privileges.";
        }
      }
    }
  } else {
    $alert_type = "error";
    $alert_html = "Error: You cannot remove all administrators. At least one admin must remain.";
  }
} elseif ($action === "deleteOrphanImages") {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
  }
  verifyCsrfOrDie(true);

  $selectedFiles = isset($_POST['orphan_files']) && is_array($_POST['orphan_files']) ? $_POST['orphan_files'] : [];
  if (empty($selectedFiles)) {
    $alert_type = "error";
    $alert_html = "No files were selected for deletion.";
  } else {
    $referenced = getReferencedUploadFiles($link);
    $uploadsDir = rtrim($base_dir, '/') . '/uploads/';
    $deletedCount = 0;
    $freedBytes = 0;
    $skippedCount = 0;

    foreach ($selectedFiles as $rawName) {
      $safeName = basename((string)$rawName);
      if ($safeName === '' || $safeName[0] === '.' || $safeName !== (string)$rawName) {
        $skippedCount++;
        continue;
      }
      if (isset($referenced[$safeName])) {
        $skippedCount++;
        continue;
      }
      $fullPath = $uploadsDir . $safeName;
      if (is_file($fullPath)) {
        $fsize = (int)@filesize($fullPath);
        if (@unlink($fullPath)) {
          $deletedCount++;
          $freedBytes += $fsize;
        } else {
          $skippedCount++;
        }
      }
    }

    if ($deletedCount > 0) {
      $alert_type = "success";
      $alert_html = "Deleted " . (int)$deletedCount . " unreferenced " . ($deletedCount === 1 ? "file" : "files") . " (" . formatAdminBytes($freedBytes) . " freed).";
      if ($skippedCount > 0) {
        $alert_html .= " Skipped " . (int)$skippedCount . " file(s).";
      }
    } else {
      $alert_type = "error";
      $alert_html = "Could not delete selected files (they may be referenced or lack write permission).";
    }
  }
  $action = "findOrphanImages";
}

function getReferencedUploadFiles($link) {
  $referenced = [];
  $res = mysqli_query($link, "SELECT iid, image FROM items WHERE image IS NOT NULL AND image != ''");
  if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
      $iidInt = (int)$r['iid'];
      if ($iidInt > 0) {
        $referenced['image-' . $iidInt . '.jpg'] = true;
        $referenced['image-' . $iidInt . '_thumb.jpg'] = true;
      }
      $base = basename((string)$r['image']);
      if ($base !== '') {
        $referenced[$base] = true;
      }
    }
  }
  return $referenced;
}

function findOrphanUploadFiles($link, $base_dir) {
  $uploadsDir = rtrim($base_dir, '/') . '/uploads/';
  $referenced = getReferencedUploadFiles($link);
  $orphans = [];

  if (is_dir($uploadsDir)) {
    $entries = @scandir($uploadsDir);
    if (is_array($entries)) {
      foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..' || $entry === '' || $entry[0] === '.') {
          continue;
        }
        $fullPath = $uploadsDir . $entry;
        if (!is_file($fullPath)) {
          continue;
        }
        if (!isset($referenced[$entry])) {
          $orphans[] = [
            'filename' => $entry,
            'size' => (int)@filesize($fullPath),
            'mtime' => (int)@filemtime($fullPath),
          ];
        }
      }
    }
  }

  usort($orphans, function ($a, $b) {
    return $b['mtime'] <=> $a['mtime'];
  });
  return $orphans;
}

function formatAdminBytes($bytes) {
  $bytes = (int)$bytes;
  if ($bytes >= 1048576) {
    return number_format($bytes / 1048576, 2) . ' MB';
  }
  if ($bytes >= 1024) {
    return number_format($bytes / 1024, 1) . ' KB';
  }
  return $bytes . ' B';
}

$orphan_files = ($action === "findOrphanImages") ? findOrphanUploadFiles($link, $base_dir) : null;

// Fetch users and group into families by last name
$families = [];
$users_by_id = [];
$total_users = 0;
$total_admins = 0;

$result = mysqli_query($link, "SELECT userid, firstname, lastname, suffix, lastLoginDate, admin FROM people ORDER BY lastname, firstname");
if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $total_users++;
    if ((int)$row['admin'] === 1) {
      $total_admins++;
    }
    $users_by_id[$row['userid']] = $row;

    $raw_last = trim((string)($row['lastname'] ?? ''));
    $fam_key = ($raw_last !== '') ? strtolower($raw_last) : '_other';
    if (!isset($families[$fam_key])) {
      $families[$fam_key] = [
        'label' => ($raw_last !== '') ? ($raw_last . ' Family') : 'Other',
        'family_raw' => $raw_last,
        'members' => [],
        'admin_count' => 0,
      ];
    }
    $families[$fam_key]['members'][] = $row;
    if ((int)$row['admin'] === 1) {
      $families[$fam_key]['admin_count']++;
    }
  }
}
$total_families = count($families);

// Count pending access requests for quick visibility
$pending_req_count = 0;
$res_pending = @mysqli_query($link, "SELECT COUNT(*) AS cnt FROM accessRequests WHERE status = 'pending'");
if ($res_pending && ($prow = mysqli_fetch_assoc($res_pending))) {
  $pending_req_count = (int)($prow['cnt'] ?? 0);
}

$csrf_token_escaped = escapeText(getCsrfToken());

// Reusable crisp inline SVG icons
$svg_admin = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>';
$svg_edit = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>';
$svg_pwd = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>';
$svg_account = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
$svg_delete = '<svg viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
  <link rel="manifest" href="manifest.json">
  <title>WishList Admin Page</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|:Admin Page", true); ?>

<div class="admin-dashboard">

  <div class="admin-header-bar">
    <div class="admin-title-group">
      <h2>Admin Dashboard</h2>
      <div class="admin-stats-line">
        <?php echo (int)$total_users; ?> <?php echo $total_users === 1 ? 'user' : 'users'; ?>
        across <?php echo (int)$total_families; ?> <?php echo $total_families === 1 ? 'family' : 'families'; ?>
        &middot; <?php echo (int)$total_admins; ?> <?php echo $total_admins === 1 ? 'admin' : 'admins'; ?>
      </div>
    </div>
    <div class="admin-top-actions">
      <a href="admin.php?action=add" class="admin-nav-btn admin-nav-btn-primary">➕ Add New User</a>
      <a href="admin/accessRequests.php" class="admin-nav-btn">
        📩 Access Requests
        <?php if ($pending_req_count > 0): ?>
          <span class="nav-pill-badge"><?php echo (int)$pending_req_count; ?></span>
        <?php endif; ?>
      </a>
      <a href="admin.php?setup=true" class="admin-nav-btn">⚙️ Site Settings</a>
    </div>
  </div>

  <?php if ($alert_html !== ""): ?>
    <div class="admin-alert <?php echo $alert_type === 'error' ? 'admin-alert-error' : 'admin-alert-success'; ?>" role="status">
      <?php echo $alert_html; ?>
    </div>
  <?php endif; ?>

  <?php if ($action === "add"): ?>
    <form method="post" action="admin.php" class="admin-modal-card">
      <input type="hidden" name="action" value="commitAdd">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_escaped; ?>">
      <div class="admin-modal-card-header">
        <span>➕ Add New User</span>
      </div>
      <div class="admin-modal-card-body">
        <div class="admin-form-row">
          <label for="add_fname">First Name *</label>
          <input type="text" id="add_fname" name="fname" required autofocus>
        </div>
        <div class="admin-form-row">
          <label for="add_lname">Last Name (Family Group) *</label>
          <input type="text" id="add_lname" name="lname" required>
        </div>
        <div class="admin-form-row">
          <label for="add_suffix">Suffix (optional)</label>
          <input type="text" id="add_suffix" name="suffix" placeholder="Jr., III, etc.">
        </div>
        <div class="admin-form-row">
          <label for="add_userid">Desired Login ID *</label>
          <input type="text" id="add_userid" name="userid" maxlength="50" required placeholder="Letters, numbers, _, ., -">
        </div>
        <div class="admin-form-row" style="margin-bottom:0;">
          <label for="add_password">Initial Password (optional)</label>
          <input type="password" id="add_password" name="password" autocomplete="new-password" placeholder="Leave blank to auto-generate random password">
        </div>
      </div>
      <div class="admin-modal-card-footer">
        <a href="admin.php" class="admin-nav-btn">Cancel</a>
        <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Create Account</button>
      </div>
    </form>
  <?php elseif ($action === "edit"): ?>
    <?php
      $editUserid = trim((string)($_REQUEST["userid"] ?? ""));
      $editUserRow = $users_by_id[$editUserid] ?? null;
      $editFullName = $editUserRow ? trim($editUserRow['firstname'] . ' ' . $editUserRow['lastname'] . ' ' . ($editUserRow['suffix'] ?? '')) : '';
      $editIsAdmin = ((int)($editUserRow['admin'] ?? 0) === 1);
    ?>
    <form method="post" action="admin.php" class="admin-modal-card">
      <input type="hidden" name="action" value="commitEdit">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_escaped; ?>">
      <input type="hidden" name="oldUserid" value="<?php echo escapeText($editUserid); ?>">
      <div class="admin-modal-card-header">
        <span>Edit User</span>
      </div>
      <div class="admin-modal-card-body">
        <div class="admin-form-row">
          <label>Current User</label>
          <div>
            <?php if ($editFullName !== ''): ?>
              <b><?php echo escapeText($editFullName); ?></b> &mdash;
            <?php endif; ?>
            <span class="admin-userid-pill"><?php echo escapeText($editUserid); ?></span>
          </div>
        </div>
        <div class="admin-form-row">
          <label for="edit_new_userid">UserID *</label>
          <input type="text" id="edit_new_userid" name="newUserid" maxlength="50" value="<?php echo escapeText($editUserid); ?>" required autofocus>
        </div>
        <div class="admin-form-row" style="margin-bottom:0;">
          <label class="admin-checkbox-row" for="edit_is_admin">
            <input
              type="checkbox"
              id="edit_is_admin"
              name="is_admin"
              value="1"
              class="admin-role-checkbox"
              <?php echo $editIsAdmin ? 'checked' : ''; ?>
            >
            <span>Site Administrator</span>
          </label>
        </div>
      </div>
      <div class="admin-modal-card-footer">
        <a href="admin.php" class="admin-nav-btn">Cancel</a>
        <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Save Changes</button>
      </div>
    </form>
  <?php elseif ($action === "delete"): ?>
    <?php
      $deleteUserid = trim((string)($_REQUEST["userid"] ?? ""));
      $delUserRow = $users_by_id[$deleteUserid] ?? null;
      $delFullName = $delUserRow ? trim($delUserRow['firstname'] . ' ' . $delUserRow['lastname'] . ' ' . ($delUserRow['suffix'] ?? '')) : '';
    ?>
    <form method="post" action="admin.php" class="admin-modal-card">
      <input type="hidden" name="action" value="commitDelete">
      <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_escaped; ?>">
      <input type="hidden" name="deleteUserid" value="<?php echo escapeText($deleteUserid); ?>">
      <div class="admin-modal-card-header danger">
        <span>Confirm Account Deletion</span>
      </div>
      <div class="admin-modal-card-body">
        <p style="margin:0 0 10px 0; font-size:14.5px;">
          Are you sure you want to delete
          <?php if ($delFullName !== ''): ?>
            <b><?php echo escapeText($delFullName); ?></b> (<span class="admin-userid-pill"><?php echo escapeText($deleteUserid); ?></span>)?
          <?php else: ?>
            <b><?php echo escapeText($deleteUserid); ?></b>?
          <?php endif; ?>
        </p>
        <p style="margin:0; font-size:13.5px; color:#b91c1c;">
          Everything relating to this user (wishlist categories, items, uploaded images, comments, messages, and purchase history) will be permanently deleted.
        </p>
      </div>
      <div class="admin-modal-card-footer">
        <a href="admin.php" class="admin-nav-btn">Cancel</a>
        <button type="submit" name="confirm" value="Yes" class="admin-btn-danger">Yes, Delete User</button>
      </div>
    </form>
  <?php endif; ?>

  <div class="admin-toolbar">
    <div class="admin-search-group">
      <input type="text" id="adminUserSearch" class="admin-search-input" placeholder="🔍 Filter by name, UserID, or family..." aria-label="Filter users or families">
      <button type="button" id="adminSearchClear" class="admin-nav-btn" style="display:none; padding:6px 10px;">Clear</button>
    </div>
    <div class="admin-toolbar-right">
      <div class="admin-legend" aria-label="Action icon key">
        <span class="admin-legend-item"><span class="admin-user-icon"><?php echo $svg_admin; ?></span> Admin</span>
        <span class="admin-legend-item"><?php echo $svg_edit; ?> Edit</span>
        <span class="admin-legend-item"><?php echo $svg_pwd; ?> Password</span>
        <span class="admin-legend-item"><?php echo $svg_account; ?> Account</span>
        <span class="admin-legend-item"><?php echo $svg_delete; ?> Delete</span>
      </div>
      <button type="button" id="toggleAllFamiliesBtn" class="admin-nav-btn" style="padding:6px 11px; font-size:12.5px;">Collapse All</button>
    </div>
  </div>

  <div class="admin-families-list" id="adminFamiliesList">
    <?php foreach ($families as $fam): ?>
      <?php
        $memberCount = count($fam['members']);
        $adminCount = (int)$fam['admin_count'];
        $famSearchAttr = strtolower($fam['label'] . ' ' . $fam['family_raw']);
      ?>
      <details class="admin-family-card" data-family-search="<?php echo escapeText($famSearchAttr); ?>" open>
        <summary class="admin-family-header">
          <div class="admin-family-title-wrap">
            <span class="admin-family-name"><?php echo escapeText($fam['label']); ?></span>
            <span class="admin-family-count"><?php echo (int)$memberCount; ?></span>
          </div>
          <span class="admin-family-chevron" aria-hidden="true">▾</span>
        </summary>

        <table class="admin-family-table">
          <tbody>
            <?php foreach ($fam['members'] as $row): ?>
              <?php
                $uid = (string)$row['userid'];
                $u_escaped = escapeText($uid);
                $u_encoded = urlencode($uid);
                $fullName = trim($row['firstname'] . ' ' . $row['lastname'] . ' ' . ($row['suffix'] ?? ''));
                if ($fullName === '') {
                  $fullName = $uid;
                }
                $fullNameEsc = escapeText($fullName);
                $isSelf = ($uid === ($_SESSION['userid'] ?? ''));
                $isAdmin = ((int)$row['admin'] === 1);
                $rowSearchAttr = strtolower($fullName . ' ' . $uid . ' ' . $fam['family_raw']);
                $lastLoginFormatted = !empty($row['lastLoginDate']) ? parseDate($row['lastLoginDate'], 1) : 'Never';
              ?>
              <tr class="admin-user-row" data-user-search="<?php echo escapeText($rowSearchAttr); ?>">
                <td>
                  <div class="admin-person-primary">
                    <span class="admin-person-name"><?php echo $fullNameEsc; ?></span>
                    <?php if ($isAdmin): ?>
                      <span class="admin-user-icon" title="Site Administrator" aria-label="Site Administrator"><?php echo $svg_admin; ?></span>
                    <?php endif; ?>
                    <span class="admin-userid-pill"><?php echo $u_escaped; ?></span>
                    <?php if ($isSelf): ?>
                      <span class="admin-you-badge">You</span>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="col-meta-mobile">
                  <span class="admin-last-login">
                    <span class="admin-mobile-label">Last login:</span><?php echo escapeText($lastLoginFormatted); ?>
                  </span>
                </td>
                <td class="col-actions">
                  <div class="admin-row-actions">
                    <a
                      href="admin.php?action=edit&amp;userid=<?php echo $u_encoded; ?>"
                      class="admin-icon-btn"
                      title="Edit User (<?php echo $u_escaped; ?>)"
                      aria-label="Edit User <?php echo $fullNameEsc; ?>"
                    ><?php echo $svg_edit; ?></a>
                    <a
                      href="./updateAccount/changePassword.php?userid=<?php echo $u_encoded; ?>"
                      class="admin-icon-btn admin-icon-pwd"
                      title="Change Password for <?php echo $fullNameEsc; ?>"
                      aria-label="Change Password for <?php echo $fullNameEsc; ?>"
                    ><?php echo $svg_pwd; ?></a>
                    <a
                      href="./updateAccount/updateAccount.php?target_userid=<?php echo $u_encoded; ?>"
                      class="admin-icon-btn admin-icon-account"
                      title="Manage Account &amp; Sharing for <?php echo $fullNameEsc; ?>"
                      aria-label="Manage Account and Sharing for <?php echo $fullNameEsc; ?>"
                    ><?php echo $svg_account; ?></a>
                    <a
                      href="admin.php?action=delete&amp;userid=<?php echo $u_encoded; ?>"
                      class="admin-icon-btn admin-icon-delete"
                      title="Delete User (<?php echo $u_escaped; ?>)"
                      aria-label="Delete User <?php echo $fullNameEsc; ?>"
                    ><?php echo $svg_delete; ?></a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
    <?php endforeach; ?>
  </div>

  <div id="adminEmptySearch" class="admin-empty-search">
    No users or families match your filter.
  </div>

  <div class="account-section-card" id="maintenance" style="margin-top:16px;">
    <div class="account-section-header">
      <span>🛠️ Maintenance Tasks</span>
    </div>
    <div class="account-section-body">
      <div style="display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px;">
        <div class="account-picker-help" style="margin:0;">
          Scan the <code>uploads/</code> directory for image files that are no longer referenced by any wishlist item.
        </div>
        <a href="admin.php?action=findOrphanImages#maintenance" class="admin-nav-btn admin-nav-btn-primary">
          🖼️ Find Unreferenced Images
        </a>
      </div>

      <?php if ($orphan_files !== null): ?>
        <?php
          $orphan_count = count($orphan_files);
          $orphan_total_bytes = array_sum(array_column($orphan_files, 'size'));
          $base_uploads_url = rtrim($base_url, '/') . '/uploads/';
        ?>
        <div style="margin-top:12px; padding-top:12px; border-top:1px solid #e2e8f0;">
          <?php if ($orphan_count === 0): ?>
            <div class="admin-alert admin-alert-success" style="margin-bottom:0;">
              No unreferenced image files found on disk. Everything in <code>uploads/</code> is in use.
            </div>
          <?php else: ?>
            <form method="post" action="admin.php#maintenance" id="orphanImagesForm" onsubmit="return confirmOrphanDelete();">
              <input type="hidden" name="action" value="deleteOrphanImages">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_escaped; ?>">

              <div class="admin-toolbar" style="margin-bottom:8px;">
                <label class="admin-checkbox-row" style="font-size:13px; padding:4px 8px;">
                  <input type="checkbox" id="orphanSelectAll" class="admin-role-checkbox">
                  <span><b>Select All</b> (<?php echo (int)$orphan_count; ?> <?php echo $orphan_count === 1 ? 'file' : 'files'; ?> &middot; <?php echo escapeText(formatAdminBytes($orphan_total_bytes)); ?>)</span>
                </label>
                <div class="admin-top-actions">
                  <a href="admin.php" class="admin-nav-btn admin-btn-sm">Close</a>
                  <button type="submit" id="deleteOrphansBtn" class="admin-btn-danger admin-btn-sm" disabled>
                    🗑️ Delete Selected (<span id="orphanSelectedCount">0</span>)
                  </button>
                </div>
              </div>

              <div class="admin-family-card">
                <table class="admin-family-table">
                  <thead>
                    <tr>
                      <th style="width:32px;"></th>
                      <th style="width:68px;">Preview</th>
                      <th>File</th>
                      <th>Size</th>
                      <th>Modified</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($orphan_files as $of): ?>
                      <?php
                        $fnameEsc = escapeText($of['filename']);
                        $fUrlEsc = escapeText($base_uploads_url . rawurlencode($of['filename']) . '?v=' . $of['mtime']);
                      ?>
                      <tr>
                        <td>
                          <input
                            type="checkbox"
                            name="orphan_files[]"
                            value="<?php echo $fnameEsc; ?>"
                            class="admin-role-checkbox orphan-checkbox"
                            aria-label="Select <?php echo $fnameEsc; ?>"
                          >
                        </td>
                        <td>
                          <a href="<?php echo $fUrlEsc; ?>" target="_blank" rel="noopener noreferrer" title="Open <?php echo $fnameEsc; ?>">
                            <img src="<?php echo $fUrlEsc; ?>" alt="<?php echo $fnameEsc; ?>" class="item-thumbnail" style="width:42px; height:42px;" loading="lazy" decoding="async">
                          </a>
                        </td>
                        <td>
                          <span class="admin-userid-pill"><?php echo $fnameEsc; ?></span>
                        </td>
                        <td>
                          <span class="admin-last-login"><?php echo escapeText(formatAdminBytes($of['size'])); ?></span>
                        </td>
                        <td>
                          <span class="admin-last-login"><?php echo escapeText(date('Y-m-d H:i', $of['mtime'])); ?></span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
(function() {
  var searchInput = document.getElementById('adminUserSearch');
  var clearBtn = document.getElementById('adminSearchClear');
  var toggleAllBtn = document.getElementById('toggleAllFamiliesBtn');
  var familyCards = Array.prototype.slice.call(document.querySelectorAll('.admin-family-card'));
  var emptyNotice = document.getElementById('adminEmptySearch');

  function updateToggleBtnLabel() {
    if (!toggleAllBtn) return;
    var visibleCards = familyCards.filter(function(c) { return c.style.display !== 'none'; });
    var anyOpen = visibleCards.some(function(c) { return c.hasAttribute('open'); });
    toggleAllBtn.textContent = anyOpen ? 'Collapse All' : 'Expand All';
  }

  familyCards.forEach(function(card) {
    card.addEventListener('toggle', updateToggleBtnLabel);
  });

  if (toggleAllBtn) {
    toggleAllBtn.addEventListener('click', function() {
      var visibleCards = familyCards.filter(function(c) { return c.style.display !== 'none'; });
      var shouldClose = visibleCards.some(function(c) { return c.hasAttribute('open'); });
      visibleCards.forEach(function(c) {
        if (shouldClose) {
          c.removeAttribute('open');
        } else {
          c.setAttribute('open', '');
        }
      });
      updateToggleBtnLabel();
    });
  }

  function applyFilter() {
    if (!searchInput) return;
    var q = (searchInput.value || '').toLowerCase().trim();
    if (clearBtn) {
      clearBtn.style.display = q !== '' ? 'inline-flex' : 'none';
    }

    var visibleFamilies = 0;
    familyCards.forEach(function(card) {
      var famMatch = q !== '' && (card.getAttribute('data-family-search') || '').indexOf(q) !== -1;
      var rows = Array.prototype.slice.call(card.querySelectorAll('.admin-user-row'));
      var visibleRows = 0;

      rows.forEach(function(row) {
        var rowMatch = q === '' || famMatch || (row.getAttribute('data-user-search') || '').indexOf(q) !== -1;
        row.style.display = rowMatch ? '' : 'none';
        if (rowMatch) visibleRows++;
      });

      if (visibleRows > 0) {
        card.style.display = '';
        if (q !== '') {
          card.setAttribute('open', '');
        }
        visibleFamilies++;
      } else {
        card.style.display = 'none';
      }
    });

    if (emptyNotice) {
      emptyNotice.style.display = (visibleFamilies === 0 && familyCards.length > 0) ? 'block' : 'none';
    }
    updateToggleBtnLabel();
  }

  if (searchInput) {
    searchInput.addEventListener('input', applyFilter);
  }
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      searchInput.value = '';
      applyFilter();
      searchInput.focus();
    });
  }

  var orphanSelectAll = document.getElementById('orphanSelectAll');
  var orphanCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.orphan-checkbox'));
  var deleteOrphansBtn = document.getElementById('deleteOrphansBtn');
  var orphanSelectedCount = document.getElementById('orphanSelectedCount');

  function updateOrphanSelection() {
    var checkedCount = orphanCheckboxes.filter(function(cb) { return cb.checked; }).length;
    if (orphanSelectedCount) {
      orphanSelectedCount.textContent = String(checkedCount);
    }
    if (deleteOrphansBtn) {
      deleteOrphansBtn.disabled = (checkedCount === 0);
    }
    if (orphanSelectAll && orphanCheckboxes.length > 0) {
      orphanSelectAll.checked = (checkedCount === orphanCheckboxes.length);
      orphanSelectAll.indeterminate = (checkedCount > 0 && checkedCount < orphanCheckboxes.length);
    }
  }

  if (orphanSelectAll) {
    orphanSelectAll.addEventListener('change', function() {
      var checked = orphanSelectAll.checked;
      orphanCheckboxes.forEach(function(cb) {
        cb.checked = checked;
      });
      updateOrphanSelection();
    });
  }

  orphanCheckboxes.forEach(function(cb) {
    cb.addEventListener('change', updateOrphanSelection);
  });

  window.confirmOrphanDelete = function() {
    var checkedCount = orphanCheckboxes.filter(function(cb) { return cb.checked; }).length;
    if (checkedCount === 0) {
      return false;
    }
    return window.confirm('Permanently delete ' + checkedCount + ' unreferenced image file(s) from disk? This cannot be undone.');
  };
})();
</script>
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
  <link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
  <title>WishList Setup</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|admin.php:Admin Page|:Change WishList Settings", true); ?>
<div class="admin-dashboard">
  <?php if ($error !== ''): ?>
    <div class="admin-alert admin-alert-error"><?php echo $error; ?></div>
  <?php endif; ?>

  <form method="post" action="admin.php" class="admin-modal-card admin-modal-card-wide">
    <input type="hidden" name="setup" value="true">
    <input type="hidden" name="updateValues" value="true">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <div class="admin-modal-card-header">
      <span>⚙️ WishList Site &amp; Database Settings</span>
    </div>
    <div class="admin-modal-card-body">
      <div class="admin-form-row">
        <label for="cfg_base_dir">Base Directory *</label>
        <input type="text" id="cfg_base_dir" name="base_dir" value="<?php echo escapeText($defaultDir); ?>" required>
      </div>
      <div class="admin-form-row">
        <label for="cfg_base_url">Base URL *</label>
        <input type="text" id="cfg_base_url" name="base_url" value="<?php echo escapeText($defaultUrl); ?>" required>
      </div>
      <div class="admin-form-row">
        <label for="cfg_admin_email">Primary Admin Email *</label>
        <input type="email" id="cfg_admin_email" name="admin_email" value="<?php echo escapeText($new_admin_email); ?>" required>
      </div>
      <div class="admin-form-row">
        <label for="cfg_db_loc">Database Server *</label>
        <input type="text" id="cfg_db_loc" name="db_loc" value="<?php echo escapeText($new_db_loc); ?>" required>
      </div>
      <div class="admin-form-row">
        <label for="cfg_db_name">Database Name *</label>
        <input type="text" id="cfg_db_name" name="db_name" value="<?php echo escapeText($new_db_name); ?>" required>
      </div>
      <div class="admin-form-row">
        <label for="cfg_db_userid">Database UserID *</label>
        <input type="text" id="cfg_db_userid" name="db_userid" value="<?php echo escapeText($new_db_userid); ?>" required>
      </div>
      <div class="admin-form-row" style="margin-bottom:0;">
        <label for="cfg_db_pass"><?php echo $has_existing_config ? 'Database Password' : 'Database Password *'; ?></label>
        <input type="password" id="cfg_db_pass" name="db_pass" value="" autocomplete="new-password" placeholder="<?php echo $has_existing_config ? 'Leave blank to keep existing password' : 'Required'; ?>">
      </div>
    </div>
    <div class="admin-modal-card-footer">
      <?php if ($has_existing_config): ?>
        <a href="admin.php" class="admin-nav-btn">Cancel</a>
      <?php endif; ?>
      <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Save Settings</button>
    </div>
  </form>

  <?php if ($show_root_form): ?>
    <form method="post" action="admin.php" class="admin-modal-card admin-modal-card-wide">
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

      <div class="admin-modal-card-header danger">
        <span>Database Root Credentials</span>
      </div>
      <div class="admin-modal-card-body">
        <div class="admin-form-row">
          <label for="cfg_root_user">Root User</label>
          <input type="text" id="cfg_root_user" name="db_root_user" value="root" required>
        </div>
        <div class="admin-form-row" style="margin-bottom:0;">
          <label for="cfg_root_pass">Root Password</label>
          <input type="password" id="cfg_root_pass" name="db_root_pass" value="" autocomplete="off" required>
        </div>
      </div>
      <div class="admin-modal-card-footer">
        <button type="submit" class="admin-btn-danger">Create Database &amp; Grant Privileges</button>
      </div>
    </form>
  <?php endif; ?>
</div>
</td>
</tr>
</table>
</body>
</html>
<?php
}
