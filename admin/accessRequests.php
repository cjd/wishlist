<?php
include "../funcLib.php";

// Ensure user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    header("Location: ../home.php");
    exit;
}

// Fetch all pending access requests
$pending_requests_query = "SELECT ar.*, p_requester.firstname AS requester_firstname, p_requester.lastname AS requester_lastname, p_target.firstname AS target_firstname, p_target.lastname AS target_lastname FROM accessRequests ar JOIN people p_requester ON ar.requesterId = p_requester.userid JOIN people p_target ON ar.targetId = p_target.userid WHERE ar.status = 'pending'";
$pending_requests_result = mysqli_query($link, $pending_requests_query);

?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
<link rel=stylesheet href=../style.css type=text/css>
<link rel="manifest" href="/manifest.json">
<title>Admin - Access Requests</title>
<BODY>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|../admin.php:Admin|accessRequests.php:Access Requests", true); ?>
<center>
<h2>Pending Access Requests</h2>
<table border=1 class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>Requester</th>
        <th>Target</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php while ($request = mysqli_fetch_assoc($pending_requests_result)): ?>
    <tr class="viewpurchases1">
        <td><?php echo htmlspecialchars($request['requester_firstname'] . ' ' . $request['requester_lastname']); ?></td>
        <td><?php echo htmlspecialchars($request['target_firstname'] . ' ' . $request['target_lastname']); ?></td>
        <td><?php echo parseDate($request['requestDate']); ?></td>
        <td>
            <form action="../handleAccessRequest.php" method="post" style="display:inline-block;">
                <input type="hidden" name="requestId" value="<?php echo $request['id']; ?>">
                <input type="hidden" name="requesterId" value="<?php echo $request['requesterId']; ?>">
                <button type="submit" name="decision" value="approve_readonly" class="actionButton">Approve (Read-Only)</button>
                <button type="submit" name="decision" value="approve_contact" class="actionButton">Approve (with List Access)</button>
                <button type="submit" name="decision" value="deny" class="actionButtonRed">Deny</button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
</center>
</td>
</tr>
</table>
</BODY>
</HTML>
