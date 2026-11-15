<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$message = "<h2>&nbsp;</h2>";
$is_admin = (!empty($_SESSION["admin"]) && $_SESSION["admin"] == 1);

$target_userid = $_SESSION["userid"];

if (isset($_REQUEST["target_userid"]) && $_REQUEST["target_userid"] !== '') {
    $requested_userid = trim((string)$_REQUEST["target_userid"]);

    if ($is_admin || $requested_userid === $_SESSION["userid"]) {
        $target_userid = $requested_userid;
        if ($target_userid !== $_SESSION["userid"]) {
            $_SESSION["euserid"] = $target_userid;
        } else {
            unset($_SESSION["euserid"]);
        }
    } else {
        $stmt_check_access = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($stmt_check_access, "ss", $requested_userid, $_SESSION["userid"]);
        mysqli_stmt_execute($stmt_check_access);
        $result_check_access = mysqli_stmt_get_result($stmt_check_access);

        if (($row_check_access = mysqli_fetch_assoc($result_check_access)) && $row_check_access["allowEdit"] == '1') {
            $target_userid = $requested_userid;
            $_SESSION["euserid"] = $target_userid;
        } else {
            header("Location: updateAccount.php");
            exit;
        }
    }
} elseif (isset($_SESSION["euserid"]) && $_SESSION["euserid"] !== $_SESSION["userid"]) {
    $candidate = $_SESSION["euserid"];
    if ($is_admin) {
        $target_userid = $candidate;
    } else {
        $stmt_check_access = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($stmt_check_access, "ss", $candidate, $_SESSION["userid"]);
        mysqli_stmt_execute($stmt_check_access);
        $result_check_access = mysqli_stmt_get_result($stmt_check_access);
        if (($row_check_access = mysqli_fetch_assoc($result_check_access)) && $row_check_access["allowEdit"] == '1') {
            $target_userid = $candidate;
        } else {
            unset($_SESSION["euserid"]);
            $target_userid = $_SESSION["userid"];
        }
    }
} else {
    unset($_SESSION["euserid"]);
}

$userid = $target_userid;
// Only the actual account owner or a site administrator may modify sharing/access permissions
$can_manage_permissions = ($userid === $_SESSION["userid"] || $is_admin);

$action = $_REQUEST["action"] ?? "";
$permission_actions = ["stopViewOther", "stopViewMine", "startViewOther", "startViewMine", "hideContactInfo"];

