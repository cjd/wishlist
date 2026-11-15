<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$recip = $userid;
$name = $activeOwnerFullname;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <link rel="stylesheet" href="../print.css" type="text/css">
  <title><?php echo escapeText($name); ?>'s WishList</title>
</head>
<body style="background-color: white;">
<font size="5"><b><?php echo escapeText($name); ?>'s WishList</b></font>
<p>
<h2><font color="red">All indications of purchases have been removed</font></h2>

<?php
// Pass $displayPurchases = 0 so the owner never sees who bought items on their own list
printList2($recip, $userid, $name, 0);
?>
</body>
</html>
