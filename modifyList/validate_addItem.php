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

$cid = $_REQUEST["cid"];

$title= convertString($_REQUEST["title"]);

$description = convertString($_REQUEST["desc"]);

$price = str_replace("$", "", trim($_REQUEST["price"]));

if(!is_numeric($price))
     $price = 0;

$quantity = $_REQUEST["quantity"];

if(!is_numeric($quantity))
     $quantity = 1;

if($quantity < 0)
     $quantity = 1;

$subdesc = convertString($_REQUEST["subdesc"]);
$link1 = convertString($_REQUEST["link1"]);
$link1url = convertString($_REQUEST["link1url"]);
if ($link1url == "") {
  $link1="";
}
if (array_key_exists("allowCheck", $_REQUEST)) {
    $allowCheck = convertString($_REQUEST["allowCheck"]);
} else {
    $allowCheck = "";
}
if (array_key_exists("addStar",$_REQUEST)) {
    $addStar = convertString($_REQUEST["addStar"]);
} else {
    $addStar = "";
}

// Note our use of ===.  Simply == would not work as expected
// because the position of "http" is at the front of a url
if($link1url != "" && strpos($link1url, "http") === false){
    $link1url = "https://" . $link1url;
}                
if($link1url != "") {
    $link1 = preg_replace("#^h.*://(.*?)/.*$#","$1",$link1url);
}

if($allowCheck == "")
  $allowCheck = "false";
else
  $allowCheck = "true";


if($addStar == "")
  $addStar = '0';
else
  $addStar = '1';


$stmt = mysqli_prepare($link, "SELECT max(itemSortOrder) as iso FROM items WHERE cid = ?");
mysqli_stmt_bind_param($stmt, "i", $cid);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$iso = 0;

if($row = mysqli_fetch_assoc($rs)){
  if($row["iso"] != ""){
    $iso = $row["iso"];
    $iso++;
  }
}

$stmt = mysqli_prepare($link, "INSERT INTO items (iid, cid, addStar, title, description, price, quantity, link1, link1url, subdesc, allowCheck, itemSortOrder, createDate) VALUES (null, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "isssdissssi", $cid, $addStar, $title, $description, $price, $quantity, $link1, $link1url, $subdesc, $allowCheck, $iso);
mysqli_stmt_execute($stmt) or die("Could not execute statement: " . mysqli_stmt_error($stmt));

if (isset($_FILES['image']) && ($_FILES['image']['error']==0)) {
    $uploadDir = $base_dir.'/uploads/';
    $id = mysqli_insert_id($link);
    $uploadFile = "image-" . $id.".jpg";
    $result = process_image_upload('image',$uploadDir.$uploadFile);
    if ($result) {
        $stmt = mysqli_prepare($link, "UPDATE items SET image = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt, "si", $uploadFile, $id);
        mysqli_stmt_execute($stmt) or die("Could not execute statement: " . mysqli_stmt_error($stmt));
    }
}

header("Location: " . getFullPath("modifyList.php"));
$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt) or die("Could not execute statement: " . mysqli_stmt_error($stmt));

