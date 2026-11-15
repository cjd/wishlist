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
        $selectedIds = [];
        if (isset($_POST["userids"]) && is_array($_POST["userids"])) {
            foreach ($_POST["userids"] as $uidItem) {
                $uidTrim = trim((string)$uidItem);
                if ($uidTrim !== "") {
                    $selectedIds[$uidTrim] = true;
                }
            }
        }
        $singleUid = trim((string)($_POST["userid"] ?? ""));
        if ($singleUid !== "") {
            $selectedIds[$singleUid] = true;
        }
        $requesterId = $userid;
        unset($selectedIds[$requesterId]);

        if (empty($selectedIds)) {
            $message = "<h2 style='color:#c00;'>Please select at least one person whose list you want to view.</h2>";
        } else {
            $sentCount = 0;
            $alreadyViewCount = 0;
            $alreadyPendingCount = 0;

            $stmt_p = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
            $stmt_vl = mysqli_prepare($link, "SELECT 1 FROM viewList WHERE pid = ? AND viewer = ?");
            $stmt_ex = mysqli_prepare($link, "SELECT id, status FROM accessRequests WHERE requesterId = ? AND targetId = ? ORDER BY id DESC LIMIT 1");
            $stmt_re = mysqli_prepare($link, "UPDATE accessRequests SET status = 'pending', requestDate = NOW(), notified = 0 WHERE id = ?");
            $stmt_ins = mysqli_prepare($link, "INSERT INTO accessRequests (requesterId, targetId, status, notified) VALUES (?, ?, 'pending', 0)");

            foreach (array_keys($selectedIds) as $adduserid) {
                mysqli_stmt_bind_param($stmt_p, "s", $adduserid);
                mysqli_stmt_execute($stmt_p);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
                    continue;
                }

                mysqli_stmt_bind_param($stmt_vl, "ss", $adduserid, $requesterId);
                mysqli_stmt_execute($stmt_vl);
                if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_vl))) {
                    $alreadyViewCount++;
                    continue;
                }

                mysqli_stmt_bind_param($stmt_ex, "ss", $requesterId, $adduserid);
                mysqli_stmt_execute($stmt_ex);
                $existing_req = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_ex));

                if ($existing_req && $existing_req['status'] === 'pending') {
                    $alreadyPendingCount++;
                } elseif ($existing_req) {
                    $req_id = (int)$existing_req['id'];
                    mysqli_stmt_bind_param($stmt_re, "i", $req_id);
                    mysqli_stmt_execute($stmt_re);
                    $sentCount++;
                } else {
                    mysqli_stmt_bind_param($stmt_ins, "ss", $requesterId, $adduserid);
                    mysqli_stmt_execute($stmt_ins);
                    $sentCount++;
                }
            }

            if ($sentCount > 0) {
                $label = ($sentCount === 1) ? "1 access request has been sent." : ($sentCount . " access requests have been sent.");
                $message = "<h2>" . escapeText($label) . " You will be notified when they respond.</h2>";
            } elseif ($alreadyPendingCount > 0) {
                $message = "<h2>You already have pending access request(s) for the selected user(s).</h2>";
            } elseif ($alreadyViewCount > 0) {
                $message = "<h2>You already have access to view the selected list(s).</h2>";
            } else {
                $message = "<h2 style='color:#c00;'>Selected user does not exist.</h2>";
            }
        }
    } elseif ($action === "startViewMine") {
        $selectedIds = [];
        if (isset($_POST["userids"]) && is_array($_POST["userids"])) {
            foreach ($_POST["userids"] as $uidItem) {
                $uidTrim = trim((string)$uidItem);
                if ($uidTrim !== "") {
                    $selectedIds[$uidTrim] = true;
                }
            }
        }
        $singleUid = trim((string)($_POST["userid"] ?? ""));
        if ($singleUid !== "") {
            $selectedIds[$singleUid] = true;
        }
        unset($selectedIds[$userid]);

        $shareContact = !empty($_POST["share_contact"]) ? 1 : 0;

        if (empty($selectedIds)) {
            $message = "<h2 style='color:#c00;'>Please select at least one person to add.</h2>";
        } else {
            $addedCount = 0;
            $alreadyCount = 0;

            $stmt_p = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
            $stmt_chk = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE viewer = ? AND pid = ?");
            // Default readOnly = 0 (Full Purchasing) so family members can check off gifts right away
            $stmt_ins = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (?, 0, 0, ?, ?, NOW())");

            foreach (array_keys($selectedIds) as $adduserid) {
                mysqli_stmt_bind_param($stmt_p, "s", $adduserid);
                mysqli_stmt_execute($stmt_p);
                if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
                    continue;
                }

                mysqli_stmt_bind_param($stmt_chk, "ss", $adduserid, $userid);
                mysqli_stmt_execute($stmt_chk);
                if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk))) {
                    $alreadyCount++;
                    continue;
                }

                mysqli_stmt_bind_param($stmt_ins, "iss", $shareContact, $userid, $adduserid);
                mysqli_stmt_execute($stmt_ins);
                $addedCount++;
            }

            if ($addedCount > 0) {
                $whoText = ($addedCount === 1) ? "1 person added" : ($addedCount . " people added");
                $message = "<h2>" . escapeText($whoText) . " with Full Purchasing access (adjust Read-Only or Contact settings below anytime).</h2>";
            } elseif ($alreadyCount > 0) {
                $message = "<h2>Selected person(s) can already view your list.</h2>";
            } else {
                $message = "<h2 style='color:#c00;'>Selected user does not exist.</h2>";
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

$addViewerGroups = [];
$requestListGroups = [];
if ($can_manage_permissions) {
    $currentViewersMap = [];
    $stmt_cv = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ?");
    mysqli_stmt_bind_param($stmt_cv, "s", $userid);
    mysqli_stmt_execute($stmt_cv);
    $res_cv = mysqli_stmt_get_result($stmt_cv);
    while ($cv = mysqli_fetch_assoc($res_cv)) {
        $currentViewersMap[$cv['viewer']] = true;
    }

    $currentViewableMap = [];
    $stmt_co = mysqli_prepare($link, "SELECT pid FROM viewList WHERE viewer = ?");
    mysqli_stmt_bind_param($stmt_co, "s", $userid);
    mysqli_stmt_execute($stmt_co);
    $res_co = mysqli_stmt_get_result($stmt_co);
    while ($co = mysqli_fetch_assoc($res_co)) {
        $currentViewableMap[$co['pid']] = true;
    }

    $pendingOutgoingMap = [];
    $stmt_po = mysqli_prepare($link, "SELECT targetId FROM accessRequests WHERE requesterId = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt_po, "s", $userid);
    mysqli_stmt_execute($stmt_po);
    $res_po = mysqli_stmt_get_result($stmt_po);
    while ($po = mysqli_fetch_assoc($res_po)) {
        $pendingOutgoingMap[$po['targetId']] = true;
    }

    $stmt_all_p = mysqli_prepare($link, "SELECT userid, firstname, lastname, suffix FROM people WHERE userid != ? ORDER BY lastname, firstname");
    mysqli_stmt_bind_param($stmt_all_p, "s", $userid);
    mysqli_stmt_execute($stmt_all_p);
    $res_all_p = mysqli_stmt_get_result($stmt_all_p);
    while ($pr = mysqli_fetch_assoc($res_all_p)) {
        $fam = trim((string)($pr['lastname'] ?? ''));
        $famLabel = ($fam !== '' ? ($fam . ' Family') : 'Other');
        $dispName = trim(($pr['firstname'] ?? '') . ' ' . ($pr['lastname'] ?? '') . ' ' . ($pr['suffix'] ?? ''));
        if ($dispName === '') {
            $dispName = $pr['userid'];
        }
        $uidKey = $pr['userid'];

        if (empty($currentViewersMap[$uidKey])) {
            $addViewerGroups[$famLabel][] = [
                'userid' => $uidKey,
                'name' => $dispName
            ];
        }
        if (empty($currentViewableMap[$uidKey])) {
            $requestListGroups[$famLabel][] = [
                'userid' => $uidKey,
                'name' => $dispName,
                'pending' => !empty($pendingOutgoingMap[$uidKey])
            ];
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
  <link rel="stylesheet" href="../css/select2.min.css?v=<?php echo (int)@filemtime(__DIR__ . '/../css/select2.min.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Update Account Information</title>
  <script src="../js/jquery-3.7.1.min.js"></script>
  <script src="../js/select2.min.js"></script>
  <script>
  $(document).ready(function() {
    if (typeof $.fn.select2 === 'function') {
      $('#addViewersSelect').select2({
        placeholder: 'Search or pick family members to add...',
        allowClear: true,
        width: '100%'
      });
      $('#requestListsSelect').select2({
        placeholder: 'Search or pick family members to request...',
        allowClear: true,
        width: '100%'
      });
    }
  });
  </script>
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
          <font size="3" color="white"><b>Add people so they can view your list</b></font>
        </td>
      </tr>
      <tr>
        <td style="padding:8px 6px;">
          <form method="post" action="updateAccount.php" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
            <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
            <input type="hidden" name="action" value="startViewMine">
            <div style="margin-bottom:6px; font-size:13px;">Select one or more family members (granted <b>Full Purchasing</b> access by default):</div>
            <select name="userids[]" id="addViewersSelect" multiple style="width:100%; max-width:360px;">
              <?php foreach ($addViewerGroups as $famGroup => $members): ?>
                <optgroup label="<?php echo escapeText($famGroup); ?>">
                  <?php foreach ($members as $m): ?>
                    <option value="<?php echo escapeText($m['userid']); ?>"><?php echo escapeText($m['name']); ?> (<?php echo escapeText($m['userid']); ?>)</option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <label style="display:block; margin:8px 0; font-size:13px; cursor:pointer;">
              <input type="checkbox" name="share_contact" value="1" checked>
              Also share Birthday &amp; Shipping/Contact Info
            </label>
            <input type="submit" value="➕ Grant Access" class="buttonstyle">
          </form>
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
          <font size="3" color="white"><b>Find lists to view</b></font>
        </td>
      </tr>
      <tr>
        <td style="padding:8px 6px;">
          <form method="post" action="updateAccount.php" style="margin:0 0 12px 0;">
            <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
            <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
            <input type="hidden" name="action" value="startViewOther">
            <div style="margin-bottom:6px; font-size:13px;">Select one or more family members to request access to their wishlists:</div>
            <select name="userids[]" id="requestListsSelect" multiple style="width:100%; max-width:360px;">
              <?php foreach ($requestListGroups as $famGroup => $members): ?>
                <optgroup label="<?php echo escapeText($famGroup); ?>">
                  <?php foreach ($members as $m): ?>
                    <option value="<?php echo escapeText($m['userid']); ?>"<?php echo !empty($m['pending']) ? ' disabled' : ''; ?>>
                      <?php echo escapeText($m['name']); ?> (<?php echo escapeText($m['userid']); ?>)<?php echo !empty($m['pending']) ? ' — Pending Request' : ''; ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
            <div style="margin-top:8px;">
              <input type="submit" value="📩 Request Access" class="buttonstyle">
            </div>
          </form>

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
