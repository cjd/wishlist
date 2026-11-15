<?php
include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: messages.php");
    exit;
}

verifyCsrfOrDie(true);

if (isset($_POST['message_id'], $_POST['status'], $_SESSION['userid'])) {
    $message_id = (int)$_POST['message_id'];
    $status = !empty($_POST['status']) ? 1 : 0;
    $user_id = $_SESSION['userid'];

    $stmt = mysqli_prepare($link, "UPDATE messages SET is_read = ? WHERE message_id = ? AND recipient_id = ?");
    mysqli_stmt_bind_param($stmt, "iis", $status, $message_id, $user_id);
    mysqli_stmt_execute($stmt);
}

header("Location: messages.php");
exit;
