<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include "funcLib.php";

// Fetch pending access requests
$pending_requests_query = "SELECT ar.*, p.firstname, p.lastname FROM accessRequests ar JOIN people p ON ar.requesterId = p.userid WHERE ar.targetId = '" . $_SESSION['userid'] . "' AND ar.status = 'pending'";
$pending_requests_result = mysqli_query($link, $pending_requests_query);

// Fetch notifications for the requester
$notifications_query = "SELECT ar.*, p.firstname, p.lastname FROM accessRequests ar JOIN people p ON ar.targetId = p.userid WHERE ar.requesterId = '" . $_SESSION['userid'] . "' AND ar.status != 'pending' AND ar.notified = 0";
$notifications_result = mysqli_query($link, $notifications_query);

if (mysqli_num_rows($notifications_result) > 0) {
    echo "<script>";
    while ($notification = mysqli_fetch_assoc($notifications_result)) {
        $targetName = $notification['firstname'] . ' ' . $notification['lastname'];
        if ($notification['status'] == 'approved') {
            echo "alert('Your request to view " . $targetName . "\\'s list has been approved.');";
        } else {
            echo "alert('Your request to view " . $targetName . "\\'s list has been denied.');";
        }
        // Mark as notified
        $update_notified_query = "UPDATE accessRequests SET notified = 1 WHERE id = " . $notification['id'];
        mysqli_query($link, $update_notified_query);
    }
    echo "</script>";
}

if (isset($_REQUEST["lastname"])) {
    $lastname = $_REQUEST["lastname"];
} else {
    $lastname = "";
}
unset($_SESSION["euserid"]);

$query = "select people.*, viewList.allowEdit from people, viewList where pid = people.userid and viewer='" . $userid . "' order by lastname, firstname";

$result = mysqli_query($link, $query) or die("Could not query: " . mysqli_error($link));

$num_rows = mysqli_num_rows($result);

?>
<!DOCTYPE html>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=style.css type=text/css>
<link rel="manifest" href="/manifest.json">

<script src="js/jquery-3.7.1.min.js"></script>
<script language="JavaScript">
<!-- Begin JavaScript
function showfamily(lastname) {
    if ($(lastname).is(":visible")) {
        $(lastname).slideUp('fast');
    } else {
        $(lastname).slideDown('fast');
    }
}

$(document).ready(function() {
  if (screen.width <= 1000) {
    $("#message").hide();
  }
});

var contactInfo = {};
<?php
   mysqli_data_seek($result, 0);
while ($row = mysqli_fetch_assoc($result)) {
    $allowView = $row["viewContactInfo"] ?? false;
    $new_userid = $row["userid"];
    $name = $row["firstname"] . ' ' . $row["lastname"] . ' ' .$row["suffix"];
    $email = $row["email"];
    if ($allowView) {
        $contact_string = $name."\\n".$email."\\n";
        if ($row['bday'] <> 0) {
            $contact_string .= "Birthday: ".$row['bmonth']." ".$row['bday'];
        }
        echo "contactInfo['".$new_userid."'] = \"".$contact_string."\";\n";
    }
}
?>
  var theObject;
  navName = navigator.appName;
  navVer = parseInt(navigator.appVersion);

  function show_record(obj, txtArea, PersonName) {
    if ((theObject != obj) && (screen.width > 1000)) {
      if (contactInfo[PersonName]) {
        txtArea.value = contactInfo[PersonName];
      }
      theObject = obj;
    }
  }
// End JavaScript-->
</script>

<title>WishList Home Page</title>
<BODY>
<table class="pagetable">
<tr>

<td valign="top">

<?php
createNavBar("home.php:Home|", true);
?>

<center>

              <table class="headerBox" cellspacing="0" cellpadding="5" nosave border="0" >
                <tr>
                  <td class="headerCell" align="center" >
<?php

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
$image = "wishlist.png";
?>
<script type="text/javascript">
  if (screen.width <= 1000) {
    document.write('<img border="0" height=50px src="images/<?php echo $image ?>">');
  } else {
    document.write('<img border="0" src="images/<?php echo $image ?>">');
  }
</script>
</td><td class=headerCell>
                    Welcome to the WishList Site
                  </td>
                </tr>

              </table>

<table border=0>
<tr><td valign="center">
<?php

if ($num_rows > 0) {
    mysqli_data_seek($result, 0);
}

$oldlast = "";
$div_last = "<div id='lastnames'><div style='display:none;'>";
$div_first = "";
while ($row = mysqli_fetch_assoc($result)) {
    if ($oldlast != $row['lastname']) {
        $oldlast = $row['lastname'];
        $div_last .= "<br></div><button class='buttonstyle' style='width:100%;' onclick=\"showfamily('#".$row['lastname']."')\" >".$row['lastname']."</button><br>\n";
        $div_last .= "<div id='".$row['lastname']."' style='display:none;'>";
    }

    $name =  $row['firstname'] . ' ' . $row['lastname'];

    $div_last .= "<button class='lightbutton' style='width:100%;' onclick='window.location=\"viewList/viewList.php?recip=" . $row['userid']. "&name=" . $name . "\"' onmouseover='show_record(this, theForm.contact, \"" . $row['userid']. "\")'>" . $name . "</button><br>\n";
}
$div_last .= "</div>\n</div>\n";


