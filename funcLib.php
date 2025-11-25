<?php

@(include 'config.php');

if(!isset($base_dir)){
  // this should only be true when phpWishList is first
  // installed so we should be in the root directory.
  // redirect to admin only if we aren't currently there
  if(!isset($doingSetup)){
    header("Location: admin.php?setup=true");
    exit;
  }
}

if(!isset($_SESSION)){
  session_name("WishListSite");
  session_start();

  if (!isset($_SESSION["userid"])) {

    // if the page that is including this file has not set $ignoreSession then
    // redirect the user to login
    if(!isset($ignoreSession)){
      header("Location: " . $base_url."/login.php");
      exit;
    }
  }
  else {
    $userid = $_SESSION["userid"];
    if (isset($_SESSION["euserid"]))
        $userid = $_SESSION["euserid"];
  }
}

if(!isset($doingSetup)){
  $link = @(mysqli_connect($db_loc, $db_userid, $db_pass));
  if(!$link){
    sendEmail($admin_email, "", "Database is dead", "umm, the database is dead", 0);
    die("<p><font size=6>Danger Will Robinson!!!!  It looks like the database is dead.<p>Try back at the top of the hour.</big>");
  }
  else{
    mysqli_select_db($link,$db_name);
  }
}


/* getSeason : returns the current season
 *
 * 1 => winter (jan 1  -> mar 20)
 * 2 => spring (mar 21 -> jun 20)
 * 3 => summer (jun 21 -> sep 21) 
 * 4 => fall   (sep 22 -> nov 30)
 * 5 => christmas (dec 1 -> dec 31)
 */

function getSeason(){
  $today = getdate();
  
  $d1 = mktime(0,0,0, $today['mon'], $today['mday'], 2004);

  if($d1 >= 1072936800 and $d1 < 1079848800){ //jan 1 and mar 21
    $season = 3;
  }
  elseif ($d1 < 1087794000){ //june 21
    $season = 4;
  }
  elseif ($d1 < 1095829200){ // sept 22
    $season = 1;
  }
  elseif ($d1 < 1101880800){ // dec 1
    $season = 2;
  }
  else{ // xmas
    $season = 5;
  }

  return $season;
}


// $data must be of the form url_1:url_1Name|url_2:url_2Name...|:url_nName
// $helpLink indicates where to anchor to in help.php
function createNavBar($data, $displayGreeting = false, $helpLink = ''){

global $base_url;

print "<table class=\"navBar\">";
print "<tr><td class=navBar>"; //<b>NavBar : </b>";

$items = explode("|", $data);

$array_count = count($items);

for($i = 0; $i < $array_count; $i++){
  $piece = explode(":", $items[$i]);

  if($i == ($array_count - 1)){
    if (count($piece)>1) {
        print "<b>$piece[1]</b>";
    }
  }
  else{
    print "<a class=\"navMenuLink\" href=\"$piece[0]\">$piece[1]</a>";
    if ($items[$i+1] != "" ){
      print " > ";
    }
  }
}
print "</td>";

if($displayGreeting){
  print "<td class=navBar align=center>";
  print "<b>" . $_SESSION["fullname"] . "</b>";
  print "</td>";
}

print "<td class=navBar align=\"right\"><a class=navMenuLink target=\"_blank\" href=\"$base_url/help.php#$helpLink\">Help</a> :: <a class=\"navMenuLink\" href=\"$base_url/logout.php\">logout</a>";
print "</td></tr></table>";
}

function getFullPath($url) {
  $fp = "https://";
  $fp .= $_SERVER["HTTP_HOST"];
  $dir = dirname($_SERVER["PHP_SELF"]);
  if ($dir != "/")
    $fp .= $dir;
  $fp .= "/" . $url;
  return $fp;
}

/* $date is assumed to be a date obtained from mysql so it's of the form
 * YYYY-MM-DD hh:mm:ss
 *
 * All dates should be passed through this function before display
 */
