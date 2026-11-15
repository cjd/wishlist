<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "funcLib.php";

unset($_SESSION["euserid"]);
$userid = $_SESSION["userid"];
$csrf_token = getCsrfToken();

// Fetch pending access requests for this user's list
$pending_requests_query = "SELECT ar.*, p.firstname, p.lastname, p.suffix FROM accessRequests ar JOIN people p ON ar.requesterId = p.userid WHERE ar.targetId = ? AND ar.status = 'pending' ORDER BY ar.requestDate DESC";
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

$lastname = $_REQUEST["lastname"] ?? "";

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

$contact_map = [];
foreach ($people_rows as $row) {
    if (!empty($row["viewContactInfo"])) {
        $name = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
        $lines = [$name];

        if (!empty($row['bmonth']) && !empty($row['bday']) && (int)$row['bday'] > 0) {
            $lines[] = "Birthday: " . $row['bmonth'] . " " . $row['bday'];
        }
        if (!empty($row['street'])) {
            $lines[] = $row['street'];
        }
        $cityStateZip = trim(
            ($row['city'] ?? '') .
            (!empty($row['city']) && !empty($row['state']) ? ', ' : '') .
            ($row['state'] ?? '') .
            ((!empty($row['zip']) && $row['zip'] !== '0') ? ' ' . $row['zip'] : '')
        );
        if ($cityStateZip !== '') {
            $lines[] = $cityStateZip;
        }
        if (!empty($row['phone'])) {
            $lines[] = "Home: " . $row['phone'];
        }
        if (!empty($row['mobilephone'])) {
            $lines[] = "Mobile: " . $row['mobilephone'];
        }
        $contact_map[$row['userid']] = implode("\n", $lines);
    }
}

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
  <link rel="stylesheet" href="style.css" type="text/css">
  <title>WishList Home Page</title>
  <script src="js/jquery-3.7.1.min.js"></script>
  <script>
  function showfamily(selector) {
      if ($(selector).is(":visible")) {
          $(selector).slideUp('fast');
      } else {
          $(selector).slideDown('fast');
      }
  }

  $(document).ready(function() {
      if (window.innerWidth <= 1000) {
          $("#message").hide();
      }
  });

  var contactInfo = <?php echo json_encode($contact_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
  var theObject = null;

  function show_record(obj, txtArea, personId) {
      if ((theObject !== obj) && (window.innerWidth > 1000)) {
          if (txtArea && contactInfo[personId]) {
              txtArea.value = contactInfo[personId];
          }
          theObject = obj;
      }
  }
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

  <table border="0">
  <tr><td valign="center">
<?php
$oldlast = null;
$div_last = "<div id='lastnames'>";
foreach ($people_rows as $row) {
    $family_name = trim($row['lastname'] ?? '');
    $display_family = ($family_name !== '') ? $family_name : 'Other';

    if ($oldlast === null || strcasecmp($oldlast, $family_name) !== 0) {
        if ($oldlast !== null) {
            $div_last .= "<br></div>\n";
        }
        $oldlast = $family_name;
        $family_id = "fam_" . md5(strtolower($family_name));
        $div_last .= "<button class='buttonstyle' style='width:100%;' onclick=\"showfamily('#" . $family_id . "')\">" . escapeText($display_family) . "</button><br>\n";
        $div_last .= "<div id='" . $family_id . "' style='display:none;'>";
    }

    $name = trim($row['firstname'] . ' ' . $row['lastname'] . ' ' . ($row['suffix'] ?? ''));
    $name_url = urlencode($name);
    $recip_url = urlencode($row['userid']);
    $name_esc = escapeText($name);
    $userid_js = htmlspecialchars(json_encode($row['userid']), ENT_QUOTES, 'UTF-8');

    $div_last .= "<button class='lightbutton' style='width:100%;' onclick='window.location=\"viewList/viewList.php?recip=" . $recip_url . "&name=" . $name_url . "\"' onmouseover='show_record(this, document.theForm ? document.theForm.contact : null, " . $userid_js . ")'>" . $name_esc . "</button><br>\n";
}
if ($oldlast !== null) {
    $div_last .= "</div>\n";
}
$div_last .= "</div>\n";

if ($num_rows > 0) {
    $message_box = ($lastname !== "")
        ? "Click on a name to view their Wishlist"
        : "Click on a family to view their members";
} else {
    $message_box = "You cannot view anybody's list yet.\nGo to Update Your Account to request access to lists.";
}

$textarea = "<div id='message'><form name='theForm'><textarea cols='32' rows='5' class='contact' name='contact' readonly>" . escapeText($message_box) . "</textarea></form></div>";
echo "<table width='100%'><tr><td>" . $div_last . "</td><td></td></tr></table>\n";
echo "</td><td>\n" . $textarea . "\n";
?>
  </td></tr>
  <tr><td colspan="2">
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
            $senderLabel = 'System';
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
      <?php $reqFull = trim($request['firstname'] . ' ' . $request['lastname'] . ' ' . ($request['suffix'] ?? '')); ?>
      <div class="request">
        <p><b><?php echo escapeText($reqFull); ?></b> would like to view your wishlist.</p>
        <form action="handleAccessRequest.php" method="post">
          <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
          <input type="hidden" name="return_to" value="home">
          <input type="hidden" name="requestId" value="<?php echo (int)$request['id']; ?>">
          <button type="submit" name="decision" value="approve_readonly" class="buttonstyle">Approve (Read-Only)</button>
          <button type="submit" name="decision" value="approve_contact" class="buttonstyle">Approve (Full Purchasing + Contact)</button>
          <button type="submit" name="decision" value="deny" class="buttonstyle">Deny</button>
        </form>
      </div>
    <?php endwhile; ?>
  </div>
</div>
<?php endif; ?>

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
</script>
</td></tr></table>
</body>
</html>
