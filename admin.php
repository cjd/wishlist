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


if(isset($_REQUEST['setup'])){
  // if true then we need to setup the phpWishList environment
  // for the first time
  createConfig();
  exit;
}

include "funcLib.php";

if($_SESSION['admin'] != 1){
  print "Shame on you";
  exit;
}

if(!isset($_REQUEST["action"])) {
  $_REQUEST["action"]="";
}
                      
$message = "<h2>Admin Page</h2>";

if($_REQUEST["action"] == "commitDelete"){
  $delUserid = $_REQUEST["userid"];
  if($_REQUEST["confirm"] == "Yes"){
    $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE pid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "DELETE FROM viewList WHERE viewer = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE comment_userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "SELECT iid FROM items, categories WHERE items.cid = categories.cid AND userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)){
      $stmt2 = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
      mysqli_stmt_bind_param($stmt2, "i", $row["iid"]);
      mysqli_stmt_execute($stmt2);

      $stmt2 = mysqli_prepare($link, "DELETE FROM items WHERE iid = ?");
      mysqli_stmt_bind_param($stmt2, "i", $row["iid"]);
      mysqli_stmt_execute($stmt2);
    }
    
    $stmt = mysqli_prepare($link, "DELETE FROM categories WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "DELETE FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $delUserid);
    mysqli_stmt_execute($stmt);
    
    $message = "<h2>Person deleted</h2>";
  }
}
elseif($_REQUEST["action"] == "commitEdit"){
  $oldUserid = $_REQUEST["oldUserid"];
  $newUserid = $_REQUEST["newUserid"];

  if($oldUserid != "" and $newUserid != "" and $oldUserid != $newUserid){

    $stmt = mysqli_prepare($link, "UPDATE viewList SET pid = ? WHERE pid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "UPDATE viewList SET viewer = ? WHERE viewer = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "UPDATE comments SET userid = ? WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "UPDATE comments SET comment_userid = ? WHERE comment_userid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "UPDATE purchaseHistory SET userid = ? WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "UPDATE categories SET userid = ? WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);
    
    $stmt = mysqli_prepare($link, "UPDATE people SET userid = ? WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "ss", $newUserid, $oldUserid);
    mysqli_stmt_execute($stmt);
  }
}
elseif($_REQUEST["action"] == "commitAdd"){
  $fname = convertString($_REQUEST["fname"]);
  $lname = convertString($_REQUEST["lname"]);
  $suffix = convertString($_REQUEST["suffix"] ?? '');
  $desiredUserid = convertString($_REQUEST["userid"]);
  $email = convertString($_REQUEST["email"]);
  
  if($desiredUserid != ""){
    // use submitted password or come up with random password
    if (isset($_REQUEST['password']) && $_REQUEST['password'] != '') {
        $password = $_REQUEST['password'];
    } else {
        $salt = "abchefghjkmnpqrstuvwxyz0123456789";
        srand((double)microtime()*1000000); 
        $password = '';
        $i = 0;
        while ($i <= 7) {
          $num = rand() % 33;
          $tmp = substr($salt, $num, 1);
          $password = $password . $tmp;
          $i++;
        }
    }
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = mysqli_prepare($link, "INSERT INTO people (userid, firstname, lastname, suffix, email, password, lastLoginDate, lastModDate) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
    mysqli_stmt_bind_param($stmt, "sssss", $desiredUserid, $fname, $lname, $suffix, $email, $password_hash);
    
    // first add them to the people table
    mysqli_stmt_execute($stmt) or die("Could not execute statement: " . mysqli_stmt_error($stmt));

    // now set up the special categories
    $stmt = mysqli_prepare($link, "INSERT INTO categories (catSortOrder, userid, name, catSubDescription) VALUES (-10000, ?, 'Items Under Consideration', 'You are the only person who can view items in this category. Use it for items you are thinking of adding to your list')");
    mysqli_stmt_bind_param($stmt, "s", $desiredUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "INSERT INTO categories (catSortOrder, userid, name, description, linkname, linkurl, catSubDescription) VALUES (-1000, ?, '', '', '', '', '')");
    mysqli_stmt_bind_param($stmt, "s", $desiredUserid);
    mysqli_stmt_execute($stmt);

    $stmt = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, pid, viewer) VALUES (1, 0, ?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $desiredUserid, $desiredUserid);
    mysqli_stmt_execute($stmt);

    $subject = "WishList Account Created";
    $email_message = "<b>WishList Account Created</b><br><br><b>Userid:</b> " . $desiredUserid . "<br><b>Password:</b> " . $password . "<br><br>In order to change your password, login to the WishList site with the password provided above.  Once logged in, click on the <b><font color=navy>Update Your Account</font></b> button located at the bottom of the homepage.  On the page that opens, click on the <b><font color=navy>Change Password</font></b> button.";
    sendEmail($desiredUserid, $_SESSION['userid'], $subject, $email_message, 0);
    $message = "<h2>Account created - Password = " . $password . "</h2><p>A message has been sent to the user with their login details.</p>";
  }
  else{
    $message = "<h2> The userid cannot be empty!</h2>";
  }
}
elseif($_REQUEST["action"] == "changeAdmin"){

  if(!empty($_REQUEST["grantAdmin"])){
    $grantAdmin = $_REQUEST["grantAdmin"];
    $placeholders = implode(',', array_fill(0, count($grantAdmin), '?'));
    
    // Set admin flag for selected users
    $stmt = mysqli_prepare($link, "UPDATE people SET admin = '1' WHERE userid IN ($placeholders)");
    $types = str_repeat('s', count($grantAdmin));
    mysqli_stmt_bind_param($stmt, $types, ...$grantAdmin);
    mysqli_stmt_execute($stmt);

    // Unset admin flag for all other users
    $stmt = mysqli_prepare($link, "UPDATE people SET admin = '0' WHERE userid NOT IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, ...$grantAdmin);
    mysqli_stmt_execute($stmt);
  }
  else{
    // Unset admin flag for all users if none are selected
    $query = "update people set admin='0'";
    $result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
  }
  $message = "<h2>Admin privileges changes</h2>";
}
?>

<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=style.css type=text/css>

<title>WishList Admin Page</title>
<BODY>
<table class="pagetable">
<tr>

<td valign="top">

<?php
createNavBar("home.php:Home|:Admin Page");
?>

<center>

<?php

print $message;

if($_REQUEST["action"] == "add"){
?>
<form method=post>
<input type=hidden name="action" value="commitAdd" value=1>
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="2" cellspacing="0" bgcolor=lightYellow>
<tr><td colspan=2 align=center bgcolor="#6702cc"><font size=3 color=white><b>Add new user</b></font></td></tr>

<tr>
<td align=right><b>First Name</b></td>
<td><input type=text name=fname size=20></td>
</tr>
<tr>
<td align=right><b>Last Name</b></td>
<td><input type=text name=lname size=20></td>
</tr>
<tr>
<td align=right><b>Desired Login name</b></td>
<td><input type=text name=userid size=20></td>
</tr>

<tr>
<td align=right><b>Email</b></td>
<td><input type=text name=email size=40></td>
</tr>
<tr>
<td align=right><b>Password</b></td>
<td><input type=password name=password size=20></td>
</tr>
<tr><td align=center colspan=2 bgcolor="#c0c0c0"><input type=submit value="Add User" style="font-weight:bold"></td>
</tr></table>
</form>
<?php
}
elseif($_REQUEST["action"] == "edit"){
  $editUserid = $_REQUEST["userid"];
  print "<form method=post>";
  print "<input type=hidden name=action value=commitEdit>";
  print "<input type=hidden name=oldUserid value=" . $editUserid . ">";
  print "<table style=\"border-collapse: collapse;\" id=\"AutoNumber1\" border=\"1\" bordercolor=\"#111111\" cellpadding=\"2\" cellspacing=\"0\" bgcolor=lightYellow>";
  print "<tr><td colspan=2 align=center bgcolor=\"#6702cc\"><font size=3 color=white><b>Change Userid</b></font></td></tr>";
  print "<tr><td><b>Old Userid</b></td><td>" . $editUserid . "</td></tr>";
  print "<tr><td><b>New Userid</b></td><td><input type=text name=newUserid></td>";
  print "<tr><td align=center colspan=2 bgcolor=\"#c0c0c0\"><input type=submit value=\"Update \" style=\"font-weight:bold\"></td>";
  print "</tr></table>";
  print "</form>";
}
elseif($_REQUEST["action"] == "delete"){
  $deleteUserid = $_REQUEST["userid"];
  print "<form method=post>";
  print "<input type=hidden name=action value=commitDelete>";
  print "<input type=hidden name=deleteUserid value=" . $editUserid . ">";
  print "<table style=\"border-collapse: collapse;\" id=\"AutoNumber1\" border=\"1\" bordercolor=\"#111111\" cellpadding=\"2\" cellspacing=\"0\" bgcolor=lightYellow>";
  print "<tr><td colspan=2 align=center bgcolor=\"#6702cc\"><font size=3 color=white><b>Confirm Delete</b></font></td></tr>";
  print "<tr><td>Are you sure you want to delete <b>" . $deleteUserid . "</b>?";
  print "<p>Everything relating to this user will be deleted";
  print "<tr><td align=center colspan=2 bgcolor=\"#c0c0c0\"><input type=submit name=confirm value=Yes style=\"font-weight:bold\"> <input type=submit name=confirm value=No style=\"font-weight:bold\"></td>";
  print "</tr></table>";
  print "</form>";
}

?>

<form method="post" action="admin.php">
<input type=hidden name=action value=changeAdmin>

<table style="border-collapse: collapse;" id="AutoNumber1" border="1" bordercolor="#111111" cellpadding="2" cellspacing="0">
<tr><td colspan="5" align="center" bgcolor="#6702cc">
<font size=3 color=white><b>Current Users</b></font>
</td></tr>
<tr bgcolor=lightYellow><td align="center">
<b>UserId</b>
</td><td align="center" valign="top">
<b>Name</b>
</td><td align="center">
<b>Last Login</b>
</td><td align="center">
<b>Admin</b>
</td><td align="center">
<b>Email</b>
</td>
</tr>


<?php

$query = "select * from people order by lastname, firstname";

$result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

while($row = mysqli_fetch_assoc($result)){
  print "<tr>";
  print "<td><a href=\"admin.php?action=edit&userid=" . $row["userid"] . "\">[edit]</a> <a href=\"admin.php?action=delete&userid=" . $row["userid"] . "\">[del]</a> <a href=\"./updateAccount/changePassword.php?userid=" . $row["userid"] . "\">[pwd]</a> <a href=\"./updateAccount/updateAccount.php?target_userid=" . $row["userid"] . "\">[account]</a> " . $row["userid"] . "</td>";
  print "<td>" . $row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"] . "</td>";
  print "<td><font face=Courier>" . parseDate($row["lastLoginDate"], 1) . "</font></td>";
  print "<td align=center><input type=checkbox name=grantAdmin[] value=" . $row["userid"] . " " . ($row["admin"] == 1 ? "checked" : "") . "></td>";
  print "<td>" . $row["email"] . "</td>";
  print "</tr>";
}
?>


<tr><td align="center" colspan="5" bgcolor="#c0c0c0">
<input type="submit" value="Update Admin Status" class='buttonstyle'>

</td></tr></table>

</form>

<button class='buttonstyle' onclick="location.href='admin.php?action=add'">Add New User</button><br>
<button class='buttonstyle' onclick="location.href='admin/accessRequests.php'">Manage Access Requests</button><br>
<button class='buttonstyle' onclick="location.href='admin.php?setup=true'">Change Settings</button>

</center>
</td>
</tr>
</table>
</body>
</html>

<?php

function createConfig(){

  // --- 1. INITIALIZE VARS ---
  $new_base_dir = isset($_REQUEST['base_dir']) ? $_REQUEST['base_dir'] : '';
  $new_base_url = isset($_REQUEST['base_url']) ? $_REQUEST['base_url'] : '';
  $new_admin_email = isset($_REQUEST['admin_email']) ? $_REQUEST['admin_email'] : '';
  $new_db_loc = isset($_REQUEST['db_loc']) ? $_REQUEST['db_loc'] : '';
  $new_db_name = isset($_REQUEST['db_name']) ? $_REQUEST['db_name'] : '';
  $new_db_userid = isset($_REQUEST['db_userid']) ? $_REQUEST['db_userid'] : '';
  $new_db_pass = isset($_REQUEST['db_pass']) ? $_REQUEST['db_pass'] : '';

  global $base_dir, $base_url, $admin_email, $db_loc, $db_name,
          $db_userid, $db_pass ;

  $error = '';
  $allowUpdate = false;
  $show_root_form = false;

  // --- 2. HANDLE ROOT CREDENTIAL SUBMISSION ---
  if (isset($_REQUEST['updateValues']) && isset($_REQUEST['db_root_pass']) && $_REQUEST['db_root_pass'] != '') {
      $root_user = $_REQUEST['db_root_user'];
      $root_pass = $_REQUEST['db_root_pass'];

      try {
          $root_link = mysqli_connect($new_db_loc, $root_user, $root_pass);

          $query_create_db = "CREATE DATABASE IF NOT EXISTS `$new_db_name`";
          if (!mysqli_query($root_link, $query_create_db)) {
              throw new Exception("Could not create database: " . mysqli_error($root_link));
          }

          // Drop user in case they exist with a different password. Requires MySQL 5.7.8+
          @mysqli_query($root_link, "DROP USER IF EXISTS '$new_db_userid'@'%'");

          // Create the user with the new password
          $create_user_query = "CREATE USER '$new_db_userid'@'%' IDENTIFIED BY '$new_db_pass'";
          if (!mysqli_query($root_link, $create_user_query)) {
              throw new Exception("Could not create user: " . mysqli_error($root_link));
          }

          // Grant privileges
          $query_grant = "GRANT ALL PRIVILEGES ON `$new_db_name`.* TO '$new_db_userid'@'%'";
          if (!mysqli_query($root_link, $query_grant)) {
              throw new Exception("Could not grant privileges: " . mysqli_error($root_link));
          }
          
          @mysqli_query($root_link, "FLUSH PRIVILEGES");
          // Success! Let the normal flow continue.

          mysqli_close($root_link);

      } catch (mysqli_sql_exception | Exception $e) {
          $error .= "ERROR during root operation: " . $e->getMessage() . "<br>";
          $show_root_form = true; // Show the form again if it fails
      }
  }


  // --- 3. SESSION AND PERMISSION SETUP ---
  session_name("WishListSite");
  session_start();

  if((file_exists($base_dir . "/config.php") || file_exists("config.php"))){
    if(!isset($_SESSION["admin"]) || $_SESSION["admin"] != 1){
      print "Stop that!!!";
      exit;
    }
    else{
      $allowUpdate = true;
    }
  }
  else{
    $doingSetup = true;
    $ignoreSession = true;
    $allowUpdate = true;
  }

  include 'funcLib.php';

  if(!isset($_REQUEST['updateValues'])){
    $allowUpdate = false;
    $new_base_dir = $base_dir; $new_base_url = $base_url; $new_admin_email = $admin_email;
    $new_db_loc = $db_loc; $new_db_name = $db_name; $new_db_userid = $db_userid; 
    $new_db_pass = $db_pass;
  }

  // --- 4. DB CONNECTION & SELECTION TEST ---
  if($new_db_loc != '' && $new_db_name != '' && $new_db_userid != '' && $new_db_pass != '' && !$show_root_form){
    try {
      $link = mysqli_connect($new_db_loc, $new_db_userid, $new_db_pass);

      if (!mysqli_select_db($link, $new_db_name)) {
          $error .= "INFO: Could not select database '$new_db_name'. It might not exist or user '$new_db_userid' lacks permissions.<br>Please provide root credentials to create the database and grant access.<br>";
          $allowUpdate = false;
          $show_root_form = true;
      }
      mysqli_close($link);
    } catch (mysqli_sql_exception $e) {
        $error .= "ERROR connecting as '$new_db_userid': " . $e->getMessage() . "<br>";
        $error .= "If the user or database needs to be created, use the root credential form below.<br>";
        $allowUpdate = false;
        $show_root_form = true;
    }
  }

  // --- 5. WRITE CONFIG FILE ---
  if($allowUpdate == true && $error == '' &&
     $new_base_dir != '' && $new_base_url != '' &&
     $new_admin_email != '' && $new_db_loc != '' && $new_db_name != '' &&
     $new_db_userid != '' && $new_db_pass != ''){
    $base_dir=".";

    $fh = fopen($base_dir . "/config.php", 'w');
    if($fh){
      fwrite($fh, "<?php\r\n");
      fwrite($fh, "\$base_dir = '$new_base_dir';\r\n");
      fwrite($fh, "\$base_url = '$new_base_url';\r\n\r\n");
      fwrite($fh, "\$admin_email = '$new_admin_email';\r\n\r\n");
      fwrite($fh, "\$db_loc = '$new_db_loc';\r\n");
      fwrite($fh, "\$db_name = '$new_db_name';\r\n");
      fwrite($fh, "\$db_userid = '$new_db_userid';\r\n");
      fwrite($fh, "\$db_pass = '$new_db_pass';\r\n\r\n");
      fwrite($fh, "?>\r\n");
      fclose($fh);

      chmod($base_dir . "/config.php", 0770);
      header("Location: admin.php");
      exit; // Exit after redirect
    }
    else{
      $error .= "Couldn't write config file!!!";
    }
  }

  // --- 6. DISPLAY HTML ---
  // This part is reached if we are not writing the config file.
  // (e.g. initial view, or an error occurred)
?>
<HTML>
<link rel=stylesheet href=style.css type=text/css>
<title>WishList Admin Page</title>
<BODY>
<table cellspacing="0" cellpadding="5" width="100%" height="100%" bgcolor="#FFFFFF" nosave border="0" style="border: 1px solid rgb(128,255,128)">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|admin.php:Admin Page|:Change WishList Settings"); ?>
<center>
<?php
if($error){
  print "<h2>$error</h2>";
}
?>
<p>
<form method=post>
<input type=hidden name=setup value=true>
<input type=hidden name=updateValues value=true>
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="2" cellspacing="0" bgcolor=lightYellow>
<tr><td colspan=2 align=center bgcolor="#6702cc"><font size=3 color=white><b>WishList Setup</b></font></td></tr>
<td align=right>* Base Dir</td>
<td bgcolor=white><input type=text name=base_dir value="<?php echo htmlspecialchars($new_base_dir != '' ? $new_base_dir : getcwd(), ENT_QUOTES); ?>" size=50></td>
</tr>
<tr>
<td align=right>* Base URL</td>
<td bgcolor=white><input type=text name=base_url value="<?php echo htmlspecialchars($new_base_url != '' ? $new_base_url : "http://" . $_SERVER['HTTP_HOST'], ENT_QUOTES); ?>" size=50></td>
</tr>
<tr><td colspan=2>&nbsp;</td></tr>
<tr>
<td align=right>* Primary Admin Email</td>
<td><input type=text name=admin_email value="<?php echo htmlspecialchars($new_admin_email, ENT_QUOTES); ?>" size=50></td>
</tr>
<tr><td colspan=2>&nbsp;</td></tr>
<tr>
<td align=right>* Database Server</td>
<td><input type=text name=db_loc value="<?php echo htmlspecialchars($new_db_loc, ENT_QUOTES); ?>" size=50></td>
</tr>
<tr>
<td align=right>* Database Name</td>
<td><input type=text name=db_name value="<?php echo htmlspecialchars($new_db_name, ENT_QUOTES); ?>" size=50></td>
</tr>
<tr>
<td align=right>* Database Userid</td>
<td><input type=text name=db_userid value="<?php echo htmlspecialchars($new_db_userid, ENT_QUOTES); ?>" size=50></td>
</tr>
<tr>
<td align=right>* Database Password</td>
<td><input type=password name=db_pass value="<?php echo htmlspecialchars($new_db_pass, ENT_QUOTES); ?>" size=50></td>
</tr>
<tr><td colspan=2>&nbsp;</td></tr>
</table>
<p>
<input type=submit value=Submit>
</form>
* = required field

<?php
// --- 7. DISPLAY ROOT FORM ---
if ($show_root_form) {
?>
    <hr>
    <p>
    <form method=post>
    <input type=hidden name=setup value=true>
    <input type=hidden name=updateValues value=true>
    
    <input type=hidden name=base_dir value="<?php echo htmlspecialchars($new_base_dir, ENT_QUOTES); ?>">
    <input type=hidden name=base_url value="<?php echo htmlspecialchars($new_base_url, ENT_QUOTES); ?>">
    <input type=hidden name=admin_email value="<?php echo htmlspecialchars($new_admin_email, ENT_QUOTES); ?>">
    <input type=hidden name=db_loc value="<?php echo htmlspecialchars($new_db_loc, ENT_QUOTES); ?>">
    <input type=hidden name=db_name value="<?php echo htmlspecialchars($new_db_name, ENT_QUOTES); ?>">
    <input type=hidden name=db_userid value="<?php echo htmlspecialchars($new_db_userid, ENT_QUOTES); ?>">
    <input type=hidden name=db_pass value="<?php echo htmlspecialchars($new_db_pass, ENT_QUOTES); ?>">

    <table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="2" cellspacing="0" bgcolor=lightYellow>
    <tr><td colspan=2 align=center bgcolor="#cc0202"><font size=3 color=white><b>Database Root Credentials</b></font></td></tr>
    <tr>
    <td align=right>Root User</td>
    <td><input type=text name=db_root_user value="root" size=50></td>
    </tr>
    <tr>
    <td align=right>Root Password</td>
    <td><input type=password name=db_root_pass value="" size=50></td>
    </tr>
    </table>
    <p>
    <input type=submit value="Create Database & Grant Privileges">
    </form>
<?php
}
?>
</center>
</td>
</tr>
</table>
</body>
</html>
<?php
} // end of function
