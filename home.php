<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "funcLib.php";

if (!headers_sent()) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
}

unset($_SESSION["euserid"]);
$userid = $_SESSION["userid"];
$csrf_token = getCsrfToken();

$showWhatsNewPopup = false;
try {
    $stmt_wn = mysqli_prepare($link, "SELECT whatsNewSeenVersion FROM people WHERE userid = ? LIMIT 1");
    if ($stmt_wn) {
        mysqli_stmt_bind_param($stmt_wn, "s", $userid);
        mysqli_stmt_execute($stmt_wn);
        if ($wn_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_wn))) {
            $showWhatsNewPopup = ((int)($wn_row['whatsNewSeenVersion'] ?? 0) < WHATS_NEW_VERSION);
        }
    }
} catch (Throwable $e) {
    $showWhatsNewPopup = false;
}

// Fetch pending access requests for this user's list (checking whether we already view or requested theirs)
$pending_requests_query = "
    SELECT ar.*, p.firstname, p.lastname, p.suffix,
           EXISTS(SELECT 1 FROM viewList vl WHERE vl.pid = ar.requesterId AND vl.viewer = ar.targetId) AS already_viewing_theirs,
           EXISTS(SELECT 1 FROM accessRequests ar2 WHERE ar2.requesterId = ar.targetId AND ar2.targetId = ar.requesterId AND ar2.status = 'pending') AS already_requested_theirs
    FROM accessRequests ar
    JOIN people p ON ar.requesterId = p.userid
    WHERE ar.targetId = ? AND ar.status = 'pending'
    ORDER BY ar.requestDate DESC
";
$stmt_pending = mysqli_prepare($link, $pending_requests_query);
mysqli_stmt_bind_param($stmt_pending, "s", $userid);
mysqli_stmt_execute($stmt_pending);
$pending_requests_result = mysqli_stmt_get_result($stmt_pending);

// Fetch access-request decision notifications for this user (and batch-mark as notified)
$notifications_query = "SELECT ar.id, ar.status, p.firstname, p.lastname, p.suffix FROM accessRequests ar JOIN people p ON ar.targetId = p.userid WHERE ar.requesterId = ? AND ar.status != 'pending' AND ar.notified = 0";
$stmt_notif = mysqli_prepare($link, $notifications_query);
mysqli_stmt_bind_param($stmt_notif, "s", $userid);
mysqli_stmt_execute($stmt_notif);
$notifications_result = mysqli_stmt_get_result($stmt_notif);

$access_notifications = [];
$notif_ids = [];
while ($notification = mysqli_fetch_assoc($notifications_result)) {
    $targetName = trim($notification['firstname'] . ' ' . $notification['lastname'] . ' ' . ($notification['suffix'] ?? ''));
    $statusWord = ($notification['status'] === 'approved') ? 'approved' : 'denied';
    $access_notifications[] = [
        'text' => "Your request to view " . $targetName . "'s list has been " . $statusWord . ".",
        'type' => ($notification['status'] === 'approved') ? 'success' : 'error'
    ];
    $notif_ids[] = (int)$notification['id'];
}

if (!empty($notif_ids)) {
    $id_list = implode(',', $notif_ids);
    mysqli_query($link, "UPDATE accessRequests SET notified = 1 WHERE id IN ($id_list)");
}

if (!empty($_GET['reciprocal_sent'])) {
    $access_notifications[] = [
        'text' => "Approved access and sent a request to view their wishlist back!",
        'type' => 'success'
    ];
}

$query = "SELECT people.*, MAX(viewList.allowEdit) AS allowEdit, MAX(viewList.viewContactInfo) AS viewContactInfo FROM people JOIN viewList ON viewList.pid = people.userid WHERE viewList.viewer = ? GROUP BY people.userid ORDER BY people.lastname, people.firstname";
$stmt_people = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt_people, "s", $userid);
mysqli_stmt_execute($stmt_people);
$result = mysqli_stmt_get_result($stmt_people);

