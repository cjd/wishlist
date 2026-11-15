<?php
include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

verifyCsrfOrDie(true);

$categoryIds = $_POST['categoryIds'] ?? [];
if (!is_array($categoryIds) || empty($categoryIds)) {
    http_response_code(400);
    echo "Invalid data";
    exit;
}

mysqli_begin_transaction($link);
try {
    $stmt_check = mysqli_prepare($link, "SELECT cid FROM categories WHERE cid = ? AND userid = ? AND catSortOrder > -1 FOR UPDATE");
    $stmt_update = mysqli_prepare($link, "UPDATE categories SET catSortOrder = ? WHERE cid = ? AND userid = ?");

    $order = 0;
    $seen = [];
    foreach ($categoryIds as $cidRaw) {
        $cid = (int)$cidRaw;
        if ($cid <= 0 || isset($seen[$cid])) {
            continue;
        }
        $seen[$cid] = true;

        mysqli_stmt_bind_param($stmt_check, "is", $cid, $userid);
        mysqli_stmt_execute($stmt_check);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_check))) {
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
    error_log("updateCategoryOrder failed: " . $e->getMessage());
    http_response_code(500);
    echo "Error updating category order";
}
