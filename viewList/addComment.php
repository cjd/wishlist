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

if (isset ($_REQUEST["email"])) {
    $email = $_REQUEST["email"];
} else {
    $email = "";
}

if (isset ($_REQUEST["confirm"])) {
    $confirm = $_REQUEST["confirm"];
} else {
    $confirm = "";
}

if($confirm != ""){

$recip = convertString($_REQUEST["recip"]);
$comment_userid = convertString($_SESSION["userid"]);
$comment = convertString($_REQUEST["comment"]);
$name = convertString($_REQUEST["name"]);

$query = "insert into comments (commentId, userid, comment_userid, comment, date) values (null, '" . $recip . "', '" . $comment_userid . "', '" . $comment . "', NOW())";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  header("Location: " . getFullPath("viewList.php") . "?recip=" . $recip . "&name=" . $name);

if (isset($_REQUEST["recipients"])) {
    $recipients = $_REQUEST["recipients"];
} else {
    $recipients = array();
}

if(!empty($recipients)){

  $to = implode(",", $recipients);

  $from = $_SESSION["userid"];
  $subject = $_SESSION["fullname"] . " has added a comment to " . $_REQUEST["name"] . "'s list";
  $message = "<b>Comment:</b><br>" . cleanString($_REQUEST["comment"]);

  sendEmail($to, $from, $subject, $message, 0);
}
}
else{
?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<title><?php echo $name ?>'s WishList</title>
<BODY>
<table class=pagetable>
<tr>
<td valign="top">

<?php
createNavBar("../home.php:Home|viewList.php?recip=" . $_REQUEST["recip"] . "&name=" . $_REQUEST["name"] . ":View List - " . $_REQUEST["name"] . "|:Add Comment", false, "addComment");
?>

<table><tr><td>
<form name="theForm" method=post action=addComment.php>
<p>
<b>Comment</b><br> <textarea ROWS='5' COLS='90' name=comment 
onFocus="javascript:if(this.value=='Type comment here') this.value='';">Type comment here</textarea>
</td></tr>
<tr><td align=center>
<table style="border: 1px solid lightBlue" >
<tr><td colspan="2" align="center" bgcolor="#6702cc"> <b><font size=3 color="#ffffff">
Send Email?</font></b>
</td>
</tr>
<tr>
<td align=center>

<b>Your comment will be sent as a message to each person who has a check next to their name</b>
<br>You may want to include your own name to verify the messages are sent
</td></tr>
<tr><td align=center>
<table><tr><td align=center>
<table><tr><td>
<?php

$query = "select * from people, viewList where pid = '" . $_REQUEST["recip"] . "' and viewer!='" . $_REQUEST["recip"] . "' and viewer = people.userid order by lastname, firstname";

$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

while($row = mysqli_fetch_assoc($rs)){
?>

<input type=checkbox name=recipients[] value="<?php echo $row["userid"] ?>"><?php echo $row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"] ?><br>
<?php
}
?>
</td></tr></table>
</td></tr>
<tr><td>

<SCRIPT LANGUAGE="JavaScript">
<!--    

function checkAll(field)
{
for (i = 0; i < this.document.theForm.elements['recipients[]'].length; i++)
    this.document.theForm.elements['recipients[]'][i].checked = true;
}

function uncheckAll(field)
{
for (i = 0; i < this.document.theForm.elements['recipients[]'].length; i++)
    this.document.theForm.elements['recipients[]'][i].checked = false;
}

//  End -->
</script>

</td></tr>
<tr><td colspan="2" bgcolor="#c0c0c0" align=center>
<input type=button value="Check All" onClick="checkAll(document.theForm.email)"; class="buttonstyle">
<input type=button value="Uncheck All" onClick="uncheckAll(document.theForm.email)"; class="buttonstyle">

</td></tr></table>

</td></tr></table>

<br>
<input type=hidden name=confirm value=yes>
<input type=hidden name=recip value=<?php echo $_REQUEST["recip"] ?>>
<input type=hidden name=name value="<?php echo $_REQUEST["name"] ?>">
<input type=submit value="Add Comment" class="buttonstyle">

</form>
</td><tr><table>
<?php
}
?>
