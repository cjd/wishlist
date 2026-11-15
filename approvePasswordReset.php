<?php
include "funcLib.php";

$reqId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
$rawToken = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
$currentUser = $_SESSION['userid'];
$isAdmin = !empty($_SESSION['admin']) && $_SESSION['admin'] == 1;

$error = "";
$newPassword = "";
$resetRow = null;

if ($reqId <= 0 || $rawToken === '') {
  $error = "Invalid password reset approval link.";
} else {
  $stmt = mysqli_prepare(
    $link,
    "SELECT r.*, p.firstname, p.lastname
     FROM passwordResetRequests r
     JOIN people p ON p.userid = r.target_userid
     WHERE r.id = ?"
  );
  mysqli_stmt_bind_param($stmt, "i", $reqId);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);
  $resetRow = mysqli_fetch_assoc($rs);

  if (!$resetRow) {
    $error = "Password reset request not found.";
  } elseif ($resetRow['approver_userid'] !== $currentUser && !$isAdmin) {
    $error = "You are not authorized to approve this password reset request.";
  } elseif ($resetRow['status'] !== 'pending') {
    $error = "This password reset request has already been " . $resetRow['status'] . ".";
  } elseif (strtotime($resetRow['expires_at']) < time()) {
    $stmt_exp = mysqli_prepare($link, "UPDATE passwordResetRequests SET status = 'expired' WHERE id = ?");
    mysqli_stmt_bind_param($stmt_exp, "i", $reqId);
    mysqli_stmt_execute($stmt_exp);
    $error = "This password reset request has expired.";
  } elseif (!hash_equals($resetRow['token_hash'], hash('sha256', $rawToken))) {
    $error = "Invalid security token for this password reset request.";
  }
}

if ($error === "" && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decision'])) {
  verifyCsrfOrDie(true);
  $decision = $_POST['decision'];

  if ($decision === 'deny') {
    $stmt_d = mysqli_prepare($link, "UPDATE passwordResetRequests SET status = 'denied' WHERE id = ? AND status = 'pending'");
    mysqli_stmt_bind_param($stmt_d, "i", $reqId);
    mysqli_stmt_execute($stmt_d);
    header("Location: messages.php");
    exit;
  } elseif ($decision === 'approve') {
    $newPassword = generateRandomPassword(10);
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    mysqli_begin_transaction($link);
    try {
      $stmt_u = mysqli_prepare($link, "UPDATE passwordResetRequests SET status = 'approved' WHERE id = ? AND status = 'pending'");
      mysqli_stmt_bind_param($stmt_u, "i", $reqId);
      mysqli_stmt_execute($stmt_u);

      if (mysqli_stmt_affected_rows($stmt_u) !== 1) {
        throw new Exception("Request was already processed.");
      }

      $stmt_pw = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
      mysqli_stmt_bind_param($stmt_pw, "ss", $newHash, $resetRow['target_userid']);
      mysqli_stmt_execute($stmt_pw);

      clearRateLimitAttempts($link, '', $resetRow['target_userid'], 'login');

      $targetFull = trim($resetRow['firstname'] . ' ' . $resetRow['lastname']);
      $confirmSubject = "Temporary Password for " . $targetFull;
      $confirmBody = "You approved the password reset for <b>" . escapeText($targetFull) . "</b> (" . escapeText($resetRow['target_userid']) . ").<p>" .
        "Their temporary password is: <b>" . escapeText($newPassword) . "</b><p>" .
        "Please share this temporary password with them and remind them to change it under <b>Update Your Account &gt; Change Password</b> after signing in.";
      sendEmail($currentUser, "", $confirmSubject, $confirmBody, 0);

      mysqli_commit($link);
    } catch (Throwable $e) {
      mysqli_rollback($link);
      $error = "Could not complete password reset: " . $e->getMessage();
      $newPassword = "";
    }
  }
}
?>
<!DOCTYPE html>
<HTML>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
<title>Approve Password Reset</title>
</head>
<BODY>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|messages.php:Messages|:Approve Password Reset", true); ?>
<center>
<div style="max-width: 560px; margin-top: 24px; padding: 16px;">
  <?php if ($error !== ""): ?>
    <h3 style="color: #c62828;"><?php echo escapeText($error); ?></h3>
    <p><a href="messages.php" class="actionButton">Back to Messages</a></p>

  <?php elseif ($newPassword !== ""): ?>
    <h2>Password Reset Approved</h2>
    <p>
      A temporary password has been set for
      <b><?php echo escapeText($resetRow['firstname'] . ' ' . $resetRow['lastname']); ?></b>
      (<code><?php echo escapeText($resetRow['target_userid']); ?></code>):
    </p>
    <div class="roundedbox" style="padding: 14px; margin: 16px 0; font-size: 1.25em;">
      <code><b><?php echo escapeText($newPassword); ?></b></code>
    </div>
    <p>Please pass this temporary password to them so they can sign in and set a new password under <b>Update Your Account &gt; Change Password</b>. A copy has also been saved in your Messages.</p>
    <p><a href="messages.php" class="buttonstyle">Return to Messages</a></p>

  <?php else: ?>
    <h2>Confirm Password Reset</h2>
    <p>
      Did <b><?php echo escapeText($resetRow['firstname'] . ' ' . $resetRow['lastname']); ?></b>
      (<code><?php echo escapeText($resetRow['target_userid']); ?></code>) ask you to help reset their WishList password?
    </p>
    <form method="post" action="approvePasswordReset.php">
      <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
      <input type="hidden" name="id" value="<?php echo (int)$reqId; ?>">
      <input type="hidden" name="token" value="<?php echo escapeText($rawToken); ?>">
      <button type="submit" name="decision" value="approve" class="buttonstyle">Confirm &amp; Generate Temporary Password</button>
      <button type="submit" name="decision" value="deny" class="actionButtonRed" style="padding: 8px 14px;">Deny Request</button>
    </form>
  <?php endif; ?>
</div>
</center>
</td>
</tr>
</table>
</BODY>
</HTML>
