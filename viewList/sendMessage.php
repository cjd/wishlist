<?php
include "../funcLib.php";

if (isset($_POST['recipient_id']) && isset($_POST['subject']) && isset($_POST['body'])) {
    $recipient_id = $_POST['recipient_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $sender_id = $_SESSION['userid'];

    sendEmail($recipient_id, $sender_id, $subject, $body, 0);

    echo "Message sent!";
} else {
    echo "Error: missing parameters.";
}
?>