function parseDate($date, $shortMonth=0, $clientLoc = 3){
  $date = date_create($date);
  if($shortMonth) {
    return date_format($date, "M d, Y g:ia");
  }
  else{
    return date_format($date, "F d, Y g:ia");
  }
  $serverLoc = -16; // timezone difference between server and clients

  preg_match("/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})/", $date, $regs);

  // now we need to localize the time.  This would be much better if we knew the time
  // zone of the user and then added/subtracted the approiate amount dynamically
  $dateCon = date_create(mktime ($regs[4] + ($clientLoc - $serverLoc), $regs[5],$regs[6],$regs[2],$regs[3],$regs[1]));

  if($shortMonth){
    return strftime("%b %d, %Y at %I:%M%p", $dateCon);
  }
  else{
    return strftime("%B %d, %Y at %I:%M%p", $dateCon);
  }
}

/* $to should be a comma-separated list of userids.
 * $from is the userid of the sender.
 * $message should have all new lines converted to <br> wherever desired
 * before calling this method */
function sendEmail($to, $from, $subject, $message, $debug){
    global $link;

    // 'to' can be a comma-separated list of userids
    $recipients = explode(',', $to);

    foreach ($recipients as $recipient_id) {
        $recipient_id = trim($recipient_id);
        if (empty($recipient_id)) {
            continue;
        }

        // If debug is enabled, send to admin instead
        if ($debug == 1) {
            global $admin_email;
            // This assumes the admin's email is their userid.
            // A better approach would be to have a dedicated admin userid.
            $stmt = mysqli_prepare($link, "SELECT userid FROM people WHERE email = ?");
            mysqli_stmt_bind_param($stmt, "s", $admin_email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                $recipient_id = $row['userid'];
            } else {
                continue; // Admin user not found
            }
        }

        $sender_id = NULL;
        if (!empty($from)) {
            // Assuming 'from' is a userid
            $sender_id = $from;
        }

        $stmt = mysqli_prepare($link, "INSERT INTO messages (recipient_id, sender_id, subject, body, is_read) VALUES (?, ?, ?, ?, 0)");
        mysqli_stmt_bind_param($stmt, "ssss", $recipient_id, $sender_id, $subject, $message);
        mysqli_stmt_execute($stmt);
    }
    return 1; // Assume success
}

/* useful function to get rid of the auto escapes either the server is doing
 * or the browser is doing.  Should use this function whenever we get text from
 * the user though we should call convertString if we are going to put the text
 * into the database
 */
function cleanString($str){
  return str_replace("\\'", "'", str_replace("\\\\", "\\", str_replace("\\\"", "\"", $str)));
}

/* convertString - a primitive method of form validation.  This needs more work
 * 
 * $str : the string to convert
 */
function convertString($str){
  // the server I am hosting this on automatically escapes single and
  // double quotes.  Since this may not be the case on all servers, I
  // explictily remove the escaping
  global $link;
  $str = cleanString($str);

  return trim(mysqli_escape_string($link,str_replace(chr(34), "&quot;", (str_replace("\r\n", "<br>" , $str)))));
}

function convertString_no_escape($str){
  return trim(str_replace(chr(34), "&quot;", (str_replace("\r\n", "<br>" , $str))));
}


/* printList2
 *
 * $recip : The user whos list to print
 * $buyerUserId : The user requesting to view recip's list
 * $name : The full name of $recip
 * $displayPurchases : if equal to 0 then don't indicate if item has 
 *   been purchased
 */
function printList2($recip, $buyerUserId, $name, $displayPurchases = 1){

  global $link;
  // first need to determine if $buyeruserid has read only access
  $stmt = mysqli_prepare($link, "SELECT readOnly FROM viewList WHERE pid = ? AND viewer = ?");
  mysqli_stmt_bind_param($stmt, "ss", $recip, $buyerUserId);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);

  $readOnly = 1;

  if($row = mysqli_fetch_assoc($rs)){
    $readOnly = $row["readOnly"];
  }

  if($readOnly)
    print "<h2>You have Read Only Access to this list</h2>";


  // ok, I really hate doing this query but it is the only way that I can
  // determine if recip has starred an item or not
  $stmt = mysqli_prepare($link, "SELECT addStar FROM categories, items WHERE addStar='1' AND categories.cid=items.cid AND userid=? LIMIT 1");
  mysqli_stmt_bind_param($stmt, "s", $recip);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);

  if($row = mysqli_fetch_assoc($rs)){
    print "<table><tr><td>A <img src=\"../images/star.gif\"> indicates an item " . $name . " wants most</td></tr></table>&nbsp;";
  }
  // end of the hate


  // Now find all categories associated with $recip
  $stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE userid = ? ORDER BY catSortOrder");
  mysqli_stmt_bind_param($stmt, "s", $recip);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);

  while($row = mysqli_fetch_assoc($rs)){
    printCategory($row, $name, $displayPurchases, $readOnly, 0, -1, -1);
  }

}

