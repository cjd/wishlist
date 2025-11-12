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

$cname = convertString($_REQUEST["cname"]);
$description = convertString($_REQUEST["description"]);
$linkname = convertString($_REQUEST["linkname"]);
$linkurl = convertString($_REQUEST["linkurl"]);
$catSubDescription = convertString($_REQUEST["catSubDescription"]);

// Note our use of ===.  Simply == would not work as expected
// because the position of "http" is at the front of a url
if($linkurl != "" && strpos($linkurl, "http") === false){
    $linkurl = "https://" . $linkurl;
}                

$stmt = mysqli_prepare($link, "SELECT max(catSortOrder) as cso FROM categories WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$cso = 0;

if($row = mysqli_fetch_assoc($rs)){
   $cso = $row["cso"];
   $cso++; // increment
}

if($cso == "" or $cso < 0)
   $cso = 0;

$stmt = mysqli_prepare($link, "INSERT INTO categories (cid, userid, name, description, linkname, linkurl, catSortOrder, catSubDescription) VALUES (null, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "sssssis", $userid, $cname, $description, $linkname, $linkurl, $cso, $catSubDescription);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
