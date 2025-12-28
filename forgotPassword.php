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


// throw this flag to tell funcLib not to redirect us to login.php which it
// would try to do if the user is not logged in
$ignoreSession = true;

include "funcLib.php";

?>

<HTML>

<link rel=stylesheet href=style.css type=text/css>

<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<title>Forgot password</title>
</head>

<?php 
$userid = isset($_REQUEST["userid"]) ? $_REQUEST["userid"] : "";
$password = isset($_REQUEST["password"]) ? $_REQUEST["password"] : "";

if($userid != ""){

   if(isset($_REQUEST["changePassword"]) && $_REQUEST["changePassword"] == "true"){
     
     // come up with random password
     $salt = "abchefghjkmnpqrstuvwxyz0123456789";
     srand((double)microtime()*1000000); 
     $i = 0;
     $pass = "";
     while ($i <= 7) {
       $num = rand() % 33;
       $tmp = substr($salt, $num, 1);
       $pass = $pass . $tmp;
       $i++;
     }
     
     // Secure update
     $stmt = mysqli_prepare($link, "UPDATE people SET password=? WHERE userid=?");
     $md5pass = md5($pass);
     mysqli_stmt_bind_param($stmt, "ss", $md5pass, $userid);
     mysqli_stmt_execute($stmt);

     $to = $_REQUEST["recipient"];
     $from = ""; // System message
     $subject = "Password Reset for " . $userid;
     $message = "The password for user <b>" . $userid . "</b> has been reset.<br>" .
                "The new password is <b>" . $pass . "</b><p>" .
                "Please inform them to change their password by going to <b>Update Your Account</b> and clicking on the <b>Change Password</b> button.";

     sendEmail($to,$from,$subject,$message,0);
?>
     <BODY>   
     <table cellspacing="0" cellpadding="5" width="100%" height="100%" bgcolor="#FFFFFF" nosave border="0" style="border: 1px solid rgb(128,255,128)">
     <tr>
     <td valign="top" align=center>
     <p>&nbsp;
     <p>&nbsp;  
     The new password has been messaged to the selected user.
     <br>
     <a href="login.php">Return to login</a>

<?php     
   }
   else{

     $stmt = mysqli_prepare($link, "SELECT * FROM people WHERE userid=?");
     mysqli_stmt_bind_param($stmt, "s", $userid);
     mysqli_stmt_execute($stmt);
     $result = mysqli_stmt_get_result($stmt);

     if($row = mysqli_fetch_assoc($result)){
         // Fetch users that the current user ($userid) has permission to view
         $users_query = "SELECT p.userid, p.firstname, p.lastname FROM people p JOIN viewList vl ON p.userid = vl.pid WHERE vl.viewer = ? AND p.userid != ? ORDER BY p.lastname, p.firstname";
         $stmt_users = mysqli_prepare($link, $users_query);
         mysqli_stmt_bind_param($stmt_users, "ss", $userid, $userid);
         mysqli_stmt_execute($stmt_users);
         $users_result = mysqli_stmt_get_result($stmt_users);
?>
     <BODY>
     <table class=pagetable>
     <tr>
     <td valign="top" align=center>
     <p>&nbsp;
     <p>&nbsp;
     <form name=theForm method=post action=forgotPassword.php>

     <input type=hidden name=changePassword value="true">
     <input type=hidden name=userid value=<?php echo htmlspecialchars($_REQUEST["userid"]) ?>>
     
     <h3>Reset Password for <?php echo htmlspecialchars($row["firstname"] . " " . $row["lastname"]) ?></h3>
     
     <label for="recipient">Select a user to receive the new password:</label><br>
     <select name="recipient" id="recipient" style="width: 300px;">
        <?php 
        while($user_row = mysqli_fetch_assoc($users_result)){
             echo "<option value='" . htmlspecialchars($user_row["userid"]) . "'>" . htmlspecialchars($user_row["firstname"] . " " . $user_row["lastname"]) . "</option>";
        }
        ?>
     </select>
     <br><br>
     
     <input type=submit value="Reset and Send Password" class="buttonstyle">
     <button type="button" class="buttonstyle" onclick="location.href='login.php'">Cancel</button>
     </form>
     
     <script>
        $(document).ready(function() {
            $('#recipient').select2();
        });
     </script>

<?php
     }
     else{
?>
     <body>
     <table cellspacing="0" cellpadding="5" width="100%" height="100%" bgcolor="#FFFFFF" nosave border="0" style="border: 1px solid rgb(128,255,128)">
     <tr>
     <td valign="top" align=center>
     <p>&nbsp;
     <p>&nbsp;
     No user found with that id - <a href="forgotPassword.php">Try Again?</a>        
     <p><a href="login.php">Return to login page</a>

<?php
    }
  }
}
else{

?>
<BODY onLoad="document.theForm.userid.focus();">
<table class=pagetable>
<tr>
<td valign="top" align=center colspan=2>
<table id="AutoNumber1" class=headerBox>
<tr><td colspan="2" align="center" class=headerCell>
Forgot Password?
</td></tr>
</table>
<table>
<tr>
<td align=center valign=top>
    <form name=theForm method=post action=forgotPassword.php>
    <b>Enter your WishList userid</b><br>
    <input type=text name=userid><br>
    <input type=submit value=Submit class=buttonstyle>
    </form>
</td>
</tr>
</table>

<?php
}
?>
</td>
</tr>
</table>
</body>
</html>