/* printModifyList
 *
 * $userid : the owner of the list to print
 */
function printModifyList($userid){
  global $link;

  $stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE catSortOrder > -1 AND userid = ? ORDER BY catSortOrder");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  
  $val = "";
  
  $i = 0;
  $last = mysqli_num_rows($rs);

  while($row = mysqli_fetch_assoc($rs)){
    
    if ($row["cid"] != "") {
        printCategory($row, $_SESSION["fullname"], 0, 0, 1, $i, $last);
    }
    $i++;
  }
}

/* printCategory
 *   if displayPurchases = 0 then the user is modifying his/her list 
 */
function printCategory($row, $name, $displayPurchases, $readOnly, $modifyList, $i, $last, $printConsider = 0){
  global $link;

  // handle any special categories first
  if($row["catSortOrder"] < 0){
    if($row["catSortOrder"] == -1000){
      if($row["catSubDescription"] != ""){
        print "\n<table width=100% border=0 cellpadding=0 cellspacing=0>";
        print "<tr><td valign=top>\n";
        print "<table cellpadding=0 cellspacing=0><tr><td>\n";
        print $row["catSubDescription"];// . "<br>&nbsp;\n";
        print "</td></tr><tr><td>&nbsp;</td></tr></table>\n";
        print "</td></tr></table>\n";
      }
      return;
    }
    if($printConsider == 0 and $row["catSortOrder"] == -10000)
      return;
  }

  print "\n<table width=100% border=0 cellpadding=0 cellspacing=0><tr>\n";

  print "<td valign=top class=\"categoryHeader\">";

  print "\n<table cellpadding=0 cellspacing=0 width=100%>";
  print "<tr>";

  if($modifyList and $row["catSortOrder"] != -10000){
  ?>
 <td NOWRAP valign=top>
 <form method="post" action="editCategory.php" style="display:inline-block;">
 <input type="hidden" name="cso" value="<?php echo $row["catSortOrder"] ?>">
 <input type="hidden" name="cid" value="<?php echo $row["cid"] ?>">
 <input type="hidden" name="cname" value="<?php echo $row["name"] ?>">
 <input type="submit" value="✏️" class="actionButton">
 </form>
 <form method="post" action="deleteCategory.php" style="display:inline-block;">
 <input type="hidden" name="cso" value="<?php echo $row["catSortOrder"] ?>">
 <input type="hidden" name="cid" value="<?php echo $row["cid"] ?>">
 <input type="hidden" name="cname" value="<?php echo $row["name"] ?>">
 <input type="hidden" name="referrer" value="modifyList.php">
 <input type="submit" value="🗑️" class="actionButtonRed" title="Click this to delete the category">
 </form>
<?php 
    if ($last == 1){
      print "<img width=26px height=26px src=\"../images/space.GIF\">";
    }
    else{
      if ($i != 0){
        print "<a href=\"moveCategory.php?dir=up&cid=" . $row["cid"] . "&cso=" . $row["catSortOrder"] . "\"><img width=13px height=13px border=0 src=\"../images/up_arrow_lightBlue.gif\" title=\"Click this arrow to move the category up\"></a>";
      }
      
      if($i != $last - 1){
        print "<a href=\"moveCategory.php?dir=down&cid=" . $row["cid"] . "&cso=" . $row["catSortOrder"] . "\"><img width=13px height=13px border=0 src=\"../images/down_arrow_lightBlue.gif\" title=\"Click this arrow to move the category down\"></a>";
        if($i == 0){
          print "<img width=13px height=13px src=\"../images/space.GIF\">";
        }
      }
      else{
        print "<img width=13px height=13px src=\"../images/space.GIF\">";
      }
    }
      
 print "</td><td width=100% align=center>";

  }
  else{
    print "\n<tr><td>\n";
  }
  
  if($row["name"] != ""){
    $text = "<b>" . $row["name"] . "</b> ";
  }

  if($row["description"] != ""){
    $text .= $row["description"] . ' ';
  }
  
  if($row["linkurl"] != ""){
    if ($row["linkname"] != "" ) {
      $text .= " - <a href='" . $row["linkurl"] . "'>" . $row["linkname"] . "</a>";
    } else {
      $text .= " - <a href='" . $row["linkurl"] . "'>" . $row["linkurl"] . "</a>";
    }
  }
  $len = strlen($text);

  if($text != ""){
    print $text;
  }
  

  if($row["catSubDescription"] != ""){
    if($text != ""){
      print "<br>";
    }
    print $row["catSubDescription"];// . "<br>&nbsp;";
  }
  
  print "</td>";
  if($modifyList){
?>
<td align="right">
<form method="post" action="addItem.php" style="display:inline-block;">
<input type="hidden" name="cid" value="<?php echo $row["cid"] ?>">
<input type="hidden" name="cname" value="<?php echo $row["name"] ?>">
<input type="submit" value="➕ Add Item" class="actionButton">
</form>
</td>
<?php
  }
  print "</tr></table>";
  
  print "</td></tr></table>\n";

  print "<div class='sortable-list' data-category-id='" . $row["cid"] . "'>";

  if ($row["cid"] == "") { $row["cid"]=-1;}
  // Now begin iterating through items in this category
  $stmt = mysqli_prepare($link, "SELECT *, DATE_FORMAT(createDate, '%M %d, %Y') as createDateFormatted FROM items WHERE cid = ? ORDER BY itemSortOrder");
  mysqli_stmt_bind_param($stmt, "i", $row["cid"]);
  mysqli_stmt_execute($stmt);
  $rs2 = mysqli_stmt_get_result($stmt);

  $j = 0; 
  $last2 = mysqli_num_rows($rs2);

  // before we print out the item details, need to display either a checkbox
  // or a drop down box to allow people to purchase the item
  while($row2 = mysqli_fetch_assoc($rs2)){
    print "\n<div class='sortable-item' data-item-id='" . $row2["iid"] . "'>\n";
    print "<div class='item-row'>";
    
    if($modifyList){
      print "<div class='item-cell item-buttons'>";
?>
     <form method="post" action="editItem.php" style="display:inline-block;">
     <input type="hidden" name="iso" value="<?php echo $row2["itemSortOrder"] ?>">
     <input type="hidden" name="iid" value="<?php echo $row2["iid"] ?>">
     <input type="hidden" name="cid" value="<?php echo $row["cid"] ?>">
     <input type="hidden" name="cname" value="<?php echo $row["name"] ?>">
     <input type="submit" value="✏️" class="actionButton">
     </form>
     <form method="post" action="deleteItem.php" style="display:inline-block;">
     <input type="hidden" name="iso" value="<?php echo $row2["itemSortOrder"] ?>">
     <input type="hidden" name="iid" value="<?php echo $row2["iid"] ?>">
     <input type="hidden" name="cid" value="<?php echo $row["cid"] ?>">
     <input type="hidden" name="cname" value="<?php echo $row["name"] ?>">
     <input type="hidden" name="referrer" value="modifyList.php">
     <input type="submit" value="🗑️" class="actionButtonRed" title="Click this to delete the item">
     </form>
<?php 
     print "</div>";
    }

    $bought = 0;
    $quantity = 0;
    $iid = $row2["iid"]; 
    
    if($row2["allowCheck"] == "true"){

      if($modifyList == 0){
        $stmt = mysqli_prepare($link, "SELECT sum(quantity) as numBought FROM purchaseHistory WHERE iid = ? GROUP BY iid");
        mysqli_stmt_bind_param($stmt, "i", $iid);
        mysqli_stmt_execute($stmt);
        $rs = mysqli_stmt_get_result($stmt);
        
        if($sumRow = mysqli_fetch_assoc($rs)){
          $bought = $sumRow["numBought"];
        }  
      }

      print "<div class='item-cell item-checkbox'>";
      $quantity = $row2["quantity"];
      
      if($quantity > 1){
        // person wants more than one of the items so use a drop down box 
        // instead of a checkbox.

        if($displayPurchases == 0) // not displaying purchases so reset bought
          $bought = 0;
        
        if(($quantity - $bought) > 0 and !$readOnly){
          // if the person's desired quantity has not been reached yet, display
          // a drop-down

          $titley = "";
          if($displayPurchases == 1)
            $titley = "title=\"Select the number you have purchased\"";

          print "<select $titley  name=\"sel" . $iid . "\">";
          for($i = 0; $i <= $quantity - $bought; $i++){
            print "<option value='" . $i . "'>" . $i . "</option>";
          }
        }
        else{ // Everything already bought, display disabled drop down
          print "<select disabled>";
        }
        print "</select>";
        print "</div><div class='item-cell item-content'>";
      }
      else{
        // person only wants one of the item so display a checkbox
        if($bought > 0){
          // Item has been bought, display a custom "X" checkbox
          print "<span class='purchased-checkbox-x' title='This item has been purchased'>&#10006;</span>"; // Unicode 'X' mark
        }
        else{
          // Item not bought, display a regular checkbox
          if($readOnly)
            $val = "disabled=true";
          else
            $val = "";
          
          if($displayPurchases == 0)
            $val = "";

          $titley = "";
          if($displayPurchases == 1)
            $titley = "title=\"Click here if you purchased this item\"";

          print "<input $titley type='checkbox' name='chk" . $iid . "' " . $val . ">";
        }
        print "</div><div class='item-cell item-content'>";
      }
    }
    else{
      // can't check this item off so don't display checkbox or drop down
      print "<div class='item-cell item-content' colspan=2>";
    }
    
    if($bought > 0 && $quantity==1){ print "<s>"; }
    printItem($row2, $name, $quantity, $bought, $row2['createDateFormatted']);
    if($bought > 0 && $quantity==1){ print "</s>"; }
    
    print "</div>"; // close the item content td
    
    if ($modifyList) {
        print "<div class='item-cell item-handle drag-handle'>&#9776;</div>";
    }

    print "</div>"; // end item-row
    print "</div>\n"; // end sortable-item
    $j++;
  }
  print "</div>";

  if($modifyList){
?>
<?php
  }
  else{
    print "<div>&nbsp;</div>"; // blank line
  }
}



