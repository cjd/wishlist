<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: home.php");
    exit;
}

verifyCsrfOrDie(true);

$loginUserid = $_SESSION['userid'] ?? '';
$targetVer = defined('WHATS_NEW_VERSION') ? (int)WHATS_NEW_VERSION : 1;

if ($loginUserid !== '') {
    try {
        $stmt = mysqli_prepare($link, "UPDATE people SET whatsNewSeenVersion = GREATEST(whatsNewSeenVersion, ?) WHERE userid = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "is", $targetVer, $loginUserid);
            mysqli_stmt_execute($stmt);
        }
    } catch (Throwable $e) {
        error_log("dismissWhatsNew update failed: " . $e->getMessage());
    }
}

$isAjax = (!empty($_POST['ajax']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest');
if ($isAjax) {
    header("Content-Type: application/json; charset=UTF-8");
    echo json_encode(['ok' => true, 'version' => $targetVer]);
    exit;
}

if (!empty($_POST['redirect_help'])) {
    $anchor = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['anchor'] ?? ''));
    $dest = "help.php" . ($anchor !== '' ? ("#" . $anchor) : "");
    header("Location: " . $dest);
    exit;
}

header("Location: home.php");
exit;
