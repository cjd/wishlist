<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$alert_html = "";
$alert_type = "success";
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
$permission_actions = ["stopViewOther", "stopViewMine", "startViewOther", "startViewMine", "updatePermissions", "hideContactInfo", "updateTextSize"];

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
            $alert_type = "error";
            $alert_html = "You cannot remove your own list entry.";
        } elseif ($removeuserid !== "") {
            $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
            mysqli_stmt_bind_param($stmt, "ss", $removeuserid, $userid);
            mysqli_stmt_execute($stmt);
            $alert_type = "success";
            $alert_html = "Stopped viewing <code>" . escapeText($removeuserid) . "</code>'s list.";
        }
    } elseif ($action === "stopViewMine") {
        $removeuserid = trim($_POST["userid"] ?? "");
        if ($removeuserid === $userid) {
            $alert_type = "error";
            $alert_html = "You cannot remove yourself from your own list.";
        } elseif ($removeuserid !== "") {
            $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
            mysqli_stmt_bind_param($stmt, "ss", $userid, $removeuserid);
            mysqli_stmt_execute($stmt);
            $alert_type = "success";
            $alert_html = "Removed <code>" . escapeText($removeuserid) . "</code> from viewing the list of <code>" . escapeText($userid) . "</code>.";
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
            $alert_type = "error";
            $alert_html = "Please select at least one person whose list you want to view.";
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
                $alert_type = "success";
                $alert_html = escapeText($label) . " You will be notified when they respond.";
            } elseif ($alreadyPendingCount > 0) {
                $alert_type = "success";
                $alert_html = "You already have pending access request(s) for the selected user(s).";
            } elseif ($alreadyViewCount > 0) {
                $alert_type = "success";
                $alert_html = "You already have access to view the selected list(s).";
            } else {
                $alert_type = "error";
                $alert_html = "Selected user does not exist.";
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

        if (empty($selectedIds)) {
            $alert_type = "error";
            $alert_html = "Please select at least one person to add.";
        } else {
            $addedCount = 0;
            $alreadyCount = 0;

            $stmt_p = mysqli_prepare($link, "SELECT userid FROM people WHERE userid = ?");
            $stmt_chk = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE viewer = ? AND pid = ?");
            // Default readOnly = 0 (Full Purchasing)
            $stmt_ins = mysqli_prepare($link, "INSERT INTO viewList (readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (0, 0, ?, ?, NOW())");

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

                mysqli_stmt_bind_param($stmt_ins, "ss", $userid, $adduserid);
                mysqli_stmt_execute($stmt_ins);
                $addedCount++;
            }

            if ($addedCount > 0) {
                $whoText = ($addedCount === 1) ? "1 person added" : ($addedCount . " people added");
                $alert_type = "success";
                $alert_html = escapeText($whoText) . " with Full Purchasing access (adjust Read-Only or Edit settings below anytime).";
            } elseif ($alreadyCount > 0) {
                $alert_type = "success";
                $alert_html = "Selected person(s) can already view your list.";
            } else {
                $alert_type = "error";
                $alert_html = "Selected user does not exist.";
            }
        }
    } elseif ($action === "updatePermissions" || $action === "hideContactInfo") {
        $stmt_viewers = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ?");
        mysqli_stmt_bind_param($stmt_viewers, "s", $userid);
        mysqli_stmt_execute($stmt_viewers);
        $result = mysqli_stmt_get_result($stmt_viewers);

        $readOnlyArr = isset($_POST['readOnly']) && is_array($_POST['readOnly']) ? $_POST['readOnly'] : [];
        $allowEditArr = isset($_POST['allowEdit']) && is_array($_POST['allowEdit']) ? $_POST['allowEdit'] : [];

        $stmt_upd = mysqli_prepare($link, "UPDATE viewList SET readOnly = ?, allowEdit = ? WHERE viewer = ? AND pid = ?");

        while ($row_v = mysqli_fetch_assoc($result)) {
            $viewerId = $row_v['viewer'];
            $readOnly = in_array($viewerId, $readOnlyArr, true) ? 1 : 0;
            $allowEdit = in_array($viewerId, $allowEditArr, true) ? 1 : 0;

            if ($allowEdit === 1) {
                $readOnly = 0;
            }

            mysqli_stmt_bind_param($stmt_upd, "iiss", $readOnly, $allowEdit, $viewerId, $userid);
            mysqli_stmt_execute($stmt_upd);
        }

        $alert_type = "success";
        $alert_html = "Sharing privileges updated.";
    } elseif ($action === "updateTextSize") {
        $newTextSize = normalizeTextSize($_POST["textSize"] ?? "normal");
        $stmt_ts = null;
        try {
            $stmt_ts = mysqli_prepare($link, "UPDATE people SET textSize = ?, lastModDate = NOW() WHERE userid = ?");
        } catch (mysqli_sql_exception $ex) {
            if ((int)$ex->getCode() === 1054) {
                applyPendingSchemaMigrations($link, (string)$base_dir);
                $stmt_ts = mysqli_prepare($link, "UPDATE people SET textSize = ?, lastModDate = NOW() WHERE userid = ?");
            } else {
                throw $ex;
            }
        }
        if ($stmt_ts) {
            mysqli_stmt_bind_param($stmt_ts, "ss", $newTextSize, $userid);
            mysqli_stmt_execute($stmt_ts);
        }
        if ($userid === $_SESSION["userid"]) {
            $_SESSION["textSize"] = $newTextSize;
        }
        $textSizeLabels = [
            'normal' => 'Standard (Default)',
            'large' => 'Large',
            'xlarge' => 'I forgot my glasses',
        ];
        $alert_type = "success";
        $alert_html = "Text size updated to <b>" . escapeText($textSizeLabels[$newTextSize]) . "</b>.";
    }
}

