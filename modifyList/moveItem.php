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
  $query = "update items set itemSortOrder = itemSortOrder - 1 where itemSortOrder = " . ($iso + 1) . " and cid = " . $cid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  $query = "update items set itemSortOrder = itemSortOrder + 1 where iid = " . $iid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
}
else{
  $query = "update items set itemSortOrder = itemSortOrder + 1 where itemSortOrder = " . ($iso - 1) . " and cid = " . $cid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  $query = "update items set itemSortOrder = itemSortOrder - 1 where iid = " . $iid;
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

}

  header("Location: " . getFullPath("modifyList.php"));


  $query =  "update people set lastModDate=NOW() where userid='" . $userid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
