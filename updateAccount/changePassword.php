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

if($_SESSION['admin'] == 1){
  if(isset($_REQUEST['userid'])) {
    $userid=$_REQUEST['userid'];
  }
}

?>

<HTML>
<link rel=stylesheet href=../style.css type=text/css>

<title>Change Password</title>
<BODY onload="document.theForm.password.focus();">
<table class="pagetable">
<tr>

<td valign="top" >
<script language="javascript">
function validate(form) {
    if(form.password.value != form.challenge.value){
      alert("Passwords don't match");
      return false;
    }
    return true;
}
</script>

<?php
createNavBar("../home.php:Home|updateAccount.php:Update Account|:Change Password for " . $userid, false, "password");
?>

<?php
  if(isset($_REQUEST['password'])){
    if ($_REQUEST['password'] != $_REQUEST['challenge']) {
        print "<p><center><h2>Passwords do not match</h2>";
        print "<b><a href=\"changePassword.php?userid=" . $userid . "\">Back</a></center></b>";
    } else {
        $password_hash = password_hash($_REQUEST['password'], PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
        mysqli_stmt_bind_param($stmt, "ss", $password_hash, $userid);
        mysqli_stmt_execute($stmt);
       
        print "<p><center><h2>Password Successfully Changed</h2>";
        print "<b><a href=\"updateAccount.php\">Back</a></center></b>";
    }
  }
  else{
?>
<center>
<p>
<form name="theForm" method="post" onSubmit="return validate(this);">
<table width="70%"><tr><td><b>Your password will be securely hashed before being stored in the database.</b></td</tr></table><p>

<table style="border-collapse: collapse;" id="AutoNumber1" border="0" bordercolor="#111111" cellpadding="2" cellspacing="0" bgcolor=lightYellow>
<tr><td colspan="2" align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Enter New Password</b></font>
</td></tr>
<tr><td align=right><b>Password</b></td>
<td><input type=password name=password></td></tr>
<tr><td align=right><b>Reenter Password</b></td>
<td><input type=password name=challenge></tr></tr>
<tr><td align="center" colspan="2" bgcolor="#c0c0c0">
<input type="submit" value="Submit" style="font-weight:bold">
</td></tr></table>
</form>
<?php
}
?>
</body>
</html>
