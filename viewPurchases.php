<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "funcLib.php";
$userid = $_SESSION["userid"];
$csrf_token = getCsrfToken();

// Pre-fetch accounts where $userid has allowEdit=1 (spouse/children proxy accounts)
$proxy_pids = [];
$stmt_proxy = mysqli_prepare($link, "SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1");
mysqli_stmt_bind_param($stmt_proxy, "s", $userid);
mysqli_stmt_execute($stmt_proxy);
$res_proxy = mysqli_stmt_get_result($stmt_proxy);
while ($pr = mysqli_fetch_assoc($res_proxy)) {
    $proxy_pids[$pr['pid']] = true;
}

$query = "(SELECT 'item' AS type,
    TRIM(CONCAT_WS(' ', p1.firstname, p1.lastname, NULLIF(p1.suffix, ''))) AS recipient,
    TRIM(CONCAT_WS(' ', p2.firstname, p2.lastname, NULLIF(p2.suffix, ''))) AS buyer,
    p1.userid AS userid,
    p2.userid AS actor_userid,
    purchaseHistory.boughtDate AS boughtDate,
    purchaseHistory.purchaseId AS recordId,
    items.title AS content,
    purchaseHistory.quantity AS quantity,
    items.description AS description,
    items.price AS price,
    p1.firstname AS firstname,
    p1.lastname AS lastname,
    purchaseHistory.splitWith AS splitWith
  FROM purchaseHistory
  JOIN items ON purchaseHistory.iid = items.iid
  JOIN categories ON items.cid = categories.cid
  JOIN people AS p1 ON p1.userid = categories.userid
  JOIN people AS p2 ON p2.userid = purchaseHistory.userid
  WHERE (purchaseHistory.userid = ?
         OR purchaseHistory.userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1)
         OR FIND_IN_SET(?, purchaseHistory.splitUsers) > 0)
    AND categories.userid != ?)
UNION ALL
(SELECT 'comment' AS type,
    TRIM(CONCAT_WS(' ', p1.firstname, p1.lastname, NULLIF(p1.suffix, ''))) AS recipient,
    TRIM(CONCAT_WS(' ', p2.firstname, p2.lastname, NULLIF(p2.suffix, ''))) AS buyer,
    p1.userid AS userid,
    p2.userid AS actor_userid,
    comments.date AS boughtDate,
    comments.commentId AS recordId,
    comments.comment AS content,
    0 AS quantity,
    '' AS description,
    0 AS price,
    p1.firstname AS firstname,
    p1.lastname AS lastname,
    '' AS splitWith
  FROM comments
  JOIN people AS p1 ON p1.userid = comments.userid
  JOIN people AS p2 ON p2.userid = comments.comment_userid
  WHERE (comments.comment_userid = ?
         OR comments.comment_userid IN (SELECT pid FROM viewList WHERE viewer = ? AND allowEdit = 1))
    AND comments.userid != ?)
ORDER BY lastname, firstname, userid, boughtDate DESC";

$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "sssssss", $userid, $userid, $userid, $userid, $userid, $userid, $userid);
mysqli_stmt_execute($stmt);
$rs = mysqli_stmt_get_result($stmt);

$rows = [];
$totalSpent = 0.0;
$totalGifts = 0;
$personSummary = [];