$stmt_info = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_info, "s", $userid);
mysqli_stmt_execute($stmt_info);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_info)) ?? [];

$fullName = formatPersonName($row, $userid);
$bdayDisplay = (!empty($row['bmonth']) && !empty($row['bday'])) ? ($row['bmonth'] . ' ' . $row['bday']) : 'Not set';
$currentTextSize = normalizeTextSize($row['textSize'] ?? 'normal');
$isOwnAccount = ($userid === $_SESSION['userid']);
$csrf_token = getCsrfToken();

$addViewerGroups = [];
$requestListGroups = [];
$viewersByFamily = [];
$viewerRows = [];
$viewableByFamily = [];
$totalViewersCount = 0;
$totalViewableCount = 0;

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
        $dispName = formatPersonName($pr);
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

    // Load people who can view this user's list, grouped into families
    $stmt_viewers_list = mysqli_prepare($link, "SELECT viewList.viewer, people.firstname, people.lastname, people.suffix, people.userid, viewList.readOnly, viewList.allowEdit FROM viewList JOIN people ON viewList.viewer = people.userid WHERE viewList.pid = ? ORDER BY people.lastname, people.firstname");
    mysqli_stmt_bind_param($stmt_viewers_list, "s", $userid);
    mysqli_stmt_execute($stmt_viewers_list);
    $res_vl = mysqli_stmt_get_result($stmt_viewers_list);
    while ($vrow = mysqli_fetch_assoc($res_vl)) {
        $viewerRows[] = $vrow;
        $totalViewersCount++;
        $raw_last = trim((string)($vrow['lastname'] ?? ''));
        $fam_key = ($raw_last !== '') ? strtolower($raw_last) : '_other';
        if (!isset($viewersByFamily[$fam_key])) {
            $viewersByFamily[$fam_key] = [
                'label' => ($raw_last !== '') ? ($raw_last . ' Family') : 'Other',
                'members' => []
            ];
        }
        $viewersByFamily[$fam_key]['members'][] = $vrow;
    }

    // Load lists this user can view, grouped into families
    $stmt_viewable = mysqli_prepare($link, "SELECT people.firstname, people.lastname, people.suffix, people.userid FROM viewList JOIN people ON viewList.pid = people.userid WHERE viewList.viewer = ? ORDER BY people.lastname, people.firstname");
    mysqli_stmt_bind_param($stmt_viewable, "s", $userid);
    mysqli_stmt_execute($stmt_viewable);
    $res_viewable = mysqli_stmt_get_result($stmt_viewable);
    while ($orow = mysqli_fetch_assoc($res_viewable)) {
        $totalViewableCount++;
        $raw_last = trim((string)($orow['lastname'] ?? ''));
        $fam_key = ($raw_last !== '') ? strtolower($raw_last) : '_other';
        if (!isset($viewableByFamily[$fam_key])) {
            $viewableByFamily[$fam_key] = [
                'label' => ($raw_last !== '') ? ($raw_last . ' Family') : 'Other',
                'members' => []
            ];
        }
        $viewableByFamily[$fam_key]['members'][] = $orow;
    }
}

