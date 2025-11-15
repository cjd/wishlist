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

if (isset($_POST['message_id']) && isset($_SESSION['userid'])) {
    $message_id = $_POST['message_id'];
    $user_id = $_SESSION['userid'];

    // Ensure the message belongs to the current user to prevent unauthorized modifications
    $stmt = mysqli_prepare($link, "DELETE FROM messages WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "is", $message_id, $user_id);
    mysqli_stmt_execute($stmt);
}

// Redirect back to the messages page
header("Location: viewMessages.php");
exit;
?>
