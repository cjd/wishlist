<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$selfUserid = $_SESSION["userid"];
$editableAccounts = [];
$stmt_ed = mysqli_prepare(
    $link,
    "SELECT p.userid, p.firstname, p.lastname, p.suffix
     FROM people p
     JOIN viewList v ON v.pid = p.userid
     WHERE v.viewer = ? AND v.allowEdit = 1 AND p.userid != ?
     ORDER BY p.lastname, p.firstname"
);
if ($stmt_ed) {
    mysqli_stmt_bind_param($stmt_ed, "ss", $selfUserid, $selfUserid);
    mysqli_stmt_execute($stmt_ed);
    $res_ed = mysqli_stmt_get_result($stmt_ed);
    while ($erow = mysqli_fetch_assoc($res_ed)) {
        $editableAccounts[$erow['userid']] = trim($erow['firstname'] . ' ' . $erow['lastname'] . ' ' . ($erow['suffix'] ?? ''));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_edit_userid'])) {
    verifyCsrfOrDie(true);
    $reqEditUser = trim((string)$_POST['switch_edit_userid']);
    if ($reqEditUser === '' || $reqEditUser === $selfUserid) {
        unset($_SESSION['euserid']);
    } elseif (isset($editableAccounts[$reqEditUser])) {
        $_SESSION['euserid'] = $reqEditUser;
    }
    header("Location: " . getFullPath("modifyList.php"));
    exit;
}

$displayName = "Your ";
if ($userid !== $_SESSION["userid"]) {
    $stmt_name = mysqli_prepare($link, "SELECT firstname FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_name, "s", $userid);
    mysqli_stmt_execute($stmt_name);
    $res_name = mysqli_stmt_get_result($stmt_name);
    if ($row_name = mysqli_fetch_assoc($res_name)) {
        $displayName = $row_name["firstname"] . "'s ";
    } else {
        $displayName = $userid . "'s ";
    }
}

$csrf_token = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="stylesheet" href="../print.css?v=<?php echo (int)@filemtime(__DIR__ . '/../print.css'); ?>" type="text/css" media="print">
  <link rel="manifest" href="../manifest.json">
  <title>Modify List</title>
  <script src="../js/jquery-3.7.1.min.js"></script>
  <script src="../js/lightbox.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/lightbox.js'); ?>"></script>
  <script src="../js/wishlist-filter.js?v=<?php echo (int)@filemtime(__DIR__ . '/../js/wishlist-filter.js'); ?>"></script>
  <script src="../js/Sortable.min.js"></script>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">

<?php createNavBar("../home.php:Home|:Modify WishList"); ?>

<?php if (!empty($editableAccounts)): ?>
<div class="proxy-switcher-bar no-print">
  <form method="post" action="modifyList.php" style="margin:0; display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
    <label for="switchEditUserSelect">👥 <b>Managing List For:</b></label>
    <select id="switchEditUserSelect" name="switch_edit_userid" onchange="this.form.submit()">
      <option value="<?php echo escapeText($selfUserid); ?>"<?php echo ($userid === $selfUserid) ? ' selected' : ''; ?>>
        Myself (<?php echo escapeText($_SESSION['fullname'] ?? $selfUserid); ?>)
      </option>
      <?php foreach ($editableAccounts as $edUid => $edFullName): ?>
        <option value="<?php echo escapeText($edUid); ?>"<?php echo ($userid === $edUid) ? ' selected' : ''; ?>>
          <?php echo escapeText($edFullName); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php if ($userid !== $selfUserid): ?>
      <span class="proxy-active-pill">Editing <?php echo escapeText($editableAccounts[$userid] ?? $userid); ?>'s list</span>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<table width="100%"><tr><td>
  <font face="arial" size="5" color="red"><b><?php echo escapeText($displayName); ?>List</b></font> -
  <font size="-1" face="arial"><b>All check marks have been removed</b></font>
</td></tr></table>

<?php
$stmt = mysqli_prepare($link, "SELECT catSubDescription FROM categories WHERE userid = ? AND catSortOrder = -1000");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
$mrs = mysqli_stmt_get_result($stmt);
$mrow = mysqli_fetch_assoc($mrs);

echo "<table border=\"0\" cellpadding=\"2\" cellspacing=\"0\" width=\"100%\"><tr>";
if (!empty($mrow["catSubDescription"])) {
    echo "<td nowrap bgcolor=\"#99ff99\"><form method=\"get\" action=\"editListComment.php\" style=\"margin:0;display:inline;\"><input type=\"submit\" value=\"✏️\" class=\"actionButton\" title=\"Edit List Comment\"></form><img width=\"26\" height=\"13\" src=\"../images/space.GIF\" alt=\"\"></td>";
    echo "<td width=\"99%\" bgcolor=\"#ffffcc\"> " . formatMultilineText($mrow["catSubDescription"]) . "</td>";
} else {
    echo "<td nowrap width=\"100\" bgcolor=\"#99ff99\"><form method=\"get\" action=\"editListComment.php\" style=\"margin:0;display:inline;\"><input type=\"submit\" value=\"➕\" class=\"actionButton\" title=\"Add List Comment\"></form><img width=\"26\" height=\"13\" src=\"../images/space.GIF\" alt=\"\"></td>";
    echo "<td width=\"99%\" bgcolor=\"#ffffcc\"> Click the button to the left to add a comment to the top of your list for others to see</td>";
}
echo "</tr></table><p>";
?>

<div class="wishlist-search-box">
  <label for="wishlistSearch"><b>Search List:</b></label>
  <input type="text" id="wishlistSearch" class="wishlist-search-input" placeholder="Type to filter items..." oninput="filterWishlist(this.value)">
  <input type="button" id="wishlistSearchClear" class="actionButton" value="Clear" onclick="clearWishlistFilter()" style="display:none;">
  <span id="wishlistSearchCount" class="wishlist-search-count"></span>
</div>

<?php printModifyList($userid); ?>

<table width="100%">
<tr><td colspan="3" align="center">
  <form method="get" action="addCategory.php" style="display:inline-block;">
    <input type="submit" value="Add New Category" class="buttonstyle">
  </form>
  <input type="button" value="🔖 Add from Web / Phone" class="buttonstyle" onclick="location.href='../wishlist_button.php'">
  <input type="button" value="🖨️ Print List" class="buttonstyle" onclick="location.href='print.php'">
</td></tr>
</table>

<p>

<?php
$stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE userid = ? AND catSortOrder = -10000");
mysqli_stmt_bind_param($stmt, "s", $userid);
mysqli_stmt_execute($stmt);
$mrs = mysqli_stmt_get_result($stmt);
$mrow = mysqli_fetch_assoc($mrs);
if ($mrow) {
    printCategory($mrow, $activeOwnerFullname, 0, 0, 1, 0, 0, 1);
}
?>

<center>
  <input type="button" value="Edit Account" class="buttonstyle" onclick="location.href='../updateAccount/updateAccount.php<?php echo $userid !== $_SESSION['userid'] ? '?target_userid=' . urlencode($userid) : ''; ?>'">
  <form method="get" action="removeCrossedItems.php" style="display:inline-block;">
    <input type="submit" value="Remove Crossed Off Items" class="buttonstyle">
  </form>
</center>

</td>
</tr>
</table>

<div id="infoModal" class="info-modal">
  <div class="info-modal-content">
    <span class="close-info-modal">&times;</span>
    <p id="infoModalText"></p>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var csrfToken = <?php echo json_encode($csrf_token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    if (typeof Sortable !== 'undefined') {
        var lists = document.querySelectorAll('.sortable-list');
        lists.forEach(function (list) {
            new Sortable(list, {
                group: 'shared',
                animation: 150,
                handle: '.drag-handle',
                onAdd: function (evt) {
                    var placeholder = evt.to.querySelector('.empty-category-placeholder');
                    if (placeholder) {
                        placeholder.remove();
                    }
                },
                onEnd: function (evt) {
                    var itemEl = evt.item;
                    var itemId = itemEl.dataset.itemId;
                    var fromCategoryId = evt.from.dataset.categoryId;
                    var toCategoryId = evt.to.dataset.categoryId;
                    var newIndex = evt.newIndex;

                    $.ajax({
                        url: 'updateItemOrder.php',
                        type: 'POST',
                        data: {
                            csrf_token: csrfToken,
                            itemId: itemId,
                            fromCategoryId: fromCategoryId,
                            toCategoryId: toCategoryId,
                            newIndex: newIndex
                        },
                        success: function() {
                            if (typeof showToast === 'function') {
                                showToast('Item order updated');
                            }
                        },
                        error: function() {
                            if (typeof showToast === 'function') {
                                showToast('Error updating item order', 'error');
                            }
                        }
                    });
                }
            });
        });

        var catList = document.getElementById('categorySortableList');
        if (catList) {
            new Sortable(catList, {
                animation: 150,
                handle: '.category-drag-handle',
                draggable: '.wishlist-category-block',
                onEnd: function () {
                    var categoryIds = [];
                    catList.querySelectorAll('.wishlist-category-block').forEach(function(el) {
                        var cid = el.getAttribute('data-category-id');
                        if (cid) {
                            categoryIds.push(cid);
                        }
                    });

                    $.ajax({
                        url: 'updateCategoryOrder.php',
                        type: 'POST',
                        data: {
                            csrf_token: csrfToken,
                            categoryIds: categoryIds
                        },
                        success: function() {
                            if (typeof showToast === 'function') {
                                showToast('Category order updated');
                            }
                        },
                        error: function() {
                            if (typeof showToast === 'function') {
                                showToast('Error updating category order', 'error');
                            }
                        }
                    });
                }
            });
        }
    }

    var infoModal = document.getElementById("infoModal");
    var infoModalText = document.getElementById("infoModalText");
    var closeInfoModal = document.querySelector(".close-info-modal");

    $('.info-icon').on('click', function() {
        var createDate = $(this).data('create-date') || '';
        if (infoModalText) {
            infoModalText.textContent = "This item was added on " + createDate;
        }
        if (infoModal) {
            infoModal.style.display = "block";
        }
    });

    if (closeInfoModal && infoModal) {
        closeInfoModal.addEventListener("click", function() {
            infoModal.style.display = "none";
        });
    }

    window.addEventListener("click", function(event) {
        if (infoModal && event.target === infoModal) {
            infoModal.style.display = "none";
        }
    });
});
</script>
</body>
</html>
