<?php
$ignoreSession = true;
include "funcLib.php";

$userid = trim((string)($_POST["userid"] ?? $_GET["userid"] ?? ""));
$clientIp = getClientIp();
$statusMessage = "";
$isError = false;
$step = "enter_userid";
$eligibleContacts = [];
$targetDisplayName = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["requestReset"]) && $_POST["requestReset"] === "true") {
  verifyCsrfOrDie(true);
  $to = trim((string)($_POST["recipient"] ?? ""));

  $rl = checkRateLimit($link, $clientIp, $userid, 'reset', 5, 900);
  if ($rl['blocked']) {
    $mins = max(1, (int)ceil($rl['retry_after'] / 60));
    $statusMessage = "Too many password reset requests. Please wait {$mins} minute(s) before trying again.";
    $isError = true;
    $step = "done";
  } elseif ($userid === "" || $to === "" || $to === $userid) {
    $statusMessage = "Invalid user or recipient selected.";
    $isError = true;
    $step = "done";
  } else {
    recordRateLimitAttempt($link, $clientIp, $userid, 'reset');

    // Verify target user exists
    $stmt_t = mysqli_prepare($link, "SELECT userid, firstname, lastname FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt_t, "s", $userid);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    $targetRow = mysqli_fetch_assoc($res_t);

    // Verify recipient is someone the TARGET user authorized to view THEIR list (vl.pid = target AND vl.viewer = recipient), OR is a site admin
    $stmt_chk = mysqli_prepare(
      $link,
      "SELECT p.userid
       FROM people p
       WHERE p.userid = ? AND p.userid != ?
         AND (
           p.admin = '1'
           OR EXISTS (SELECT 1 FROM viewList vl WHERE vl.pid = ? AND vl.viewer = p.userid)
         )"
    );
    mysqli_stmt_bind_param($stmt_chk, "sss", $to, $userid, $userid);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);

    if (!$targetRow || mysqli_num_rows($res_chk) === 0) {
      $statusMessage = "Invalid or unauthorized recipient selected.";
      $isError = true;
      $step = "done";
    } else {
      // Expire any older pending reset requests for this target user
      $stmt_exp = mysqli_prepare($link, "UPDATE passwordResetRequests SET status = 'expired' WHERE target_userid = ? AND status = 'pending'");
      mysqli_stmt_bind_param($stmt_exp, "s", $userid);
      mysqli_stmt_execute($stmt_exp);

      $rawToken = bin2hex(random_bytes(32));
      $tokenHash = hash('sha256', $rawToken);

      $stmt_ins = mysqli_prepare(
        $link,
        "INSERT INTO passwordResetRequests (target_userid, approver_userid, token_hash, status, requester_ip, expires_at)
         VALUES (?, ?, ?, 'pending', ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
      );
      mysqli_stmt_bind_param($stmt_ins, "ssss", $userid, $to, $tokenHash, $clientIp);
      mysqli_stmt_execute($stmt_ins);
      $reqId = mysqli_insert_id($link);

      $safeTargetName = escapeText(trim($targetRow["firstname"] . " " . $targetRow["lastname"]));
      $safeUid = escapeText($userid);
      $approveHref = "approvePasswordReset.php?id=" . (int)$reqId . "&token=" . urlencode($rawToken);

      $subject = "Password Reset Request for " . trim($targetRow["firstname"] . " " . $targetRow["lastname"]);
      $message = "A password reset was requested for <b>" . $safeTargetName . "</b> (<code>" . $safeUid . "</code>).<p>" .
        "Their current password has <b>not</b> been changed yet. If they asked you to help reset their password, click below to approve the reset and generate a temporary password for them:<p>" .
        "<a class=\"actionButton\" href=\"" . $approveHref . "\">Approve Password Reset</a><p>" .
        "If you were not expecting this request, simply ignore or delete this message (this link expires in 24 hours).";

      sendEmail($to, "", $subject, $message, 0);

      $statusMessage = "A password reset approval request has been sent to the selected user's WishList Messages. Once they approve it, they will receive a temporary password to share with you.";
      $step = "done";
    }
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $userid !== "") {
  verifyCsrfOrDie(true);

  $rl = checkRateLimit($link, $clientIp, $userid, 'reset_lookup', 10, 300);
  if ($rl['blocked']) {
    $statusMessage = "Too many lookup attempts. Please wait a few minutes and try again.";
    $isError = true;
    $step = "done";
  } else {
    recordRateLimitAttempt($link, $clientIp, $userid, 'reset_lookup');

    $stmt = mysqli_prepare($link, "SELECT userid, firstname, lastname FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $userid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
      $targetDisplayName = trim($row["firstname"] . " " . $row["lastname"]);
      // Only offer contacts whom the target user has permitted to view their list (vl.pid = target), plus site admins as fallback
      $users_query = "
        SELECT DISTINCT p.userid, p.firstname, p.lastname
        FROM people p
        WHERE p.userid != ?
          AND (
            p.userid IN (SELECT vl.viewer FROM viewList vl WHERE vl.pid = ?)
            OR p.admin = '1'
          )
        ORDER BY p.lastname, p.firstname
      ";
      $stmt_users = mysqli_prepare($link, $users_query);
      mysqli_stmt_bind_param($stmt_users, "ss", $userid, $userid);
      mysqli_stmt_execute($stmt_users);
      $users_result = mysqli_stmt_get_result($stmt_users);
      while ($u = mysqli_fetch_assoc($users_result)) {
        $eligibleContacts[] = $u;
      }
      $step = "select_recipient";
    } else {
      $statusMessage = "No user found with that ID.";
      $isError = true;
      $step = "done";
    }
  }
}
?>
<!DOCTYPE html>
<HTML>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="style.css" type="text/css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="js/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script type="text/javascript">window.WISHLIST_BASE_URL = <?php echo json_encode($base_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="js/theme.js"></script>
<title>Forgot Password</title>
</head>
<BODY <?php echo $step === 'enter_userid' ? 'onload="document.theForm.userid.focus();"' : ''; ?>>
<table class="pagetable">
<tr>
<td valign="top" align="center">
<p>&nbsp;</p>

<?php if ($step === "done"): ?>
  <div style="max-width: 520px; padding: 16px;">
    <p style="color: <?php echo $isError ? '#c62828' : 'inherit'; ?>; font-weight: bold;">
      <?php echo escapeText($statusMessage); ?>
    </p>
    <?php if ($isError): ?>
      <p><a href="forgotPassword.php">Try Again</a></p>
    <?php endif; ?>
    <p><a href="login.php">Return to Login Page</a></p>
  </div>

<?php elseif ($step === "select_recipient"): ?>
  <form name="theForm" method="post" action="forgotPassword.php">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <input type="hidden" name="requestReset" value="true">
    <input type="hidden" name="userid" value="<?php echo escapeText($userid); ?>">

    <h3>Request Password Reset for <?php echo escapeText($targetDisplayName); ?></h3>
    <p style="max-width: 480px; font-size: 0.95em;">
      Select a family member or administrator who can approve your password reset request via their WishList Messages:
    </p>

    <label for="recipient"><b>Trusted Contact:</b></label><br>
    <select name="recipient" id="recipient" style="width: 300px;" required>
      <?php foreach ($eligibleContacts as $user_row): ?>
        <option value="<?php echo escapeText($user_row['userid']); ?>">
          <?php echo escapeText($user_row['firstname'] . ' ' . $user_row['lastname']); ?>
        </option>
      <?php endforeach; ?>
    </select>
    <br><br>

    <input type="submit" value="Send Reset Request" class="buttonstyle">
    <button type="button" class="buttonstyle" onclick="location.href='login.php'">Cancel</button>
  </form>
  <script>
    $(document).ready(function() {
      $('#recipient').select2();
    });
  </script>

<?php else: ?>
  <form name="theForm" method="post" action="forgotPassword.php">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <table class="roundedbox" cellspacing="6" cellpadding="8">
      <tr>
        <td colspan="2" align="center">
          <b>Forgot Your WishList Password?</b><br>
          <font size="-1">Enter your WishList ID to request a password reset from a family member or admin.</font>
        </td>
      </tr>
      <tr>
        <td align="right"><b>WishList ID:</b></td>
        <td><input type="text" name="userid" size="25" required></td>
      </tr>
      <tr>
        <td colspan="2" align="center">
          <input type="submit" value="Continue" class="buttonstyle">
          <button type="button" class="buttonstyle" onclick="location.href='login.php'">Cancel</button>
        </td>
      </tr>
    </table>
  </form>
<?php endif; ?>

</td>
</tr>
</table>
</BODY>
</HTML>
