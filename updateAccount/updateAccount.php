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

$message = "<h2>&nbsp;</h2>";

$target_userid = $_SESSION["userid"];

if (isset($_REQUEST["target_userid"])) {
    $requested_userid = $_REQUEST["target_userid"];
        
    if (($_SESSION["admin"] == 1) || ($requested_userid == $target_userid)) {
        $target_userid = $requested_userid;
        $_SESSION["euserid"] = $target_userid;
    } else {
        // Check if the logged-in user has edit access to the requested user's list
    $query_check_access = "SELECT allowEdit FROM viewList WHERE pid = '" . $requested_userid . "' AND viewer = '" . $_SESSION["userid"] . "'";
    $result_check_access = mysqli_query($link, $query_check_access) or die("Could not query: " . mysqli_error($link));

        if ($row_check_access = mysqli_fetch_assoc($result_check_access)) {
            if ($row_check_access["allowEdit"] == '1') {
                $target_userid = $requested_userid;
                $_SESSION["euserid"] = $target_userid; // Store in session for other pages
            } else {
                // No edit access, redirect to own account or show error
                header("Location: updateAccount.php");
                exit;
            }
        } else {
            // No entry in viewList, redirect to own account or show error
            header("Location: updateAccount.php");
            exit;
        }
    }
} else if (isset($_SESSION["euserid"])) {    // If no target_userid is specified, but euserid is set, use euserid
    $target_userid = $_SESSION["euserid"];
    // Re-check access in case session was manipulated or permissions changed
    $query_check_access = "SELECT allowEdit FROM viewList WHERE pid = '" . $target_userid . "' AND viewer = '" . $_SESSION["userid"] . "'";
    $result_check_access = mysqli_query($link, $query_check_access) or die("Could not query: " . mysqli_error($link));
    if (!($row_check_access = mysqli_fetch_assoc($result_check_access)) || $row_check_access["allowEdit"] != '1') {
        unset($_SESSION["euserid"]);
        $target_userid = $_SESSION["userid"];
    }
} else {
    unset($_SESSION["euserid"]);
}

// Use $target_userid for all subsequent operations
$userid = $target_userid;

if (!isset($_REQUEST["action"])) {
   $_REQUEST["action"] = "";
}
$action = $_REQUEST["action"];

if($action == "stopViewOther"){
  $removeuserid = $_REQUEST["userid"];

  $query = "delete from viewList where pid='" . $removeuserid . "' and viewer='" . $userid . "'";

  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  $message = "<h2>Successfully removed yourself</h2>";
}
elseif ($action == "stopViewMine"){

  $removeuserid = $_REQUEST["userid"];

  $query = "delete from viewList where pid='" . $userid . "' and viewer='" . $removeuserid . "'";

  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  $message = "<h2>The person you removed can no longer view your list</h2>";

}
elseif ($action == "startViewOther"){
  $adduserid = $_REQUEST["userid"]; // This is the target user
  $requesterId = $userid; // This is the current user

  // Check if a request already exists
  $query = "select id from accessRequests where requesterId='" . $requesterId . "' and targetId='" . $adduserid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
  if(mysqli_num_rows($result) > 0){
    $message = "<h2>You have already sent an access request to this person.</h2>";
  }
  else{
    // Insert a new access request
    $query = "insert into accessRequests (requesterId, targetId, status) values ('" . $requesterId . "', '" . $adduserid . "', 'pending')";
    
    $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
    $message = "<h2>Your request to view this person's list has been sent. You will be notified when they respond.</h2>";
  }
}
elseif ($action == "startViewMine"){
  $adduserid = $_REQUEST["userid"];

  $query = "select viewer from viewList where viewer='" . $adduserid . "' and pid='" . $userid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  
  if(mysqli_num_rows($result) > 0){
    $message = "<h2>That person can already view your list</h2>";
  }
  else{
    // need to add new field
    $query = "insert into viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) " .
      "values (0, 1, 0, '" . $userid . "', '" . $adduserid . "', now())";
    
    $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
    $message = "<h2>Person added</h2>";
  }
}
elseif ($action == "hideContactInfo"){
  
  $query = "select viewer from viewList where pid='" . $userid . "'";
  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  while($row = mysqli_fetch_assoc($result)){
    $viewerId = $row['viewer'];

    $viewContactInfo = in_array($viewerId, $_REQUEST['admin'] ?? []) ? 1 : 0;
    $readOnly = in_array($viewerId, $_REQUEST['readOnly'] ?? []) ? 1 : 0;
    $allowEdit = in_array($viewerId, $_REQUEST['allowEdit'] ?? []) ? 1 : 0;

    // If allowEdit is 1, then readOnly must be 0
    if ($allowEdit == 1) {
        $readOnly = 0;
    }

    $update_query = "UPDATE viewList SET viewContactInfo = '$viewContactInfo', readOnly = '$readOnly', allowEdit = '$allowEdit' WHERE viewer = '$viewerId' AND pid = '$userid'";
    mysqli_query($link, $update_query) or die("Could not query: " . mysqli_error($link));
  }

  $message = "<h2>Privileges changed</h2>";
  
}
?>

