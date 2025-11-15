<?php
session_name("WishListSite");
session_start();

if (!isset($_SESSION["userid"])) {
    header("Location: login.php");
    exit;
}

$userid = $_SESSION["userid"];
if (isset($_SESSION["euserid"])) {
    $userid = $_SESSION["euserid"];
}

include "funcLib.php";

if (isset($_POST['recipient_id']) && isset($_POST['subject']) && isset($_POST['body'])) {
    $recipient_id = $_POST['recipient_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    $sender_id = $_SESSION['userid'];

    sendEmail($recipient_id, $sender_id, $subject, $body, 0);

    header("Location: viewMessages.php");
    exit;
} else {
    // Redirect back to the messages page with an error
    header("Location: viewMessages.php?error=missing_parameters");
    exit;
}
?>