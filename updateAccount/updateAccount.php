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
        $stmt_check_access = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($stmt_check_access, "ss", $requested_userid, $_SESSION["userid"]);
        mysqli_stmt_execute($stmt_check_access);
        $result_check_access = mysqli_stmt_get_result($stmt_check_access);

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
    $stmt_check_access = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
    mysqli_stmt_bind_param($stmt_check_access, "ss", $target_userid, $_SESSION["userid"]);
    mysqli_stmt_execute($stmt_check_access);
    $result_check_access = mysqli_stmt_get_result($stmt_check_access);
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
  verifyCsrfOrDie();
  $removeuserid = $_REQUEST["userid"] ?? "";

  $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
  mysqli_stmt_bind_param($stmt, "ss", $removeuserid, $userid);
  mysqli_stmt_execute($stmt);
  $message = "<h2>Successfully stopped viewing " . htmlspecialchars($removeuserid) . " for " . htmlspecialchars($userid) . "</h2>";
}
elseif ($action == "stopViewMine"){
  verifyCsrfOrDie();
  $removeuserid = $_REQUEST["userid"] ?? "";

  $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ? AND viewer = ?");
  mysqli_stmt_bind_param($stmt, "ss", $userid, $removeuserid);
  mysqli_stmt_execute($stmt);
  $message = "<h2>Successfully stopped " . htmlspecialchars($removeuserid) . " from viewing list of " . htmlspecialchars($userid) . "</h2>";

}
elseif ($action == "startViewOther"){
  verifyCsrfOrDie();
  $adduserid = $_REQUEST["userid"] ?? ""; // This is the target user
  $requesterId = $userid; // This is the current user

  // Check if a request already exists
  $stmt = mysqli_prepare($link, "SELECT id FROM accessRequests WHERE requesterId = ? AND targetId = ?");
  mysqli_stmt_bind_param($stmt, "ss", $requesterId, $adduserid);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  if(mysqli_num_rows($result) > 0){
    $message = "<h2>You have already sent an access request to this person.</h2>";
  }
  else{
    // Insert a new access request
    $stmt_ins = mysqli_prepare($link, "INSERT INTO accessRequests (requesterId, targetId, status) VALUES (?, ?, 'pending')");
    mysqli_stmt_bind_param($stmt_ins, "ss", $requesterId, $adduserid);
    mysqli_stmt_execute($stmt_ins);
    $message = "<h2>Your request to view this person's list has been sent. You will be notified when they respond.</h2>";
  }
}
elseif ($action == "startViewMine"){
  verifyCsrfOrDie();
  $adduserid = $_REQUEST["userid"] ?? "";

  $stmt = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE viewer = ? AND pid = ?");
  mysqli_stmt_bind_param($stmt, "ss", $adduserid, $userid);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);
  
  if(mysqli_num_rows($result) > 0){
    $message = "<h2>That person can already view your list</h2>";
  }
  else{
    // need to add new field
    $stmt_ins = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (0, 1, 0, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_ins, "ss", $userid, $adduserid);
    mysqli_stmt_execute($stmt_ins);
    $message = "<h2>Person added</h2>";
  }
}
elseif ($action == "hideContactInfo"){
  verifyCsrfOrDie();
  $stmt_viewers = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ?");
  mysqli_stmt_bind_param($stmt_viewers, "s", $userid);
  mysqli_stmt_execute($stmt_viewers);
  $result = mysqli_stmt_get_result($stmt_viewers);

  $stmt_upd = mysqli_prepare($link, "UPDATE viewList SET viewContactInfo = ?, readOnly = ?, allowEdit = ? WHERE viewer = ? AND pid = ?");

  while($row = mysqli_fetch_assoc($result)){
    $viewerId = $row['viewer'];

    $viewContactInfo = in_array($viewerId, $_REQUEST['admin'] ?? []) ? 1 : 0;
    $readOnly = in_array($viewerId, $_REQUEST['readOnly'] ?? []) ? 1 : 0;
    $allowEdit = in_array($viewerId, $_REQUEST['allowEdit'] ?? []) ? 1 : 0;

    // If allowEdit is 1, then readOnly must be 0
    if ($allowEdit == 1) {
        $readOnly = 0;
    }

    mysqli_stmt_bind_param($stmt_upd, "iiiss", $viewContactInfo, $readOnly, $allowEdit, $viewerId, $userid);
    mysqli_stmt_execute($stmt_upd);
  }

  $message = "<h2>Privileges changed</h2>";
  
}
?>
<!DOCTYPE html>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
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

