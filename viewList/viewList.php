<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$viewerUserid = $_SESSION["userid"];
$recip = trim((string)($_REQUEST["recip"] ?? ""));
$name = trim((string)($_REQUEST["name"] ?? ""));
$confirm = $_REQUEST["confirm"] ?? "";

if ($confirm === "Go Home") {
    unset($_SESSION["euserid"]);
    header("Location: " . getFullPath("../home.php"));
    exit;
}

if ($recip === "") {
    header("Location: " . getFullPath("../home.php"));
    exit;
}

// Resolve canonical recipient full name and contact fields from DB
$stmt_person = mysqli_prepare($link, "SELECT firstname, lastname, suffix, lastModDate, bmonth, bday, street, city, state, zip, phone, mobilephone FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_person, "s", $recip);
mysqli_stmt_execute($stmt_person);
$person_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_person));
if (!$person_row) {
    header("Location: " . getFullPath("../home.php"));
    exit;
}
$canonicalName = trim($person_row["firstname"] . " " . $person_row["lastname"] . " " . ($person_row["suffix"] ?? ""));
if ($canonicalName !== "") {
    $name = $canonicalName;
}
$lastModDate = $person_row["lastModDate"] ?? null;

$access = canViewList($link, $recip, $viewerUserid);

$recipientBirthdayText = "";
$recipientAddressLines = [];
$recipientPhones = [];
if ($access['allowed']) {
    $bmonthVal = trim((string)($person_row['bmonth'] ?? ''));
    $bdayVal = (int)($person_row['bday'] ?? 0);
    if ($bmonthVal !== '' && $bdayVal >= 1 && $bdayVal <= 31) {
        $recipientBirthdayText = $bmonthVal . ' ' . $bdayVal;
    }
    $streetVal = trim((string)($person_row['street'] ?? ''));
    $cityStateZipVal = trim(
        ($person_row['city'] ?? '') .
        (!empty($person_row['city']) && !empty($person_row['state']) ? ', ' : '') .
        ($person_row['state'] ?? '') .
        ((!empty($person_row['zip']) && $person_row['zip'] !== '0') ? ' ' . $person_row['zip'] : '')
    );
    if ($streetVal !== '') {
        $recipientAddressLines[] = $streetVal;
    }
    if ($cityStateZipVal !== '') {
        $recipientAddressLines[] = $cityStateZipVal;
    }
    if (!empty($person_row['phone'])) {
        $recipientPhones[] = 'Home: ' . trim((string)$person_row['phone']);
    }
    if (!empty($person_row['mobilephone'])) {
        $recipientPhones[] = 'Mobile: ' . trim((string)$person_row['mobilephone']);
    }
}
$fullClipboardAddress = !empty($recipientAddressLines)
    ? ($name . "\n" . implode("\n", $recipientAddressLines))
    : "";

if ($access['allowed'] && ($recip === $viewerUserid || $access['allowEdit']) && $confirm === "Edit List") {
    if ($recip !== $viewerUserid) {
        $_SESSION["euserid"] = $recip;
    } else {
        unset($_SESSION["euserid"]);
    }
    header("Location: " . getFullPath("../modifyList/modifyList.php"));
    exit;
}

