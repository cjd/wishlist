<?php
include "funcLib.php";

verifyCsrfOrDie();

if (isset($_POST['message_id']) && isset($_SESSION['userid'])) {
    $message_id = intval($_POST['message_id']);
    $user_id = $_SESSION['userid'];

    // Soft-delete for recipient
    $stmt = mysqli_prepare($link, "UPDATE messages SET recipient_deleted = 1 WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
    mysqli_stmt_execute($stmt);

    // Delete permanently if sender also deleted or sender was system
    $cleanup = mysqli_prepare($link, "DELETE FROM messages WHERE message_id = ? AND recipient_deleted = 1 AND (sender_deleted = 1 OR sender_id IS NULL)");
    mysqli_stmt_bind_param($cleanup, "i", $message_id);
    mysqli_stmt_execute($cleanup);
}

// Redirect back to the messages page
header("Location: messages.php");
exit;
?>
