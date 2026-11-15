<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: updateAccount.php");
    exit;
}

verifyCsrfOrDie(true);

$actual_userid = $_SESSION["userid"];
$requested_userid = trim((string)($_POST["target_userid"] ?? ($_SESSION["euserid"] ?? "")));
if ($requested_userid !== "" && $requested_userid !== $_SESSION["userid"]) {
    if (!empty($_SESSION["admin"]) && $_SESSION["admin"] == 1) {
        $actual_userid = $requested_userid;
    } else {
        $check_perm = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($check_perm, "ss", $requested_userid, $_SESSION["userid"]);
        mysqli_stmt_execute($check_perm);
        $res_perm = mysqli_stmt_get_result($check_perm);
        if (($prow = mysqli_fetch_assoc($res_perm)) && $prow["allowEdit"] == '1') {
            $actual_userid = $requested_userid;
        } else {
            http_response_code(403);
            die("Unauthorized: You do not have permission to edit this account.");
        }
    }
}

$firstname = trim((string)($_POST["firstname"] ?? ''));
$lastname = trim((string)($_POST["lastname"] ?? ''));
$suffix = trim((string)($_POST["suffix"] ?? ''));
$email_in = trim((string)($_POST["email"] ?? ''));
$street = trim((string)($_POST["street"] ?? ''));
$city = trim((string)($_POST["city"] ?? ''));
$state = trim((string)($_POST["state"] ?? ''));
$zip = substr(trim((string)($_POST["zip"] ?? '')), 0, 20);
$phone = trim((string)($_POST["phone"] ?? ''));
$mobilephone = trim((string)($_POST["mobilephone"] ?? ''));
$bmonth_in = trim((string)($_POST["bmonth"] ?? ''));
$bday_in = trim((string)($_POST["bday"] ?? ''));
$url_in = trim((string)($_POST["url"] ?? ''));

if ($firstname === '' || $lastname === '') {
    header("Location: editInfo.php?target_userid=" . urlencode($actual_userid) . "&error=missing_name");
    exit;
}

$month_map = [
    "January" => 1, "February" => 2, "March" => 3, "April" => 4,
    "May" => 5, "June" => 6, "July" => 7, "August" => 8,
    "September" => 9, "October" => 10, "November" => 11, "December" => 12
];

if ($bmonth_in === '' && $bday_in === '') {
    $bmonth = '';
    $bday = null;
} elseif (!isset($month_map[$bmonth_in]) || !ctype_digit($bday_in) || !checkdate($month_map[$bmonth_in], (int)$bday_in, 2024)) {
    header("Location: editInfo.php?target_userid=" . urlencode($actual_userid) . "&error=invalid_birthday");
    exit;
} else {
    $bmonth = $bmonth_in;
    $bday = (int)$bday_in;
}

// Validate optional email (preserve blank if cleared, or valid email)
$email = ($email_in !== '' && filter_var($email_in, FILTER_VALIDATE_EMAIL)) ? $email_in : '';

// Validate optional URL
$url = '';
if ($url_in !== '') {
    if (!preg_match('#^https?://#i', $url_in)) {
        $url_in = 'https://' . $url_in;
    }
    $url = sanitizeHttpUrl($url_in, false);
}

$stmt = mysqli_prepare($link, "UPDATE people SET firstname = ?, lastname = ?, suffix = ?, street = ?, city = ?, state = ?, zip = ?, email = ?, phone = ?, mobilephone = ?, url = ?, bmonth = ?, bday = ?, lastModDate = NOW() WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "ssssssssssssis", $firstname, $lastname, $suffix, $street, $city, $state, $zip, $email, $phone, $mobilephone, $url, $bmonth, $bday, $actual_userid);
mysqli_stmt_execute($stmt);

if ($actual_userid === $_SESSION["userid"]) {
    $_SESSION["fullname"] = trim($firstname . " " . $lastname . " " . $suffix);
}

header("Location: " . getFullPath("updateAccount.php?target_userid=" . urlencode($actual_userid)));
exit;