<HTML>
<link rel=stylesheet href=../style.css type=text/css>

<title>Update Account Information</title>
<BODY>
<table class="pagetable">
<tr>
<td valign="top" >

<?php
createNavBar("../home.php:Home|:Update Account", false, "listAccess");
?>

<center>
<p>&nbsp;

<?php echo $message?>

<?php

$query = "select * from people where userid='" . $userid . "'";

$result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

$row = mysqli_fetch_assoc($result);

?>

<table width="100%">
<tr><td align=center valign=top>



<form method="post" action="editInfo.php">

<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0" bgcolor=lightYellow>
<tr><td colspan="2" align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Information On File</b></font>
</td></tr>
<tr><td align="right">
<b>Name</b>
</td><td bgcolor="white">
<?php echo $row['firstname'] . ' ' . $row['lastname'] . ' ' . $row['suffix'] ?>
</td></tr>
<tr><td align="right">
<b>Email</b>
</td><td bgcolor=white>
<?php echo $row["email"] ?>
</td></tr>
<tr><td align="right">
<b>Birthday</b>
</td><td bgcolor=white>
<?php echo $row["bmonth"] . " " . $row["bday"] ?>
</td></tr>
<tr><td align="center" colspan="2" bgcolor="#c0c0c0">
<input type="submit" value="Edit Personal Info" class="buttonstyle">

</td></tr></table>
</form>


<button class='buttonstyle' onclick="location.href='changePassword.php'">Change Password</button><br>



</td>
</tr>
</table>

<table border=0 width=90%>
<tr>
<td align=center width=50%>



<form action="updateAccount.php" method=post>
<input type=hidden name=action value="hideContactInfo">
<input type=hidden name=target_userid value="<?php echo $userid; ?>">




<table bgcolor=lightyellow style="border-collapse: collapse;" border="0" bordercolor="#111111" cellPadding="0" cellSpacing="0">
<tr>
<td colspan=3>&nbsp;</td>
</tr>
<tr>
<tr>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
<td>


<!-- start of first table -->
<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellPadding="0" cellSpacing="0">
<tr>
<td colspan=6 align="center" bgcolor="#6702cc">
<font size=3 color=white><b>People who can view your list</b></font>
</td>
</tr>

<tr bgcolor=lightYellow>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td valign=bottom><b>Name</b></td>
<td align=center><b>Can View Your<br>Contact Info</b></td>
<td align=center><b>Read Only<br>Access</b></td>
<td align=center><b>Edit<br>Access</b></td>
</tr>
<?php
$query = "select viewer, firstname, lastname, suffix, userid, viewContactInfo, readOnly, allowEdit from viewList, people where viewList.viewer = people.userid and viewList.pid = '" . $userid . "'" . " order by lastname, firstname";

$result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

$list = "";

while($row = mysqli_fetch_assoc($result)){
  $list .= $row["userid"] . ",";
 
  print "<tr><td><a class=\"menuLink\" href=updateAccount.php?action=stopViewMine&userid=" . $row["userid"] . ($userid != $_SESSION["userid"] ? "&target_userid=" . $userid : "") . ">[Remove]</a></td><td>&nbsp;</td><td>" . $row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"] . "</td><td align=center><input name=admin[] value=" . $row["userid"] . " type='checkbox' ";
  print $row["viewContactInfo"] ? "checked" : "";
  print "></td>";
  print "<td align=center><input name=readOnly[] type=checkbox value=" . $row["userid"];
  print $row["readOnly"] ? " checked" : "";
  print "></td><td align=center><input name=allowEdit[] type=checkbox value=" . $row["userid"];
  print $row["allowEdit"] ? " checked" : "";
  print "></td></tr>";
}
print "<input type=hidden name=listUsers value=" . $list . ">";
?>
<tr><td colspan=6 align="center" bgcolor="#c0c0c0">
<input type=submit value="Make Changes"  style="font-weight:bold">
</td></tr></table>
</form>

