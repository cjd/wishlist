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

include "funcLib.php";

if (isset($_POST['requestId']) && isset($_POST['requesterId']) && isset($_POST['decision'])) {
    $requestId = $_POST['requestId'];
    $requesterId = $_POST['requesterId'];
    $decision = $_POST['decision'];
    
    // Get the targetId from the database
    $stmt_target = mysqli_prepare($link, "SELECT targetId FROM accessRequests WHERE id = ?");
    mysqli_stmt_bind_param($stmt_target, "i", $requestId);
    mysqli_stmt_execute($stmt_target);
    $result_target = mysqli_stmt_get_result($stmt_target);
    if ($row_target = mysqli_fetch_assoc($result_target)) {
        $targetId = $row_target['targetId'];
    } else {
        header("Location: home.php?error=invalid_request");
        exit();
    }

    // Security check: ensure the user is either the target or an admin
    if ($_SESSION['userid'] != $targetId && $_SESSION['admin'] != 1) {
        header("Location: home.php?error=unauthorized");
        exit();
    }

    mysqli_begin_transaction($link);

    try {
        if ($decision == 'approve_readonly') {
            $query = "INSERT INTO viewList (pid, viewer, readOnly, viewContactInfo, allowEdit, lastViewDate) VALUES (?, ?, 1, 0, 0, NOW())";
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "ss", $targetId, $requesterId);
            mysqli_stmt_execute($stmt);

            $query_update = "UPDATE accessRequests SET status = 'approved' WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $query_update);
            mysqli_stmt_bind_param($stmt_update, "i", $requestId);
            mysqli_stmt_execute($stmt_update);

        } elseif ($decision == 'approve_contact') {
            $query = "INSERT INTO viewList (pid, viewer, readOnly, viewContactInfo, allowEdit, lastViewDate) VALUES (?, ?, 0, 1, 0, NOW())";
            $stmt = mysqli_prepare($link, $query);
            mysqli_stmt_bind_param($stmt, "ss", $targetId, $requesterId);
            mysqli_stmt_execute($stmt);

            $query_update = "UPDATE accessRequests SET status = 'approved' WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $query_update);
            mysqli_stmt_bind_param($stmt_update, "i", $requestId);
            mysqli_stmt_execute($stmt_update);

        } elseif ($decision == 'deny') {
            $query_update = "UPDATE accessRequests SET status = 'denied' WHERE id = ?";
            $stmt_update = mysqli_prepare($link, $query_update);
            mysqli_stmt_bind_param($stmt_update, "i", $requestId);
            mysqli_stmt_execute($stmt_update);
        }

        mysqli_commit($link);
        if ($_SESSION['admin'] == 1) {
            header("Location: admin/accessRequests.php");
        } else {
            header("Location: home.php");
        }
        exit();

    } catch (mysqli_sql_exception $exception) {
        mysqli_rollback($link);
        // You can log the error message
        header("Location: home.php?error=1");
        exit();
    }
} else {
    header("Location: home.php");
    exit();
}
?>
