<?php
include "funcLib.php";

$user_id = $_SESSION['userid'];
$is_admin = (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1);

// Fetch inbox messages
$messages_query = "SELECT m.*, p.firstname, p.lastname, p.suffix FROM messages m LEFT JOIN people p ON m.sender_id = p.userid WHERE m.recipient_id = ? AND m.recipient_deleted = 0 ORDER BY m.timestamp DESC";
$stmt = mysqli_prepare($link, $messages_query);
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$messages_result = mysqli_stmt_get_result($stmt);

// Fetch authorized recipients for new message dropdown
if ($is_admin) {
    $stmt_users = mysqli_prepare($link, "SELECT userid, firstname, lastname, suffix FROM people WHERE userid != ? ORDER BY lastname, firstname");
    mysqli_stmt_bind_param($stmt_users, "s", $user_id);
} else {
    $users_query = "SELECT DISTINCT p.userid, p.firstname, p.lastname, p.suffix
                    FROM people p
                    LEFT JOIN viewList vl1 ON p.userid = vl1.pid AND vl1.viewer = ?
                    LEFT JOIN viewList vl2 ON p.userid = vl2.viewer AND vl2.pid = ?
                    WHERE p.userid != ?
                      AND (p.admin = 1 OR vl1.viewer IS NOT NULL OR vl2.viewer IS NOT NULL)
                    ORDER BY p.lastname, p.firstname";
    $stmt_users = mysqli_prepare($link, $users_query);
    mysqli_stmt_bind_param($stmt_users, "sss", $user_id, $user_id, $user_id);
}
mysqli_stmt_execute($stmt_users);
$users_result = mysqli_stmt_get_result($stmt_users);

