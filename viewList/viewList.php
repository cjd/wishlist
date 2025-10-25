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

$recip = $_REQUEST["recip"];
$name = $_REQUEST["name"];

$confirm = "";
if (isset ($_REQUEST["confirm"])) {
    $confirm = $_REQUEST["confirm"];
}

if($confirm == "No"){
     header("Location: " . getFullPath("../home.php"));
}
?>
<HTML>

<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>

<link rel=stylesheet href=../style.css type=text/css>
<script type="text/javascript" src="../js/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="../js/jquery.vanillabox-0.1.6.min.js"></script>
<link rel=stylesheet href="../js/theme/bitter/vanillabox.css">
<script type="text/javascript">
$(document).ready(function() {
    $('#single-image').vanillabox();
});
</script>


<head>
</head>

<title><?php echo $name ?>'s WishList</title>

<?php
// Alert $userid if new comments have been added to $recip's list since the 
// last time $userid viewed it (unless $recip == $userid of course)
$userid=$_SESSION["userid"];
$query="DELETE FROM comments WHERE date < DATE_SUB(NOW(), INTERVAL 4 MONTH) and userid = '".$recip."'";
$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
if($recip != $userid){
  $query = "select allowEdit from viewList where viewList.viewer = '".$userid."' and viewList.pid='".$recip."'";
  $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  $allowEdit=0;
  while ($row = mysqli_fetch_assoc($rs)) {
    if ($row["allowEdit"] == "1") {
      $userid=$recip;
      $allowEdit=1;
      $_SESSION["euserid"]=$recip;
    }
  }
  $query = "select lastViewDate from comments, viewList where comments.userid=viewList.pid and pid='" . $recip . "' and viewer='" . $userid . "' and comment_userid!='" . $userid . "' and date > lastViewDate";

  $rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  if(mysqli_num_rows($rs) > 0){
    $alert = "onLoad=\"javascript:alert('New comments have been added since the last time you viewed this list')\"";
  } else {
    $alert = "";
  }
} else {
  $allowEdit=1;
}
?>

<BODY <?php if (isset($alert)) {echo $alert;} ?>>
<table class=pagetable>
<tr>
<td valign="top">

<?php
createNavBar("../home.php:Home|:View List - " . $_REQUEST["name"], false, "viewOther");
?>

<br>


<font size="2">

<?php

if($recip == $_SESSION["userid"] and $confirm != "Yes"){

  print "<center><p>&nbsp;<form method=post>";
  print "<b>Are you sure you want to view this list?<br>You will be able to see any purchases that may have been made!  This could ruin the surprise</b>";
  print "<p><input type=submit name=confirm value=Yes class=\"buttonstyle\"> <input type=submit name=confirm value=No class=\"buttonstyle\">";
  print "</form>\n";
  print "<p>&nbsp;<p><b>Perhaps you wanted to <a href=\"../modifyList/modifyList.php\">Modify</a> your list instead?</b></center>";
  print "</td></tr></table></body></html>";
  return;
}
?>
<form method="post" action="validate_purchase.php" name="wishlist">
<input type="hidden" name="receiverUserid" value="<?php echo $recip ?>">
<input type="hidden" name="receiverName" value="<?php echo $name ?>">

<?php

  printList2($recip, $userid, $name, 0);

?>

<p>

<input type="submit" value="Save Changes" class="buttonstyle">
<input type="reset" value="Undo Changes" class="buttonstyle" onclick="history.go(0)">
<?php if ($allowEdit==1) { ?>
<input type="button" value="Edit" class="buttonstyle" onclick="location.href='../modifyList/modifyList.php?recip=<?php echo $recip ?>'">
<?php } ?>
<input type="button" value="Go Home" class="buttonstyle" onclick="location.href='../home.php'">
</form>

<hr>

<font size="4"><b>Comments</b></font> - <?php echo $name ?> will not see these
<p>

<?php

if($recip != $_SESSION["userid"]){

?>
<?php
 $query = "select commentId, comment_userid, firstname, lastname, suffix, comment, date from comments, people where comments.userid='" . $recip . "' and comments.comment_userid=people.userid order by date desc";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

while($row = mysqli_fetch_assoc($rs)){
?>
<font color="navy"><?php echo $row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"] ?> made the following comments on <?php echo parseDate($row["date"],1) ?></font>
<?php
  if($_SESSION["userid"] == $row["comment_userid"]){
?>
    <b><a class="menuLink" href="deleteComment.php?commentId=<?php echo $row["commentId"] ?>&recip=<?php echo $recip ?>&name=<?php echo $name ?>"  >[Remove Comment]</a></b>
<?php
  }
?>
  
<br>
<?php echo $row["comment"] ?><p>

<?php
  }
?>
<form method="post" action="addComment.php">
<input type="hidden" name="recip" value="<?php echo $recip ?>">
<input type="hidden" name="name" value="<?php echo $name ?>">
<input type="submit" value="Add New Comment" class="buttonstyle">
</form>

<?php
}
else{
  print "<font color=red><b>You are not allowed to view comments added to your list</b></font>";
}
?>

</font>
</td>
</tr>
<tr><td>
<table class=navBar>
<tr>
<td class=navBar align="left">
<font size=2>
<a class="navMenuLink" href="viewLog.php?recip=<?php echo $recip ?>&name=<?php echo $name ?>">Click here to see what you bought or to unselect items</a>
</font></td>
<td class=navBar align="center">

<?php

$query = "select lastModDate from people where userid='" . $recip . "'";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
   

  if($row = mysqli_fetch_assoc($rs)){
?>
<font size=2>
<font color=indianred><?php echo $name ?></font> last modified this list on <font color=navy><?php echo parseDate($row["lastModDate"],1) ?></font></font>
<?php
}
?>
  
</td><td class=navBar align="right">
<font size=2>
        <a class="navMenuLink" target="_blank" href="print.php?recip=<?php echo $recip ?>&name=<?php echo $name ?>">Printer Friendly Version</a>
</font>
</td></tr></table>
</td></tr>
</table>
</body>
</html>

<?php

$query = "update viewList set lastViewDate=NOW() where pid='" . $recip . "' and viewer='" . $userid . "'";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

?>