<p>&nbsp;


<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr>
<td align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Add a person so they can view your list</b></font>
</td>
</tr>
<tr>
<td>
Enter the person's userid or e-mail:<br>
<form method=post action="updateAccount.php">
<input type=text size=20 name=email>
<input type=hidden name=action value=addUser>
<input type=submit value="Go">
</form>
<?php

if($_REQUEST["action"] == "addUser"){
  $email = $_REQUEST["email"];
  
  $pos = strpos($email, "@");

  // Note our use of ===.  Simply == would not work as expected
  // because the position of 'a' was the 0th (first) character.
  if ($pos === false) {
    $query = "select firstname, lastname, suffix, userid from people where userid like '%" . $email . "%'";
  }
  else{
    $query = "select firstname,lastname, suffix, userid from people where email='" . $email . "'";
  }

  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  while ($row = mysqli_fetch_assoc($result)){
    print "<a class='menuLink' href=\"updateAccount.php?action=startViewMine&userid=" . $row["userid"] . ($userid != $_SESSION["userid"] ? "&target_userid=" . $userid : "") . "\">[Add]</a> " . $row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"] . "<br>";
  }
    
}
?>
</td>
</tr>
</table>


<!-- end of first table -->
</td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
</tr>
<tr><td colspan=3>&nbsp;</td></tr>
</table>




</td><td valign=top align=center width=50%>


<!-- begin second table -->

<table bgcolor=lightyellow style="border-collapse: collapse;" border="0" bordercolor="#111111" cellPadding="0" cellSpacing="0">
<tr>
<td colspan=3>&nbsp;</td>
</tr>
<tr>
<tr>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
<td>


<table border=0>
<tr>
<td align=center>


<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr>
<td colspan=2 align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Lists you can view</b></font>
</td>
</tr>
<tr>
<td>

<?php 
$query = "select viewer, firstname, lastname, suffix, userid from viewList, people where viewList.viewer = '" . $userid . "' and viewList.pid = people.userid order by lastname, firstname";

//$query = "select * from people where userid='" . $userid . "'";

$result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

while($row = mysqli_fetch_assoc($result)){
  print "<tr><td><a class=\"menuLink\" href=updateAccount.php?action=stopViewOther&userid=" . $row["userid"] . ($userid != $_SESSION["userid"] ? "&target_userid=" . $userid : "") . ">[Remove]</a></td><td>" . $row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"] . "</td></tr>";
}
?>
</table>


<p>&nbsp;


<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr>
<td align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Find someone elses list to view</b></font>
</td>
</tr>
<tr>
<td>
Enter the person's userid or e-mail:<br>
<form method=post action="updateAccount.php">
<input type=text size=20 name=email>
<input type=hidden name=action value=findUser>
<input type=submit value="Go">
</form>
<?php

if($_REQUEST["action"] == "findUser"){
  $email = $_REQUEST["email"];

  $pos = strpos($email, "@");

  // Note our use of ===.  Simply == would not work as expected
  // because the position of 'a' was the 0th (first) character.
  if ($pos === false) {
    $query = "select firstname, lastname, suffix, userid from people where userid like '%" . $email . "%'";
  }
  else{
    $query = "select firstname,lastname, suffix, userid from people where email='" . $email . "'";
  }

  $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

  while ($row = mysqli_fetch_assoc($result)){
    print "<a href=\"updateAccount.php?action=startViewOther&userid=" . $row["userid"] . ($userid != $_SESSION["userid"] ? "&target_userid=" . $userid : "") . "\">[Add]</a> " . $row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"] . "<br>";
  }
    
}
?>
</td>
</tr>
</table>



</td>
</tr>
</table>

</td>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
</tr>
<tr><td colspan=3>&nbsp;</td></tr>
</table>

<!-- end second table -->

</td>
</tr>
</table>
</body>
</html>
