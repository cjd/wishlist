<?php
include "../funcLib.php";

if (!isset($_SESSION['admin']) || $_SESSION['admin'] != 1) {
    header("Location: ../home.php");
    exit;
}

$pending_requests_query = "SELECT ar.*, p_requester.firstname AS requester_firstname, p_requester.lastname AS requester_lastname, p_requester.suffix AS requester_suffix, p_target.firstname AS target_firstname, p_target.lastname AS target_lastname, p_target.suffix AS target_suffix FROM accessRequests ar JOIN people p_requester ON ar.requesterId = p_requester.userid JOIN people p_target ON ar.targetId = p_target.userid WHERE ar.status = 'pending' ORDER BY ar.requestDate DESC";
$pending_requests_result = mysqli_query($link, $pending_requests_query);
$pending_requests = [];
if ($pending_requests_result) {
    while ($r = mysqli_fetch_assoc($pending_requests_result)) {
        $pending_requests[] = $r;
    }
}
$pending_count = count($pending_requests);
$csrf_token = getCsrfToken();

$error_code = $_GET['error'] ?? '';
$alert_html = '';
if ($error_code === 'invalid_request') {
    $alert_html = 'Invalid or missing access request.';
} elseif ($error_code !== '') {
    $alert_html = 'Unable to process the access request. Please try again.';
}
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

<div class="admin-dashboard">

  <div class="admin-header-bar">
    <div class="admin-title-group">
      <h2>Pending Access Requests</h2>
      <div class="admin-stats-line">
        <?php echo (int)$pending_count; ?> pending <?php echo $pending_count === 1 ? 'request' : 'requests'; ?>
      </div>
    </div>
    <div class="admin-top-actions">
      <a href="../admin.php" class="admin-nav-btn">← Back to Admin</a>
    </div>
  </div>

  <?php if ($alert_html !== ''): ?>
    <div class="admin-alert admin-alert-error" role="status">
      <?php echo escapeText($alert_html); ?>
    </div>
  <?php endif; ?>

  <?php if ($pending_count > 0): ?>
    <div class="account-section-card">
      <div class="account-section-header">
        <span>Requests Awaiting Approval</span>
        <span class="admin-family-count"><?php echo (int)$pending_count; ?></span>
      </div>
      <table class="admin-family-table">
        <thead>
          <tr>
            <th>Requester</th>
            <th>Target List</th>
            <th>Requested</th>
            <th class="col-actions">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending_requests as $request): ?>
            <?php
              $reqName = trim($request['requester_firstname'] . ' ' . $request['requester_lastname'] . ' ' . ($request['requester_suffix'] ?? ''));
              if ($reqName === '') {
                  $reqName = $request['requesterId'];
              }
              $tgtName = trim($request['target_firstname'] . ' ' . $request['target_lastname'] . ' ' . ($request['target_suffix'] ?? ''));
              if ($tgtName === '') {
                  $tgtName = $request['targetId'];
              }
            ?>
            <tr>
              <td>
                <div class="admin-person-primary">
                  <span class="admin-mobile-label">Requester:</span>
                  <span class="admin-person-name"><?php echo escapeText($reqName); ?></span>
                  <span class="admin-userid-pill"><?php echo escapeText($request['requesterId']); ?></span>
                </div>
              </td>
              <td>
                <div class="admin-person-primary">
                  <span class="admin-mobile-label">Target:</span>
                  <span class="admin-person-name"><?php echo escapeText($tgtName); ?></span>
                  <span class="admin-userid-pill"><?php echo escapeText($request['targetId']); ?></span>
                </div>
              </td>
              <td>
                <span class="admin-last-login">
                  <span class="admin-mobile-label">Date:</span><?php echo escapeText(parseDate($request['requestDate'])); ?>
                </span>
              </td>
              <td class="col-actions">
                <form action="../handleAccessRequest.php" method="post" class="admin-row-actions" style="margin:0; flex-wrap:wrap; justify-content:flex-end;">
                  <input type="hidden" name="csrf_token" value="<?php echo escapeText($csrf_token); ?>">
                  <input type="hidden" name="return_to" value="admin">
                  <input type="hidden" name="requestId" value="<?php echo (int)$request['id']; ?>">
                  <button type="submit" name="decision" value="approve_contact" class="admin-nav-btn admin-nav-btn-primary admin-btn-sm">Approve (Full Purchasing)</button>
                  <button type="submit" name="decision" value="approve_readonly" class="admin-nav-btn admin-btn-sm">Approve (Read-Only)</button>
                  <button type="submit" name="decision" value="deny" class="admin-btn-danger admin-btn-sm">Deny</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <div class="admin-empty-search" style="display:block;">
      No pending access requests.
    </div>
  <?php endif; ?>

</div>
</td>
</tr>
</table>
</body>
</html>
