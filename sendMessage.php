<?php
include "funcLib.php";

if (isset($_POST['recipient_id']) && isset($_POST['subject']) && isset($_POST['body'])) {
    $recipient_ids = $_POST['recipient_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $sender_id = $_SESSION['userid'];

    $recipient_id_string = implode(',', $recipient_ids);

    sendEmail($recipient_id_string, $sender_id, $subject, $body, 0);

    header("Location: messages.php");
    exit;
} else {
    // Redirect back to the messages page with an error
    header("Location: messages.php?error=missing_parameters");
    exit;
}
?>
