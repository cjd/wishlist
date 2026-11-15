<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . getFullPath("../home.php"));
    exit;
}

verifyCsrfOrDie(true);

$actorUserid = $_SESSION["userid"];
$commentId = (int)($_POST["commentId"] ?? 0);
$action = trim((string)($_POST["action"] ?? "claim"));
$recipFallback = trim((string)($_POST["recip"] ?? ""));
$nameFallback = trim((string)($_POST["name"] ?? ""));
$returnTo = trim((string)($_POST["return_to"] ?? ""));

if ($commentId <= 0 || ($action !== "claim" && $action !== "unclaim")) {
    header("Location: " . getFullPath("../home.php"));
    exit;
}

$targetOwner = $recipFallback;
$receiverName = $nameFallback;
$notifyCoBuyers = [];
$notifyGiftTitle = "Secret Gift Idea";
$notifySplitText = "";

mysqli_begin_transaction($link);
try {
    $stmt_lock = mysqli_prepare($link, "SELECT commentId, userid, comment, is_suggestion, suggest_title, claimed_by FROM comments WHERE commentId = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_lock, "i", $commentId);
    mysqli_stmt_execute($stmt_lock);
    $crow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_lock));

    if (!$crow) {
        mysqli_rollback($link);
        header("Location: " . getFullPath("../home.php"));
        exit;
    }

    $targetOwner = (string)$crow["userid"];
    // List owners can never claim or unclaim secret suggestions on their own list
    if ($targetOwner === "" || $targetOwner === $actorUserid) {
        mysqli_rollback($link);
        http_response_code(403);
        die("Unauthorized.");
    }

    $isCard = (!empty($crow["is_suggestion"])
        || strpos((string)$crow["comment"], "<div class='secret-suggestion-card'>") === 0
        || strpos((string)$crow["comment"], '<div class="secret-suggestion-card">') === 0);
    if (!$isCard) {
        mysqli_rollback($link);
        http_response_code(400);
        die("Only Secret Gift Ideas can be claimed.");
    }

    $stmt_rec = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_rec, "s", $targetOwner);
    mysqli_stmt_execute($stmt_rec);
    if ($r_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rec))) {
        $receiverName = trim($r_row["firstname"] . " " . $r_row["lastname"] . " " . ($r_row["suffix"] ?? ""));
    }

    if ($action === "claim") {
        $access = canViewList($link, $targetOwner, $actorUserid);
        if (!$access['allowed'] || $access['readOnly']) {
            mysqli_rollback($link);
            http_response_code(403);
            die("Unauthorized: You need purchasing access to claim a gift idea.");
        }

        if (!empty($crow["claimed_by"])) {
            // Already claimed by someone else
            mysqli_rollback($link);
            header("Location: " . getFullPath("viewList.php?recip=" . urlencode($targetOwner) . "&name=" . urlencode($receiverName)));
            exit;
        }

        $selectedUsers = $_POST["split_users"] ?? [];
        if (!is_array($selectedUsers)) {
            $selectedUsers = $selectedUsers !== '' ? [$selectedUsers] : [];
        }

        $stmt_u = mysqli_prepare($link, "SELECT userid, firstname, lastname, suffix FROM people WHERE userid = ?");
        $userNames = [];
        $userIdList = [];
        foreach (array_unique($selectedUsers) as $uId) {
            $cleanUid = trim((string)$uId);
            if ($cleanUid === "" || $cleanUid === $targetOwner || $cleanUid === $actorUserid) {
                continue;
            }
            mysqli_stmt_bind_param($stmt_u, "s", $cleanUid);
            mysqli_stmt_execute($stmt_u);
            if ($u_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u))) {
                $userNames[] = trim($u_row["firstname"] . " " . $u_row["lastname"] . " " . ($u_row["suffix"] ?? ""));
                $userIdList[] = $u_row["userid"];
            }
        }

        $extraNote = trim((string)($_POST["split_note"] ?? ""));
        $splitWithParts = $userNames;
        if ($extraNote !== "") {
            $splitWithParts[] = $extraNote;
        }
        $splitWith = implode(", ", $splitWithParts);
        $splitUsersStr = implode(",", $userIdList);

        $stmt_upd = mysqli_prepare($link, "UPDATE comments SET is_suggestion = 1, claimed_by = ?, claimed_date = NOW(), split_users = ?, split_with = ? WHERE commentId = ?");
        mysqli_stmt_bind_param($stmt_upd, "sssi", $actorUserid, $splitUsersStr, $splitWith, $commentId);
        mysqli_stmt_execute($stmt_upd);

        $titleText = trim((string)($crow["suggest_title"] ?? ""));
        if ($titleText === "" && preg_match("#<div class=['\"]suggestion-title['\"]><b>(.*?)</b></div>#si", (string)$crow["comment"], $tm)) {
            $titleText = html_entity_decode(strip_tags($tm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if ($titleText !== "") {
            $notifyGiftTitle = $titleText;
        }
        $notifyCoBuyers = $userIdList;
        $notifySplitText = $splitWith;
    } else {
        // action === 'unclaim'
        $claimedBy = trim((string)($crow["claimed_by"] ?? ""));
        $canUnclaim = ($claimedBy !== "" && $claimedBy === $actorUserid);
        if (!$canUnclaim && $claimedBy !== "") {
            $stmt_proxy = mysqli_prepare($link, "SELECT 1 FROM viewList WHERE pid = ? AND viewer = ? AND allowEdit = 1 LIMIT 1");
            mysqli_stmt_bind_param($stmt_proxy, "ss", $claimedBy, $actorUserid);
            mysqli_stmt_execute($stmt_proxy);
            if (mysqli_fetch_row(mysqli_stmt_get_result($stmt_proxy))) {
                $canUnclaim = true;
            }
        }

        if (!$canUnclaim) {
            mysqli_rollback($link);
            http_response_code(403);
            die("Unauthorized: Only the buyer who claimed this gift idea can undo the claim.");
        }

        $stmt_upd = mysqli_prepare($link, "UPDATE comments SET claimed_by = NULL, claimed_date = NULL, split_users = '', split_with = '' WHERE commentId = ?");
        mysqli_stmt_bind_param($stmt_upd, "i", $commentId);
        mysqli_stmt_execute($stmt_upd);
    }

    mysqli_commit($link);
} catch (mysqli_sql_exception $e) {
    mysqli_rollback($link);
    error_log("claimSuggestion transaction failed: " . $e->getMessage());
}

if (!empty($notifyCoBuyers)) {
    $splitLabel = ($notifySplitText !== "")
        ? " <font color=purple><b>(Group gift with " . escapeText($notifySplitText) . ")</b></font>"
        : "";
    $cbSubject = "Secret Group Gift: " . $receiverName . "'s WishList";
    $cbBody = "<font color=indianred><b>" . escapeText($_SESSION["fullname"]) . "</b></font> has claimed a Secret Gift Idea on <b>" . escapeText($receiverName) . "'s</b> WishList and added you as a co-contributor:<p><dir><font color=red>Claimed Secret Gift:</font> <i>" . escapeText($notifyGiftTitle) . "</i>" . $splitLabel . "</dir>";
    foreach ($notifyCoBuyers as $cbUid) {
        sendEmail($cbUid, $actorUserid, $cbSubject, $cbBody, 0);
    }
}

if ($returnTo === "viewPurchases") {
    header("Location: " . getFullPath("../viewPurchases.php"));
} else {
    header("Location: " . getFullPath("viewList.php?recip=" . urlencode($targetOwner) . "&name=" . urlencode($receiverName)));
}
exit;
