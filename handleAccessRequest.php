<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: home.php");
    exit();
}

verifyCsrfOrDie(true);

$requestId = isset($_POST['requestId']) ? (int)$_POST['requestId'] : 0;
$decision = $_POST['decision'] ?? '';
$returnTo = ($_POST['return_to'] ?? '') === 'admin' && !empty($_SESSION['admin']) && $_SESSION['admin'] == 1
    ? 'admin/accessRequests.php'
    : 'home.php';

if ($requestId <= 0 || !in_array($decision, ['approve_readonly', 'approve_contact', 'approve_reciprocal', 'deny'], true)) {
    header("Location: " . $returnTo . "?error=invalid_request");
    exit();
}

$reciprocalSent = false;
mysqli_begin_transaction($link);

try {
    $stmt_req = mysqli_prepare($link, "SELECT requesterId, targetId, status FROM accessRequests WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($stmt_req, "i", $requestId);
    mysqli_stmt_execute($stmt_req);
    $res_req = mysqli_stmt_get_result($stmt_req);
    $row_req = mysqli_fetch_assoc($res_req);

    if (!$row_req) {
        mysqli_rollback($link);
        header("Location: " . $returnTo . "?error=invalid_request");
        exit();
    }

    $requesterId = $row_req['requesterId'];
    $targetId = $row_req['targetId'];

    // Ensure the user is either the target list owner or an admin
    if ($_SESSION['userid'] !== $targetId && (empty($_SESSION['admin']) || $_SESSION['admin'] != 1)) {
        mysqli_rollback($link);
        header("Location: home.php?error=unauthorized");
        exit();
    }

    if ($row_req['status'] !== 'pending') {
        mysqli_rollback($link);
        header("Location: " . $returnTo);
        exit();
    }

    if ($decision === 'approve_readonly') {
        $stmt = mysqli_prepare($link, "INSERT INTO viewList (pid, viewer, readOnly, viewContactInfo, allowEdit, lastViewDate) VALUES (?, ?, 1, 1, 0, NOW()) ON DUPLICATE KEY UPDATE readOnly = 1, viewContactInfo = 1, lastViewDate = NOW()");
        mysqli_stmt_bind_param($stmt, "ss", $targetId, $requesterId);
        mysqli_stmt_execute($stmt);

        $stmt_update = mysqli_prepare($link, "UPDATE accessRequests SET status = 'approved', notified = 0 WHERE id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt_update, "i", $requestId);
        mysqli_stmt_execute($stmt_update);
    } elseif ($decision === 'approve_contact' || $decision === 'approve_reciprocal') {
        $stmt = mysqli_prepare($link, "INSERT INTO viewList (pid, viewer, readOnly, viewContactInfo, allowEdit, lastViewDate) VALUES (?, ?, 0, 1, 0, NOW()) ON DUPLICATE KEY UPDATE readOnly = 0, viewContactInfo = 1, lastViewDate = NOW()");
        mysqli_stmt_bind_param($stmt, "ss", $targetId, $requesterId);
        mysqli_stmt_execute($stmt);

        $stmt_update = mysqli_prepare($link, "UPDATE accessRequests SET status = 'approved', notified = 0 WHERE id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt_update, "i", $requestId);
        mysqli_stmt_execute($stmt_update);

        if ($decision === 'approve_reciprocal' && $targetId !== $requesterId) {
            $stmt_has = mysqli_prepare($link, "SELECT 1 FROM viewList WHERE pid = ? AND viewer = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_has, "ss", $requesterId, $targetId);
            mysqli_stmt_execute($stmt_has);
            if (!mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_has))) {
                $stmt_rev = mysqli_prepare($link, "SELECT id, status FROM accessRequests WHERE requesterId = ? AND targetId = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                mysqli_stmt_bind_param($stmt_rev, "ss", $targetId, $requesterId);
                mysqli_stmt_execute($stmt_rev);
                $existingRev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rev));

                if ($existingRev && $existingRev['status'] === 'pending') {
                    $reciprocalSent = true;
                } elseif ($existingRev) {
                    $revId = (int)$existingRev['id'];
                    $stmt_re = mysqli_prepare($link, "UPDATE accessRequests SET status = 'pending', requestDate = NOW(), notified = 0 WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_re, "i", $revId);
                    mysqli_stmt_execute($stmt_re);
                    $reciprocalSent = true;
                } else {
                    $stmt_ins = mysqli_prepare($link, "INSERT INTO accessRequests (requesterId, targetId, status, notified) VALUES (?, ?, 'pending', 0)");
                    mysqli_stmt_bind_param($stmt_ins, "ss", $targetId, $requesterId);
                    mysqli_stmt_execute($stmt_ins);
                    $reciprocalSent = true;
                }
            }
        }
    } elseif ($decision === 'deny') {
        $stmt_update = mysqli_prepare($link, "UPDATE accessRequests SET status = 'denied', notified = 0 WHERE id = ? AND status = 'pending'");
        mysqli_stmt_bind_param($stmt_update, "i", $requestId);
        mysqli_stmt_execute($stmt_update);
    }

    mysqli_commit($link);
    $redirUrl = $returnTo . ($reciprocalSent ? '?reciprocal_sent=1' : '');
    header("Location: " . $redirUrl);
    exit();
} catch (mysqli_sql_exception $exception) {
    mysqli_rollback($link);
    error_log("handleAccessRequest failed: " . $exception->getMessage());
    header("Location: " . $returnTo . "?error=1");
    exit();
}
