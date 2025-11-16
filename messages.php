<?php
include "funcLib.php";

// Fetch all messages for the current user
$user_id = $_SESSION['userid'];
$messages_query = "SELECT m.*, p.firstname, p.lastname FROM messages m LEFT JOIN people p ON m.sender_id = p.userid WHERE m.recipient_id = ? ORDER BY m.timestamp DESC";
$stmt = mysqli_prepare($link, $messages_query);
mysqli_stmt_bind_param($stmt, "s", $user_id);
mysqli_stmt_execute($stmt);
$messages_result = mysqli_stmt_get_result($stmt);

// Fetch all users for the new message dropdown
$users_query = "SELECT p.userid, p.firstname, p.lastname FROM people p JOIN viewList vl ON p.userid = vl.pid WHERE vl.viewer = ? AND p.userid != ? ORDER BY p.lastname, p.firstname";
$stmt_users = mysqli_prepare($link, $users_query);
mysqli_stmt_bind_param($stmt_users, "ss", $user_id, $user_id);
mysqli_stmt_execute($stmt_users);
$users_result = mysqli_stmt_get_result($stmt_users);
?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>
<link rel=stylesheet href=style.css type=text/css>
<link rel="manifest" href="/manifest.json">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<title>Messages</title>
<BODY>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("home.php:Home|messages.php:Messages", true); ?>
<center>
<h2>Your Messages</h2>
<table border=1 class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>From</th>
        <th>Subject</th>
        <th>Date</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
    <?php while ($message = mysqli_fetch_assoc($messages_result)): ?>
    <tr class="<?php echo $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1'; ?>">
        <td><?php echo $message['sender_id'] ? htmlspecialchars($message['firstname'] . ' ' . $message['lastname']) : 'System'; ?></td>
        <td><?php echo htmlspecialchars($message['subject']); ?></td>
        <td><?php echo parseDate($message['timestamp']); ?></td>
        <td><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></td>
        <td>
            <form action="updateMessageStatus.php" method="post" style="display:inline-block;">
                <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                <input type="hidden" name="status" value="<?php echo $message['is_read'] ? 0 : 1; ?>">
                <button type="submit" class="actionButton"><?php echo $message['is_read'] ? 'Mark as Unread' : 'Mark as Read'; ?></button>
            </form>
            <form action="deleteMessage.php" method="post" style="display:inline-block;">
                <input type="hidden" name="message_id" value="<?php echo $message['message_id']; ?>">
                <button type="submit" class="actionButtonRed">Delete</button>
            </form>
        </td>
    </tr>
    <tr>
        <td colspan="5" class="<?php echo $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1'; ?>">
            <?php echo nl2br(htmlspecialchars($message['body'])); ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<hr>

<h2>Sent Messages</h2>
<table border=1 class="viewpurchases">
    <tr class="viewpurchaseshead">
        <th>To</th>
        <th>Subject</th>
        <th>Date</th>
        <th>Status</th>
    </tr>
    <?php
    // Fetch sent messages
    $sent_messages_query = "SELECT m.*, p.firstname, p.lastname FROM messages m LEFT JOIN people p ON m.recipient_id = p.userid WHERE m.sender_id = ? ORDER BY m.timestamp DESC";
    $stmt_sent = mysqli_prepare($link, $sent_messages_query);
    mysqli_stmt_bind_param($stmt_sent, "s", $user_id);
    mysqli_stmt_execute($stmt_sent);
    $sent_messages_result = mysqli_stmt_get_result($stmt_sent);

    while ($message = mysqli_fetch_assoc($sent_messages_result)):
    ?>
    <tr class="<?php echo $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1'; ?>">
        <td><?php echo htmlspecialchars($message['firstname'] . ' ' . $message['lastname']); ?></td>
        <td><?php echo htmlspecialchars($message['subject']); ?></td>
        <td><?php echo parseDate($message['timestamp']); ?></td>
        <td><?php echo $message['is_read'] ? 'Read' : 'Unread'; ?></td>
    </tr>
    <tr>
        <td colspan="4" class="<?php echo $message['is_read'] ? 'viewpurchases2' : 'viewpurchases1'; ?>">
            <?php echo nl2br(htmlspecialchars($message['body'])); ?>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

<hr>

<h2>Send a New Message</h2>
<form action="sendMessage.php" method="post">
    <table border=0>
        <tr>
            <td>Recipient(s):</td>
            <td>
                <select id="recipient_id" name="recipient_id[]" multiple="multiple" style="width: 300px;">
                    <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                    <option value="<?php echo htmlspecialchars($user['userid']); ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option>
                    <?php endwhile; ?>
                </select>
            </td>
        </tr>
        <tr>
            <td>Subject:</td>
            <td><input type="text" name="subject" size="50"></td>
        </tr>
        <tr>
            <td valign="top">Message:</td>
            <td><textarea name="body" cols="50" rows="10"></textarea></td>
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
    $('#recipient_id').select2();
});
</script>
</BODY>
</HTML>
