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

include "funcLib.php";
if (isset ($_REQUEST["lastname"])) {
    $lastname = $_REQUEST["lastname"];
} else {
    $lastname = "";
}
unset($_SESSION["euserid"]);

$query = "select * from people, viewList where pid = people.userid and viewer='" . $userid . "' order by lastname, firstname";

$result = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));

$num_rows = mysqli_num_rows($result);

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=style.css type=text/css>

<script src="js/jquery-1.7.1.min.js"></script>
<script language="JavaScript">
<!-- Begin JavaScript
function showfamily(lastname) {
    if ($(lastname).is(":visible")) {
        $(lastname).slideUp('fast');
    } else {
        $(lastname).slideDown('fast');
    }
}

$(document).ready(function() {
  if (screen.width <= 1000) {
    $("#message").hide();
  }
});

<?php 
   while($row = mysqli_fetch_assoc($result)){
           $allowView = $row["viewContactInfo"];
           $new_userid = $row["userid"];
           $name = $row["firstname"] . ' ' . $row["lastname"] . ' ' .$row["suffix"];
           $email = $row["email"];
           if ($allowView) {
             echo "  ".$new_userid."_contact =\"".$name."\\n".$email."\\n";
             if ($row['bday'] <> 0) {
                echo "Birthday: ".$row['bmonth']." ".$row['bday'];
             }
             echo "\";\n";
           }
    }
?>
  var theObject;
  navName = navigator.appName;
  navVer = parseInt(navigator.appVersion);

  function show_record(obj, txtArea, PersonName) {
    if ((theObject != obj) && (screen.width > 1000)) {
      cnt = eval(PersonName + "_contact");
      txtArea.value = cnt;
      theObject = obj;
    }
  }
// End JavaScript-->
</script>

<title>WishList Home Page</title>
<BODY>
<table class="pagetable">
<tr>

<td valign="top">

<?php
createNavBar("home.php:Home|", true);
?>

<center>

              <table class="headerBox" cellspacing="0" cellpadding="5" nosave border="0" >
                <tr>
                  <td class="headerCell" align="center" >
<?php

$season = getSeason();

if($season == 1){
  $image = "winter.png";
}
elseif($season == 2){
  $image = "spring.png";
}
elseif($season == 3){
  $image = "summer.png";
}
elseif($season == 4){
  $image = "autumn.png";
}
else{
  $image = "waving_santa2.gif";
}
?>
<script type="text/javascript">
  if (screen.width <= 1000) {
    document.write('<img border="0" height=50px src="images/<?php echo $image ?>">');
  } else {
    document.write('<img border="0" src="images/<?php echo $image ?>">');
  }
</script>
</td><td class=headerCell>
                    Welcome to the Wish List Site
                  </td>
                </tr>

              </table>

<table border=0>
<tr><td valign="center">
<?php

if ($num_rows > 0)
     mysqli_data_seek($result, 0);

$oldlast = "";
$div_last = "<div id='lastnames'><div style='display:none;'>";
$div_first = "";
while($row = mysqli_fetch_assoc($result)){
  if ($oldlast != $row['lastname']) {
    $oldlast=$row['lastname'];
    $div_last .= "<br></div><button class='buttonstyle' style='width:100%;' onclick=\"showfamily('#".$row['lastname']."')\" >".$row['lastname']."</button><br>\n";
    $div_last .= "<div id='".$row['lastname']."' style='display:none;'>";
  }

  $name =  $row['firstname'] . ' ' . $row['lastname'];
  $div_last.= "<button class='lightbutton' style='width:100%;' onclick='window.location=\"viewList/viewList.php?recip=" . $row['userid']. "&name=" . $name . "\"' onmouseover='show_record(this, theForm.contact, \"" . $row['userid']. "\")'>".$name."</button><br>\n";
}
$div_last .= "</div>\n</div>\n";


if($num_rows > 0){
  if ($lastname != "") {
    $message = "Click on a name to view their Wishlist";
  } else {
    $message = "Click on a family to view their members";
  }
}
else{
  $message = "You cannot view anybody's list\\nGoto Update Your Account > Manage List Access to add people";
}

$textarea = "<div id=message><form name=theForm><textarea COLS='30' ROWS='3' class='contact' WRAP='physical' noscroll NAME='contact' readonly>" . $message ."</textarea></form></div>";
echo "<table width=100%><tr><td>";
echo $div_last."\n";
echo "</td><td>\n";
echo $div_first."\n";
echo "</td></tr></table>\n";
echo "</td><td>\n";
echo $textarea."\n";
?>
</td></tr><tr><td>
</center>
<div id=options>
<hr>
<button class='buttonstyle' style='width:100%;' onclick="location.href='modifyList/modifyList.php'">Modify Your List</button><br/>
<button class='buttonstyle' style='width:100%;' onclick="location.href='viewPurchases.php'">View your Purchases</button><br/>
<button class='buttonstyle' style='width:100%;' onclick="location.href='updateAccount/updateAccount.php'">Update Your Account</button><br/>
<?php
if($_SESSION["admin"] == 1){
?>
<button class='buttonstyle' style='width:100%;' onclick="location.href='admin.php'">Admin Page</button><br/>
<?php
   }
?>
</div>
</td></tr></table>

</td></tr></table>
</body>
</html>
