<?php
include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: home.php");
    exit;
}

verifyCsrfOrDie(true);

$user_id = $_SESSION['userid'] ?? '';
if (isset($_POST['message_id']) && $user_id !== '') {
    $message_id = (int)$_POST['message_id'];

    $stmt = mysqli_prepare($link, "UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
    mysqli_stmt_execute($stmt);

    if (isset($_SESSION['messages']) && is_array($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $key => $message) {
            if ((int)$message['message_id'] === $message_id) {
                unset($_SESSION['messages'][$key]);
                break;
            }
        }
        if (empty($_SESSION['messages'])) {
            unset($_SESSION['messages']);
        }
    }
}

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (!empty($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

if ($isAjax) {
    $remaining = 0;
    if ($user_id !== '') {
        $stmt_rem = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM messages WHERE recipient_id = ? AND is_read = 0 AND recipient_deleted = 0");
        if ($stmt_rem) {
            mysqli_stmt_bind_param($stmt_rem, "s", $user_id);
            mysqli_stmt_execute($stmt_rem);
            $rrow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_rem));
            $remaining = (int)($rrow['cnt'] ?? 0);
        }
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'remaining' => $remaining]);
    exit;
}

header("Location: home.php");
exit;