$svgIcons = getSvgIcons();
$svg_edit = $svgIcons['edit'];
$svg_pwd = $svgIcons['pwd'];
$svg_view = $svgIcons['view'];
$svg_delete = $svgIcons['delete'];

$pwd_href = ($userid !== $_SESSION['userid']) ? ("changePassword.php?userid=" . urlencode($userid)) : "changePassword.php";
$edit_info_href = "editInfo.php?target_userid=" . urlencode($userid);
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
<?php createNavBar("../home.php:Home|:Update Account (" . $userid . ")", true, "listAccess"); ?>

<div class="account-dashboard">

  <div class="admin-header-bar">
    <div class="admin-title-group">
      <div class="admin-person-primary">
        <h2><?php echo escapeText($fullName); ?></h2>
        <span class="admin-userid-pill"><?php echo escapeText($userid); ?></span>
        <?php if ($userid === $_SESSION['userid']): ?>
          <span class="admin-you-badge">Your Account</span>
        <?php else: ?>
          <span class="admin-family-admin-pill">Managing User</span>
        <?php endif; ?>
      </div>
      <div class="account-info-pills">
        <span class="account-info-pill">🎂 <b>Birthday:</b> <?php echo escapeText($bdayDisplay); ?></span>
        <?php if (!empty($row['email'])): ?>
          <span class="account-info-pill">✉️ <?php echo escapeText($row['email']); ?></span>
        <?php endif; ?>
        <?php if (!empty($row['city']) || !empty($row['state'])): ?>
          <span class="account-info-pill">📍 <?php echo escapeText(trim(($row['city'] ?? '') . (!empty($row['city']) && !empty($row['state']) ? ', ' : '') . ($row['state'] ?? ''))); ?></span>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-top-actions">
      <a href="<?php echo escapeText($edit_info_href); ?>" class="admin-nav-btn admin-nav-btn-primary">
        <span class="admin-legend-item"><?php echo $svg_edit; ?></span> Edit Personal Info
      </a>
      <?php if ($can_manage_permissions): ?>
        <a href="<?php echo escapeText($pwd_href); ?>" class="admin-nav-btn">
          <span class="admin-legend-item"><?php echo $svg_pwd; ?></span> Change Password
        </a>
      <?php endif; ?>
      <?php if ($is_admin && $userid !== $_SESSION['userid']): ?>
        <a href="../admin.php" class="admin-nav-btn">← Back to Admin</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($alert_html !== ""): ?>
    <div class="admin-alert <?php echo $alert_type === 'error' ? 'admin-alert-error' : 'admin-alert-success'; ?>" role="status">
      <?php echo $alert_html; ?>
    </div>
  <?php endif; ?>

  <?php if ($can_manage_permissions): ?>
    <?php
      $textSizeOptions = [
        'normal' => ['title' => 'Standard', 'desc' => 'Default site size', 'previewPx' => '16px'],
        'large'  => ['title' => 'Large', 'desc' => 'Easier to read (~120%)', 'previewPx' => '19px'],
        'xlarge' => ['title' => 'I forgot my glasses', 'desc' => 'Maximum readability (~138%)', 'previewPx' => '22px'],
      ];
      $activeSizeLabel = $textSizeOptions[$currentTextSize]['title'];
    ?>
    <div class="account-section-card account-display-card">
      <div class="account-section-header">
        <span>🔠 Display &amp; Text Size</span>
        <span class="admin-family-count"><?php echo escapeText($activeSizeLabel); ?></span>
      </div>
      <div class="account-section-body">
        <form method="post" action="updateAccount.php" id="textSizeForm" style="margin:0;">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
          <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
          <input type="hidden" name="action" value="updateTextSize">
          <p class="account-picker-help">
            Choose your preferred text size across the WishList site<?php echo $isOwnAccount ? ' (saved to your account across all devices).' : (' for <b>' . escapeText($fullName) . '</b>.'); ?>
          </p>
          <div class="text-size-options" role="radiogroup" aria-label="Site text size">
            <?php foreach ($textSizeOptions as $tsKey => $tsMeta): ?>
              <?php $isChecked = ($currentTextSize === $tsKey); ?>
              <label class="text-size-option<?php echo $isChecked ? ' is-selected' : ''; ?>">
                <input
                  type="radio"
                  name="textSize"
                  value="<?php echo escapeText($tsKey); ?>"
                  class="text-size-radio"
                  data-initial="<?php echo escapeText($currentTextSize); ?>"
                  <?php echo $isChecked ? 'checked' : ''; ?>
                >
                <span class="text-size-sample" style="font-size: <?php echo escapeText($tsMeta['previewPx']); ?>;" aria-hidden="true">Aa</span>
                <span class="text-size-meta">
                  <span class="text-size-title"><?php echo escapeText($tsMeta['title']); ?></span>
                  <span class="text-size-desc"><?php echo escapeText($tsMeta['desc']); ?></span>
                </span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="admin-save-bar" id="textSizeSaveBar" style="margin-top:10px;">
            <span class="admin-save-hint" id="textSizeSaveHint">
              <?php echo $isOwnAccount ? 'Select a size to preview it, then save to keep your preference.' : 'Select a text size and save to update this user\'s preference.'; ?>
            </span>
            <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Save Text Size</button>
          </div>
        </form>
      </div>
    </div>

    <div class="account-sharing-grid">

      <!-- LEFT COLUMN: People Who Can View/Edit Your List -->
      <div class="account-column">
        <div class="account-section-card">
          <div class="account-section-header">
            <span>Add People So They Can View Your List</span>
          </div>
          <div class="account-section-body">
            <form method="post" action="updateAccount.php" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
              <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
              <input type="hidden" name="action" value="startViewMine">
              <p class="account-picker-help">Select one or more family members (granted <b>Full Purchasing</b> access by default):</p>
              <select name="userids[]" id="addViewersSelect" multiple style="width:100%;">
                <?php foreach ($addViewerGroups as $famGroup => $members): ?>
                  <optgroup label="<?php echo escapeText($famGroup); ?>">
                    <?php foreach ($members as $m): ?>
                      <option value="<?php echo escapeText($m['userid']); ?>"><?php echo escapeText($m['name']); ?> (<?php echo escapeText($m['userid']); ?>)</option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
              <div style="margin-top:8px;">
                <button type="submit" class="admin-nav-btn admin-nav-btn-primary">➕ Grant Access</button>
              </div>
            </form>
          </div>
        </div>

        <form action="updateAccount.php" method="post" id="privForm">
          <input type="hidden" name="action" value="updatePermissions">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
          <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">

          <div class="account-section-card">
            <div class="account-section-header">
              <span>People Who Can View/Edit Your List</span>
              <span class="admin-family-count"><?php echo (int)$totalViewersCount; ?></span>
            </div>
            <div class="account-section-body" style="padding:8px;">
              <div class="admin-families-list">
                <?php foreach ($viewersByFamily as $fam): ?>
                  <?php $mCount = count($fam['members']); ?>
                  <details class="admin-family-card" open>
                    <summary class="admin-family-header">
                      <div class="admin-family-title-wrap">
                        <span class="admin-family-name"><?php echo escapeText($fam['label']); ?></span>
                        <span class="admin-family-count"><?php echo (int)$mCount; ?></span>
                      </div>
                      <span class="admin-family-chevron" aria-hidden="true">▾</span>
                    </summary>

                    <table class="admin-family-table">
                      <tbody>
                        <?php foreach ($fam['members'] as $vrow): ?>
                          <?php
                            $vName = formatPersonName($vrow);
                            $vNameEsc = escapeText($vName);
                            $vu = escapeText($vrow["userid"]);
                            $isSelfRow = ($vrow["userid"] === $userid);
                            $hasReadOnly = !empty($vrow["readOnly"]);
                            $hasAllowEdit = !empty($vrow["allowEdit"]);
                          ?>
                          <tr>
                            <td>
                              <div class="admin-person-primary">
                                <span class="admin-person-name"><?php echo $vNameEsc; ?></span>
                                <span class="admin-userid-pill"><?php echo $vu; ?></span>
                                <?php if ($isSelfRow): ?>
                                  <span class="admin-you-badge">Self</span>
                                <?php endif; ?>
                              </div>
                            </td>
                            <td class="col-actions">
                              <div class="admin-row-actions">
                                <label class="compact-perm-pill" title="Read-Only Access for <?php echo $vNameEsc; ?>">
                                  <input
                                    name="readOnly[]"
                                    value="<?php echo $vu; ?>"
                                    type="checkbox"
                                    class="admin-role-checkbox perm-checkbox"
                                    data-initial="<?php echo $hasReadOnly ? '1' : '0'; ?>"
                                    <?php echo $hasReadOnly ? "checked" : ""; ?>
                                  >
                                  <span>Read-Only</span>
                                </label>
                                <label class="compact-perm-pill" title="Allow <?php echo $vNameEsc; ?> to edit wishlist">
                                  <input
                                    name="allowEdit[]"
                                    value="<?php echo $vu; ?>"
                                    type="checkbox"
                                    class="admin-role-checkbox perm-checkbox"
                                    data-initial="<?php echo $hasAllowEdit ? '1' : '0'; ?>"
                                    <?php echo $hasAllowEdit ? "checked" : ""; ?>
                                  >
                                  <span>Edit</span>
                                </label>
                                <?php if (!$isSelfRow): ?>
                                  <button
                                    type="submit"
                                    form="rmMine_<?php echo $vu; ?>"
                                    class="admin-icon-btn admin-icon-delete"
                                    title="Remove <?php echo $vNameEsc; ?> from viewing your list"
                                    aria-label="Remove <?php echo $vNameEsc; ?> from viewing your list"
                                  ><?php echo $svg_delete; ?></button>
                                <?php else: ?>
                                  <span class="admin-icon-spacer" aria-hidden="true"></span>
                                <?php endif; ?>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </details>
                <?php endforeach; ?>
              </div>

              <div class="admin-save-bar" id="permSaveBar" style="margin-top:8px;">
                <span class="admin-save-hint" id="permSaveHint">Adjust Read-Only or Edit checkboxes above and save.</span>
                <button type="submit" class="admin-nav-btn admin-nav-btn-primary">Save Changes</button>
              </div>
            </div>
          </div>
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
      </div>

      <!-- RIGHT COLUMN: Lists You Can View -->
      <div class="account-column">
        <div class="account-section-card">
          <div class="account-section-header">
            <span>Find Lists to View</span>
          </div>
          <div class="account-section-body">
            <form method="post" action="updateAccount.php" style="margin:0;">
              <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
              <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
              <input type="hidden" name="action" value="startViewOther">
              <p class="account-picker-help">Select one or more family members to request access to their wishlists:</p>
              <select name="userids[]" id="requestListsSelect" multiple style="width:100%;">
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
                <button type="submit" class="admin-nav-btn admin-nav-btn-primary">📩 Request Access</button>
              </div>
            </form>
          </div>
        </div>

        <div class="account-section-card">
          <div class="account-section-header">
            <span>Lists You Can View</span>
            <span class="admin-family-count"><?php echo (int)$totalViewableCount; ?></span>
          </div>
          <div class="account-section-body" style="padding:8px;">
            <div class="admin-families-list">
              <?php foreach ($viewableByFamily as $fam): ?>
                <?php $mCount = count($fam['members']); ?>
                <details class="admin-family-card" open>
                  <summary class="admin-family-header">
                    <div class="admin-family-title-wrap">
                      <span class="admin-family-name"><?php echo escapeText($fam['label']); ?></span>
                      <span class="admin-family-count"><?php echo (int)$mCount; ?></span>
                    </div>
                    <span class="admin-family-chevron" aria-hidden="true">▾</span>
                  </summary>

                  <table class="admin-family-table">
                    <tbody>
                      <?php foreach ($fam['members'] as $orow): ?>
                        <?php
                          $oName = formatPersonName($orow);
                          $oNameEsc = escapeText($oName);
                          $ou = escapeText($orow["userid"]);
                          $isSelfOther = ($orow["userid"] === $userid);
                          $viewHref = "../viewList/viewList.php?recip=" . urlencode($orow["userid"]) . "&name=" . urlencode($oName);
                        ?>
                        <tr>
                          <td>
                            <div class="admin-person-primary">
                              <span class="admin-person-name"><?php echo $oNameEsc; ?></span>
                              <span class="admin-userid-pill"><?php echo $ou; ?></span>
                              <?php if ($isSelfOther): ?>
                                <span class="admin-you-badge">Self</span>
                              <?php endif; ?>
                            </div>
                          </td>
                          <td class="col-actions">
                            <div class="admin-row-actions">
                              <a
                                href="<?php echo escapeText($viewHref); ?>"
                                class="admin-icon-btn"
                                title="View <?php echo $oNameEsc; ?>'s Wishlist"
                                aria-label="View <?php echo $oNameEsc; ?>'s Wishlist"
                              ><?php echo $svg_view; ?></a>
                              <?php if (!$isSelfOther): ?>
                                <form method="post" action="updateAccount.php" style="display:inline; margin:0;">
                                  <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
                                  <input type="hidden" name="action" value="stopViewOther">
                                  <input type="hidden" name="target_userid" value="<?php echo escapeText($userid); ?>">
                                  <input type="hidden" name="userid" value="<?php echo $ou; ?>">
                                  <button
                                    type="submit"
                                    class="admin-icon-btn admin-icon-delete"
                                    title="Stop viewing <?php echo $oNameEsc; ?>'s list"
                                    aria-label="Stop viewing <?php echo $oNameEsc; ?>'s list"
                                  ><?php echo $svg_delete; ?></button>
                                </form>
                              <?php else: ?>
                                <span class="admin-icon-spacer" aria-hidden="true"></span>
                              <?php endif; ?>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </details>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

    </div>
  <?php else: ?>
    <div class="admin-alert admin-alert-success">
      Note: You have edit access to manage <b><?php echo escapeText($fullName); ?></b>'s wishlist and profile details. Sharing permissions can only be changed by the account owner or an administrator.
    </div>
  <?php endif; ?>

