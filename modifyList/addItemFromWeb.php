<?php

// throw this flag to tell funcLib not to redirect us to login.php which it
// would try to do if the user is not logged in
$ignoreSession = true;

include "../funcLib.php";

if (isset($_SESSION["userid"])) {
  createPage();
}
else {
  $displayLogin = 1;
  $errorMessage = "Please Sign In";
  $userid = $_REQUEST["userid"] ?? "";
  $password = $_REQUEST["password"] ?? "";

  if($userid != "" and $password != ""){

    $stmt_user = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_user, "s", $userid);
    mysqli_stmt_execute($stmt_user);
    $rs = mysqli_stmt_get_result($stmt_user);    
    $userValidate = 0;
    $passValidate = 0;

    if($row = mysqli_fetch_assoc($rs)){    
      $userValidate = 1;

      if (password_verify($password, $row["password"])) {
        $passValidate = 1;
      } elseif (md5($password) === $row["password"] || $password === $row["password"]) {
        $passValidate = 1;
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $up = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
        mysqli_stmt_bind_param($up, "ss", $new_hash, $row["userid"]);
        mysqli_stmt_execute($up);
      }
    }

    if ($userValidate == 1 and $passValidate == 1){
      if (session_status() === PHP_SESSION_NONE) {
        session_start();
      }
      session_regenerate_id(true);
      unset($_SESSION['login_attempts']);
      unset($_SESSION['login_lockout_until']);
      $_SESSION['last_activity'] = time();

      $_SESSION["userid"] = $row["userid"];
      $_SESSION["fullname"] = $row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"];
      $_SESSION["admin"] = $row["admin"];
      $stmt_login = mysqli_prepare($link, "UPDATE people SET lastLoginDate = NOW() WHERE userid = ?");
      mysqli_stmt_bind_param($stmt_login, "s", $row["userid"]);
      mysqli_stmt_execute($stmt_login);

      createPage();
      $displayLogin = 0;
     }
    elseif($userValidate == 1 and $passValidate == 0){
      $errorMessage = "Password Error! Please Try Again";
    }
    else{
      $errorMessage = "User Name Error! Please Try Again";
    }
}

if($displayLogin == 1){

  $raw_url = $_REQUEST["wlurl"] ?? "";
  $parsed_host = parse_url($raw_url, PHP_URL_HOST);
  $suggested_linkname = $parsed_host ? preg_replace('/^www\./i', '', $parsed_host) : '';

?>
<!DOCTYPE html>
<HTML>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel=stylesheet href=../style.css type=text/css>
<title>Add New Item</title>
</head>
<body bgcolor=#ffffff onload="document.login_form.userid.focus();">
<table class="pagetable">
<tr>
<td valign="top" >
<center>
<b><?php echo htmlspecialchars($errorMessage); ?></b>

<form method="post" action="addItemFromWeb.php" name="login_form">

<table style="border-collapse: collapse;" id="AutoNumber1" border="1" bordercolor="#11111" cellpadding="2" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc"> <b><font size=3 color="#ffffff">
Add New Item From the Web<br>
You will have a chance to modify this after you sign in 
</td></tr>
<tr><td align=right>
<b>Name</b>
</td>
<td bgcolor=white>
<input size=50 type=text name=wldesc value="<?php echo htmlspecialchars($_REQUEST["wldesc"] ?? "", ENT_QUOTES); ?>">
</td></tr>

  <tr><td align="right">
    <table>
      <tr>
        <td align=right>
          <b>Link 1 Name</b>
        </td>
      </tr>
      <tr>
        <td align=right>
          <b>URL</b>
        </td>
      </tr>
    </table>
  </td><td bgcolor="#eeeeee">
  <input size=50 type=text name=wlurlname value="<?php echo htmlspecialchars($suggested_linkname, ENT_QUOTES); ?>">
  <br>
  <input type=text size=50 name=wlurl value="<?php echo htmlspecialchars($raw_url, ENT_QUOTES); ?>">
  </td></tr>
</table>
<p>
<table>
<tr><td colspan=2 align=center>
<b>Please sign in</b>
</td>
</tr>
<tr>
<td align=right>Userid</td>
<td><input type=text name=userid value="<?php echo htmlspecialchars($userid, ENT_QUOTES); ?>"></td>
</tr>
<tr>
<td align=right>Password</td>
<td><input name="password" type="password" maxlength="64"></td>
</tr>
</table>
<br>
<input type=submit value="Submit">
</form>
<?php
}
}


