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

$dir = mysqli_escape_string($link,$_REQUEST["dir"]);

$cid = mysqli_escape_string($link,$_REQUEST["cid"]);

$iid = mysqli_escape_string($link,$_REQUEST["iid"]);

$iso = mysqli_escape_string($link,$_REQUEST["iso"]);

if($dir == "down"){
  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE itemSortOrder = ? AND cid = ?");
  $new_iso = $iso + 1;
  mysqli_stmt_bind_param($stmt, "ii", $new_iso, $cid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);
}
else{
  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE itemSortOrder = ? AND cid = ?");
  $new_iso = $iso - 1;
  mysqli_stmt_bind_param($stmt, "ii", $new_iso, $cid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);
}

  header("Location: " . getFullPath("modifyList.php"));

  $stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