$hasNewComments = false;
$prevViewDate = null;
if ($access['allowed']) {
    // Clean expired comments only after confirming viewer permission (preserving claimed Secret Gift Ideas)
    $stmt_del = mysqli_prepare($link, "DELETE FROM comments WHERE date < DATE_SUB(NOW(), INTERVAL 4 MONTH) AND userid = ? AND (claimed_by IS NULL OR claimed_by = '')");
    mysqli_stmt_bind_param($stmt_del, "s", $recip);
    mysqli_stmt_execute($stmt_del);

    if ($recip !== $viewerUserid) {
        $stmt_lv = mysqli_prepare($link, "SELECT lastViewDate FROM viewList WHERE pid = ? AND viewer = ? LIMIT 1");
        if ($stmt_lv) {
            mysqli_stmt_bind_param($stmt_lv, "ss", $recip, $viewerUserid);
            mysqli_stmt_execute($stmt_lv);
            if ($lvRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lv))) {
                $prevViewDate = $lvRow['lastViewDate'] ?? null;
            }
        }

        $stmt_comm = mysqli_prepare($link, "SELECT 1 FROM comments JOIN viewList ON comments.userid = viewList.pid WHERE viewList.pid = ? AND viewList.viewer = ? AND comments.comment_userid != ? AND comments.date > viewList.lastViewDate LIMIT 1");
        mysqli_stmt_bind_param($stmt_comm, "sss", $recip, $viewerUserid, $viewerUserid);
        mysqli_stmt_execute($stmt_comm);
        if (mysqli_fetch_row(mysqli_stmt_get_result($stmt_comm))) {
            $hasNewComments = true;
        }
    }
}

