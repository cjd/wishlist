<?php
include "../funcLib.php";

if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    header("Location: ../home.php");
    exit;
}

$pending_requests_query = "SELECT ar.*, p_requester.firstname AS requester_firstname, p_requester.lastname AS requester_lastname, p_requester.suffix AS requester_suffix, p_target.firstname AS target_firstname, p_target.lastname AS target_lastname, p_target.suffix AS target_suffix FROM accessRequests ar JOIN people p_requester ON ar.requesterId = p_requester.userid JOIN people p_target ON ar.targetId = p_target.userid WHERE ar.status = 'pending' ORDER BY ar.requestDate DESC";
$pending_requests_result = mysqli_query($link, $pending_requests_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title>Admin - Access Requests</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|../admin.php:Admin|:Access Requests", true); ?>
<center>
<h2>Pending Access Requests</h2>
<table border="1" class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>Requester</th>
        <th>Target</th>
        <th>Date</th>
        <th>Actions</th>
    </tr>
    <?php if ($pending_requests_result && mysqli_num_rows($pending_requests_result) > 0): ?>
      <?php while ($request = mysqli_fetch_assoc($pending_requests_result)): ?>
      <?php
        $reqName = trim($request['requester_firstname'] . ' ' . $request['requester_lastname'] . ' ' . ($request['requester_suffix'] ?? ''));
        $tgtName = trim($request['target_firstname'] . ' ' . $request['target_lastname'] . ' ' . ($request['target_suffix'] ?? ''));
      ?>
      <tr class="viewpurchases1">
          <td><?php echo escapeText($reqName); ?> (<?php echo escapeText($request['requesterId']); ?>)</td>
          <td><?php echo escapeText($tgtName); ?> (<?php echo escapeText($request['targetId']); ?>)</td>
          <td><?php echo escapeText(parseDate($request['requestDate'])); ?></td>
          <td>
              <form action="../handleAccessRequest.php" method="post" style="display:inline-block;">
                  <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
                  <input type="hidden" name="return_to" value="admin">
                  <input type="hidden" name="requestId" value="<?php echo (int)$request['id']; ?>">
                  <button type="submit" name="decision" value="approve_readonly" class="actionButton">Approve (Read-Only)</button>
                  <button type="submit" name="decision" value="approve_contact" class="actionButton">Approve (Full Purchasing + Contact)</button>
                  <button type="submit" name="decision" value="deny" class="actionButtonRed">Deny</button>
              </form>
          </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr class="viewpurchases1">
          <td colspan="4" align="center">No pending access requests.</td>
      </tr>
    <?php endif; ?>
</table>
</center>
</td>
</tr>
</table>
</body>
</html>
