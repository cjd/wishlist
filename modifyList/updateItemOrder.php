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

include "../funcLib.php";

if (isset($_POST['itemId']) && isset($_POST['toCategoryId']) && isset($_POST['newIndex'])) {
    $itemId = $_POST['itemId'];
    $fromCategoryId = $_POST['fromCategoryId'];
    $toCategoryId = $_POST['toCategoryId'];
    $newIndex = $_POST['newIndex'];

    mysqli_begin_transaction($link);

    try {
        // Get the old sort order
        $query = "SELECT itemSortOrder FROM items WHERE iid = ?";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $itemId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $oldSortOrder = $row['itemSortOrder'];

        // Temporarily move the item out of the way
        $query = "UPDATE items SET itemSortOrder = -1 WHERE iid = ?";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "i", $itemId);
        mysqli_stmt_execute($stmt);

        // Shift items in the old category
        if ($fromCategoryId == $toCategoryId) {
            if ($newIndex > $oldSortOrder) {
                $query = "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE cid = ? AND itemSortOrder > ? AND itemSortOrder <= ?";
                $stmt = mysqli_prepare($link, $query);
                mysqli_stmt_bind_param($stmt, "iii", $fromCategoryId, $oldSortOrder, $newIndex);
                mysqli_stmt_execute($stmt);
            } else {
                $query = "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE cid = ? AND itemSortOrder >= ? AND itemSortOrder < ?";
                $stmt = mysqli_prepare($link, $query);
                mysqli_stmt_bind_param($stmt, "iii", $fromCategoryId, $newIndex, $oldSortOrder);
                mysqli_stmt_execute($stmt);
            }
        } else {
            $query = "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE cid = ? AND itemSortOrder > ?";
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "ii", $fromCategoryId, $oldSortOrder);
            mysqli_stmt_execute($stmt);

            // Shift items in the new category
            $query = "UPDATE items SET itemSortOrder = itemSortOrder + 1 WHERE cid = ? AND itemSortOrder >= ?";
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "ii", $toCategoryId, $newIndex);
            mysqli_stmt_execute($stmt);
        }

        // Place the item in its new position
        $query = "UPDATE items SET cid = ?, itemSortOrder = ? WHERE iid = ?";
        $stmt = mysqli_prepare($link, $query);
        mysqli_stmt_bind_param($stmt, "iii", $toCategoryId, $newIndex, $itemId);
        mysqli_stmt_execute($stmt);

        mysqli_commit($link);
        echo "Item order updated successfully.";
    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($link);
        echo "Error updating item order: " . $exception->getMessage();
    }
} else {
    echo "Invalid request.";
}
?>