if (in_array($action, $permission_actions, true)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        die("Method Not Allowed");
    }
    verifyCsrfOrDie(true);
    if (!$can_manage_permissions) {
        http_response_code(403);
        die("Unauthorized: Only the account owner or an administrator can modify list sharing permissions.");
    }

    if ($action === "stopViewOther") {
        $removeuserid = trim($_POST["userid"] ?? "");
        if ($removeuserid === $userid) {
            $message = "<h2 style='color:#c00;'>You cannot remove your own list entry.</h2>";
        } elseif ($removeuserid !== "") {
            $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
            mysqli_stmt_bind_param($stmt, "ss", $removeuserid, $userid);
            mysqli_stmt_execute($stmt);
            $message = "<h2>Successfully stopped viewing " . escapeText($removeuserid) . "</h2>";
        }
    } elseif ($action === "stopViewMine") {
        $removeuserid = trim($_POST["userid"] ?? "");
        if ($removeuserid === $userid) {
            $message = "<h2 style='color:#c00;'>You cannot remove yourself from your own list.</h2>";
        } elseif ($removeuserid !== "") {
            $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
            mysqli_stmt_bind_param($stmt, "ss", $userid, $removeuserid);
            mysqli_stmt_execute($stmt);
            $message = "<h2>Successfully removed " . escapeText($removeuserid) . " from viewing list of " . escapeText($userid) . "</h2>";
        }
    } elseif ($action === "startViewOther") {
        $adduserid = trim($_POST["userid"] ?? "");
        $requesterId = $userid;

        if ($adduserid === "" || $adduserid === $requesterId) {
            $message = "<h2 style='color:#c00;'>Invalid user selection.</h2>";
        } else {
            // Check if target exists
            $stmt_p = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_p, "s", $adduserid);
            mysqli_stmt_execute($stmt_p);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
                $message = "<h2 style='color:#c00;'>Selected user does not exist.</h2>";
            } else {
                // Check if viewer already has access in viewList
                $stmt_vl = mysqli_prepare($link, "SELECT 1 FROM viewList WHERE pid = ? AND viewer = ?");
                mysqli_stmt_bind_param($stmt_vl, "ss", $adduserid, $requesterId);
                mysqli_stmt_execute($stmt_vl);
                if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_vl))) {
                    $message = "<h2>You already have access to view this person's list.</h2>";
                } else {
                    $stmt = mysqli_prepare($link, "SELECT id, status FROM accessRequests WHERE requesterId = ? AND targetId = ? ORDER BY id DESC LIMIT 1");
                    mysqli_stmt_bind_param($stmt, "ss", $requesterId, $adduserid);
                    mysqli_stmt_execute($stmt);
                    $existing_req = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

                    if ($existing_req && $existing_req['status'] === 'pending') {
                        $message = "<h2>You already have a pending access request for this person.</h2>";
                    } elseif ($existing_req) {
                        // Re-activate previously denied or stale approved request
                        $req_id = (int)$existing_req['id'];
                        $stmt_re = mysqli_prepare($link, "UPDATE accessRequests SET status = 'pending', requestDate = NOW(), notified = 0 WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_re, "i", $req_id);
                        mysqli_stmt_execute($stmt_re);
                        $message = "<h2>Your request to view this person's list has been sent. You will be notified when they respond.</h2>";
                    } else {
                        $stmt_ins = mysqli_prepare($link, "INSERT INTO accessRequests (requesterId, targetId, status, notified) VALUES (?, ?, 'pending', 0)");
                        mysqli_stmt_bind_param($stmt_ins, "ss", $requesterId, $adduserid);
                        mysqli_stmt_execute($stmt_ins);
                        $message = "<h2>Your request to view this person's list has been sent. You will be notified when they respond.</h2>";
                    }
                }
            }
        }
    } elseif ($action === "startViewMine") {
        $adduserid = trim($_POST["userid"] ?? "");
        if ($adduserid !== "") {
            $stmt_p = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_p, "s", $adduserid);
            mysqli_stmt_execute($stmt_p);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
                $message = "<h2 style='color:#c00;'>Selected user does not exist.</h2>";
            } else {
                $stmt = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE viewer = ? AND pid = ?");
                mysqli_stmt_bind_param($stmt, "ss", $adduserid, $userid);
                mysqli_stmt_execute($stmt);
                if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
                    $message = "<h2>That person can already view your list.</h2>";
                } else {
                    $stmt_ins = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (0, 1, 0, ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_ins, "ss", $userid, $adduserid);
                    mysqli_stmt_execute($stmt_ins);
                    $message = "<h2>Person added (Read-Only by default; uncheck Read Only below to allow purchasing).</h2>";
                }
            }
        }
    } elseif ($action === "hideContactInfo") {
        $stmt_viewers = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ?");
        mysqli_stmt_bind_param($stmt_viewers, "s", $userid);
        mysqli_stmt_execute($stmt_viewers);
        $result = mysqli_stmt_get_result($stmt_viewers);

        $contactArr = isset($_POST['admin']) && is_array($_POST['admin']) ? $_POST['admin'] : [];
        $readOnlyArr = isset($_POST['readOnly']) && is_array($_POST['readOnly']) ? $_POST['readOnly'] : [];
        $allowEditArr = isset($_POST['allowEdit']) && is_array($_POST['allowEdit']) ? $_POST['allowEdit'] : [];

        $stmt_upd = mysqli_prepare($link, "UPDATE viewList SET viewContactInfo = ?, readOnly = ?, allowEdit = ? WHERE viewer = ? AND pid = ?");

        while ($row_v = mysqli_fetch_assoc($result)) {
            $viewerId = $row_v['viewer'];
            $viewContactInfo = in_array($viewerId, $contactArr, true) ? 1 : 0;
            $readOnly = in_array($viewerId, $readOnlyArr, true) ? 1 : 0;
            $allowEdit = in_array($viewerId, $allowEditArr, true) ? 1 : 0;

            if ($allowEdit === 1) {
                $readOnly = 0;
            }

            mysqli_stmt_bind_param($stmt_upd, "iiiss", $viewContactInfo, $readOnly, $allowEdit, $viewerId, $userid);
            mysqli_stmt_execute($stmt_upd);
        }

        $message = "<h2>Privileges updated</h2>";
    }
}