function printItem($row2, $name, $quantity, $bought, $createDateFormatted){
    global $base_url;
  global $link;

  $text = "";

  $dash = 0;

  if($row2["addStar"] == '1'){
    $text = "<img src=\"".$base_url."/images/star.gif\">";
  }

  if($row2["image"] != "") {
    $text .= "<a href=\"#\" data-featherlight=\"".$base_url."/uploads/image-".$row2["iid"].".jpg\" title=\"".$row2["title"]."\">";
    $text .= "<img src=\"".$base_url."/uploads/image-".$row2["iid"].".jpg\" width=64></a>";
  }
    
  if($row2["title"] != ""){  
    $text .= "<i>" . $row2["title"] . "</i>";
    $dash = 1;
  }
  
  if($row2["description"] != ""){
    if($dash)
      $text .= " - ";
    $text .= $row2["description"];
    $dash = 1;
  }
  
  if($row2["price"] != "0.00"){
    if($dash)
      $text .= " - ";

    $text .= "$" . $row2["price"];
    $dash = 1;
  }
  
  if($row2["link1url"] != ""){
    if($dash)
      $text .= " - ";
    if ($row2["link1"] != "") {
      $text .= "<a target='_blank' href='" . $row2["link1url"] . "'>" . $row2["link1"] . "</a>";
    } else {
      $text .= "<a target='_blank' href='" . $row2["link1url"] . "'>Link</a>";
    }
  }

  print $text;  

  // Only show info icon if createDate is later than 2020
  if (strtotime($row2['createDate']) > strtotime('2020-12-31')) {
    print "<span class='info-icon' data-create-date='" . $row2['createDateFormatted'] . "'>&#9432;</span>";
  }

  //if($row2["userid"] != null and 
  //   $row2["userid"] == $buyerUserId){
  //  print " <b><a class=menuLinkRed href=\"viewLog.php?recip=" . $recip . "&name=" . $name . "\">[Undo]</a></b>";
  //}
  
  $subdesc = $row2["subdesc"];
  $link2 = $row2["link2"];
  $link2url = $row2["link2url"];
  $link3 = $row2["link3"];
  $link3url = $row2["link3url"];
  
  
  if($subdesc != "" or $link2 != "" or $link3 != "" or $quantity > 1){
    print "<div class='item-details'>";
    
    if($subdesc != ""){
      print "<div class='item-detail-row'>";
      print $subdesc;
      print "</div>";
    }
    
    if($link2 != ""){
      print "<div class='item-detail-row'>";
      print "<a target='_blank' href='" . $link2url . "'>" . $link2 ."</a></div>";
      
      if($link3 != ""){
        print "<div class='item-detail-row'>";
        print "<a target='_blank' href='" . $link3url . "'>" . $link3 . "</a></div>";
      }
    }
    
    if($quantity > 1){ 
      print "<div class='item-detail-row'>";
      print "<font color=red>" . $name . " wants " . $quantity . " of these, there are " . ($quantity - $bought) . " left to be purchased";
      print "</font></div>";
    }
    print "</div>";
  }
  else{
    print "<br>";
  }
}

