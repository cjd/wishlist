<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include "../funcLib.php";

verifyCsrfOrDie();

$actual_userid = $_SESSION["userid"];
$requested_userid = $_REQUEST["target_userid"] ?? ($_SESSION["euserid"] ?? "");
if (!empty($requested_userid) && $requested_userid != $_SESSION["userid"]) {
    if (isset($_SESSION["admin"]) && $_SESSION["admin"] == 1) {
        $actual_userid = $requested_userid;
    } else {
        $check_perm = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
        mysqli_stmt_bind_param($check_perm, "ss", $requested_userid, $_SESSION["userid"]);
        mysqli_stmt_execute($check_perm);
        $res_perm = mysqli_stmt_get_result($check_perm);
        if ($prow = mysqli_fetch_assoc($res_perm)) {
            if ($prow["allowEdit"] == '1') {
                $actual_userid = $requested_userid;
            } else {
                die("Unauthorized: You do not have permission to edit this account.");
            }
        } else {
            die("Unauthorized: You do not have permission to edit this account.");
        }
    }
}

$firstname= convertString($_REQUEST["firstname"] ?? '');
$lastname= convertString($_REQUEST["lastname"] ?? '');
$suffix= convertString($_REQUEST["suffix"] ?? '');
$street = convertString($_REQUEST["street"] ?? '');
$city = convertString($_REQUEST["city"] ?? '');
$state = convertString($_REQUEST["state"] ?? '');
$zip = intval($_REQUEST["zip"] ?? 0);
$phone = convertString($_REQUEST["phone"] ?? '');
$mobilephone = convertString($_REQUEST["mobilephone"] ?? '');
$email = '';
$bmonth = convertString($_REQUEST["bmonth"] ?? '');
$bday = intval($_REQUEST["bday"] ?? 1);
$url = convertString($_REQUEST["url"] ?? '');

$stmt = mysqli_prepare($link, "UPDATE people SET firstname = ?, lastname = ?, suffix = ?, street = ?, city = ?, state = ?, zip = ?, email = ?, phone = ?, mobilephone = ?, url = ?, bmonth = ?, bday = ? WHERE userid = ?");
mysqli_stmt_bind_param($stmt, "ssssssisssssis", $firstname, $lastname, $suffix, $street, $city, $state, $zip, $email, $phone, $mobilephone, $url, $bmonth, $bday, $actual_userid);
mysqli_stmt_execute($stmt);

header("Location: " . getFullPath("updateAccount.php?target_userid=" . urlencode($actual_userid)));
exit;
?>