function createPage(){
  global $link;
  $stmt_cat = mysqli_prepare($link, "SELECT * FROM categories WHERE userid = ? AND catSortOrder != -1000 ORDER BY name");
  mysqli_stmt_bind_param($stmt_cat, "s", $_SESSION["userid"]);
  mysqli_stmt_execute($stmt_cat);
  $rs = mysqli_stmt_get_result($stmt_cat);

  if(!($row = mysqli_fetch_assoc($rs))){
    print "You have not created any categories yet.  Please do so and then try again";
  }
  else{
    
    $url= $_REQUEST["wlurl"] ?? "";
?>
<!DOCTYPE html>
<HTML>

<link rel=stylesheet href=../style.css type=text/css>

<title>Add New Item</title>
      <BODY>
      <table class="pagetable">
      <tr>
      <td valign="top">

<?php
createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Item From Web");
?>

      <center>
      <p>&nbsp;
      <form method="post" action="validate_addItem.php">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">
      <table style="border-collapse: collapse;" id="AutoNumber1" border="1" bordercolor="#11111" cellpadding="2" cellspacing="0" bgcolor="lightyellow">
      <tr>
      <td colspan="2" align="center" bgcolor="#6702cc"> 
      <b>
      <font size=3 color="#ffffff">Add New Item from the Web
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <b>Category</b>
      </td>
      <td bgcolor="#eeeeee">    

<?php
      mysqli_data_seek($rs, 0);

      print "<select name=cid>";

      while($row = mysqli_fetch_assoc($rs)){
        if(strcmp($row["name"], "Items Under Consideration") == 0){
          $considerCid = $row["cid"];
        }
        else{
          print "<option value=\"" . htmlspecialchars($row["cid"], ENT_QUOTES) . "\">" . htmlspecialchars($row["name"]) . "</option>\n";
        }
      }
      print "<option value=\"" . htmlspecialchars($considerCid ?? '', ENT_QUOTES) . "\"";
      if(!$select){
        print " selected";
      }
      print ">Items Under Consideration</option>";
?>
      </select>

      </td>
      </tr>

      <tr>
      <td align="right">    
      <b>Title
      </b>  
      </td>
      <td bgcolor="#eeeeee">    
      <input type="text" size="50" name="title" value="<?php echo htmlspecialchars($_REQUEST["wldesc"] ?? '', ENT_QUOTES); ?>">  
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <b>Description
      </b>  
      </td>
      <td bgcolor="#eeeeee">    
      <input type="text" size="50" name="desc">  
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <b>Price
      </b>  
      </td>
      <td bgcolor="#eeeeee">    
      <input type="text" size="7" name="price" value="<?php echo htmlspecialchars($OurPrice ?? '', ENT_QUOTES); ?>">  
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <b>Quantity
      </b>  
      </td>
      <td bgcolor="#eeeeee">    
      <input type="text" size="7" name="quantity" value="1">  
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <table>      
      <tr>        
      <td align=right>          
      <b>Link 1 Name
      </b>        
      </td>      
      </tr>      
      <tr>        
      <td align=right>          
      <b>URL
      </b>        
      </td>      
      </tr>    
      </table>  
      </td>
      <td bgcolor="#eeeeee">    

<?php
        $url = $_REQUEST["wlurl"] ?? '';
        $parsed_host = parse_url($url, PHP_URL_HOST);
        $urlName = $parsed_host ? preg_replace('/^www\./i', '', $parsed_host) : '';
?>

      <input type="text" size="50" name="link1" value="<?php echo htmlspecialchars($urlName, ENT_QUOTES); ?>">
      <br>    
      <input type="text" size="50" name="link1url" value="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>">  
      </td>
      </tr>  
      <tr>
      <td align="right">    
      <b>Additional
      <br>Information
      </b>  
      </td>
      <td bgcolor="#eeeeee">    
      <textarea ROWS='3' COLS='50' name="subdesc"></textarea>  
      </td>
      </tr>  
      <tr>
      <td align="center" colspan="2">    
      <b>Allow people to check this item off
      </b>    
      <input type="checkbox" name="allowCheck" checked>  
      </td>
      </tr>
      <tr>
      <td colspan="2" bgcolor="#c0c0c0" align=center>
      <input type="submit" value="Add" class="buttonstyle">
      </td>
      </tr>
      </table>
      </form>
      </td>
      </tr>
      </table>
      </body>
      </html>
<?php
    }
}

?>
