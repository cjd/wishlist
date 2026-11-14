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

$name = $_REQUEST["name"] ?? "";
$recip = $_REQUEST["recip"] ?? "";
$bad = intval($_REQUEST["commentId"] ?? 0);
$userid = $_SESSION["userid"];

if ($bad > 0) {
    if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1) {
        $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE commentId = ?");
        mysqli_stmt_bind_param($stmt, "i", $bad);
    } else {
        $stmt = mysqli_prepare($link, "DELETE FROM comments WHERE commentId = ? AND (comment_userid = ? OR userid = ?)");
        mysqli_stmt_bind_param($stmt, "iss", $bad, $userid, $userid);
    }
    mysqli_stmt_execute($stmt);
}

header("Location: " . getFullPath("viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name)));
exit;


?>
