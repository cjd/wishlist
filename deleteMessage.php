<?php
include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages.php");
    exit;
}

verifyCsrfOrDie(true);

if (isset($_POST['message_id'], $_SESSION['userid'])) {
    $message_id = (int)$_POST['message_id'];
    $user_id = $_SESSION['userid'];
    $box = $_POST['box'] ?? 'inbox';

    if ($box === 'sent') {
        $stmt = mysqli_prepare($link, "UPDATE messages SET sender_deleted = 1 WHERE message_id = ? AND sender_id = ?");
        mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
        mysqli_stmt_execute($stmt);
    } else {
        $stmt = mysqli_prepare($link, "UPDATE messages SET recipient_deleted = 1 WHERE message_id = ? AND recipient_id = ?");
        mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
        mysqli_stmt_execute($stmt);
    }

    // Permanently remove once both sides deleted (or system message deleted by recipient)
    $cleanup = mysqli_prepare($link, "DELETE FROM messages WHERE message_id = ? AND recipient_deleted = 1 AND (sender_deleted = 1 OR sender_id IS NULL)");
    mysqli_stmt_bind_param($cleanup, "i", $message_id);
    mysqli_stmt_execute($cleanup);
}

header("Location: messages.php");
exit;