$stmt_info = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_info, "s", $userid);
mysqli_stmt_execute($stmt_info);
$result = mysqli_stmt_get_result($stmt_info);

$row = mysqli_fetch_assoc($result) ?? [];

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
<?php echo htmlspecialchars(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '') . ' ' . ($row['suffix'] ?? '')); ?>
</td></tr>
<tr><td align="right">
<b>Birthday</b>
</td><td bgcolor=white>
<?php echo htmlspecialchars(($row["bmonth"] ?? '') . " " . ($row["bday"] ?? '')); ?>
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

<table bgcolor=lightyellow style="border-collapse: collapse;" border="0" bordercolor="#111111" cellPadding="0" cellSpacing="0">
<tr>
<td colspan=3>&nbsp;</td>
</tr>
<tr>
<tr>
<td>&nbsp;&nbsp;&nbsp;&nbsp;</td>
<td>


<!-- start of first table -->
<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr>
<td align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Add a person so they can view your list</b></font>
</td>
</tr>
<tr>
<td>
Enter the person's name or username:<br>
<form method=post action="updateAccount.php">
<input type=text size=20 name=searchUser>
<input type=hidden name=action value=addUser>
<input type=submit value="Go">
</form>
<?php

if($_REQUEST["action"] == "addUser"){
  $searchUser = trim($_REQUEST["searchUser"] ?? ($_REQUEST["email"] ?? ""));
  $search = "%" . $searchUser . "%";
  $stmt_add = mysqli_prepare($link, "SELECT firstname, lastname, suffix, userid FROM people WHERE userid LIKE ? OR firstname LIKE ? OR lastname LIKE ?");
  mysqli_stmt_bind_param($stmt_add, "sss", $search, $search, $search);

  mysqli_stmt_execute($stmt_add);
  $result = mysqli_stmt_get_result($stmt_add);
  while ($row = mysqli_fetch_assoc($result)){
    print "<a class='menuLink' href=\"updateAccount.php?action=startViewMine&userid=" . urlencode($row["userid"]) . ($userid != $_SESSION["userid"] ? "&target_userid=" . urlencode($userid) : "") . "&csrf_token=" . urlencode(getCsrfToken()) . "\">[Add]</a> " . htmlspecialchars($row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"]) . "<br>";
  }
    
}
?>
</td>
</tr>
</table>
<form action="updateAccount.php" method=post>
<input type=hidden name=action value="hideContactInfo">
<input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
<input type=hidden name=target_userid value="<?php echo htmlspecialchars($userid); ?>">
<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellPadding="2" cellSpacing="0">
<tr>
<td colspan=6 align="center" bgcolor="#6702cc">
<font size=3 color=white><b>People who can view your list</b></font>
</td>
</tr>

<tr bgcolor=lightYellow>
<td>&nbsp;</td>
<td>&nbsp;</td>
<td valign=bottom><b>Name</b></td>
<td align=center><b>Can View<br>Your List</b></td>
<td align=center><b>Read Only<br>Access</b></td>
<td align=center><b>Edit<br>Access</b></td>
</tr>
<?php
$stmt_viewers_list = mysqli_prepare($link, "SELECT viewer, firstname, lastname, suffix, userid, viewContactInfo, readOnly, allowEdit FROM viewList, people WHERE viewList.viewer = people.userid AND viewList.pid = ? ORDER BY lastname, firstname");
mysqli_stmt_bind_param($stmt_viewers_list, "s", $userid);
mysqli_stmt_execute($stmt_viewers_list);
$result = mysqli_stmt_get_result($stmt_viewers_list);

$list = "";

while($row = mysqli_fetch_assoc($result)){
  $list .= $row["userid"] . ",";
 
  print "<tr><td><a class=\"menuLink\" href=\"updateAccount.php?action=stopViewMine&userid=" . urlencode($row["userid"]) . ($userid != $_SESSION["userid"] ? "&target_userid=" . urlencode($userid) : "") . "&csrf_token=" . urlencode(getCsrfToken()) . "\">[Remove]</a></td><td>&nbsp;</td><td>" . htmlspecialchars($row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"]) . "</td><td align=center><input name=admin[] value=\"" . htmlspecialchars($row["userid"]) . "\" type='checkbox' ";
  print $row["viewContactInfo"] ? "checked" : "";
  print "></td>";
  print "<td align=center><input name=readOnly[] type=checkbox value=\"" . htmlspecialchars($row["userid"]) . "\"";
  print $row["readOnly"] ? " checked" : "";
  print "></td><td align=center><input name=allowEdit[] type=checkbox value=\"" . htmlspecialchars($row["userid"]) . "\"";
  print $row["allowEdit"] ? " checked" : "";
  print "></td></tr>";
}
print "<input type=hidden name=listUsers value=\"" . htmlspecialchars($list) . "\">";
?>
<tr><td colspan=6 align="center" bgcolor="#c0c0c0">
<input type=submit value="Make Changes"  style="font-weight:bold">
</td></tr></table>
</form>

<p>&nbsp;




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
<td align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Find someone elses list to view</b></font>
</td>
</tr>
<tr>
<td>
Enter the person's name or username:<br>
<form method=post action="updateAccount.php">
<input type=text size=20 name=searchUser>
<input type=hidden name=action value=findUser>
<input type=submit value="Go">
</form>

<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr>
<td colspan=2 align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Lists you can view</b></font>
</td>
</tr>
<tr>
<td>

<?php 
$stmt_viewable = mysqli_prepare($link, "SELECT viewer, firstname, lastname, suffix, userid FROM viewList, people WHERE viewList.viewer = ? AND viewList.pid = people.userid ORDER BY lastname, firstname");
mysqli_stmt_bind_param($stmt_viewable, "s", $userid);
mysqli_stmt_execute($stmt_viewable);
$result = mysqli_stmt_get_result($stmt_viewable);

while($row = mysqli_fetch_assoc($result)){
  print "<tr><td><a class=\"menuLink\" href=\"updateAccount.php?action=stopViewOther&userid=" . urlencode($row["userid"]) . ($userid != $_SESSION["userid"] ? "&target_userid=" . urlencode($userid) : "") . "&csrf_token=" . urlencode(getCsrfToken()) . "\">[Remove]</a></td><td>" . htmlspecialchars($row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"]) . "</td></tr>";
}
?>
</table>


<p>&nbsp;


<?php

if($_REQUEST["action"] == "findUser"){
  $searchUser = trim($_REQUEST["searchUser"] ?? ($_REQUEST["email"] ?? ""));
  $search = "%" . $searchUser . "%";
  $stmt_find = mysqli_prepare($link, "SELECT firstname, lastname, suffix, userid FROM people WHERE userid LIKE ? OR firstname LIKE ? OR lastname LIKE ?");
  mysqli_stmt_bind_param($stmt_find, "sss", $search, $search, $search);

  mysqli_stmt_execute($stmt_find);
  $result = mysqli_stmt_get_result($stmt_find);

  while ($row = mysqli_fetch_assoc($result)){
    print "<a href=\"updateAccount.php?action=startViewOther&userid=" . urlencode($row["userid"]) . ($userid != $_SESSION["userid"] ? "&target_userid=" . urlencode($userid) : "") . "&csrf_token=" . urlencode(getCsrfToken()) . "\">[Add]</a> " . htmlspecialchars($row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"]) . "<br>";
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
