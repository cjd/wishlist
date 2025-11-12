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

$iid = $_REQUEST["iid"];
$cid = $_REQUEST["cid"];
$movecid = $_REQUEST["movecid"];

$title= convertString($_REQUEST["title"]);
$description = convertString($_REQUEST["desc"]);

$iso = convertString($_REQUEST["iso"]);

if(!is_numeric($iso))
     $iso = 0;

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
$allowCheck = convertString($_REQUEST["allowCheck"]);
if (isset($_REQUEST["addStar"])) {
    $addStar = convertString($_REQUEST["addStar"]);
} else {
    $addStar="";
}

// Note our use of ===.  Simply == would not work as expected
// because the position of "http" is at the front of a url
if($link1url != "" && strpos($link1url, "http") === false){
    $link1url = "https://" . $link1url;
}                

if($allowCheck == "")
  $allowCheck = "false";
else
  $allowCheck = "true";

if($addStar == "")
  $addStar = '0';
else
  $addStar = '1';


if($cid != $movecid){
  // moving to a different category so update iso

  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE itemSortOrder > ? AND cid = ?");
  mysqli_stmt_bind_param($stmt, "ii", $iso, $cid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "SELECT max(itemSortOrder) as iso FROM items WHERE cid = ?");
  mysqli_stmt_bind_param($stmt, "i", $movecid);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  
  if($row = mysqli_fetch_assoc($rs)){
    if($row["iso"] != ""){
      $iso = $row["iso"];
      $iso++;
    }
    else
      $iso = 0;
  }
  else
    $iso = 0;

}


$stmt = mysqli_prepare($link, "UPDATE items SET title = ?, description = ?, quantity = ?, subdesc = ?, price = ?, allowCheck = ?, addStar = ?, link1 = ?, link1url = ?, itemSortOrder = ?, cid = ? WHERE iid = ?");
mysqli_stmt_bind_param($stmt, "ssisdsissiii", $title, $description, $quantity, $subdesc, $price, $allowCheck, $addStar, $link1, $link1url, $iso, $movecid, $iid);
mysqli_stmt_execute($stmt);

if (isset($_FILES['image']) && ($_FILES['image']['error']==0)) {
    $uploadDir = $base_dir.'/uploads/';
    $uploadFile = "image-" . $iid.".jpg";
    if (is_file($uploadFile)) {
      unlink($uploadFile);
    }
    $result = process_image_upload('image',$uploadDir.$uploadFile);
    if ($result) {
        $stmt = mysqli_prepare($link, "UPDATE items SET image = ? WHERE iid = ?");
        mysqli_stmt_bind_param($stmt, "si", $uploadFile, $iid);
        mysqli_stmt_execute($stmt);
    }
}

//print $query . "<br>";
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("modifyList.php"));

$stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
