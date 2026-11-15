<?php
include "funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: home.php");
    exit;
}

verifyCsrfOrDie(true);

if (isset($_POST['message_id']) && isset($_SESSION['userid'])) {
    $message_id = (int)$_POST['message_id'];
    $user_id = $_SESSION['userid'];

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

header("Location: home.php");
exit;
