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
$insert_stmt = mysqli_prepare($link, "INSERT INTO purchaseHistory (iid, userid, quantity, boughtDate) VALUES (?, ?, ?, NOW())");

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
      $ar[$iid] = $bought;
      mysqli_stmt_bind_param($insert_stmt, "isi", $iid, $userid, $bought);
      mysqli_stmt_execute($insert_stmt);
    }
  }
}

if (!empty($ar)) {
  $stmt_item = mysqli_prepare($link, "SELECT title, description FROM items WHERE iid = ?");
  foreach ($ar as $iid => $qty) {
    mysqli_stmt_bind_param($stmt_item, "i", $iid);
    mysqli_stmt_execute($stmt_item);
    $rs_item = mysqli_stmt_get_result($stmt_item);
    if ($row = mysqli_fetch_assoc($rs_item)) {
      $message .= "<font color=red>Bought " . $qty . " of</font> <i>" . $row["title"] . 
        "</i> - " . $row["description"] . "<p>";
    }
  }
  $message .= "</dir>";

  $stmt_viewers = mysqli_prepare($link, "SELECT email FROM viewList JOIN people ON viewer = userid WHERE pid = ? AND viewer != ? AND viewer != ?");
  mysqli_stmt_bind_param($stmt_viewers, "sss", $receiverUserid, $receiverUserid, $userid);
  mysqli_stmt_execute($stmt_viewers);
  $res_viewers = mysqli_stmt_get_result($stmt_viewers);
  $to = "";
  while ($vrow = mysqli_fetch_assoc($res_viewers)) {
    $to .= $vrow["email"] . ",";
  }
  $to = rtrim($to, ",");
  if (!empty($to)) {
    $subject = $receiverName . "'s WishList has been modified by " . $_SESSION["fullname"];
    sendEmail($to, "", $subject, $message, 0);
  }
}

header("Location: " . getFullPath("viewList.php?recip=". urlencode($receiverUserid) ."&name=". urlencode($receiverName)));
exit;
