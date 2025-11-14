<?php
include "funcLib.php";

if (isset($_POST['message_id']) && isset($_SESSION['userid'])) {
    $message_id = $_POST['message_id'];
    $user_id = $_SESSION['userid'];

    // Ensure the message belongs to the current user to prevent unauthorized modifications
    $stmt = mysqli_prepare($link, "UPDATE messages SET is_read = 1 WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
    mysqli_stmt_execute($stmt);

    // Remove the message from the session
    if (isset($_SESSION['messages'])) {
        foreach ($_SESSION['messages'] as $key => $message) {
            if ($message['message_id'] == $message_id) {
                unset($_SESSION['messages'][$key]);
                break;
            }
        }
        // If no messages are left, unset the session variable
        if (empty($_SESSION['messages'])) {
            unset($_SESSION['messages']);
        }
    }
}

// Redirect back to the home page
header("Location: home.php");
exit;
?>