$stmt_info = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_info, "s", $userid);
mysqli_stmt_execute($stmt_info);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info)) ?? [];

$fullName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '') . ' ' . ($row['suffix'] ?? ''));
$bdayDisplay = (!empty($row['bmonth']) && !empty($row['bday'])) ? ($row['bmonth'] . ' ' . $row['bday']) : 'Not set';
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Update Account Information</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|:Update Account (" . $userid . ")", false, "listAccess"); ?>
<center>
<p>&nbsp;</p>

<?php echo $message; ?>

<table width="100%">
<tr><td align="center" valign="top">
  <form method="post" action="editInfo.php">
    <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
    <table style="border-collapse: collapse;" border="0" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
      <tr>
        <td colspan="2" align="center" bgcolor="#6702cc">
          <font size="3" color="white"><b>Information On File</b></font>
        </td>
      </tr>
      <tr>
        <td align="right"><b>Name</b></td>
        <td bgcolor="white"><?php echo escapeText($fullName); ?></td>
      </tr>
      <tr>
        <td align="right"><b>Birthday</b></td>
        <td bgcolor="white"><?php echo escapeText($bdayDisplay); ?></td>
      </tr>
      <?php if (!empty($row['email'])): ?>
      <tr>
        <td align="right"><b>Email</b></td>
        <td bgcolor="white"><?php echo escapeText($row['email']); ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td align="center" colspan="2" bgcolor="#c0c0c0">
          <input type="submit" value="Edit Personal Info" class="buttonstyle">
        </td>
      </tr>
    </table>
  </form>

  <?php if ($can_manage_permissions): ?>
    <?php $pwd_href = ($userid !== $_SESSION['userid']) ? "changePassword.php?userid=" . urlencode($userid) : "changePassword.php"; ?>
    <button class="buttonstyle" onclick="location.href='<?php echo escapeText($pwd_href); ?>'">Change Password</button><br>
  <?php endif; ?>
</td></tr>
</table>

