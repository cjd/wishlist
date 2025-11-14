<?php
include "funcLib.php";

if (isset($_POST['message_id'])) {
    $message_id = $_POST['message_id'];

    $stmt = mysqli_prepare($link, "UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "is", $message_id, $_SESSION['userid']);
    mysqli_stmt_execute($stmt);

    // Remove the message from the session
    if (isset($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $key => $message) {
            if ($message['message_id'] == $message_id) {
                unset($_SESSION['messages'][$key]);
                break;
            }
        }
    }
}

header("Location: home.php");
exit;
?>