$error_code = $_GET['error'] ?? '';
$sent_ok = !empty($_GET['sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
  <link rel="stylesheet" href="css/select2.min.css" type="text/css">
  <link rel="manifest" href="manifest.json">
  <script src="js/jquery-3.7.1.min.js"></script>
  <script src="js/select2.min.js"></script>
  <title>Messages</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|:Messages", true); ?>
<center>

<?php if ($error_code === 'missing_parameters'): ?>
  <p style="color:#c00; font-weight:bold;">Please select at least one recipient and enter both a subject and message.</p>
<?php elseif ($error_code === 'invalid_recipient'): ?>
  <p style="color:#c00; font-weight:bold;">You do not have permission to message the selected recipient.</p>
<?php elseif ($sent_ok): ?>
  <p style="color:#155724; font-weight:bold;">Message sent!</p>
<?php endif; ?>

<h2>Your Messages</h2>
<table border="1" class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>From</th>
        <th>Subject</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php if ($messages_result && mysqli_num_rows($messages_result) > 0): ?>
      <?php while ($message = mysqli_fetch_assoc($messages_result)): ?>
      <?php
        if (empty($message['sender_id'])) {
            $fromLabel = (stripos((string)($message['subject'] ?? ''), '[Anonymous Gift Shopper]') === 0)
                ? '🕵️ A Gift Shopper (Anonymous)'
                : 'System';
        } elseif (!empty($message['firstname']) || !empty($message['lastname'])) {
            $fromLabel = trim($message['firstname'] . ' ' . $message['lastname'] . ' ' . ($message['suffix'] ?? ''));
        } else {
            $fromLabel = $message['sender_id'];
        }
        $rowClass = $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1';
      ?>
      <tr class="<?php echo $rowClass; ?>">
          <td><?php echo escapeText($fromLabel); ?></td>
          <td><?php echo escapeText($message['subject']); ?></td>
          <td><?php echo escapeText(parseDate($message['timestamp'])); ?></td>
          <td><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></td>
          <td>
              <form action="updateMessageStatus.php" method="post" style="display:inline-block;">
                  <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
                  <input type="hidden" name="message_id" value="<?php echo (int)$message['message_id']; ?>">
                  <input type="hidden" name="status" value="<?php echo $message['is_read'] ? 0 : 1; ?>">
                  <button type="submit" class="actionButton"><?php echo $message['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?></button>
              </form>
              <form action="deleteMessage.php" method="post" style="display:inline-block;">
                  <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
                  <input type="hidden" name="box" value="inbox">
                  <input type="hidden" name="message_id" value="<?php echo (int)$message['message_id']; ?>">
                  <button type="submit" class="actionButtonRed">Delete</button>
              </form>
          </td>
      </tr>
      <tr>
          <td colspan="5" class="<?php echo $rowClass; ?>">
              <?php echo formatMessage($message['body']); ?>
          </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr class="viewpurchases2">
          <td colspan="5" align="center">No messages in your inbox.</td>
      </tr>
    <?php endif; ?>
</table>

<hr>

<h2>Sent Messages</h2>
<table border="1" class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>To</th>
        <th>Subject</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php
    $sent_messages_query = "SELECT m.*, p.firstname, p.lastname, p.suffix FROM messages m LEFT JOIN people p ON m.recipient_id = p.userid WHERE m.sender_id = ? AND m.sender_deleted = 0 ORDER BY m.timestamp DESC";
    $stmt_sent = mysqli_prepare($link, $sent_messages_query);
    mysqli_stmt_bind_param($stmt_sent, "s", $user_id);
    mysqli_stmt_execute($stmt_sent);
    $sent_messages_result = mysqli_stmt_get_result($stmt_sent);
    ?>
    <?php if ($sent_messages_result && mysqli_num_rows($sent_messages_result) > 0): ?>
      <?php while ($message = mysqli_fetch_assoc($sent_messages_result)): ?>
      <?php
        if (!empty($message['firstname']) || !empty($message['lastname'])) {
            $toLabel = trim($message['firstname'] . ' ' . $message['lastname'] . ' ' . ($message['suffix'] ?? ''));
        } else {
            $toLabel = $message['recipient_id'] ?: 'Unknown';
        }
        $rowClass = $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1';
      ?>
      <tr class="<?php echo $rowClass; ?>">
          <td><?php echo escapeText($toLabel); ?></td>
          <td><?php echo escapeText($message['subject']); ?></td>
          <td><?php echo escapeText(parseDate($message['timestamp'])); ?></td>
          <td><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></td>
          <td>
              <form action="deleteMessage.php" method="post" style="display:inline-block;">
                  <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
                  <input type="hidden" name="box" value="sent">
                  <input type="hidden" name="message_id" value="<?php echo (int)$message['message_id']; ?>">
                  <button type="submit" class="actionButtonRed">Delete</button>
              </form>
          </td>
      </tr>
      <tr>
          <td colspan="5" class="<?php echo $rowClass; ?>">
              <?php echo formatMessage($message['body']); ?>
          </td>
      </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr class="viewpurchases2">
          <td colspan="5" align="center">No sent messages.</td>
      </tr>
    <?php endif; ?>
</table>

<hr>

<h2>Send a New Message</h2>
<form action="sendMessage.php" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
    <table border="0" cellpadding="4">
        <tr>
            <td><b>Recipient(s):</b></td>
            <td>
                <select id="recipient_id" name="recipient_id[]" multiple="multiple" style="width: 300px;" required>
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <?php $uName = trim($user['firstname'] . ' ' . $user['lastname'] . ' ' . ($user['suffix'] ?? '')); ?>
                    <option value="<?php echo escapeText($user['userid']); ?>"><?php echo escapeText($uName); ?></option>
                    <?php endwhile; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td><b>Subject:</b></td>
            <td><input type="text" name="subject" size="50" required></td>
        </tr>
        <tr>
            <td valign="top"><b>Message:</b></td>
            <td><textarea name="body" cols="50" rows="8" required></textarea></td>
        </tr>
        <tr>
            <td colspan="2" align="center">
                <input type="submit" value="Send Message" class="buttonstyle">
            </td>
        </tr>
    </table>
</form>
</center>
</td>
</tr>
</table>
<script>
$(document).ready(function() {
    if ($.fn.select2) {
        $('#recipient_id').select2({ placeholder: "Select recipient(s)..." });
    }
});
</script>
</body>
</html>