while ($r = mysqli_fetch_assoc($rs)) {
    $rows[] = $r;
    if ($r["type"] === "item") {
        $qty = (int)($r["quantity"] ?? 1);
        if ($qty <= 0) {
            $qty = 1;
        }
        $price = (float)($r["price"] ?? 0);
        $subtotal = $qty * $price;
        $totalSpent += $subtotal;
        $totalGifts += $qty;

        $recipKey = $r["userid"];
        if (!isset($personSummary[$recipKey])) {
            $personSummary[$recipKey] = ['name' => $r["recipient"], 'count' => 0, 'total' => 0.0];
        }
        $personSummary[$recipKey]['count'] += $qty;
        $personSummary[$recipKey]['total'] += $subtotal;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css" type="text/css">
  <link rel="stylesheet" href="print.css" type="text/css" media="print">
  <link rel="manifest" href="manifest.json">
  <title>Your Purchases</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|:View Purchases", false, "viewPurchases"); ?>
<font size="3">
<p>Below is a list of purchases and comments made by you or family accounts you have Edit Access to, <b>excluding</b> gifts bought for you.<br>
To include additional family members' purchases here, ask them to grant you Edit Access under Update Account.
</p>

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
    <?php foreach ($personSummary as $data): ?>
      <div class="spending-stat-pill">
        <span class="stat-label"><?php echo escapeText($data['name']); ?>:</span>
        <span class="stat-value price-secret">$<?php echo number_format($data['total'], 2); ?></span>
        <span class="stat-sub">(<?php echo (int)$data['count']; ?>)</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<table class="viewpurchases">
<tr>
  <th class="headerCell">List</th>
  <th class="headerCell">Gift / Comment</th>
  <th class="headerCell">$</th>
  <th class="headerCell">Buyer</th>
  <th class="headerCell">Bought @</th>
  <th class="headerCell">Action</th>
</tr>

<?php
$style = "viewpurchases1";
$oldrecip_id = "";
$oldlast = "";
foreach ($rows as $row) {
    if ($row["userid"] !== $oldrecip_id) {
        $oldrecip_id = $row["userid"];
        if (strcasecmp($row["lastname"], $oldlast) !== 0) {
            if ($oldlast !== "") {
                echo "<tr><td colspan=\"6\" class=\"viewpurchaseshead\"></td></tr>";
                echo "<tr><td colspan=\"6\">&nbsp;</td></tr>";
            }
            $oldlast = $row["lastname"];
        }
        echo "<tr><td colspan=\"6\" class=\"viewpurchaseshead\"></td></tr>";
        $style = "viewpurchases1";
        echo "<tr><td class=\"" . $style . "\"><b><a href=\"viewList/viewList.php?recip=" . urlencode($row["userid"]) . "&amp;name=" . urlencode($row["recipient"]) . "\">" . escapeText($row["recipient"]) . "</a></b></td>";
    } else {
        echo "<tr><td class=\"" . $style . "\"></td>";
    }

    echo "<td class=\"" . $style . "\">";
    if ($row["type"] === "item") {
        if ((int)$row["quantity"] > 0) {
            echo (int)$row["quantity"] . " x ";
        }
        echo escapeText($row["content"]);
    } else {
        $cText = (string)$row["content"];
        if (strpos($cText, "<div class='secret-suggestion-card'>") === 0 || strpos($cText, '<div class="secret-suggestion-card">') === 0) {
            echo formatMessage($cText);
        } else {
            echo "<i>Comment:</i> " . formatMultilineText($cText);
        }
    }
    echo "</td>";

    echo "<td class=\"" . $style . "\">";
    if ($row["type"] === "item" && ((float)$row["price"] * (int)$row["quantity"]) > 0) {
        echo "<span class='price-secret'>$" . number_format((float)$row["price"] * (int)$row["quantity"], 2) . "</span>";
    }
    echo "</td>";

    echo "<td class=\"" . $style . "\">" . escapeText($row["buyer"]);
    if (!empty($row["splitWith"])) {
        echo "<div class='split-subtext'>👥 with " . escapeText($row["splitWith"]) . "</div>";
    }
    echo "</td>";

    echo "<td class=\"" . $style . "\">" . escapeText(parseDate($row["boughtDate"], 1)) . "</td>\n";

    echo "<td class=\"" . $style . "\">";
    if ($row["type"] === "item") {
        $canUndo = ($row["actor_userid"] === $userid || isset($proxy_pids[$row["actor_userid"]]));
        if ($canUndo) {
            echo "<b><a href=\"viewList/deletePurchase.php?recip=" . urlencode($row["userid"]) . "&amp;name=" . urlencode($row["recipient"]) . "&amp;purchaseId=" . (int)$row["recordId"] . "&amp;return_to=viewPurchases\">[Undo this purchase]</a></b>";
        } else {
            echo "<small>(Co-buyer)</small>";
        }
    } else {
        if ($row["actor_userid"] === $userid) {
            echo "<form method=\"post\" action=\"viewList/deleteComment.php\" style=\"display:inline; margin:0;\">";
            echo "<input type=\"hidden\" name=\"csrf_token\" value=\"" . escapeText($csrf_token) . "\">";
            echo "<input type=\"hidden\" name=\"commentId\" value=\"" . (int)$row["recordId"] . "\">";
            echo "<input type=\"hidden\" name=\"recip\" value=\"" . escapeText($row["userid"]) . "\">";
            echo "<input type=\"hidden\" name=\"name\" value=\"" . escapeText($row["recipient"]) . "\">";
            echo "<input type=\"hidden\" name=\"return_to\" value=\"viewPurchases\">";
            echo "<button type=\"submit\" class=\"menuLink\" style=\"background:none;border:none;padding:0;color:#0000cc;cursor:pointer;font-weight:bold;text-decoration:underline;\">[Delete this comment]</button>";
            echo "</form>";
        }
    }
    echo "</td></tr>\n";

    $style = ($style === "viewpurchases1") ? "viewpurchases2" : "viewpurchases1";
}
?>
<tr><td colspan="6" class="viewpurchaseshead"></td></tr>
</table>
</font>
</td></tr>
</table>

<script>
function toggleSpendingPrivacy() {
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
