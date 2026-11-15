<?php
include "../funcLib.php";

verifyCsrfOrDie();

$categoryIds = $_POST['categoryIds'] ?? [];
if (!is_array($categoryIds) || empty($categoryIds)) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

mysqli_begin_transaction($link);
try {
    $stmt_check = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ? AND catSortOrder > -1");
    $stmt_update = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE cid = ? AND userid = ?");

    $order = 0;
    foreach ($categoryIds as $cid) {
        $cid = intval($cid);
        // Verify ownership and that it's a reorderable category
        mysqli_stmt_bind_param($stmt_check, "is", $cid, $userid);
        mysqli_stmt_execute($stmt_check);
        $res = mysqli_stmt_get_result($stmt_check);
        if (mysqli_num_rows($res) > 0) {
            mysqli_stmt_bind_param($stmt_update, "iis", $order, $cid, $userid);
            mysqli_stmt_execute($stmt_update);
            $order++;
        }
    }

    $stmt_mod = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_mod, "s", $userid);
    mysqli_stmt_execute($stmt_mod);

    mysqli_commit($link);
    echo "OK";
} catch (Exception $e) {
    mysqli_rollback($link);
    http_response_code(500);
    echo "Error updating category order";
}
