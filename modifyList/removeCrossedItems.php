<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$confirm = $_REQUEST["confirm"] ?? "";
$reveal = ($_GET["reveal"] ?? "") === "1";

function getOrCreateConsiderCategory($link, $ownerUserid) {
    $stmt = mysqli_prepare($link, "SELECT cid FROM categories WHERE userid = ? AND catSortOrder = -10000 LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $ownerUserid);
    mysqli_stmt_execute($stmt);
    if ($row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
        return (int)$row["cid"];
    }
    $stmt_ins = mysqli_prepare(
        $link,
        "INSERT INTO categories (catSortOrder, userid, name, catSubDescription) VALUES (-10000, ?, 'Items Under Consideration', 'You are the only person who can view items in this category. Use it for items you are thinking of adding to your list')"
    );
    mysqli_stmt_bind_param($stmt_ins, "s", $ownerUserid);
    mysqli_stmt_execute($stmt_ins);
    return (int)mysqli_insert_id($link);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie(true);

    if (!empty($_POST['apply_cleanup']) && isset($_POST['item_action']) && is_array($_POST['item_action'])) {
        $actions = $_POST['item_action'];
        $changedAny = false;
        $considerCid = null;

        foreach ($actions as $rawIid => $act) {
            $iid = (int)$rawIid;
            $act = trim((string)$act);
            if ($iid <= 0 || $act === 'skip' || !in_array($act, ['delete', 'consider', 'reset_remaining', 'reset_all'], true)) {
                continue;
            }

            $stmt_item = mysqli_prepare(
                $link,
                "SELECT i.iid, i.cid, i.quantity, i.itemSortOrder, COALESCE(ph.boughtQty, 0) AS boughtQty
                 FROM items i
                 JOIN categories c ON c.cid = i.cid
                 LEFT JOIN (
                     SELECT iid, SUM(quantity) AS boughtQty
                     FROM purchaseHistory
                     GROUP BY iid
                 ) ph ON ph.iid = i.iid
                 WHERE i.iid = ? AND c.userid = ? AND c.catSortOrder > -1
                 LIMIT 1"
            );
            mysqli_stmt_bind_param($stmt_item, "is", $iid, $userid);
            mysqli_stmt_execute($stmt_item);
            $itemRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_item));
            if (!$itemRow || (int)$itemRow['boughtQty'] <= 0) {
                continue;
            }

            if ($act === 'delete') {
                deleteItem($iid, $userid, $activeOwnerFullname, $base_dir, true);
                $changedAny = true;
            } elseif ($act === 'consider') {
                if ($considerCid === null) {
                    $considerCid = getOrCreateConsiderCategory($link, $userid);
                }
                $oldCid = (int)$itemRow['cid'];
                $oldSort = (int)$itemRow['itemSortOrder'];

                $stmt_del_ph = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
                mysqli_stmt_bind_param($stmt_del_ph, "i", $iid);
                mysqli_stmt_execute($stmt_del_ph);

                $stmt_shift = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE cid = ? AND itemSortOrder > ?");
                mysqli_stmt_bind_param($stmt_shift, "ii", $oldCid, $oldSort);
                mysqli_stmt_execute($stmt_shift);

                $stmt_max = mysqli_prepare($link, "SELECT COALESCE(MAX(itemSortOrder), 0) + 1 AS nextSort FROM items WHERE cid = ?");
                mysqli_stmt_bind_param($stmt_max, "i", $considerCid);
                mysqli_stmt_execute($stmt_max);
                $nextSortRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_max));
                $nextSort = (int)($nextSortRow['nextSort'] ?? 1);

                $stmt_mv = mysqli_prepare($link, "UPDATE items SET cid = ?, itemSortOrder = ? WHERE iid = ?");
                mysqli_stmt_bind_param($stmt_mv, "iii", $considerCid, $nextSort, $iid);
                mysqli_stmt_execute($stmt_mv);

                $changedAny = true;
            } elseif ($act === 'reset_remaining') {
                $remaining = (int)$itemRow['quantity'] - (int)$itemRow['boughtQty'];
                if ($remaining <= 0) {
                    deleteItem($iid, $userid, $activeOwnerFullname, $base_dir, true);
                } else {
                    $stmt_del_ph = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
                    mysqli_stmt_bind_param($stmt_del_ph, "i", $iid);
                    mysqli_stmt_execute($stmt_del_ph);

                    $stmt_qty = mysqli_prepare($link, "UPDATE items SET quantity = ? WHERE iid = ?");
                    mysqli_stmt_bind_param($stmt_qty, "ii", $remaining, $iid);
                    mysqli_stmt_execute($stmt_qty);
                }
                $changedAny = true;
            } elseif ($act === 'reset_all') {
                $stmt_del_ph = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
                mysqli_stmt_bind_param($stmt_del_ph, "i", $iid);
                mysqli_stmt_execute($stmt_del_ph);
                $changedAny = true;
            }
        }

        if ($changedAny) {
            $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_m, "s", $userid);
            mysqli_stmt_execute($stmt_m);
        }

        header("Location: " . getFullPath("modifyList.php"));
        exit;
    } elseif ($confirm === "yes") {
        $sql = "SELECT items.iid
                FROM items
                JOIN categories ON categories.cid = items.cid
                JOIN purchaseHistory ON purchaseHistory.iid = items.iid
                WHERE categories.userid = ?
                  AND categories.catSortOrder > -1
                  AND items.allowCheck = 'true'
                GROUP BY items.iid, items.quantity
                HAVING SUM(purchaseHistory.quantity) >= items.quantity";
        $stmt = mysqli_prepare($link, $sql);
        mysqli_stmt_bind_param($stmt, "s", $userid);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $deletedAny = false;
        while ($row = mysqli_fetch_assoc($result)) {
            deleteItem((int)$row["iid"], $userid, $activeOwnerFullname, $base_dir, true);
            $deletedAny = true;
        }

        if ($deletedAny) {
            $stmt_m = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_m, "s", $userid);
            mysqli_stmt_execute($stmt_m);
        }

        header("Location: " . getFullPath("modifyList.php"));
        exit;
    }
}