<?php if ($can_manage_permissions): ?>
<table border="0" width="90%">
<tr>
<td align="center" valign="top" width="50%">

  <table bgcolor="lightyellow" style="border-collapse: collapse;" border="0" cellpadding="8" cellspacing="0">
  <tr><td>
    <table style="border-collapse: collapse;" border="0" cellpadding="2" cellspacing="0" width="100%">
      <tr>
        <td align="center" bgcolor="#6702cc">
          <font size="3" color="white"><b>Add a person so they can view your list</b></font>
        </td>
      </tr>
      <tr>
        <td>
          Enter the person's name or username:<br>
          <form method="post" action="updateAccount.php">
            <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
            <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
            <input type="hidden" name="action" value="addUser">
            <input type="text" size="20" name="searchUser" value="<?php echo $action === 'addUser' ? escapeText($_REQUEST['searchUser'] ?? '') : ''; ?>">
            <input type="submit" value="Go">
          </form>
          <?php
          if ($action === "addUser") {
              $searchUser = trim($_REQUEST["searchUser"] ?? "");
              $search = "%" . $searchUser . "%";
              $stmt_add = mysqli_prepare($link, "SELECT firstname, lastname, suffix, userid FROM people WHERE (userid LIKE ? OR firstname LIKE ? OR lastname LIKE ?) AND userid != ? ORDER BY lastname, firstname");
              mysqli_stmt_bind_param($stmt_add, "ssss", $search, $search, $search, $userid);
              mysqli_stmt_execute($stmt_add);
              $res_add = mysqli_stmt_get_result($stmt_add);
              while ($srow = mysqli_fetch_assoc($res_add)) {
                  $sName = trim($srow["firstname"] . " " . $srow["lastname"] . " " . ($srow["suffix"] ?? ""));
                  echo '<form method="post" action="updateAccount.php" style="display:inline; margin:0;">';
                  echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
                  echo '<input type="hidden" name="action" value="startViewMine">';
                  echo '<input type="hidden" name="target_userid" value="' . escapeText($userid) . '">';
                  echo '<input type="hidden" name="userid" value="' . escapeText($srow["userid"]) . '">';
                  echo '<button type="submit" class="menuLink" style="background:none;border:none;padding:0;color:#0000cc;cursor:pointer;text-decoration:underline;">[Add]</button>';
                  echo '</form> ' . escapeText($sName) . '<br>';
              }
          }
          ?>
        </td>
      </tr>
    </table>

    <br>

    <form action="updateAccount.php" method="post" id="privForm">
      <input type="hidden" name="action" value="hideContactInfo">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
      <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
      <table style="border-collapse: collapse;" border="0" cellpadding="3" cellspacing="0">
        <tr>
          <td colspan="5" align="center" bgcolor="#6702cc">
            <font size="3" color="white"><b>People who can view your list</b></font>
          </td>
        </tr>
        <tr bgcolor="lightYellow">
          <td>&nbsp;</td>
          <td valign="bottom"><b>Name</b></td>
          <td align="center"><b>Show Birthday &amp;<br>Contact Info</b></td>
          <td align="center"><b>Read Only<br>Access</b></td>
          <td align="center"><b>Edit<br>Access</b></td>
        </tr>
        <?php
        $stmt_viewers_list = mysqli_prepare($link, "SELECT viewList.viewer, people.firstname, people.lastname, people.suffix, people.userid, viewList.viewContactInfo, viewList.readOnly, viewList.allowEdit FROM viewList JOIN people ON viewList.viewer = people.userid WHERE viewList.pid = ? ORDER BY people.lastname, people.firstname");
        mysqli_stmt_bind_param($stmt_viewers_list, "s", $userid);
        mysqli_stmt_execute($stmt_viewers_list);
        $res_vl = mysqli_stmt_get_result($stmt_viewers_list);
        $viewerRows = [];
        while ($vrow = mysqli_fetch_assoc($res_vl)) {
            $viewerRows[] = $vrow;
            $vName = trim($vrow["firstname"] . " " . $vrow["lastname"] . " " . ($vrow["suffix"] ?? ""));
            $vu = escapeText($vrow["userid"]);
            echo "<tr>";
            echo "<td>";
            if ($vrow["userid"] !== $userid) {
                echo "<button type=\"submit\" form=\"rmMine_" . $vu . "\" class=\"menuLink\" style=\"background:none;border:none;padding:0;color:#0000cc;cursor:pointer;text-decoration:underline;\">[Remove]</button>";
            } else {
                echo "<small>(Self)</small>";
            }
            echo "</td>";
            echo "<td>" . escapeText($vName) . "</td>";
            echo "<td align=\"center\"><input name=\"admin[]\" value=\"" . $vu . "\" type=\"checkbox\"" . ($vrow["viewContactInfo"] ? " checked" : "") . "></td>";
            echo "<td align=\"center\"><input name=\"readOnly[]\" value=\"" . $vu . "\" type=\"checkbox\"" . ($vrow["readOnly"] ? " checked" : "") . "></td>";
            echo "<td align=\"center\"><input name=\"allowEdit[]\" value=\"" . $vu . "\" type=\"checkbox\"" . ($vrow["allowEdit"] ? " checked" : "") . "></td>";
            echo "</tr>";
        }
        ?>
        <tr>
          <td colspan="5" align="center" bgcolor="#c0c0c0">
            <input type="submit" value="Make Changes" style="font-weight:bold">
          </td>
        </tr>
      </table>
    </form>
    <?php foreach ($viewerRows as $vrow): ?>
      <?php if ($vrow["userid"] !== $userid): $vu = escapeText($vrow["userid"]); ?>
      <form id="rmMine_<?php echo $vu; ?>" method="post" action="updateAccount.php" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
        <input type="hidden" name="action" value="stopViewMine">
        <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
        <input type="hidden" name="userid" value="<?php echo $vu; ?>">
      </form>
      <?php endif; ?>
    <?php endforeach; ?>
  </td></tr>
  </table>