/* deleteItem deletes $iid and any purchaseHistory entries for $iid.  
 * If $iid has been bought and the item is deleted 20 before $userid's bday
 * or christmas, an email will be sent to the buyer
 */
function deleteItem($iid, $userid, $fullname, $base_dir){
  global $link;
  $sendMail = 0;
  
  $stmt = mysqli_prepare($link, "SELECT title, items.description, people.userid FROM items, purchaseHistory, people WHERE items.iid = ? AND items.iid=purchaseHistory.iid AND people.userid=purchaseHistory.userid");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);
  $rs1 = mysqli_stmt_get_result($stmt);

  if(mysqli_num_rows($rs1) > 0){  
    // the item has been puchased so may have to send warning message
    $sendMail = 1;
  }
  
  // delete image
  if ($item_row['image'] != "") {
    $uploadDir = $base_dir.'/uploads/';
    $uploadFile = $uploadDir.$item_row['image'];
    if (is_file($uploadFile)) {
      unlink($uploadFile);
    }
  }

  // delete from purchaseHistory
  $stmt = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);

  // delete from items
  $stmt = mysqli_prepare($link, "DELETE FROM items WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);
  
  $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE itemSortOrder > ? AND cid = ?");
  mysqli_stmt_bind_param($stmt, "ii", $item_row["iso"], $item_row["cid"]);
  mysqli_stmt_execute($stmt);
  
  $stmt = mysqli_prepare($link, "UPDATE people SET lastModDate=NOW() WHERE userid = ?");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
  
  if($sendMail == 1){

    // get userid's bday and bmonth
    $stmt = mysqli_prepare($link, "SELECT bday, bmonth FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $userid);
    mysqli_stmt_execute($stmt);
    $rs2 = mysqli_stmt_get_result($stmt);
    
    $array = array(1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May',
                   6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 
                   10 => 'October', 11 => 'November', 12 => 'December');
    
    if($row2 = mysqli_fetch_assoc($rs2)){
      $bmonth = array_search($row2["bmonth"], $array);
      $bday = $row2["bday"];
      $d1 = mktime(0,0,0, $bmonth, $bday);
    }
    
    $diff =  ceil(($d1 - time())/(60*60*24));
    
    $d2 = mktime(0,0,0, 12, 25); // christmas
    $xmas = ceil(($d2 - time())/(60*60*24));
    
    if(($diff > 0 and $diff <= 20) or ($xmas > 0 and $xmas <= 20)){
      // less than 20 days till bday or xmas so 
      // send an email to each person who has purchased this gift
      
      $to = "";
      while($row1 = mysqli_fetch_assoc($rs1)){
        $to .= $row1["userid"] .",";
      }
      $to = rtrim($to, ',');

      // rewind cursor so that we can get the title and description
      mysqli_data_seek($rs1, 0);
      $row1 = mysqli_fetch_assoc($rs1);      

      $from = $userid;
      $subject = $fullname . "'s WishList has been modified";
      $message = "<p><font color=indianred><b>" . $fullname .
        "</b></font> has <b>deleted</b> the following item that you have already bought <dir>" .
        "<i>" . $row1["title"] . "</i> - " . $row1["description"] . "</dir>" .
        "Remember, " . $fullname . " had no way of knowing that the item " .
        "had been purchased. This warning is sent out if a person deletes an item (which has been " .
        "purchased) from their list " .
        "within 20 days of their birthday or Christmas<br>" .
        "<br>Day until " . $fullname . "'s bday is " . $diff . "<br>Days till Christmas=" . $xmas . 
        "</body></html>";
      sendEmail($to,$from,$subject,$message,0);
    } 
  }
  return "Success";
}


define('THUMBNAIL_IMAGE_MAX_WIDTH', 600);
define('THUMBNAIL_IMAGE_MAX_HEIGHT', 600);

function generate_image_thumbnail($source_image_path, $thumbnail_image_path)
{
    list($source_image_width, $source_image_height, $source_image_type) = getimagesize($source_image_path);
    switch ($source_image_type) {
        case IMAGETYPE_GIF:
            $source_gd_image = imagecreatefromgif($source_image_path);
            break;
        case IMAGETYPE_JPEG:
            $source_gd_image = imagecreatefromjpeg($source_image_path);
            break;
        case IMAGETYPE_PNG:
            $source_gd_image = imagecreatefrompng($source_image_path);
            break;
    }
    if ($source_gd_image === false) {
        return false;
    }

    // auto-rotate based on EXIF orientation
    $exif = exif_read_data($source_image_path);
    if (!empty($exif['Orientation'])) {
        switch ($exif['Orientation']) {
            case 3:
                $source_gd_image = imagerotate($source_gd_image, 180, 0);
                break;
            case 6:
                $source_gd_image = imagerotate($source_gd_image, -90, 0);
                break;
            case 8:
                $source_gd_image = imagerotate($source_gd_image, 90, 0);
                break;
        }
    }

    $source_aspect_ratio = $source_image_width / $source_image_height;
    $thumbnail_aspect_ratio = THUMBNAIL_IMAGE_MAX_WIDTH / THUMBNAIL_IMAGE_MAX_HEIGHT;
    if ($source_image_width <= THUMBNAIL_IMAGE_MAX_WIDTH && $source_image_height <= THUMBNAIL_IMAGE_MAX_HEIGHT) {
        $thumbnail_image_width = $source_image_width;
        $thumbnail_image_height = $source_image_height;
    } elseif ($thumbnail_aspect_ratio > $source_aspect_ratio) {
        $thumbnail_image_width = (int) (THUMBNAIL_IMAGE_MAX_HEIGHT * $source_aspect_ratio);
        $thumbnail_image_height = THUMBNAIL_IMAGE_MAX_HEIGHT;
    } else {
        $thumbnail_image_width = THUMBNAIL_IMAGE_MAX_WIDTH;
        $thumbnail_image_height = (int) (THUMBNAIL_IMAGE_MAX_WIDTH / $source_aspect_ratio);
    }
    $thumbnail_gd_image = imagecreatetruecolor($thumbnail_image_width, $thumbnail_image_height);
    imagecopyresampled($thumbnail_gd_image, $source_gd_image, 0, 0, 0, 0, $thumbnail_image_width, $thumbnail_image_height, $source_image_width, $source_image_height);
    imagejpeg($thumbnail_gd_image, $thumbnail_image_path, 90);
    imagedestroy($source_gd_image);
    imagedestroy($thumbnail_gd_image);
    return true;
}

/*
 * Uploaded file processing function
 */

function process_image_upload($field,$destination)
{
    $temp_image_path = $_FILES[$field]['tmp_name'];
    $temp_image_name = $_FILES[$field]['name'];
    list(, , $temp_image_type) = getimagesize($temp_image_path);
    if ($temp_image_type === NULL) {
        return false;
    }
    switch ($temp_image_type) {
        case IMAGETYPE_GIF:
            break;
        case IMAGETYPE_JPEG:
            break;
        case IMAGETYPE_PNG:
            break;
        default:
            return false;
    }
    if (is_uploaded_file($temp_image_path)) {
        $result = generate_image_thumbnail($temp_image_path, $destination);
    }
    return $result ? array($temp_image_path, $destination) : false;
}


?>
