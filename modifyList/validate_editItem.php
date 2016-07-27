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
    $link1url = "http://" . $link1url;
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

  $query = "update items set itemSortOrder = itemSortOrder - 1 where itemSortOrder > " . 
    $iso . " and cid=" . $cid;
  //print $query . "<br>";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  $query = "select max(itemSortOrder) as iso from items where cid = " . $movecid;
  $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
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


$query = "update items set title='" . $title . 
"', description='" . $description . 
"', quantity=" . $quantity . 
", subdesc='" . $subdesc . 
"', price = " . $price . 
", allowCheck='" . $allowCheck . 
"', addStar=" . $addStar . 
", link1='" . $link1 . 
"', link1url='" . $link1url .
"', itemSortOrder = " . $iso .
", cid = " . $movecid .
" where iid=" . $iid;

if (isset($_FILES['image']) && ($_FILES['image']['error']==0)) {
    $uploadDir = $base_dir.'/uploads/';
    $uploadFile = "image-" . $iid.".jpg";
    if (is_file($uploadFile)) {
      unlink($uploadFile);
    }
    $result = process_image_upload('image',$uploadDir.$uploadFile);
    if ($result) {
        $query = "UPDATE items SET image = \"".$uploadFile."\" WHERE iid=".$iid.";";
        $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
    }
}



//print $query . "<br>";
$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

header("Location: " . getFullPath("modifyList.php"));

$query = "update people set lastModDate=NOW() where userid='" . $userid . "'";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