$purchasedItems = [];
if ($reveal) {
    $stmt_rev = mysqli_prepare(
        $link,
        "SELECT i.iid, i.title, i.description, i.price, i.quantity, i.image,
                c.name AS categoryName, ph.boughtQty
         FROM items i
         JOIN categories c ON c.cid = i.cid
         JOIN (
             SELECT iid, SUM(quantity) AS boughtQty
             FROM purchaseHistory
             GROUP BY iid
             HAVING SUM(quantity) > 0
         ) ph ON ph.iid = i.iid
         WHERE c.userid = ?
           AND c.catSortOrder > -1
           AND i.allowCheck = 'true'
         ORDER BY c.catSortOrder, i.itemSortOrder"
    );
    mysqli_stmt_bind_param($stmt_rev, "s", $userid);
    mysqli_stmt_execute($stmt_rev);
    $res_rev = mysqli_stmt_get_result($stmt_rev);
    while ($prow = mysqli_fetch_assoc($res_rev)) {
        $purchasedItems[] = $prow;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Post-Holiday Cleanup &amp; Remove Crossed Off Items</title>
  <script>
  function setBulkCleanupAction(mode) {
    var selects = document.querySelectorAll('.cleanup-action-select');
    for (var i = 0; i < selects.length; i++) {
      var sel = selects[i];
      if (mode === 'default') {
        sel.value = sel.getAttribute('data-default') || 'delete';
      } else {
        for (var j = 0; j < sel.options.length; j++) {
          if (sel.options[j].value === mode) {
            sel.value = mode;
            break;
          }
        }
      }
    }
  }
  </script>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Post-Holiday Cleanup"); ?>

<div class="cleanup-review-wrap">
  <h2>🎁 Post-Holiday Cleanup (Purchased Items)</h2>

  <?php if (!$reveal): ?>
    <div class="spoiler-gate-card">
      <div class="spoiler-gate-title">⚠️ Spoiler Warning — Gifts Ahead!</div>
      <p>
        Continuing will show which items on <b><?php echo escapeText($activeOwnerFullname); ?>'s</b> wishlist have been marked as purchased (including partially fulfilled multi-quantity items).
      </p>
      <p>
        <i>Only reveal this screen <b>after</b> your birthday or holiday gift exchange has passed so upcoming surprises aren't spoiled early!</i>
      </p>
      <div class="spoiler-gate-actions">
        <a href="removeCrossedItems.php?reveal=1" class="buttonstyle" style="text-decoration:none; display:inline-block;">🎁 Reveal Purchased Items &amp; Review</a>
        <a href="modifyList.php" class="lightbutton" style="text-decoration:none; display:inline-block;">Cancel — Back to Modify List</a>
      </div>
    </div>
  <?php elseif (empty($purchasedItems)): ?>
    <div class="spoiler-gate-card" style="border-color:#86efac; background:#f0fdf4;">
      <div class="spoiler-gate-title" style="color:#166534;">✨ All Clear!</div>
      <p>None of the items on <b><?php echo escapeText($activeOwnerFullname); ?>'s</b> wishlist are currently marked as purchased.</p>
      <div class="spoiler-gate-actions">
        <a href="modifyList.php" class="buttonstyle" style="text-decoration:none; display:inline-block;">Back to Modify List</a>
      </div>
    </div>
  <?php else: ?>
    <p>
      Choose what to do with each purchased or partially purchased item below. Buyer deletion warnings are automatically suppressed during post-holiday cleanup.
    </p>

    <div class="cleanup-bulk-bar">
      <span><b>Quick select all:</b></span>
      <button type="button" class="actionButton" onclick="setBulkCleanupAction('default')">Recommended Defaults</button>
      <button type="button" class="actionButton" onclick="setBulkCleanupAction('delete')">🗑️ Delete All</button>
      <button type="button" class="actionButton" onclick="setBulkCleanupAction('consider')">💭 Move All to Consideration</button>
      <button type="button" class="actionButton" onclick="setBulkCleanupAction('skip')">📌 Keep All As-Is</button>
    </div>

    <form method="post" action="removeCrossedItems.php">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
      <input type="hidden" name="apply_cleanup" value="1">

      <table class="cleanup-review-table">
        <thead>
          <tr>
            <th>Item</th>
            <th>Category</th>
            <th>Purchase Status</th>
            <th>Cleanup Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($purchasedItems as $pItem): ?>
            <?php
              $iid = (int)$pItem['iid'];
              $qty = max(1, (int)$pItem['quantity']);
              $bought = (int)$pItem['boughtQty'];
              $remaining = max(0, $qty - $bought);
              $isPartial = ($remaining > 0);
              $defaultAction = $isPartial ? 'reset_remaining' : 'delete';
              $imgHtml = '';
              if (!empty($pItem['image'])) {
                  $imgFile = 'image-' . $iid . '.jpg';
                  $diskPath = $base_dir . '/uploads/' . $imgFile;
                  if (is_file($diskPath)) {
                      $imgSrc = escapeText($base_url . '/uploads/' . $imgFile . '?v=' . filemtime($diskPath));
                      $imgHtml = '<img src="' . $imgSrc . '" alt="" class="cleanup-thumb" loading="lazy" decoding="async">';
                  }
              }
            ?>
            <tr>
              <td>
                <div style="display:flex; align-items:center; gap:10px;">
                  <?php echo $imgHtml; ?>
                  <div>
                    <b><?php echo escapeText($pItem['title'] !== '' ? $pItem['title'] : '(Untitled Item)'); ?></b>
                    <?php if (!empty($pItem['price']) && $pItem['price'] !== '0.00'): ?>
                      <span class="price-badge">$<?php echo escapeText($pItem['price']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pItem['description'])): ?>
                      <div style="font-size:12.5px; color:#64748b; margin-top:2px;"><?php echo formatMultilineText($pItem['description']); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td><?php echo escapeText($pItem['categoryName'] ?? ''); ?></td>
              <td>
                <?php if ($isPartial): ?>
                  <span class="cleanup-status-pill cleanup-status-partial">
                    🌗 <?php echo $bought; ?> of <?php echo $qty; ?> bought (<?php echo $remaining; ?> left)
                  </span>
                <?php else: ?>
                  <span class="cleanup-status-pill cleanup-status-full">
                    ✅ Bought (<?php echo $bought; ?> of <?php echo $qty; ?>)
                  </span>
                <?php endif; ?>
              </td>
              <td>
                <select name="item_action[<?php echo $iid; ?>]" class="cleanup-action-select" data-default="<?php echo escapeText($defaultAction); ?>">
                  <?php if ($isPartial): ?>
                    <option value="reset_remaining" selected>🔄 Keep remaining <?php echo $remaining; ?> on list (clear bought)</option>
                  <?php endif; ?>
                  <option value="delete"<?php echo !$isPartial ? ' selected' : ''; ?>>🗑️ Delete item from list</option>
                  <option value="consider">💭 Move to "Items Under Consideration"</option>
                  <option value="reset_all">♻️ Uncross &amp; request again (qty <?php echo $qty; ?>)</option>
                  <option value="skip">📌 Keep as-is (do nothing)</option>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end;">
        <a href="modifyList.php" class="lightbutton" style="text-decoration:none; display:inline-block;">Cancel</a>
        <button type="submit" class="buttonstyle">✨ Apply Selected Cleanup Actions</button>
      </div>
    </form>
  <?php endif; ?>
</div>

</td></tr></table>
</body>
</html>
