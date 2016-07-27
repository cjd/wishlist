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
    $link1url = "http://" . $link1url;
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


$query = "select max(itemSortOrder) as iso from items where cid = " . $cid;

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

$iso = 0;

if($row = mysqli_fetch_assoc($rs)){
  if($row["iso"] != ""){
    $iso = $row["iso"];
    $iso++;
  }
}

$query = "insert into items (iid, cid, addStar, title, description, price, quantity, link1, link1url, subdesc, allowCheck, itemSortOrder) values (null, " .
"" . $cid . ", " .
"" . $addStar . ", " .
"'" . $title . "', " .
"'" . $description . "', " .
"" . $price . ", " .
"" . $quantity . ", " .
"'" . $link1 . "', " .
"'" . $link1url . "', " .
"'" . $subdesc . "', " .
"'" . $allowCheck . "', " .
"" . $iso . ")";

//print $query;
$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

if (isset($_FILES['image']) && ($_FILES['image']['error']==0)) {
    $uploadDir = $base_dir.'/uploads/';
    $id = mysqli_insert_id();
    $uploadFile = "image-" . $id.".jpg";
    $result = process_image_upload('image',$uploadDir.$uploadFile);
    if ($result) {
        $query = "UPDATE items SET image = \"".$uploadFile."\" WHERE iid=".$id.";";
        $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
    }
}

header("Location: " . getFullPath("modifyList.php"));
$query = "update people set lastModDate=NOW() where userid='" . $userid . "'";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

