<?php
/// This program is free software; you can redistribute it and/or modify
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

@(include 'config.php');

if(!isset($base_dir)){
  // this should only be true when phpWishList is first
  // installed so we should be in the root directory.
  // redirect to admin
  header("Location: admin.php?setup=true");
  exit;
}

session_name("WishListSite");
session_start();

if (isset($_REQUEST["action"])) {
  if ($_REQUEST["action"] == "logout") {
    session_destroy();
  }
}

if(isset($_SESSION['userid'])){
   // already logged in
   header("Location: home.php");
   return;
}

$displayError = True;
$errorMessage = "Please Sign In";

if (!empty($_REQUEST["userid"]) && !empty($_REQUEST["password"])) {

  $link = mysqli_connect($db_loc, $db_userid, $db_pass);
  if(!$link){
    sendEmail($admin_email, "", "Database is dead", "umm, the database is dead", 0);
    die("<p><font size=+2>Danger Will Robinson!!!!  It looks like the database is dead.<p>Try back at the top of the hour.</big>");
  }
  mysqli_select_db($link,$db_name);

  $stmt = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
  mysqli_stmt_bind_param($stmt, "s", $_REQUEST["userid"]);
  mysqli_stmt_execute($stmt);
  $result = mysqli_stmt_get_result($stmt);

  if ($row = mysqli_fetch_assoc($result)) {
    $password_from_db = $row["password"];
    $submitted_password = $_REQUEST["password"];

    // Check if the provided password matches the stored hash
    if (password_verify($submitted_password, $password_from_db)) {
        // Modern hash, password is correct
        $passValidate = 1;
    }
    // Legacy check for MD5 (if password_verify fails)
    else if (md5($submitted_password) === $password_from_db) {
        // MD5 hash matches. Rehash and update the password in the database.
        $passValidate = 1;
        $new_hash = password_hash($submitted_password, PASSWORD_DEFAULT);
        
        $update_stmt = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
        mysqli_stmt_bind_param($update_stmt, "ss", $new_hash, $row["userid"]);
        mysqli_stmt_execute($update_stmt);
    }

    if($passValidate){
      session_start();

      $_SESSION["userid"] = $row["userid"];
      $_SESSION["fullname"] = $row["firstname"] . " " . $row["lastname"] . ' ' . $row["suffix"];
      $_SESSION["admin"] = $row["admin"];

      // Check for unread messages
      $stmt = mysqli_prepare($link, "SELECT * FROM messages WHERE recipient_id = ? AND is_read = 0");
      mysqli_stmt_bind_param($stmt, "s", $row["userid"]);
      mysqli_stmt_execute($stmt);
      $messages_result = mysqli_stmt_get_result($stmt);
      
      $messages = array();
      while($message = mysqli_fetch_assoc($messages_result)){
          $messages[] = $message;
      }
      $_SESSION["messages"] = $messages;
      
      header("Location: home.php");
      mysqli_free_result($result);
      
      $query = "update people set lastLoginDate=NOW() where userid='" .
        $row["userid"] . "'";
      mysqli_query($link, $query) or die("Could not query: " . mysqli_error($link));
      
      exit;
    }
  }
  
  $errorMessage = "Invalid username or password.";
}
?>

<HTML>

<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>

<script language="javascript">
function valid_js() {
   // anything that claims NS 4 or higher functionality better work 
   if (navigator.userAgent.indexOf("Mozilla/") == 0) {
      return (parseInt(navigator.appVersion) >= 4);
   }
   return false;
}
</script>

<link rel=stylesheet href=style.css type=text/css>

<title>WishList Login</title>

<body bgcolor=#ffffff onload="document.login_form.userid.focus();">
<center>

<table class=pagetable>
<tr>

<td valign="top" align=center>
<table border=0 cellpadding=0 cellspacing=0 width="100%">
  <tr>
    <td colspan=2 align=center>
      <table class=headerBox>
        <tr>
	  <td align="center" class=headerCell>
            <font size=+1 color=white><b>Welcome to the WishList Site!</b></font>
	  </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td align="center">
      <font color="green"><b>
      <?php print $errorMessage ?>
      </b></font>                                          
    </td>
  </tr>
  <tr>
    <td align="center"> 
      <table border=0 cellspacing=2 cellpadding=0 >
        <tr>
          <td align="left" valign="top">
            <form method=post action=login.php name=login_form>
            <table border="0" cellspacing="6" cellpadding="6" bgcolor="ffffff" width="100%" class="roundedbox">
              <tr bgcolor="eeeeee">
                <td align="center">
                  <b>Existing WishList users</b><br>
                  <font size="-1"><nobr>&nbsp;Enter your ID and password to sign in&nbsp; </nobr></font>
                  <table border="0" cellpadding="4" cellspacing="0">
                    <tr>
                      <td align="right">
                        <table border="0" cellpadding="2" cellspacing="0">
                          <tr>
                            <td align="right" nowrap>
                              <font size="-1">
                              WishList ID:
                              </font>
                            </td>
                            <td>
                              <input name="userid" size="17" value="">
                            </td>
                          </tr>
                          <tr>
                            <td align="right" nowrap>
                              <font size="-1">Password:</font>
                            </td>
                            <td>
                              <input name="password" type="password" size="17" maxlength="32">
                            </td>
                          </tr>
                          <tr>
                            <td>&nbsp;</td>
                            <td>
                              <input name="submitButton" type="submit" value="Sign In" class="buttonstyle">
                            </td> 
                          </tr>
                        </table>
                      </td>
                    </tr> 
                  </table>
                </td>
              </tr>
              <tr bgcolor="eeeeee">
                <td valign="top" align="center">
                  <table border=0 width="100%">
                    <tr>
                      <td align="center">
                        <font size="-1">
                        <a href="forgotPassword.php">
                        Forgot your password?</a>
                        </font>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
            </form>
          </td>
<tr>
                <td align="center">
                  <font size=+1><b>New to the WishList Site?</b></font>
                  <p>
                  <a href="registerUser.php">
                  Sign up now</a> to enjoy the WishList site              
                </td>
</tr>
        </tr>
      </table>	
    </td>
  </tr>
</table>	

<p>
<table>
<tr><td><a href="wishlist_button.php" >Add the WishList button to your ToolBar!</a></td></tr>
</table>
</center>
</body>
</html>
