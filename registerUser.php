<?php
$ignoreSession = true;
include "funcLib.php";

$registrationDisabled = true;
?>
<!DOCTYPE html>
<HTML>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="style.css" type="text/css">
<script type="text/javascript">window.WISHLIST_BASE_URL = <?php echo json_encode($base_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script src="js/theme.js"></script>
<title>WishList Register</title>
</head>
<BODY>
<table class="pagetable">
<tr>
<td valign="top">
<center>
<h2>Welcome to the WishList Site</h2>

<?php if ($registrationDisabled): ?>
  <div style="max-width: 520px; padding: 16px;">
    <p>Self-service account requests are currently disabled. Please ask a family member or the site administrator to create an account for you.</p>
    <p><a href="login.php">Return to Login Page</a></p>
  </div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST["fname"])): ?>
  <?php
  verifyCsrfOrDie(true);
  $clientIp = getClientIp();
  $rl = checkRateLimit($link, $clientIp, '', 'register', 3, 3600);
  if ($rl['blocked']) {
    print "<p style='color:#c62828;'><b>Too many account requests have been submitted recently. Please try again later.</b></p>";
  } else {
    recordRateLimitAttempt($link, $clientIp, '', 'register');
    $fname = escapeText(trim($_POST["fname"] ?? ''));
    $lname = escapeText(trim($_POST["lname"] ?? ''));
    $suffix = escapeText(trim($_POST["suffix"] ?? ''));
    $desiredUid = escapeText(trim($_POST["userid"] ?? ''));
    $comment = escapeText(trim($_POST["comment"] ?? ''));

    $result = mysqli_query($link, "SELECT userid FROM people WHERE admin = '1'");
    $admins = [];
    while ($row = mysqli_fetch_assoc($result)) {
      $admins[] = $row["userid"];
    }
    $to = implode(',', $admins);

    $subject = "Request for new WishList account";
    $message = "The following person is requesting a new account:<p>" .
      "<b>First Name:</b> {$fname}<br>" .
      "<b>Last Name:</b> {$lname}<br>" .
      "<b>Suffix:</b> {$suffix}<br>" .
      "<b>Desired Userid:</b> {$desiredUid}<br>" .
      "<b>Comment:</b><br>{$comment}";

    $sent = !empty($to) ? sendEmail($to, "", $subject, $message, 0) : 0;
    if ($sent == 1) {
      print "<h3>Thank you. Your request has been submitted to the administrator for approval.</h3>";
    } else {
      print "<h3 style='color:#c62828;'>An unexpected error occurred while submitting your request.</h3>";
    }
  }
  ?>
  <p><a href="login.php">Return to Login Page</a></p>
<?php else: ?>
  <form method="post" action="registerUser.php">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <table style="border-collapse: collapse;" border="0" cellpadding="4" cellspacing="0" bgcolor="lightYellow">
      <tr><td colspan="2" align="center" bgcolor="#6702cc"><font size="3" color="white"><b>Request an Account</b></font></td></tr>
      <tr><td align="right"><b>First Name</b></td><td><input type="text" name="fname" size="20" required></td></tr>
      <tr><td align="right"><b>Last Name</b></td><td><input type="text" name="lname" size="20" required></td></tr>
      <tr><td align="right"><b>Suffix</b></td><td><input type="text" name="suffix" size="20"></td></tr>
      <tr><td align="right"><b>Desired Userid</b></td><td><input type="text" name="userid" size="20" required></td></tr>
      <tr><td align="right"><b>Comment / Family Contact</b></td><td><textarea name="comment" rows="3" cols="50"></textarea></td></tr>
      <tr><td align="center" colspan="2" bgcolor="#c0c0c0"><input type="submit" value="Submit" class="buttonstyle"></td></tr>
    </table>
  </form>
  <p><a href="login.php">Return to Login Page</a></p>
<?php endif; ?>

</center>
</td>
</tr>
</table>
</BODY>
</HTML>
