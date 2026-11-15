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

verifyCsrfOrDie();

$receiverName = $_REQUEST["receiverName"] ?? "";
$receiverUserid = $_REQUEST["receiverUserid"] ?? "";
$userid = $_SESSION["userid"];

$message = "<font color=indianred><b>" . $_SESSION["fullname"] .
"</b></font> has checked off the following items from <b>" . $receiverName .
"'s</b> WishList<dir>";

$ar = array();
$insert_stmt = mysqli_prepare($link, "INSERT INTO purchaseHistory (iid, userid, quantity, boughtDate, splitWith, splitUsers) VALUES (?, ?, ?, NOW(), ?, ?)");

foreach ($_REQUEST as $key => $value) {
  $prefix = substr($key, 0, 3);
  if ($prefix === "chk" || $prefix === "sel") {
    $iid = intval(substr($key, 3));
    if ($iid <= 0) continue;

    $bought = 0;
    if ($prefix === "chk" && $value == "on") {
      $bought = 1;
    } elseif ($prefix === "sel" && is_numeric($value)) {
      $bought = intval($value);
    }

    if ($bought > 0) {
      $selectedUsers = $_REQUEST["split_users_" . $iid] ?? [];
      if (!is_array($selectedUsers) && !empty($selectedUsers)) {
        $selectedUsers = [$selectedUsers];
      }

      $userNames = [];
      $userIdList = [];
      if (!empty($selectedUsers)) {
        $stmt_u = mysqli_prepare($link, "SELECT userid, firstname, lastname FROM people WHERE userid = ?");
        foreach ($selectedUsers as $uId) {
          $cleanUid = trim($uId);
          if ($cleanUid === "") continue;
          mysqli_stmt_bind_param($stmt_u, "s", $cleanUid);
          mysqli_stmt_execute($stmt_u);
          $u_res = mysqli_stmt_get_result($stmt_u);
          if ($u_row = mysqli_fetch_assoc($u_res)) {
            $userNames[] = trim($u_row["firstname"] . " " . $u_row["lastname"]);
            $userIdList[] = $u_row["userid"];
          }
        }
      }

      $extraNote = trim($_REQUEST["split_note_" . $iid] ?? ($_REQUEST["split_" . $iid] ?? ""));
      $splitWithParts = $userNames;
      if (!empty($extraNote)) {
        $splitWithParts[] = $extraNote;
      }
      $splitWith = implode(", ", $splitWithParts);
      $splitUsersStr = implode(",", $userIdList);

      $ar[$iid] = ['qty' => $bought, 'split' => $splitWith, 'splitUsers' => $userIdList];
      mysqli_stmt_bind_param($insert_stmt, "isiss", $iid, $userid, $bought, $splitWith, $splitUsersStr);
      mysqli_stmt_execute($insert_stmt);
    }
  }
}

if (!empty($ar)) {
  $stmt_item = mysqli_prepare($link, "SELECT title, description FROM items WHERE iid = ?");
  $coBuyerMessages = [];
  $generalMessageLines = [];

  foreach ($ar as $iid => $pdata) {
    $qty = $pdata['qty'];
    $split = $pdata['split'];
    $splitUsers = $pdata['splitUsers'];

    mysqli_stmt_bind_param($stmt_item, "i", $iid);
    mysqli_stmt_execute($stmt_item);
    $rs_item = mysqli_stmt_get_result($stmt_item);
    if ($row = mysqli_fetch_assoc($rs_item)) {
      $splitText = !empty($split) ? " <font color=purple><b>(Group gift with " . htmlspecialchars($split) . ")</b></font>" : "";
      $itemLine = "<font color=red>Bought " . $qty . " of</font> <i>" . htmlspecialchars($row["title"]) . "</i>" . $splitText . " - " . htmlspecialchars($row["description"]) . "<p>";

      if (!empty($splitUsers)) {
        // Send dedicated notification only to selected co-buyers
        foreach ($splitUsers as $cbUser) {
          if (!isset($coBuyerMessages[$cbUser])) {
            $coBuyerMessages[$cbUser] = [];
          }
          $coBuyerMessages[$cbUser][] = $itemLine;
        }
      } else {
        // Solo purchases broadcast to other viewers
        $generalMessageLines[] = $itemLine;
      }
    }
  }

  // 1. Send dedicated group gift messages directly to the co-buyers
  if (!empty($coBuyerMessages)) {
    foreach ($coBuyerMessages as $cbUser => $lines) {
      $cbSubject = "Group Gift: " . $receiverName . "'s WishList";
      $cbBody = "<font color=indianred><b>" . $_SESSION["fullname"] . "</b></font> has added you as a co-contributor for a group gift on <b>" . htmlspecialchars($receiverName) . "'s</b> WishList:<p><dir>" . implode("", $lines) . "</dir>";
      sendEmail($cbUser, $userid, $cbSubject, $cbBody, 0);
    }
  }

  // 2. Broadcast solo purchases to viewers (if any solo items were bought)
  if (!empty($generalMessageLines)) {
    $stmt_viewers = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ? AND viewer != ? AND viewer != ?");
    mysqli_stmt_bind_param($stmt_viewers, "sss", $receiverUserid, $receiverUserid, $userid);
    mysqli_stmt_execute($stmt_viewers);
    $res_viewers = mysqli_stmt_get_result($stmt_viewers);
    $to = "";
    while ($vrow = mysqli_fetch_assoc($res_viewers)) {
      $to .= $vrow["viewer"] . ",";
    }
    $to = rtrim($to, ",");
    if (!empty($to)) {
      $subject = $receiverName . "'s WishList has been modified by " . $_SESSION["fullname"];
      $genBody = "<font color=indianred><b>" . $_SESSION["fullname"] . "</b></font> has checked off the following items from <b>" . htmlspecialchars($receiverName) . "'s</b> WishList<dir>" . implode("", $generalMessageLines) . "</dir>";
      sendEmail($to, $userid, $subject, $genBody, 0);
    }
  }
}

header("Location: " . getFullPath("viewList.php?recip=". urlencode($receiverUserid) ."&name=". urlencode($receiverName) . "&saved=1"));
exit;
