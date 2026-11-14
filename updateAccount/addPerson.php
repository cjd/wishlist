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

if (!empty($_REQUEST["person"])) {
    $person = $_REQUEST["person"];
    $check_stmt = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ? AND viewer = ?");
    mysqli_stmt_bind_param($check_stmt, "ss", $userid, $person);
    mysqli_stmt_execute($check_stmt);
    $check_res = mysqli_stmt_get_result($check_stmt);
    if (mysqli_num_rows($check_res) == 0) {
        $stmt = mysqli_prepare($link, "INSERT INTO viewList (viewContactInfo, readOnly, allowEdit, pid, viewer, lastViewDate) VALUES (0, 1, 0, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt, "ss", $userid, $person);
        mysqli_stmt_execute($stmt);
    }
}

header("Location: " . getFullPath("updateAccount.php"));
exit;
