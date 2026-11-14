<?php

include "funcLib.php";

if (session_status() === PHP_SESSION_NONE) {
    session_name("WishListSite");
    session_start();
}
session_destroy();

header("Location: " . getFullPath("login.php"));
exit;

?>