$people_rows = [];
while ($r = mysqli_fetch_assoc($result)) {
    $people_rows[] = $r;
}
$num_rows = count($people_rows);

// Count unpurchased public items added since the viewer's lastViewDate (excluding own list)
$new_counts_by_pid = [];
$new_items_query = "
    SELECT c.userid AS pid, COUNT(*) AS new_count
    FROM viewList vl
    JOIN categories c ON c.userid = vl.pid AND c.catSortOrder > -1
    JOIN items i ON i.cid = c.cid
    LEFT JOIN (
        SELECT iid, SUM(quantity) AS total_bought
        FROM purchaseHistory
        GROUP BY iid
    ) ph ON ph.iid = i.iid
    WHERE vl.viewer = ?
      AND vl.pid != ?
      AND i.createDate > '2020-12-31 23:59:59'
      AND i.createDate > vl.lastViewDate
      AND (i.allowCheck != 'true' OR COALESCE(ph.total_bought, 0) < i.quantity)
    GROUP BY c.userid
";
$stmt_new = mysqli_prepare($link, $new_items_query);
if ($stmt_new) {
    mysqli_stmt_bind_param($stmt_new, "ss", $userid, $userid);
    mysqli_stmt_execute($stmt_new);
    $res_new = mysqli_stmt_get_result($stmt_new);
    while ($nrow = mysqli_fetch_assoc($res_new)) {
        $new_counts_by_pid[$nrow['pid']] = (int)$nrow['new_count'];
    }
}

$family_new_counts = [];
foreach ($people_rows as $prow) {
    $fam_key = strtolower(trim($prow['lastname'] ?? ''));
    $p_new = $new_counts_by_pid[$prow['userid']] ?? 0;
    if ($p_new > 0) {
        $family_new_counts[$fam_key] = ($family_new_counts[$fam_key] ?? 0) + $p_new;
    }
}

$birthday_map = [];
$upcoming_events = [];
$month_lookup = [
    'january' => 1, 'february' => 2, 'march' => 3, 'april' => 4,
    'may' => 5, 'june' => 6, 'july' => 7, 'august' => 8,
    'september' => 9, 'october' => 10, 'november' => 11, 'december' => 12
];
$todayStart = strtotime('today');
$currentYear = (int)date('Y');

foreach ($people_rows as $row) {
    if (true) {
        $name = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
        $bmonthRaw = trim((string)($row['bmonth'] ?? ''));
        $bdayInt = (int)($row['bday'] ?? 0);
        if ($bmonthRaw !== '' && $bdayInt >= 1 && $bdayInt <= 31) {
            $birthday_map[$row['userid']] = "🎂 Birthday: " . $bmonthRaw . " " . $bdayInt;

            $mNum = $month_lookup[strtolower($bmonthRaw)] ?? 0;
            if ($mNum >= 1 && $mNum <= 12) {
                $targetYear = $currentYear;
                $dayForYear = ($mNum === 2 && $bdayInt === 29 && !checkdate(2, 29, $targetYear)) ? 28 : $bdayInt;
                $bdayTs = mktime(0, 0, 0, $mNum, $dayForYear, $targetYear);
                if ($bdayTs < $todayStart) {
                    $targetYear = $currentYear + 1;
                    $dayForYear = ($mNum === 2 && $bdayInt === 29 && !checkdate(2, 29, $targetYear)) ? 28 : $bdayInt;
                    $bdayTs = mktime(0, 0, 0, $mNum, $dayForYear, $targetYear);
                }
                $daysAway = (int)round(($bdayTs - $todayStart) / 86400);
                if ($daysAway >= 0 && $daysAway <= 45) {
                    $upcoming_events[] = [
                        'days' => $daysAway,
                        'icon' => '🎂',
                        'name' => $name,
                        'date_label' => substr(ucfirst(strtolower($bmonthRaw)), 0, 3) . ' ' . $bdayInt,
                        'url' => ($row['userid'] === $userid)
                            ? 'modifyList/modifyList.php'
                            : ('viewList/viewList.php?recip=' . urlencode($row['userid']) . '&name=' . urlencode($name))
                    ];
                }
            }
        }
    }
}

