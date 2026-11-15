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

include "../funcLib.php";

$recip = $_REQUEST["recip"];
$name = $_REQUEST["name"];

$confirm = "";
if (isset($_REQUEST["confirm"])) {
    $confirm = $_REQUEST["confirm"];
}

if ($confirm == "Go Home" || $confirm == "Ho Home") {
    unset($_SESSION["euserid"]);
    header("Location: " . getFullPath("../home.php"));
    exit;
}
?>
<HTML>

<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel=stylesheet href=../style.css type=text/css>
    <link rel=stylesheet href=../css/featherlight.min.css type=text/css>
    <script type="text/javascript" src="../js/jquery-3.7.1.min.js"></script>
    <script type="text/javascript" src="../js/featherlight.min.js"></script>
    <script type="text/javascript" src="../js/wishlist-filter.js"></script>
    <script type="text/javascript">
    function toggleSplitInput(iid, show) {
        var box = document.getElementById('split_box_' + iid);
        if (box) {
            box.style.display = show ? 'block' : 'none';
            if (show) {
                var input = box.querySelector('input');
                if (input) input.focus();
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
        var sendMessageModal = document.getElementById("sendMessageModal");
        var sendMessageBtn = document.getElementById("sendMessageBtn");
        var span = document.getElementsByClassName("close-send-message")[0];

        if (sendMessageBtn) {
            sendMessageBtn.onclick = function() {
              sendMessageModal.style.display = "block";
            }
        }

        if (span) {
            span.onclick = function() {
              sendMessageModal.style.display = "none";
            }
        }

        window.onclick = function(event) {
          if (event.target == sendMessageModal) {
            sendMessageModal.style.display = "none";
          }
        }

        $('#sendMessageForm').on('submit', function(e) {
            e.preventDefault();
            $.ajax({
                type: 'POST',
                url: 'sendMessage.php',
                data: $(this).serialize(),
                success: function(response) {
                    alert('Message sent!');
                    sendMessageModal.style.display = "none";
                }
            });
        });

        var infoModal = document.getElementById("infoModal");
        var infoModalText = document.getElementById("infoModalText");
        var closeInfoModal = document.getElementsByClassName("close-info-modal")[0];

        $('.info-icon').on('click', function() {
            var createDate = $(this).data('create-date');
            infoModalText.innerHTML = "This item was added on " + createDate;
            infoModal.style.display = "block";
        });

        if (closeInfoModal) {
            closeInfoModal.onclick = function() {
                infoModal.style.display = "none";
            }
        }

        window.onclick = function(event) {
          if (event.target == infoModal) {
            infoModal.style.display = "none";
          }
        }
    });
    </script>
</head>

<title><?php echo htmlspecialchars($name); ?>'s WishList</title>

<?php
// Alert $userid if new comments have been added to $recip's list since the
// last time $userid viewed it (unless $recip == $userid of course)
$userid = $_SESSION["userid"];

$stmt_del = mysqli_prepare($link, "DELETE FROM comments WHERE date < DATE_SUB(NOW(), INTERVAL 4 MONTH) AND userid = ?");
mysqli_stmt_bind_param($stmt_del, "s", $recip);
mysqli_stmt_execute($stmt_del);

if ($recip != $userid) {
    $stmt_perm = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE viewer = ? AND pid = ?");
    mysqli_stmt_bind_param($stmt_perm, "ss", $userid, $recip);
    mysqli_stmt_execute($stmt_perm);
    $rs_perm = mysqli_stmt_get_result($stmt_perm);

    if ($row_perm = mysqli_fetch_assoc($rs_perm)) {
        $allowEdit = ($row_perm["allowEdit"] == '1') ? 1 : 0;
    } elseif (isset($_SESSION['admin']) && $_SESSION['admin'] == 1) {
        $allowEdit = 1;
    } else {
        print "</head><body><table class='pagetable'><tr><td>";
        createNavBar("../home.php:Home|:View List");
        print "<h2><center><font color=red>You do not have permission to view this wishlist.</font></center></h2>";
        print "</td></tr></table></body></html>";
        exit;
    }

    $stmt_comm = mysqli_prepare($link, "SELECT lastViewDate FROM comments, viewList WHERE comments.userid = viewList.pid AND pid = ? AND viewer = ? AND comment_userid != ? AND date > lastViewDate");
    mysqli_stmt_bind_param($stmt_comm, "sss", $recip, $userid, $userid);
    mysqli_stmt_execute($stmt_comm);
    $rs = mysqli_stmt_get_result($stmt_comm);

    if (mysqli_num_rows($rs) > 0) {
        $alert = "onLoad=\"javascript:alert('New comments have been added since the last time you viewed this list')\"";
    } else {
        $alert = "";
    }
} else {
    $allowEdit = 1;
}

function displayWishlist($recip, $userid, $name, $allowEdit, $link)
{
    ?>
<form method="post" action="validate_purchase.php" name="wishlist">
<div class="wishlist-search-box">
  <label for="wishlistSearch"><b>Search List:</b></label>
  <input type="text" id="wishlistSearch" class="wishlist-search-input" placeholder="Type to filter items..." oninput="filterWishlist(this.value)">
  <input type="button" id="wishlistSearchClear" class="actionButton" value="Clear" onclick="clearWishlistFilter()" style="display:none;">
  <span id="wishlistSearchCount" class="wishlist-search-count"></span>
</div>
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
<input type="hidden" name="receiverUserid" value="<?php echo $recip ?>">
<input type="hidden" name="receiverName" value="<?php echo $name ?>">

<?php

      printList2($recip, $userid, $name, 0);

    ?>

<p>

<input type="submit" value="Save Changes" class="buttonstyle">
<input type="reset" value="Undo Changes" class="buttonstyle" onclick="history.go(0)">
<input type="button" value="Go Home" class="buttonstyle" onclick="location.href='../home.php'">
<input type="button" value="Send Message" class="buttonstyle" id="sendMessageBtn">
<input type="button" value="🖨️ Print List" class="buttonstyle" onclick="window.print()">
</form>

<hr>

<font size="4"><b>Comments</b></font> - <?php echo $name ?> will not see these
<p>

<?php

    if ($recip != $_SESSION["userid"]) {

        ?>
<?php
        $stmt_comm = mysqli_prepare($link, "SELECT commentId, comment_userid, firstname, lastname, suffix, comment, date FROM comments, people WHERE comments.userid = ? AND comments.comment_userid = people.userid ORDER BY date DESC");
        mysqli_stmt_bind_param($stmt_comm, "s", $recip);
        mysqli_stmt_execute($stmt_comm);
        $rs = mysqli_stmt_get_result($stmt_comm);

        while ($row = mysqli_fetch_assoc($rs)) {
            ?>
<font color="navy"><?php echo htmlspecialchars($row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"]) ?> made the following comments on <?php echo parseDate($row["date"], 1) ?></font>
<?php
              if ($_SESSION["userid"] == $row["comment_userid"]) {
                  ?>
    <b><a class="menuLink" href="deleteComment.php?commentId=<?php echo intval($row["commentId"]); ?>&recip=<?php echo urlencode($recip); ?>&name=<?php echo urlencode($name); ?>&csrf_token=<?php echo getCsrfToken(); ?>">[Remove Comment]</a></b>
<?php
              }
            ?>
  
<br>
<?php echo cleanString($row["comment"]); ?><p>

<?php
        }
        ?>
<div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px;">
  <form method="post" action="addComment.php" style="margin: 0;">
    <input type="hidden" name="recip" value="<?php echo htmlspecialchars($recip); ?>">
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
    <input type="submit" value="Add New Comment" class="buttonstyle">
  </form>
  <form method="post" action="addComment.php" style="margin: 0;">
    <input type="hidden" name="recip" value="<?php echo htmlspecialchars($recip); ?>">
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
    <input type="hidden" name="type" value="suggestion">
    <input type="submit" value="💡 Suggest Secret Gift Idea" class="buttonstyle suggestion-btn-accent">
  </form>
</div>

<?php
    } else {
        print "<font color=red><b>You are not allowed to view comments added to your list</b></font>";
    }
    ?>

</font>
</td>
</tr>
<tr><td>
<table class=navBar>
<tr>
<td class=navBar align="left">
<font size=2>
<a class="navMenuLink" href="viewLog.php?recip=<?php echo $recip ?>&name=<?php echo $name ?>">Click here to see what you bought or to unselect items</a>
</font></td>
<td class=navBar align="right">

<?php

    $stmt_mod = mysqli_prepare($link, "SELECT lastModDate FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_mod, "s", $recip);
    mysqli_stmt_execute($stmt_mod);
    $rs = mysqli_stmt_get_result($stmt_mod);

    if ($row = mysqli_fetch_assoc($rs)) {
        ?>
<font size=2>
<font color=indianred><?php echo htmlspecialchars($name) ?></font> last modified this list on <font color=navy><?php echo parseDate($row["lastModDate"], 1) ?></font></font>
<?php
    }
    ?>
  
</td></tr></table>
</td></tr></table>
<div id="sendMessageModal" class="modal">
  <div class="modal-content">
    <span class="close-send-message">&times;</span>
    <h2>Send a message to <?php echo htmlspecialchars($name); ?></h2>
    <form id="sendMessageForm">
      <input type="hidden" name="recipient_id" value="<?php echo htmlspecialchars($recip); ?>">
      <input type="text" name="subject" placeholder="Subject" required><br><br>
      <textarea name="body" rows="5" cols="50" placeholder="Message" required></textarea><br><br>
      <input type="submit" value="Send Message" class="buttonstyle">
    </form>
  </div>
</div>
<div id="infoModal" class="info-modal">
  <div class="info-modal-content">
    <span class="close-info-modal">&times;</span>
    <p id="infoModalText"></p>
  </div>
</div>
<?php
}
?>

<BODY <?php if (isset($alert)) {
    echo $alert;
} ?>>
<table class=pagetable>
<tr>
<td valign="top">

<?php
createNavBar("../home.php:Home|:View List - " . htmlspecialchars($_REQUEST["name"] ?? ''), false, "viewOther");
?>

<br>


<font size="4">

<?php

  $canEdit = false;
$stmt_edit = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
mysqli_stmt_bind_param($stmt_edit, "ss", $recip, $_SESSION["userid"]);
mysqli_stmt_execute($stmt_edit);
$rs_edit = mysqli_stmt_get_result($stmt_edit);
if ($row_edit = mysqli_fetch_assoc($rs_edit)) {
    if ($row_edit["allowEdit"] == '1') {
        $canEdit = true;
    }
}

if (($recip == $_SESSION["userid"] || $canEdit) and $confirm == "Edit List") {
    if ($recip != $_SESSION["userid"]) {
        $_SESSION["euserid"] = $recip;
    } else {
        unset($_SESSION["euserid"]);
    }
    print "<meta http-equiv=\"refresh\" content=\"0;url=../modifyList/modifyList.php\">";
    print "</body></html>";
    return;
}
if (($recip == $_SESSION["userid"] || $canEdit) and $confirm != "View List") {

    print "<center><p>&nbsp;<form method=post>";
    print "<b>Are you sure you want to view the list of ".htmlspecialchars($_REQUEST["name"] ?? '')."?<br>You will be able to see any purchases that may have been made!<br>This could ruin the surprise</b>";
    print "<p><input type=submit name=confirm value=\"View List\" class=\"buttonstyle\"> <input type=submit name=confirm value=\"Edit List\" class=\"buttonstyle\"> <input type=submit name=confirm value=\"Go Home\" class=\"buttonstyle\">";
    print "</form>\n";
    print "</center>";
    print "</td></tr></table></body></html>";
    return;
} else {
    displayWishlist($recip, $userid, $name, $allowEdit, $link);
}
?>
</body>
</html>

<?php

$stmt_view = mysqli_prepare($link, "UPDATE viewList SET lastViewDate = NOW() WHERE pid = ? AND viewer = ?");
mysqli_stmt_bind_param($stmt_view, "ss", $recip, $userid);
mysqli_stmt_execute($stmt_view);

?>
