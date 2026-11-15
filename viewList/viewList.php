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

if ($confirm === "Go Home" || $confirm === "Ho Home") {
    unset($_SESSION["euserid"]);
    header("Location: " . getFullPath("../home.php"));
    exit;
}

if ($recip === "") {
    header("Location: " . getFullPath("../home.php"));
    exit;
}

// Resolve canonical recipient full name from DB
$stmt_person = mysqli_prepare($link, "SELECT firstname, lastname, suffix, lastModDate FROM people WHERE userid = ?");
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
if ($access['allowed']) {
    // Clean expired comments only after confirming viewer permission
    $stmt_del = mysqli_prepare($link, "DELETE FROM comments WHERE date < DATE_SUB(NOW(), INTERVAL 4 MONTH) AND userid = ?");
    mysqli_stmt_bind_param($stmt_del, "s", $recip);
    mysqli_stmt_execute($stmt_del);

    if ($recip !== $viewerUserid) {
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
    <link rel="stylesheet" href="../style.css" type="text/css">
    <link rel="stylesheet" href="../print.css" type="text/css" media="print">
    <link rel="stylesheet" href="../css/featherlight.min.css" type="text/css">
    <link rel="manifest" href="../manifest.json">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <title><?php echo escapeText($name); ?>'s WishList</title>
    <script src="../js/jquery-3.7.1.min.js"></script>
    <script src="../js/featherlight.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../js/wishlist-filter.js"></script>
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
// Pass $displayPurchases = 1 so multi-quantity dropdowns/history and readOnly disabled controls work properly
printList2($recip, $viewerUserid, $name, 1);
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
    $stmt_comm = mysqli_prepare($link, "SELECT comments.commentId, comments.comment_userid, people.firstname, people.lastname, people.suffix, comments.comment, comments.date FROM comments JOIN people ON comments.comment_userid = people.userid WHERE comments.userid = ? ORDER BY comments.date DESC");
    mysqli_stmt_bind_param($stmt_comm, "s", $recip);
    mysqli_stmt_execute($stmt_comm);
    $rs = mysqli_stmt_get_result($stmt_comm);

    while ($row = mysqli_fetch_assoc($rs)) {
        $commenterName = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
        echo '<font color="navy">' . escapeText($commenterName) . ' made the following comments on ' . escapeText(parseDate($row["date"], 1)) . '</font> ';
        if ($viewerUserid === $row["comment_userid"]) {
            echo '<form method="post" action="deleteComment.php" style="display:inline; margin:0;">';
            echo '<input type="hidden" name="csrf_token" value="' . escapeText($csrf_token) . '">';
            echo '<input type="hidden" name="commentId" value="' . (int)$row["commentId"] . '">';
            echo '<input type="hidden" name="recip" value="' . escapeText($recip) . '">';
            echo '<input type="hidden" name="name" value="' . escapeText($name) . '">';
            echo '<button type="submit" class="menuLink" style="background:none;border:none;padding:0;color:#0000cc;cursor:pointer;font-weight:bold;text-decoration:underline;">[Remove Comment]</button>';
            echo '</form>';
        }
        echo "<br>";
        $cText = (string)$row["comment"];
        if (strpos($cText, "<div class='secret-suggestion-card'>") === 0 || strpos($cText, '<div class="secret-suggestion-card">') === 0) {
            echo formatMessage($cText);
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
      <input type="text" name="subject" placeholder="Subject" style="width:100%; max-width:420px; padding:6px;" required><br><br>
      <textarea name="body" rows="5" cols="50" placeholder="Message" style="width:100%; max-width:420px; padding:6px;" required></textarea><br><br>
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
