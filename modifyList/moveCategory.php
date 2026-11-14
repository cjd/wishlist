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

$dir = $_REQUEST["dir"] ?? "";
$cid = intval($_REQUEST["cid"] ?? 0);
$cso = intval($_REQUEST["cso"] ?? 0);

$check = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ?");
mysqli_stmt_bind_param($check, "is", $cid, $userid);
mysqli_stmt_execute($check);
$res = mysqli_stmt_get_result($check);
if (mysqli_num_rows($res) == 0) {
    die("Unauthorized: Category does not belong to you.");
}

if($dir == "down"){
  $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder - 1 WHERE catSortOrder = ? AND userid = ?");
  $new_cso = $cso + 1;
  mysqli_stmt_bind_param($stmt, "is", $new_cso, $userid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder + 1 WHERE cid = ? AND userid = ?");
  mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
  mysqli_stmt_execute($stmt);

}
else
{
  $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder + 1 WHERE catSortOrder = ? AND userid = ?");
  $new_cso = $cso - 1;
  mysqli_stmt_bind_param($stmt, "is", $new_cso, $userid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "UPDATE categories SET catSortOrder = catSortOrder - 1 WHERE cid = ? AND userid = ?");
  mysqli_stmt_bind_param($stmt, "is", $cid, $userid);
  mysqli_stmt_execute($stmt);
}

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));
exit;
