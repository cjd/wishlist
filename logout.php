<?php
$ignoreSession = true;
$doingSetup = true;
include "funcLib.php";

if (session_status() === PHP_SESSION_NONE) {
  session_name("WishListSite");
  session_start();
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params["path"],
    $params["domain"],
    $params["secure"],
    $params["httponly"]
  );
}

session_destroy();

if (!headers_sent()) {
  header('Clear-Site-Data: "cache"');
}

$target = (!empty($base_url) ? $base_url : '') . "/login.php?loggedout=1";
header("Location: " . $target);
exit;