if ($num_rows > 0) {
    if ($lastname != "") {
        $message = "Click on a name to view their Wishlist";
    } else {
        $message = "Click on a family to view their members";
    }
} else {
    $message = "You cannot view anybody's list\\nGoto Update Your Account > Manage List Access to add people";
}

$textarea = "<div id=message><form name=theForm><textarea COLS='30' ROWS='3' class='contact' WRAP='physical' noscroll NAME='contact' readonly>" . $message ."</textarea></form></div>";
echo "<table width=100%><tr><td>";
echo $div_last."\n";
echo "</td><td>\n";
echo $div_first."\n";
echo "</td></tr></table>\n";
echo "</td><td>\n";
echo $textarea."\n";
?>
</td></tr><tr><td>
</center>
<div id=options>
<hr>
<button class='buttonstyle' style='width:100%;' onclick="location.href='modifyList/modifyList.php'">Modify Your List</button><br/>
<button class='buttonstyle' style='width:100%;' onclick="location.href='viewPurchases.php'">View your Purchases</button><br/>
<button class='buttonstyle' style='width:100%;' onclick="location.href='messages.php'">Messages</button><br/>
<button class='buttonstyle' style='width:100%;' onclick="location.href='updateAccount/updateAccount.php'">Update Your Account</button><br/>
<?php
if ($_SESSION["admin"] == 1) {
    ?>
<button class='buttonstyle' style='width:100%;' onclick="location.href='admin.php'">Admin Page</button><br/>
<?php
}
?>
</div>
</td></tr></table>

<?php
// Fetch unread messages for the current user
$unread_messages_query = "SELECT m.message_id, m.sender_id, m.subject, m.body, m.timestamp, p.firstname, p.lastname FROM messages m LEFT JOIN people p ON m.sender_id = p.userid WHERE m.recipient_id = '" . $_SESSION['userid'] . "' AND m.is_read = 0 ORDER BY m.timestamp DESC";
$unread_messages_result = mysqli_query($link, $unread_messages_query);

if (mysqli_num_rows($unread_messages_result) > 0) {
    $_SESSION['messages'] = [];
    while ($message = mysqli_fetch_assoc($unread_messages_result)) {
        $_SESSION['messages'][] = $message;
    }
} else {
    unset($_SESSION['messages']);
}
?>

<?php if (isset($_SESSION['messages']) && !empty($_SESSION['messages'])): ?>
<div id="messageModal" class="modal">
  <div class="modal-content">
    <span class="close-messages">&times;</span>
    <h2>You have new messages</h2>
    <?php foreach ($_SESSION['messages'] as $message): ?>
      <div class="message">
        <h3><strong>Subject:</strong> <?php echo htmlspecialchars($message['subject']); ?></h3>
        <p><strong>From:</strong> <?php echo $message['sender_id'] ? htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) : 'System'; ?></p>
        <p><?php echo formatMessage($message['body']); ?></p>
        <form action="markMessageAsRead.php" method="post" class="mark-as-read-form">
          <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
          <button type="submit" name="decision" value="read" class="buttonstyle">Mark as Read</button>
        </form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
$(document).ready(function() {
  var modal = document.getElementById("accessRequestModal");
  if (modal) {
    var span = document.getElementsByClassName("close")[0];
    modal.style.display = "block";

    span.onclick = function() {
      modal.style.display = "none";
    }

    window.onclick = function(event) {
      if (event.target == modal) {
        modal.style.display = "none";
      }
    }
  }

  var messageModal = document.getElementById("messageModal");
  if (messageModal) {
    var span = document.getElementsByClassName("close-messages")[0];
    messageModal.style.display = "block";

    span.onclick = function() {
      messageModal.style.display = "none";
    }

    window.onclick = function(event) {
      if (event.target == messageModal) {
        messageModal.style.display = "none";
      }
    }
  }
});
</script>
<?php if (mysqli_num_rows($pending_requests_result) > 0): ?>
<div id="accessRequestModal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <h2>Access Requests</h2>
    <?php while ($request = mysqli_fetch_assoc($pending_requests_result)): ?>
      <div class="request">
        <p><b><?php echo $request['firstname'] . ' ' . $request['lastname']; ?></b> would like to view your wishlist.</p>
        <form action="handleAccessRequest.php" method="post">
          <input type="hidden" name="requestId" value="<?php echo $request['id']; ?>">
          <input type="hidden" name="requesterId" value="<?php echo $request['requesterId']; ?>">
          <button type="submit" name="decision" value="approve_readonly" class="buttonstyle">Approve (Read-Only)</button>
          <button type="submit" name="decision" value="approve_contact" class="buttonstyle">Approve (with List Access)</button>
          <button type="submit" name="decision" value="deny" class="buttonstyle">Deny</button>
        </form>
      </div>
    <?php endwhile; ?>
  </div>
</div>
<?php endif; ?>

</td></tr></table>
</body>
</html>
