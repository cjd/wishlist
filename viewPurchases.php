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
<!DOCTYPE html>
<HTML>

<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel=stylesheet href=style.css type=text/css>
<title>Your Purchases</title>
</head>

<BODY>
<table class=pagetable>
<tr>
<td valign="top">
<?php
createNavBar("home.php:Home|:View Purchases", false, "viewPurchases");
?>
<font size="3">
<p>Below is a list of purchases/comments made by you or those who you have been given access to (ie spouse/children) but <b>not</b> including gifts bought for you.<br>
If you want to include any additional people in this list then request that they give you Edit Access.
</p>
<?php
$query = "(SELECT 'item' as type, CONCAT(p1.firstname,' ',p1.lastname) AS recipient,
CONCAT(p2.firstname,' ',p2.lastname) AS buyer,
p1.userid, boughtDate, purchaseHistory.purchaseId, items.title,
purchaseHistory.quantity, items.description, items.price,
p1.firstname as firstname, p1.lastname as lastname
FROM people as p1, people as p2, purchaseHistory,items,categories
WHERE p1.userid=categories.userid
AND p2.userid=purchaseHistory.userid
AND purchaseHistory.iid=items.iid
AND items.cid=categories.cid
AND (purchaseHistory.userid = ?
OR purchaseHistory.userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1)
)
AND categories.userid != ?)
UNION
(SELECT 'comment' as type, CONCAT(p1.firstname,' ',p1.lastname) as recipient,
CONCAT(p2.firstname,' ',p2.lastname) as buyer, p1.userid,
comments.date,comments.commentId,comments.comment,
0,'',0,
p1.firstname as firstname, p1.lastname as lastname
FROM people as p1, people as p2, comments
WHERE p1.userid = comments.userid
AND p2.userid = comments.comment_userid
AND (comments.comment_userid = ?
OR comments.comment_userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1))
AND comments.userid != ?)
ORDER BY lastname,firstname;";

$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "ssssss", $userid, $userid, $userid, $userid, $userid, $userid);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$rows = [];
$totalSpent = 0.0;
$totalGifts = 0;
$personSummary = [];

while ($r = mysqli_fetch_assoc($rs)) {
    $rows[] = $r;
    if ($r["type"] === "item") {
        $qty = intval($r["quantity"] ?? 1);
        if ($qty <= 0) $qty = 1;
        $price = floatval($r["price"] ?? 0);
        $subtotal = $qty * $price;
        $totalSpent += $subtotal;
        $totalGifts += $qty;

        $recipName = $r["recipient"];
        if (!isset($personSummary[$recipName])) {
            $personSummary[$recipName] = ['count' => 0, 'total' => 0.0];
        }
        $personSummary[$recipName]['count'] += $qty;
        $personSummary[$recipName]['total'] += $subtotal;
    }
}
?>

<div class="spending-summary-card">
  <div class="spending-summary-header">
    <div class="spending-summary-title">
      <b>📊 Gift Spending Summary</b>
    </div>
    <div style="display: flex; gap: 8px;">
      <button type="button" class="spending-privacy-btn" id="spendingPrivacyBtn" onclick="toggleSpendingPrivacy()" title="Hide/Show amounts">
        👁️ <span id="spendingPrivacyLabel">Hide Prices</span>
      </button>
      <button type="button" class="spending-privacy-btn" onclick="window.print()" title="Print purchase report">
        🖨️ Print Report
      </button>
    </div>
  </div>
  <div class="spending-summary-stats" id="spendingStatsArea">
    <div class="spending-stat-pill main-total">
      <span class="stat-label">Total Spent:</span>
      <span class="stat-value price-secret">$<?php echo number_format($totalSpent, 2); ?></span>
      <span class="stat-sub">(<?php echo $totalGifts; ?> gift<?php echo $totalGifts === 1 ? '' : 's'; ?>)</span>
    </div>
    <?php foreach ($personSummary as $person => $data): ?>
      <div class="spending-stat-pill">
        <span class="stat-label"><?php echo htmlspecialchars($person); ?>:</span>
        <span class="stat-value price-secret">$<?php echo number_format($data['total'], 2); ?></span>
        <span class="stat-sub">(<?php echo $data['count']; ?>)</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<table class=viewpurchases>
<tr><th class=headerCell>List</th><th class=headerCell>Gift/Comment</th><th class=headerCell>$</th><th class=headerCell>Buyer</th><th class=headerCell>Bought @</th><th class=headerCell>Action</th></tr>

<?php
$style="viewpurchases1";
$oldrecip="";
$oldlast="";
foreach ($rows as $row){
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
    echo "<tr><td class=".$style."><b><a href=\"viewList/viewList.php?recip=".urlencode($row["userid"])."&name=".urlencode($row["recipient"])."\">".htmlspecialchars($row["recipient"])."</a></b></td>";
  } else {
    echo "<tr><td class=".$style."></td>";
  }
  echo "<td class=".$style.">";
  if ($row["quantity"] > 0) {
    echo $row["quantity"]." x ";
  }
  echo htmlspecialchars($row["title"])."</td>";
  echo "<td class=".$style.">";
  if ($row["price"]*$row["quantity"] > 0) {
    echo "<span class='price-secret'>$" . number_format($row["price"]*$row["quantity"], 2) . "</span>";
  }
  echo "</td>";
  echo "<td class=".$style.">".htmlspecialchars($row["buyer"])."</td>";
  echo "<td class=".$style.">".parseDate($row["boughtDate"],1)."</td>\n";
  if ($row["type"] == "item") {
    echo "<td class=".$style."><b><a href=\"viewList/deletePurchase.php?recip=".urlencode($row["userid"])."&name=".urlencode($row["recipient"])."&purchaseId=".intval($row["purchaseId"])."\">[Undo this purchase]</a></b></td>";
  } else {
    echo "<td class=".$style."><b><a href=\"viewList/deleteComment.php?commentId=".intval($row["purchaseId"])."&recip=".urlencode($row["userid"])."&name=".urlencode($row["recipient"])."\">[Delete this comment]</a></b></td>";

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

<script>
function toggleSpendingPrivacy() {
    var area = document.getElementById('spendingStatsArea');
    var label = document.getElementById('spendingPrivacyLabel');
    var isHidden = document.body.classList.toggle('spending-hidden');
    if (label) {
        label.textContent = isHidden ? 'Show Prices' : 'Hide Prices';
    }
    localStorage.setItem('wishlist_privacy', isHidden ? '1' : '0');
}

document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('wishlist_privacy') === '1') {
        document.body.classList.add('spending-hidden');
        var label = document.getElementById('spendingPrivacyLabel');
        if (label) label.textContent = 'Show Prices';
    }
});
</script>
</body>
</html>
