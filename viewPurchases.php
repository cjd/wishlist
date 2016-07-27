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
$userid = $_SESSION["userid"];
?>

<HTML>

<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>

<link rel=stylesheet href=style.css type=text/css>

<head>
</head>

<title>Your Purchases</title>

<BODY>
<table class=pagetable>
<tr>
<td valign="top">
<?php
createNavBar("home.php:Home|:View Purchases", false, "viewOther");
?>
<font size="3">
<p>Below is a list of purchases/comments made by you or those who you have been given access to (ie spouse/children) but <b>not</b> including gifts bought for you.<br>
If you want to include any additional people in this list then request that they give you Edit Access.
</p>
<table class=viewpurchases>
<tr><th class=headerCell>List</th><th class=headerCell>Gift/Comment</th><th class=headerCell>$</th><th class=headerCell>Buyer</th><th class=headerCell>Bought @</th><th class=headerCell>Action</th></tr>

<?php
$query = "(SELECT 'item' as type,CONCAT(p1.firstname,' ',p1.lastname) AS recipient,
CONCAT(p2.firstname,' ',p2.lastname) AS buyer,
p1.userid, boughtDate, purchaseHistory.purchaseId, items.title,
purchaseHistory.quantity, items.description, items.price,
p1.firstname as firstname, p1.lastname as lastname
FROM people as p1, people as p2, purchaseHistory,items,categories
WHERE p1.userid=categories.userid
AND p2.userid=purchaseHistory.userid
AND purchaseHistory.iid=items.iid
AND items.cid=categories.cid
AND (purchaseHistory.userid='".$userid."'
OR purchaseHistory.userid IN (SELECT pid FROM viewList WHERE viewer='".$userid."' AND allowEdit=1)
)
AND categories.userid!='".$userid."')
UNION
(SELECT 'comment' as type, concat(p1.firstname,' ',p1.lastname) as recipient,
concat(p2.firstname,' ',p2.lastname) as buyer, p1.userid,
comments.date,comments.commentId,comments.comment,
0,'',0,
p1.firstname as firstname, p1.lastname as lastname
FROM people as p1, people as p2, comments
WHERE p1.userid=comments.userid
AND p2.userid=comments.comment_userid
AND (comments.comment_userid='".$userid."'
OR comments.comment_userid IN (select pid from viewList where viewer='".$userid."' and allowEdit=1)
AND comments.userid!='".$userid."'))
ORDER BY lastname,firstname;";


$rs = mysqli_query($link,$query) or die("Could not query: " . mysqli_error($link));
$style="viewpurchases1";
$oldrecip="";
$oldlast="";
while($row = mysqli_fetch_assoc($rs)){
  if ($row["recipient"]!=$oldrecip) {
    $oldrecip=$row["recipient"];
    if ($row["lastname"] != $oldlast) {
      if ($oldlast != "") {
        echo "<tr><td colspan=6 class=viewpurchaseshead></td></tr>";
        echo "<tr><td colspan=6>&nbsp;</td></tr>";
      }
      $oldlast=$row["lastname"];
    }
    echo "<tr><td colspan=6 class=viewpurchaseshead></td></tr>";
    $style="viewpurchases1";
    echo "<tr><td class=".$style."><b><a href=\"viewList/viewList.php?recip=".$row[userid]."&name=".$row["recipient"]."\">".$row["recipient"]."</a></b></td>";
  } else {
    echo "<tr><td class=".$style."></td>";
  }
  echo "<td class=".$style.">";
  if ($row["quantity"] > 0) {
    echo $row["quantity"]." x ";
  }
  echo $row["title"]."</td>";
  echo "<td class=".$style.">";
  if ($row["price"]*$row["quantity"] > 0) {
    echo $row["price"]*$row["quantity"];
  }
  echo "</td>";
  echo "<td class=".$style.">".$row["buyer"]."</td>";
  echo "<td class=".$style.">".parseDate($row["boughtDate"],1)."</td>\n";
  if ($row["type"] == "item") {
    echo "<td class=".$style."><b><a href=\"viewList/deletePurchase.php?recip=".$userid."&name=".$row["recipient"]."&purchaseId=".$row["purchaseId"]."\">[Undo this purchase]</a></b></td>";
  } else {
    echo "<td class=".$style."><b><a href=\"viewList/deleteComment.php?commentId=".$row["purchaseId"]."&recip=".$row["userid"]."&name=".$row["recipient"]."\">[Delete this comment]</a></b></td>";

  }
  echo "</tr>\n";
  if ( $style == "viewpurchases1") {
    $style="viewpurchases2";
  } else {
    $style="viewpurchases1";
  }
}
?>
<tr><td colspan=6 class=viewpurchaseshead></td></tr>
</table>
</td></tr>
</table>
</body>
</html>