$xmasTs = mktime(0, 0, 0, 12, 25, $currentYear);
if ($xmasTs >= $todayStart) {
    $xmasDays = (int)round(($xmasTs - $todayStart) / 86400);
    if ($xmasDays <= 55) {
        $upcoming_events[] = [
            'days' => $xmasDays,
            'icon' => '🎄',
            'name' => 'Christmas',
            'date_label' => 'Dec 25',
            'url' => ''
        ];
    }
}

usort($upcoming_events, function($a, $b) {
    return $a['days'] <=> $b['days'];
});

$season = getSeason();
if ($season == 1) {
    $image = "winter.png";
} elseif ($season == 2) {
    $image = "spring.png";
} elseif ($season == 3) {
    $image = "summer.png";
} elseif ($season == 4) {
    $image = "autumn.png";
} else {
    $image = "waving_santa2.gif";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="WishList">
  <link rel="apple-touch-icon" href="images/icons/icon-192x192.png">
  <link rel="manifest" href="manifest.json">
  <link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
  <title>WishList Home Page</title>
  <script src="js/jquery-3.7.1.min.js"></script>
  <script>
  var birthdayInfo = <?php echo json_encode($birthday_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

  function hidePersonHover() {
      var card = document.getElementById("personHoverCard");
      if (card) {
          card.classList.remove("visible");
          card.setAttribute("aria-hidden", "true");
      }
  }

  function positionPersonHover(btn, card) {
      card.style.left = "0px";
      card.style.top = "0px";

      var btnRect = btn.getBoundingClientRect();
      var cardW = card.offsetWidth;
      var cardH = card.offsetHeight;
      var vw = document.documentElement.clientWidth || window.innerWidth;
      var vh = document.documentElement.clientHeight || window.innerHeight;
      var gap = 10;
      var margin = 8;
      var left, top;

      if (btnRect.right + gap + cardW <= vw - margin) {
          left = btnRect.right + gap;
          top = btnRect.top + (btnRect.height - cardH) / 2;
      } else if (btnRect.left - gap - cardW >= margin) {
          left = btnRect.left - gap - cardW;
          top = btnRect.top + (btnRect.height - cardH) / 2;
      } else {
          left = btnRect.left + (btnRect.width - cardW) / 2;
          left = Math.max(margin, Math.min(left, Math.max(margin, vw - cardW - margin)));
          if (btnRect.top - gap - cardH >= margin) {
              top = btnRect.top - gap - cardH;
          } else {
              top = btnRect.bottom + gap;
          }
      }

      top = Math.max(margin, Math.min(top, Math.max(margin, vh - cardH - margin)));
      card.style.left = Math.round(left) + "px";
      card.style.top = Math.round(top) + "px";
  }

  function showPersonHover(btn) {
      var personId = btn ? btn.getAttribute("data-userid") : null;
      var bdayText = personId ? birthdayInfo[personId] : null;
      var card = document.getElementById("personHoverCard");
      if (!card || !bdayText) {
          hidePersonHover();
          return;
      }

      card.textContent = bdayText;
      positionPersonHover(btn, card);
      card.classList.add("visible");
      card.setAttribute("aria-hidden", "false");
  }

  function showfamily(selector) {
      hidePersonHover();
      if ($(selector).is(":visible")) {
          $(selector).slideUp('fast');
      } else {
          $(selector).slideDown('fast');
      }
  }

  $(document).ready(function() {
      var lastPointerWasTouch = false;
      document.addEventListener("pointerdown", function(e) {
          lastPointerWasTouch = (e.pointerType === "touch");
      }, true);

      $(document).on("mouseenter focusin", ".person-list-btn", function() {
          if (!lastPointerWasTouch) {
              showPersonHover(this);
          }
      });

      $(document).on("mouseleave focusout click", ".person-list-btn", function() {
          hidePersonHover();
      });

      window.addEventListener("scroll", hidePersonHover, { passive: true });
      window.addEventListener("resize", hidePersonHover);
  });
  </script>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("home.php:Home|", true); ?>

<center>
  <table class="headerBox" cellspacing="0" cellpadding="5" border="0">
    <tr>
      <td class="headerCell" align="center">
        <img border="0" src="images/<?php echo escapeText($image); ?>" alt="" style="max-height: 90px; height: auto; width: auto;">
      </td>
      <td class="headerCell">
        Welcome to the WishList Site
      </td>
    </tr>
  </table>

  <?php if (!empty($upcoming_events)): ?>
  <div class="celebrations-banner no-print">
    <div class="celebrations-title">🎉 Upcoming Celebrations</div>
    <div class="celebrations-pills">
      <?php foreach ($upcoming_events as $ev): ?>
        <?php
          if ($ev['days'] === 0) {
              $whenText = 'Today!';
          } elseif ($ev['days'] === 1) {
              $whenText = 'Tomorrow (' . $ev['date_label'] . ')';
          } else {
              $whenText = 'in ' . $ev['days'] . ' days (' . $ev['date_label'] . ')';
          }
        ?>
        <?php if ($ev['url'] !== ''): ?>
          <a href="<?php echo escapeText($ev['url']); ?>" class="celebration-pill">
            <span><?php echo $ev['icon']; ?> <b><?php echo escapeText($ev['name']); ?></b></span>
            <span class="celebration-when"><?php echo escapeText($whenText); ?></span>
          </a>
        <?php else: ?>
          <span class="celebration-pill celebration-holiday">
            <span><?php echo $ev['icon']; ?> <b><?php echo escapeText($ev['name']); ?></b></span>
            <span class="celebration-when"><?php echo escapeText($whenText); ?></span>
          </span>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <table border="0" class="home-menu-table">
  <tr><td valign="center">
<?php
$oldlast = null;
$div_last = "<div id='lastnames'>";
foreach ($people_rows as $row) {
    $family_name = trim($row['lastname'] ?? '');
    $display_family = ($family_name !== '') ? $family_name : 'Other';
    $fam_key = strtolower($family_name);

    if ($oldlast === null || strcasecmp($oldlast, $family_name) !== 0) {
        if ($oldlast !== null) {
            $div_last .= "<br></div>\n";
        }
        $oldlast = $family_name;
        $family_id = "fam_" . md5($fam_key);
        $fam_new = $family_new_counts[$fam_key] ?? 0;
        $fam_badge = ($fam_new > 0) ? " <span class='new-count-pill'>🟢 " . $fam_new . " new</span>" : "";
        $div_last .= "<button type='button' class='buttonstyle' style='width:100%;' onclick=\"showfamily('#" . $family_id . "')\">" . escapeText($display_family) . $fam_badge . "</button><br>\n";
        $div_last .= "<div id='" . $family_id . "' style='display:none;'>";
    }

    $name = trim($row['firstname'] . ' ' . $row['lastname'] . ' ' . ($row['suffix'] ?? ''));
    $name_url = urlencode($name);
    $recip_url = urlencode($row['userid']);
    $name_esc = escapeText($name);
    $userid_attr = escapeText($row['userid']);
    $person_new = $new_counts_by_pid[$row['userid']] ?? 0;
    $person_badge = ($person_new > 0) ? " <span class='new-count-pill'>🟢 " . $person_new . " new</span>" : "";

    $div_last .= "<button type='button' class='lightbutton person-list-btn' data-userid='" . $userid_attr . "' style='width:100%;' onclick='window.location=\"viewList/viewList.php?recip=" . $recip_url . "&name=" . $name_url . "\"'>" . $name_esc . $person_badge . "</button><br>\n";
}
if ($oldlast !== null) {
    $div_last .= "</div>\n";
}
$div_last .= "</div>\n";

if ($num_rows > 0) {
    echo $div_last;
} else {
    echo "<div class='home-empty-notice'>You cannot view anybody's list yet.<br>Go to <b>Update Your Account</b> to request access to lists.</div>\n";
}
?>
  </td></tr>
  <tr><td>
    <div id="options">
      <hr>
      <button class="buttonstyle" style="width:100%;" onclick="location.href='modifyList/modifyList.php'">Modify Your List</button><br>
      <button class="buttonstyle" style="width:100%;" onclick="location.href='viewPurchases.php'">View your Purchases</button><br>
      <button class="buttonstyle" style="width:100%;" onclick="location.href='messages.php'">Messages</button><br>
      <button class="buttonstyle" style="width:100%;" onclick="location.href='updateAccount/updateAccount.php'">Update Your Account</button><br>
      <?php if (!empty($_SESSION["admin"]) && $_SESSION["admin"] == 1): ?>
      <button class="buttonstyle" style="width:100%;" onclick="location.href='admin.php'">Admin Page</button><br>
      <?php endif; ?>
    </div>
  </td></tr>
  </table>
  <div id="personHoverCard" class="person-hover-card no-print" role="tooltip" aria-hidden="true"></div>
</center>

<?php
// Fetch unread messages for the current user
$unread_messages_query = "SELECT m.message_id, m.sender_id, m.subject, m.body, m.timestamp, p.firstname, p.lastname, p.suffix FROM messages m LEFT JOIN people p ON m.sender_id = p.userid WHERE m.recipient_id = ? AND m.is_read = 0 AND m.recipient_deleted = 0 ORDER BY m.timestamp DESC";
$stmt_unread = mysqli_prepare($link, $unread_messages_query);
mysqli_stmt_bind_param($stmt_unread, "s", $userid);
mysqli_stmt_execute($stmt_unread);
$unread_messages_result = mysqli_stmt_get_result($stmt_unread);

if ($unread_messages_result && mysqli_num_rows($unread_messages_result) > 0) {
    $_SESSION['messages'] = [];
    while ($msg_row = mysqli_fetch_assoc($unread_messages_result)) {
        $_SESSION['messages'][] = $msg_row;
    }
} else {
    unset($_SESSION['messages']);
}
?>

<?php if (!empty($_SESSION['messages'])): ?>
<div id="messageModal" class="modal">
  <div class="modal-content">
    <span class="close-messages">&times;</span>
    <h2>You have new messages</h2>
    <?php foreach ($_SESSION['messages'] as $msg): ?>
      <?php
        if (empty($msg['sender_id'])) {
            $senderLabel = (stripos((string)($msg['subject'] ?? ''), '[Anonymous Gift Shopper]') === 0)
                ? '🕵️ A Gift Shopper (Anonymous)'
                : 'System';
        } elseif (!empty($msg['firstname']) || !empty($msg['lastname'])) {
            $senderLabel = trim($msg['firstname'] . ' ' . $msg['lastname'] . ' ' . ($msg['suffix'] ?? ''));
        } else {
            $senderLabel = $msg['sender_id'];
        }
      ?>
      <div class="message">
        <h3><strong>Subject:</strong> <?php echo escapeText($msg['subject']); ?></h3>
        <p><strong>From:</strong> <?php echo escapeText($senderLabel); ?></p>
        <div><?php echo formatMessage($msg['body']); ?></div>
        <form action="markMessageAsRead.php" method="post" class="mark-as-read-form" style="margin-top:8px;">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
          <input type="hidden" name="message_id" value="<?php echo (int)$msg['message_id']; ?>">
          <button type="submit" name="decision" value="read" class="buttonstyle">Mark as Read</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($pending_requests_result && mysqli_num_rows($pending_requests_result) > 0): ?>
<div id="accessRequestModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Access Requests</h2>
    <?php while ($request = mysqli_fetch_assoc($pending_requests_result)): ?>
      <?php
        $reqFull = trim($request['firstname'] . ' ' . $request['lastname'] . ' ' . ($request['suffix'] ?? ''));
        $canAskBack = empty($request['already_viewing_theirs']) && empty($request['already_requested_theirs']);
      ?>
      <div class="request">
        <p><b><?php echo escapeText($reqFull); ?></b> would like to view your wishlist.</p>
        <form action="handleAccessRequest.php" method="post" style="display:flex; flex-wrap:wrap; gap:6px;">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
          <input type="hidden" name="return_to" value="home">
          <input type="hidden" name="requestId" value="<?php echo (int)$request['id']; ?>">
          <?php if ($canAskBack): ?>
            <button type="submit" name="decision" value="approve_reciprocal" class="buttonstyle">🔄 Approve &amp; Request Their List Back</button>
          <?php endif; ?>
          <button type="submit" name="decision" value="approve_contact" class="buttonstyle">Approve (Full Purchasing + Contact)</button>
          <button type="submit" name="decision" value="approve_readonly" class="lightbutton">Approve (Read-Only)</button>
          <button type="submit" name="decision" value="deny" class="lightbutton">Deny</button>
        </form>
      </div>
    <?php endwhile; ?>
  </div>
</div>
<?php endif; ?>

<?php renderWhatsNewModal($showWhatsNewPopup, false, $csrf_token); ?>

<script>
$(document).ready(function() {
  var accessModal = document.getElementById("accessRequestModal");
  if (accessModal) {
    var closeAccess = accessModal.querySelector(".close");
    accessModal.style.display = "block";
    if (closeAccess) {
      closeAccess.addEventListener("click", function() {
        accessModal.style.display = "none";
      });
    }
  }

  var messageModal = document.getElementById("messageModal");
  if (messageModal) {
    var closeMessages = messageModal.querySelector(".close-messages");
    messageModal.style.display = "block";
    if (closeMessages) {
      closeMessages.addEventListener("click", function() {
        messageModal.style.display = "none";
      });
    }
  }

  window.addEventListener("click", function(event) {
    if (accessModal && event.target === accessModal) {
      accessModal.style.display = "none";
    }
    if (messageModal && event.target === messageModal) {
      messageModal.style.display = "none";
    }
  });

  $(document).on("submit", ".mark-as-read-form", function(e) {
    e.preventDefault();
    var $form = $(this);
    var $card = $form.closest(".message");
    var $btn = $form.find("button[type='submit']");
    $btn.prop("disabled", true);

    $.ajax({
      url: $form.attr("action"),
      method: "POST",
      data: $form.serialize(),
      dataType: "json",
      headers: { "X-Requested-With": "XMLHttpRequest" }
    }).done(function(resp) {
      $card.slideUp(160, function() {
        $card.remove();
        if (messageModal && $(messageModal).find(".message").length === 0) {
          messageModal.style.display = "none";
        }
      });
      var badge = document.getElementById("navUnreadBadge");
      if (badge && resp && typeof resp.remaining === "number") {
        if (resp.remaining > 0) {
          badge.textContent = String(resp.remaining);
          badge.style.display = "";
        } else {
          badge.style.display = "none";
        }
      }
    }).fail(function() {
      $form.off("submit")[0].submit();
    });
  });

  var accessNotifs = <?php echo json_encode($access_notifications, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  if (Array.isArray(accessNotifs)) {
    accessNotifs.forEach(function(n) {
      if (typeof showToast === "function") {
        showToast(n.text, n.type);
      } else {
        alert(n.text);
      }
    });
  }
});

window.addEventListener("pageshow", function(event) {
  if (event.persisted) {
    window.location.reload();
  }
});
</script>
</td></tr></table>
</body>
</html>