</td>
<td valign="top" align="center" width="50%">

  <table bgcolor="lightyellow" style="border-collapse: collapse;" border="0" cellpadding="8" cellspacing="0">
  <tr><td>
    <table style="border-collapse: collapse;" border="0" cellpadding="2" cellspacing="0" width="100%">
      <tr>
        <td align="center" bgcolor="#6702cc">
          <font size="3" color="white"><b>Find someone else's list to view</b></font>
        </td>
      </tr>
      <tr>
        <td>
          Enter the person's name or username:<br>
          <form method="post" action="updateAccount.php">
            <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
            <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
            <input type="hidden" name="action" value="findUser">
            <input type="text" size="20" name="searchUser" value="<?php echo $action === 'findUser' ? escapeText($_REQUEST['searchUser'] ?? '') : ''; ?>">
            <input type="submit" value="Go">
          </form>

          <?php
          if ($action === "findUser") {
              $searchUser = trim($_REQUEST["searchUser"] ?? "");
              $search = "%" . $searchUser . "%";
              $stmt_find = mysqli_prepare($link, "SELECT firstname, lastname, suffix, userid FROM people WHERE (userid LIKE ? OR firstname LIKE ? OR lastname LIKE ?) AND userid != ? ORDER BY lastname, firstname");
              mysqli_stmt_bind_param($stmt_find, "ssss", $search, $search, $search, $userid);
              mysqli_stmt_execute($stmt_find);
              $res_find = mysqli_stmt_get_result($stmt_find);

              while ($frow = mysqli_fetch_assoc($res_find)) {
                  $fName = trim($frow["firstname"] . " " . $frow["lastname"] . " " . ($frow["suffix"] ?? ""));
                  echo '<form method="post" action="updateAccount.php" style="display:inline; margin:0;">';
                  echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
                  echo '<input type="hidden" name="action" value="startViewOther">';
                  echo '<input type="hidden" name="target_userid" value="' . escapeText($userid) . '">';
                  echo '<input type="hidden" name="userid" value="' . escapeText($frow["userid"]) . '">';
                  echo '<button type="submit" class="menuLink" style="background:none;border:none;padding:0;color:#0000cc;cursor:pointer;text-decoration:underline;">[Request Access]</button>';
                  echo '</form> ' . escapeText($fName) . '<br>';
              }
              echo "<br>";
          }
          ?>

          <table style="border-collapse: collapse;" border="0" cellpadding="3" cellspacing="0" width="100%">
            <tr>
              <td colspan="2" align="center" bgcolor="#6702cc">
                <font size="3" color="white"><b>Lists you can view</b></font>
              </td>
            </tr>
            <?php
            $stmt_viewable = mysqli_prepare($link, "SELECT people.firstname, people.lastname, people.suffix, people.userid FROM viewList JOIN people ON viewList.pid = people.userid WHERE viewList.viewer = ? ORDER BY people.lastname, people.firstname");
            mysqli_stmt_bind_param($stmt_viewable, "s", $userid);
            mysqli_stmt_execute($stmt_viewable);
            $res_viewable = mysqli_stmt_get_result($stmt_viewable);

            while ($orow = mysqli_fetch_assoc($res_viewable)) {
                $oName = trim($orow["firstname"] . " " . $orow["lastname"] . " " . ($orow["suffix"] ?? ""));
                echo "<tr><td>";
                if ($orow["userid"] !== $userid) {
                    echo '<form method="post" action="updateAccount.php" style="display:inline; margin:0;">';
                    echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
                    echo '<input type="hidden" name="action" value="stopViewOther">';
                    echo '<input type="hidden" name="target_userid" value="' . escapeText($userid) . '">';
                    echo '<input type="hidden" name="userid" value="' . escapeText($orow["userid"]) . '">';
                    echo '<button type="submit" class="menuLink" style="background:none;border:none;padding:0;color:#0000cc;cursor:pointer;text-decoration:underline;">[Remove]</button>';
                    echo '</form>';
                } else {
                    echo '<small>(Self)</small>';
                }
                echo "</td><td>" . escapeText($oName) . "</td></tr>";
            }
            ?>
          </table>
        </td>
      </tr>
    </table>
  </td></tr>
  </table>

</td>
</tr>
</table>
<?php else: ?>
  <p><i>Note: You have edit access to manage <?php echo escapeText($fullName); ?>'s wishlist and profile details, while sharing permissions can only be changed by the account owner or an administrator.</i></p>
<?php endif; ?>

</center>
</td>
</tr>
</table>
</body>
</html>