$isReadOnlyView = (!$access['allowed'] || $access['readOnly'] || $recip === $viewerUserid);
$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
    <link rel="stylesheet" href="../print.css?v=<?php echo (int)@filemtime(__DIR__ . '/../print.css'); ?>" type="text/css" media="print">
    <link rel="stylesheet" href="../css/select2.min.css" type="text/css">
    <link rel="manifest" href="../manifest.json">
    <title><?php echo escapeText($name); ?>'s WishList</title>
    <script src="../js/jquery-3.7.1.min.js"></script>
    <script src="../js/lightbox.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/lightbox.js'); ?>"></script>
    <script src="../js/select2.min.js"></script>
    <script src="../js/wishlist-filter.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/wishlist-filter.js'); ?>"></script>
    <script>
    function toggleSplitInput(iid, show) {
        var box = document.getElementById('split_box_' + iid);
        if (box) {
            box.style.display = show ? 'block' : 'none';
            if (show) {
                var $select = $('#split_users_' + iid);
                if ($select.length && typeof $.fn.select2 === 'function' && !$select.hasClass('select2-hidden-accessible')) {
                    $select.select2({
                        placeholder: "Select co-buyer(s)...",
                        allowClear: true,
                        width: '100%'
                    });
                }
            }
        }
    }

    function toggleSuggestionSplit(cid) {
        var box = document.getElementById('sugg_split_box_' + cid);
        if (!box) return;
        var open = (box.style.display === 'none');
        box.style.display = open ? 'block' : 'none';
        if (open) {
            var $select = $('#sugg_split_users_' + cid);
            if ($select.length && typeof $.fn.select2 === 'function' && !$select.hasClass('select2-hidden-accessible')) {
                $select.select2({
                    placeholder: "Select co-buyer(s)...",
                    allowClear: true,
                    width: '100%'
                });
            }
        }
    }

    $(document).ready(function() {
        $('input[name^="chk"]:checked').each(function() {
            var iid = this.name.replace('chk', '');
            toggleSplitInput(iid, true);
        });
        $('select[name^="sel"]').each(function() {
            if (parseInt(this.value, 10) > 0) {
                var iid = this.name.replace('sel', '');
                toggleSplitInput(iid, true);
            }
        });

        <?php if (!empty($_GET['saved'])): ?>
        if (typeof showToast === 'function') {
            showToast('Changes saved successfully!');
        }
        <?php endif; ?>

        <?php if ($hasNewComments): ?>
        if (typeof showToast === 'function') {
            showToast('New comments have been added since the last time you viewed this list.', 'info');
        } else {
            alert('New comments have been added since the last time you viewed this list.');
        }
        <?php endif; ?>

        // Unsaved purchase selection tracking + sticky bar
        var $form = $('form[name="wishlist"]');
        var $stickyBar = $('#unsavedPurchaseBar');
        var isSubmitting = false;

        function countDirtyPurchases() {
            var count = 0;
            $form.find('input[name^="chk"]:not(:disabled)').each(function() {
                if (this.checked !== this.defaultChecked) count++;
            });
            $form.find('select[name^="sel"]:not(:disabled)').each(function() {
                var defVal = $(this).find('option[selected]').val() || '0';
                if (String(this.value) !== String(defVal)) count++;
            });
            return count;
        }

        function updateStickyBar() {
            var dirty = countDirtyPurchases();
            if (dirty > 0) {
                $('#unsavedCountText').text(dirty + (dirty === 1 ? ' item selected' : ' items selected') + ' — remember to Save Changes!');
                $stickyBar.fadeIn(150);
            } else {
                $stickyBar.fadeOut(150);
            }
        }

        $form.on('change', 'input[name^="chk"], select[name^="sel"]', updateStickyBar);
        $form.on('submit', function() {
            isSubmitting = true;
        });

        window.addEventListener('beforeunload', function(e) {
            if (!isSubmitting && countDirtyPurchases() > 0) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Modals
        var sendMessageModal = document.getElementById("sendMessageModal");
        var sendMessageBtn = document.getElementById("sendMessageBtn");
        var closeSendSpan = document.querySelector(".close-send-message");

        if (sendMessageBtn && sendMessageModal) {
            sendMessageBtn.addEventListener("click", function() {
                sendMessageModal.style.display = "block";
            });
        }
        if (closeSendSpan && sendMessageModal) {
            closeSendSpan.addEventListener("click", function() {
                sendMessageModal.style.display = "none";
            });
        }

        $('#sendMessageForm').on('submit', function(e) {
            e.preventDefault();
            var $f = $(this);
            $.ajax({
                type: 'POST',
                url: '../sendMessage.php',
                data: $f.serialize(),
                dataType: 'json',
                success: function() {
                    if (typeof showToast === 'function') {
                        showToast('Message sent!');
                    } else {
                        alert('Message sent!');
                    }
                    $f[0].reset();
                    if (sendMessageModal) sendMessageModal.style.display = "none";
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Failed to send message.';
                    if (typeof showToast === 'function') {
                        showToast(msg, 'error');
                    } else {
                        alert(msg);
                    }
                }
            });
        });

        var infoModal = document.getElementById("infoModal");
        var infoModalText = document.getElementById("infoModalText");
        var closeInfoModal = document.querySelector(".close-info-modal");

        $('.info-icon').on('click', function() {
            var createDate = $(this).data('create-date') || '';
            if (infoModalText) {
                infoModalText.textContent = "This item was added on " + createDate;
            }
            if (infoModal) {
                infoModal.style.display = "block";
            }
        });

        if (closeInfoModal && infoModal) {
            closeInfoModal.addEventListener("click", function() {
                infoModal.style.display = "none";
            });
        }

        window.addEventListener("click", function(event) {
            if (sendMessageModal && event.target === sendMessageModal) {
                sendMessageModal.style.display = "none";
            }
            if (infoModal && event.target === infoModal) {
                infoModal.style.display = "none";
            }
        });
    });
    </script>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php
createNavBar("../home.php:Home|:View List - " . $name, false, "viewOther");
?>
<br>
<font size="4">
<?php
if (!$access['allowed']) {
    echo "<h2><center><font color=\"red\">You do not have permission to view this wishlist.</font></center></h2>";
    echo "</font></td></tr></table></body></html>";
    exit;
}

if (($recip === $viewerUserid || $access['allowEdit']) && $confirm !== "View List") {
    echo "<center><p>&nbsp;</p><form method=\"post\" action=\"viewList.php\">";
    echo "<input type=\"hidden\" name=\"recip\" value=\"" . escapeText($recip) . "\">";
    echo "<input type=\"hidden\" name=\"name\" value=\"" . escapeText($name) . "\">";
    echo "<b>Are you sure you want to view the list of " . escapeText($name) . "?<br>You will be able to see any purchases that may have been made!<br>This could ruin the surprise.</b>";
    echo "<p><input type=\"submit\" name=\"confirm\" value=\"View List\" class=\"buttonstyle\"> ";
    echo "<input type=\"submit\" name=\"confirm\" value=\"Edit List\" class=\"buttonstyle\"> ";
    echo "<input type=\"submit\" name=\"confirm\" value=\"Go Home\" class=\"buttonstyle\"></p>";
    echo "</form></center></font></td></tr></table></body></html>";
    exit;
}
?>

<form method="post" action="validate_purchase.php" name="wishlist">
<?php if ($recipientBirthdayText !== "" || !empty($recipientAddressLines) || !empty($recipientPhones)): ?>
<div class="recipient-contact-card no-print">
  <div class="recipient-contact-summary">
    <?php if ($recipientBirthdayText !== ""): ?>
      <span class="recipient-bday-pill">🎂 <b>Birthday:</b> <?php echo escapeText($recipientBirthdayText); ?></span>
    <?php endif; ?>
    <?php if (!empty($recipientAddressLines) || !empty($recipientPhones)): ?>
      <button type="button" class="actionButton" onclick="toggleContactDrawer()" id="contactDrawerBtn">📇 Shipping &amp; Contact Info ▾</button>
    <?php endif; ?>
  </div>
  <?php if (!empty($recipientAddressLines) || !empty($recipientPhones)): ?>
  <div id="recipientContactDrawer" class="recipient-contact-drawer" style="display:none;">
    <?php if (!empty($recipientAddressLines)): ?>
      <div class="recipient-address-block">
        <div>
          <b>Shipping Address:</b><br>
          <?php echo escapeText($name); ?><br>
          <?php foreach ($recipientAddressLines as $aline): ?>
            <?php echo escapeText($aline); ?><br>
          <?php endforeach; ?>
        </div>
        <button type="button" class="actionButton" onclick="copyRecipientAddress()">📋 Copy Address</button>
      </div>
    <?php endif; ?>
    <?php if (!empty($recipientPhones)): ?>
      <div class="recipient-phones-block">
        <?php foreach ($recipientPhones as $pline): ?>
          <span>📞 <?php echo escapeText($pline); ?></span>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <script>
  var recipientShippingCopyText = <?php echo json_encode($fullClipboardAddress, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  function toggleContactDrawer() {
      var drawer = document.getElementById('recipientContactDrawer');
      var btn = document.getElementById('contactDrawerBtn');
      if (!drawer) return;
      var open = drawer.style.display === 'none';
      drawer.style.display = open ? 'block' : 'none';
      if (btn) {
          btn.textContent = open ? '📇 Shipping & Contact Info ▴' : '📇 Shipping & Contact Info ▾';
      }
  }
  function copyRecipientAddress() {
      if (!recipientShippingCopyText) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(recipientShippingCopyText).then(function() {
              if (typeof showToast === 'function') showToast('Shipping address copied!', 'success');
          }).catch(fallbackCopyAddress);
      } else {
          fallbackCopyAddress();
      }
  }
  function fallbackCopyAddress() {
      var ta = document.createElement('textarea');
      ta.value = recipientShippingCopyText;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      if (typeof showToast === 'function') showToast('Shipping address copied!', 'success');
  }
  </script>
  <?php endif; ?>
</div>
<?php endif; ?>
<div class="wishlist-search-box">
  <label for="wishlistSearch"><b>Search List:</b></label>
  <input type="text" id="wishlistSearch" class="wishlist-search-input" placeholder="Type to filter items..." oninput="filterWishlist(this.value)">
  <input type="button" id="wishlistSearchClear" class="actionButton" value="Clear" onclick="clearWishlistFilter()" style="display:none;">
  <span id="wishlistSearchCount" class="wishlist-search-count"></span>
  <?php if ($access['readOnly'] && $recip !== $viewerUserid): ?>
    <span style="margin-left:auto; font-size:12px; color:#666; font-weight:bold;">🔒 Read-Only Access</span>
  <?php endif; ?>
</div>
<input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
<input type="hidden" name="receiverUserid" value="<?php echo escapeText($recip); ?>">
<input type="hidden" name="receiverName" value="<?php echo escapeText($name); ?>">

<?php
// Pass $displayPurchases = 1 and $prevViewDate so items added since last visit show NEW badges
printList2($recip, $viewerUserid, $name, 1, $prevViewDate);
?>

<p>
<?php if (!$isReadOnlyView): ?>
  <input type="submit" value="Save Changes" class="buttonstyle">
  <input type="reset" value="Undo Changes" class="buttonstyle" onclick="location.reload()">
<?php endif; ?>
<input type="button" value="Go Home" class="buttonstyle" onclick="location.href='../home.php'">
<?php if ($recip !== $viewerUserid): ?>
  <input type="button" value="Send Message" class="buttonstyle" id="sendMessageBtn">
<?php endif; ?>
<input type="button" value="🖨️ Print List" class="buttonstyle" onclick="window.print()">
</p>

<?php if (!$isReadOnlyView): ?>
<div id="unsavedPurchaseBar" class="no-print" style="display:none; position:fixed; bottom:16px; left:50%; transform:translateX(-50%); background:#1e293b; color:#fff; padding:10px 18px; border-radius:8px; box-shadow:0 4px 14px rgba(0,0,0,0.3); z-index:9998; font-size:14px;">
  <span id="unsavedCountText" style="margin-right:12px;">Unsaved selections</span>
  <button type="submit" class="buttonstyle" style="margin:0;">Save Changes</button>
</div>
<?php endif; ?>
</form>

<hr>

<font size="4"><b>Comments</b></font> - <?php echo escapeText($name); ?> will not see these
<p>

<?php
if ($recip !== $viewerUserid) {
    $viewerProxyPids = [];
    $stmt_vp = mysqli_prepare($link, "SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1");
    if ($stmt_vp) {
        mysqli_stmt_bind_param($stmt_vp, "s", $viewerUserid);
        mysqli_stmt_execute($stmt_vp);
        $res_vp = mysqli_stmt_get_result($stmt_vp);
        while ($vpr = mysqli_fetch_assoc($res_vp)) {
            $viewerProxyPids[$vpr['pid']] = true;
        }
    }

    $suggCoBuyers = [];
    if (!$isReadOnlyView) {
        $stmt_scb = mysqli_prepare($link, "
            SELECT DISTINCT p.userid, p.firstname, p.lastname
            FROM people p
            WHERE p.userid != ? AND p.userid != ?
              AND (
                p.userid IN (SELECT viewer FROM viewList WHERE pid = ?)
                OR p.userid IN (SELECT pid FROM viewList WHERE viewer = ?)
              )
            ORDER BY p.lastname, p.firstname
        ");
        if ($stmt_scb) {
            mysqli_stmt_bind_param($stmt_scb, "ssss", $recip, $viewerUserid, $recip, $viewerUserid);
            mysqli_stmt_execute($stmt_scb);
            $rs_scb = mysqli_stmt_get_result($stmt_scb);
            while ($scb = mysqli_fetch_assoc($rs_scb)) {
                $suggCoBuyers[] = $scb;
            }
        }
    }

    $stmt_comm = mysqli_prepare($link, "SELECT comments.*, people.firstname, people.lastname, people.suffix, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', claimer.firstname, claimer.lastname, NULLIF(claimer.suffix, ''))), ''), comments.claimed_by) AS claimer_name FROM comments JOIN people ON comments.comment_userid = people.userid LEFT JOIN people AS claimer ON claimer.userid = comments.claimed_by WHERE comments.userid = ? ORDER BY comments.date DESC");
    mysqli_stmt_bind_param($stmt_comm, "s", $recip);
    mysqli_stmt_execute($stmt_comm);
    $rs = mysqli_stmt_get_result($stmt_comm);

    while ($row = mysqli_fetch_assoc($rs)) {
        $cidInt = (int)$row["commentId"];
        $claimedBy = trim((string)($row["claimed_by"] ?? ""));
        $commenterName = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
        echo '<font color="navy">' . escapeText($commenterName) . ' made the following comments on ' . escapeText(parseDate($row["date"], 1)) . '</font> ';
        if ($viewerUserid === $row["comment_userid"] && ($claimedBy === "" || $claimedBy === $viewerUserid)) {
            echo '<form method="post" action="deleteComment.php" style="display:inline; margin:0;">';
            echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
            echo '<input type="hidden" name="commentId" value="' . $cidInt . '">';
            echo '<input type="hidden" name="recip" value="' . escapeText($recip) . '">';
            echo '<input type="hidden" name="name" value="' . escapeText($name) . '">';
            echo '<button type="submit" class="menuLink" style="background:none;border:none;padding:0;color:#0000cc;cursor:pointer;font-weight:bold;text-decoration:underline;">[Remove Comment]</button>';
            echo '</form>';
        }
        echo "<br>";
        $cText = (string)$row["comment"];
        $isSecretCard = (!empty($row["is_suggestion"])
            || strpos($cText, "<div class='secret-suggestion-card'>") === 0
            || strpos($cText, '<div class="secret-suggestion-card">') === 0);
        if ($isSecretCard) {
            echo formatMessage($cText);
            echo '<div class="suggestion-claim-bar no-print">';
            if ($claimedBy !== "") {
                $isOwnClaim = ($claimedBy === $viewerUserid);
                $canUndoClaim = ($isOwnClaim || isset($viewerProxyPids[$claimedBy]));
                $claimLabel = $isOwnClaim ? '✅ Claimed by You' : '✅ Claimed / Bought';
                echo '<span class="suggestion-claimed-badge">' . $claimLabel . '</span>';
                if (!empty($row["split_with"])) {
                    $splitLabel = formatSplitBetweenText((string)($row["claimer_name"] ?? ""), (string)$row["split_with"]);
                    if ($splitLabel !== "") {
                        echo ' <span class="group-gift-badge">👥 ' . escapeText($splitLabel) . '</span>';
                    }
                }
                if ($canUndoClaim) {
                    echo ' <form method="post" action="claimSuggestion.php" style="display:inline; margin:0;">';
                    echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
                    echo '<input type="hidden" name="commentId" value="' . $cidInt . '">';
                    echo '<input type="hidden" name="action" value="unclaim">';
                    echo '<input type="hidden" name="recip" value="' . escapeText($recip) . '">';
                    echo '<input type="hidden" name="name" value="' . escapeText($name) . '">';
                    echo '<button type="submit" class="actionButton" style="font-size:11.5px; padding:2px 8px;">Undo Claim</button>';
                    echo '</form>';
                }
            } elseif (!$isReadOnlyView) {
                echo '<form method="post" action="claimSuggestion.php" style="margin:0;">';
                echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
                echo '<input type="hidden" name="commentId" value="' . $cidInt . '">';
                echo '<input type="hidden" name="action" value="claim">';
                echo '<input type="hidden" name="recip" value="' . escapeText($recip) . '">';
                echo '<input type="hidden" name="name" value="' . escapeText($name) . '">';
                echo '<div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">';
                echo '<button type="submit" class="actionButton suggestion-claim-btn">🎁 Claim / Mark Bought</button>';
                echo '<button type="button" class="actionButton" style="font-size:12px;" onclick="toggleSuggestionSplit(' . $cidInt . ')">👥 Split with co-buyers ▾</button>';
                echo '</div>';
                echo '<div id="sugg_split_box_' . $cidInt . '" class="split-input-box" style="display:none; margin-top:8px;">';
                if (!empty($suggCoBuyers)) {
                    echo '<label class="split-subtext" style="display:block; margin-bottom:4px;">👥 Split this secret gift with site users:</label>';
                    echo '<select name="split_users[]" id="sugg_split_users_' . $cidInt . '" multiple style="width:100%; max-width:320px;">';
                    foreach ($suggCoBuyers as $scbUser) {
                        echo '<option value="' . escapeText($scbUser['userid']) . '">' . escapeText($scbUser['firstname'] . ' ' . $scbUser['lastname']) . '</option>';
                    }
                    echo '</select>';
                }
                echo '<div style="margin-top:5px;"><input type="text" name="split_note" class="split-input" placeholder="Additional note or other non-user (optional)" style="font-size:11px; padding:4px 6px;"></div>';
                echo '</div>';
                echo '</form>';
            }
            echo '</div>';
        } else {
            echo formatMultilineText($cText);
        }
        echo "<p>";
    }
?>
<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
  <form method="get" action="addComment.php" style="margin: 0;">
    <input type="hidden" name="recip" value="<?php echo escapeText($recip); ?>">
    <input type="hidden" name="name" value="<?php echo escapeText($name); ?>">
    <input type="submit" value="Add New Comment" class="buttonstyle">
  </form>
  <form method="get" action="addComment.php" style="margin: 0;">
    <input type="hidden" name="recip" value="<?php echo escapeText($recip); ?>">
    <input type="hidden" name="name" value="<?php echo escapeText($name); ?>">
    <input type="hidden" name="type" value="suggestion">
    <input type="submit" value="💡 Suggest Secret Gift Idea" class="buttonstyle suggestion-btn-accent">
  </form>
</div>
<?php
} else {
    print "<font color=\"red\"><b>You are not allowed to view comments added to your list</b></font>";
}
?>
</font>
</td>
</tr>
<tr><td>
<table class="navBar">
<tr>
<td class="navBar" align="left">
  <font size="2">
    <a class="navMenuLink" href="viewLog.php?recip=<?php echo urlencode($recip); ?>&amp;name=<?php echo urlencode($name); ?>">Click here to see what you bought or to unselect items</a>
  </font>
</td>
<td class="navBar" align="right">
<?php if ($lastModDate !== null): ?>
  <font size="2">
    <font color="indianred"><?php echo escapeText($name); ?></font> last modified this list on <font color="navy"><?php echo escapeText(parseDate($lastModDate, 1)); ?></font>
  </font>
<?php endif; ?>
</td></tr></table>
</td></tr></table>

<?php if ($recip !== $viewerUserid): ?>
<div id="sendMessageModal" class="modal">
  <div class="modal-content">
    <span class="close-send-message">&times;</span>
    <h2>Send a message to <?php echo escapeText($name); ?></h2>
    <form id="sendMessageForm" method="post" action="../sendMessage.php">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
      <input type="hidden" name="ajax" value="1">
      <input type="hidden" name="recipient_id[]" value="<?php echo escapeText($recip); ?>">
      <input type="text" name="subject" placeholder="Subject (e.g. Question about boot size)" style="width:100%; max-width:440px; padding:6px; box-sizing:border-box;" required><br><br>
      <textarea name="body" rows="5" cols="50" placeholder="Message" style="width:100%; max-width:440px; padding:6px; box-sizing:border-box;" required></textarea><br>
      <label class="anon-shopper-toggle">
        <input type="checkbox" name="anonymous_shopper" value="1">
        <span>🕵️ <b>Send anonymously as &ldquo;A Gift Shopper&rdquo;</b><br>
        <small>Hides your name to keep the gift a surprise (prompts <?php echo escapeText($name); ?> to post the answer on their WishList).</small></span>
      </label><br>
      <input type="submit" value="Send Message" class="buttonstyle">
    </form>
  </div>
</div>
<?php endif; ?>

<div id="infoModal" class="info-modal">
  <div class="info-modal-content">
    <span class="close-info-modal">&times;</span>
    <p id="infoModalText"></p>
  </div>
</div>

<?php
$stmt_view = mysqli_prepare($link, "UPDATE viewList SET lastViewDate = NOW() WHERE pid = ? AND viewer = ?");
mysqli_stmt_bind_param($stmt_view, "ss", $recip, $viewerUserid);
mysqli_stmt_execute($stmt_view);
?>
</body>
</html>
