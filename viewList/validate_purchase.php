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

  foreach ($ar as $iid => $pdata) {
    $qty = $pdata['qty'];
    $split = $pdata['split'];
    $splitUsers = $pdata['splitUsers'];

    if (!empty($splitUsers)) {
      mysqli_stmt_bind_param($stmt_item, "i", $iid);
      mysqli_stmt_execute($stmt_item);
      $rs_item = mysqli_stmt_get_result($stmt_item);
      if ($row = mysqli_fetch_assoc($rs_item)) {
        $splitText = !empty($split) ? " <font color=purple><b>(Group gift with " . htmlspecialchars($split, ENT_QUOTES, 'UTF-8', false) . ")</b></font>" : "";
        $itemLine = "<font color=red>Bought " . $qty . " of</font> <i>" . htmlspecialchars($row["title"], ENT_QUOTES, 'UTF-8', false) . "</i>" . $splitText . " - " . htmlspecialchars($row["description"], ENT_QUOTES, 'UTF-8', false) . "<p>";

        foreach ($splitUsers as $cbUser) {
          if (!isset($coBuyerMessages[$cbUser])) {
            $coBuyerMessages[$cbUser] = [];
          }
          $coBuyerMessages[$cbUser][] = $itemLine;
        }
      }
    }
  }

  // Send dedicated group gift messages directly to the co-buyers (solo purchases send no messages)
  if (!empty($coBuyerMessages)) {
    foreach ($coBuyerMessages as $cbUser => $lines) {
      $cbSubject = "Group Gift: " . $receiverName . "'s WishList";
      $cbBody = "<font color=indianred><b>" . $_SESSION["fullname"] . "</b></font> has added you as a co-contributor for a group gift on <b>" . htmlspecialchars($receiverName, ENT_QUOTES, 'UTF-8', false) . "'s</b> WishList:<p><dir>" . implode("", $lines) . "</dir>";
      sendEmail($cbUser, $userid, $cbSubject, $cbBody, 0);
    }
  }
}

header("Location: " . getFullPath("viewList.php?recip=". urlencode($receiverUserid) ."&name=". urlencode($receiverName) . "&saved=1"));
exit;