</div>

<script>
(function() {
  var permSaveBar = document.getElementById('permSaveBar');
  var permSaveHint = document.getElementById('permSaveHint');
  var permCheckboxes = Array.prototype.slice.call(document.querySelectorAll('.perm-checkbox'));

  function updatePermDirty() {
    if (!permSaveBar || !permSaveHint) return;
    var changed = permCheckboxes.some(function(cb) {
      return cb.checked !== (cb.getAttribute('data-initial') === '1');
    });
    if (changed) {
      permSaveBar.classList.add('is-dirty');
      permSaveHint.textContent = 'You have unsaved permission changes.';
    } else {
      permSaveBar.classList.remove('is-dirty');
      permSaveHint.textContent = 'Adjust Read-Only or Edit checkboxes above and save.';
    }
  }

  permCheckboxes.forEach(function(cb) {
    cb.addEventListener('change', updatePermDirty);
  });

  var textSizeRadios = Array.prototype.slice.call(document.querySelectorAll('.text-size-radio'));
  var textSizeSaveBar = document.getElementById('textSizeSaveBar');
  var textSizeSaveHint = document.getElementById('textSizeSaveHint');
  var canPreviewTextSize = <?php echo $isOwnAccount ? 'true' : 'false'; ?>;
  var initialTextSize = <?php echo json_encode($currentTextSize); ?>;

  function updateTextSizeSelection() {
    var selectedVal = initialTextSize;
    textSizeRadios.forEach(function(radio) {
      var label = radio.closest('.text-size-option');
      if (radio.checked) {
        selectedVal = radio.value;
        if (label) label.classList.add('is-selected');
      } else {
        if (label) label.classList.remove('is-selected');
      }
    });

    if (canPreviewTextSize && typeof applyTextSize === 'function') {
      applyTextSize(selectedVal);
    }

    if (textSizeSaveBar && textSizeSaveHint) {
      if (selectedVal !== initialTextSize) {
        textSizeSaveBar.classList.add('is-dirty');
        textSizeSaveHint.textContent = 'Previewing unsaved text size. Click "Save Text Size" to keep it.';
      } else {
        textSizeSaveBar.classList.remove('is-dirty');
        textSizeSaveHint.textContent = canPreviewTextSize
          ? 'Select a size to preview it, then save to keep your preference.'
          : 'Select a text size and save to update this user\'s preference.';
      }
    }
  }

  textSizeRadios.forEach(function(radio) {
    radio.addEventListener('change', updateTextSizeSelection);
  });
})();
</script>
</td>
</tr>
</table>
</body>
</html>
