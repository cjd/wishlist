<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../home.php");
    exit;
}

verifyCsrfOrDie(true);

$buyerUserid = $_SESSION["userid"];
$receiverUserid = trim((string)($_POST["receiverUserid"] ?? ""));
$receiverName = trim((string)($_POST["receiverName"] ?? ""));

if ($receiverUserid === "" || $receiverUserid === $buyerUserid) {
    header("Location: ../home.php");
    exit;
}

$access = canViewList($link, $receiverUserid, $buyerUserid);
if (!$access['allowed'] || $access['readOnly']) {
    http_response_code(403);
    die("Unauthorized: You have read-only or no access to this wishlist.");
}

// Canonical recipient name
$stmt_rec = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_rec, "s", $receiverUserid);
mysqli_stmt_execute($stmt_rec);
if ($r_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rec))) {
    $receiverName = trim($r_row["firstname"] . " " . $r_row["lastname"] . " " . ($r_row["suffix"] ?? ""));
}

$recorded = [];

mysqli_begin_transaction($link);
try {
    $stmt_lock_item = mysqli_prepare($link, "SELECT i.iid, i.title, i.description, i.quantity, i.allowCheck, c.userid, c.catSortOrder FROM items i JOIN categories c ON i.cid = c.cid WHERE i.iid = ? FOR UPDATE");
    $stmt_sum_bought = mysqli_prepare($link, "SELECT COALESCE(SUM(quantity), 0) AS total_bought FROM purchaseHistory WHERE iid = ? FOR UPDATE");
    $stmt_u = mysqli_prepare($link, "SELECT userid, firstname, lastname, suffix FROM people WHERE userid = ?");
    $insert_stmt = mysqli_prepare($link, "INSERT INTO purchaseHistory (iid, userid, quantity, boughtDate, splitWith, splitUsers) VALUES (?, ?, ?, NOW(), ?, ?)");

    foreach ($_POST as $key => $value) {
        $prefix = substr((string)$key, 0, 3);
        if ($prefix !== "chk" && $prefix !== "sel") {
            continue;
        }
        $iid = (int)substr((string)$key, 3);
        if ($iid <= 0 || isset($recorded[$iid])) {
            continue;
        }

        $requestedQty = 0;
        if ($prefix === "chk" && $value === "on") {
            $requestedQty = 1;
        } elseif ($prefix === "sel" && is_numeric($value)) {
            $requestedQty = (int)$value;
        }
        if ($requestedQty <= 0) {
            continue;
        }

        mysqli_stmt_bind_param($stmt_lock_item, "i", $iid);
        mysqli_stmt_execute($stmt_lock_item);
        $itemRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lock_item));

        if (!$itemRow) {
            continue;
        }
        // Verify item belongs to target recipient, is in a visible category, and is purchasable
        if ($itemRow['userid'] !== $receiverUserid || (int)$itemRow['catSortOrder'] <= -1 || $itemRow['allowCheck'] !== 'true') {
            continue;
        }

        mysqli_stmt_bind_param($stmt_sum_bought, "i", $iid);
        mysqli_stmt_execute($stmt_sum_bought);
        $sumRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_sum_bought));
        $alreadyBought = (int)($sumRow['total_bought'] ?? 0);
        $remaining = (int)$itemRow['quantity'] - $alreadyBought;

        if ($remaining <= 0) {
            continue;
        }

        $bought = min($requestedQty, $remaining);

        $selectedUsers = $_POST["split_users_" . $iid] ?? [];
        if (!is_array($selectedUsers)) {
            $selectedUsers = $selectedUsers !== '' ? [$selectedUsers] : [];
        }

        $userNames = [];
        $userIdList = [];
        foreach (array_unique($selectedUsers) as $uId) {
            $cleanUid = trim((string)$uId);
            // Never allow splitting/notifying the gift recipient themselves or duplicating primary buyer
            if ($cleanUid === "" || $cleanUid === $receiverUserid || $cleanUid === $buyerUserid) {
                continue;
            }
            mysqli_stmt_bind_param($stmt_u, "s", $cleanUid);
            mysqli_stmt_execute($stmt_u);
            if ($u_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u))) {
                $userNames[] = trim($u_row["firstname"] . " " . $u_row["lastname"] . " " . ($u_row["suffix"] ?? ""));
                $userIdList[] = $u_row["userid"];
            }
        }

        $extraNote = trim((string)($_POST["split_note_" . $iid] ?? ($_POST["split_" . $iid] ?? "")));
        $splitWithParts = $userNames;
        if ($extraNote !== "") {
            $splitWithParts[] = $extraNote;
        }
        $splitWith = implode(", ", $splitWithParts);
        $splitUsersStr = implode(",", $userIdList);

        mysqli_stmt_bind_param($insert_stmt, "isiss", $iid, $buyerUserid, $bought, $splitWith, $splitUsersStr);
        mysqli_stmt_execute($insert_stmt);

        $recorded[$iid] = [
            'qty' => $bought,
            'split' => $splitWith,
            'splitUsers' => $userIdList,
            'title' => $itemRow['title'],
            'description' => $itemRow['description']
        ];
    }

    mysqli_commit($link);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    error_log("validate_purchase transaction failed: " . $e->getMessage());
    header("Location: " . getFullPath("viewList.php?recip=" . urlencode($receiverUserid) . "&name=" . urlencode($receiverName) . "&error=purchase_failed"));
    exit;
}

// Notify co-buyers only (solo purchases send no notifications)
if (!empty($recorded)) {
    $coBuyerMessages = [];
    foreach ($recorded as $pdata) {
        if (empty($pdata['splitUsers'])) {
            continue;
        }
        $splitText = ($pdata['split'] !== '')
            ? " <font color=purple><b>(Group gift with " . escapeText($pdata['split']) . ")</b></font>"
            : "";
        $itemLine = "<font color=red>Bought " . (int)$pdata['qty'] . " of</font> <i>" . escapeText($pdata['title']) . "</i>" . $splitText . " - " . escapeText($pdata['description']) . "<p>";

        foreach ($pdata['splitUsers'] as $cbUser) {
            $coBuyerMessages[$cbUser][] = $itemLine;
        }
    }

    foreach ($coBuyerMessages as $cbUser => $lines) {
        $cbSubject = "Group Gift: " . $receiverName . "'s WishList";
        $cbBody = "<font color=indianred><b>" . escapeText($_SESSION["fullname"]) . "</b></font> has added you as a co-contributor for a group gift on <b>" . escapeText($receiverName) . "'s</b> WishList:<p><dir>" . implode("", $lines) . "</dir>";
        sendEmail($cbUser, $buyerUserid, $cbSubject, $cbBody, 0);
    }
}

header("Location: " . getFullPath("viewList.php?recip=" . urlencode($receiverUserid) . "&name=" . urlencode($receiverName) . "&saved=1"));
exit;
