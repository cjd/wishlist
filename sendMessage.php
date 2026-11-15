<?php
include "funcLib.php";

$is_ajax = (!empty($_POST['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($is_ajax) {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }
    header("Location: messages.php");
    exit;
}

verifyCsrfOrDie(true);

$raw_recipients = $_POST['recipient_id'] ?? [];
if (!is_array($raw_recipients)) {
    $raw_recipients = [$raw_recipients];
}

$recipient_ids = array_values(array_unique(array_filter(array_map(function ($r) {
    return trim((string)$r);
}, $raw_recipients), function ($r) {
    return $r !== '';
})));

$subject = trim((string)($_POST['subject'] ?? ''));
$body = trim((string)($_POST['body'] ?? ''));
$sender_id = $_SESSION['userid'];
$send_anonymous = !empty($_POST['anonymous_shopper']);

if (empty($recipient_ids) || $subject === '' || $body === '') {
    if ($is_ajax) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Please provide a recipient, subject, and message body.']);
        exit;
    }
    header("Location: messages.php?error=missing_parameters");
    exit;
}

if ($send_anonymous) {
    $clientIp = getClientIp();
    $normSender = strtolower((string)$sender_id);
    $rl = checkRateLimit($link, $clientIp, $normSender, 'anon_msg', 5, 600);
    if ($rl['blocked']) {
        if ($is_ajax) {
            http_response_code(429);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Too many anonymous questions sent recently. Please wait a few minutes and try again.']);
            exit;
        }
        header("Location: messages.php?error=rate_limited");
        exit;
    }

    // Anonymous shopper mode is strictly limited to wishlist owners the sender has permission to view
    $anon_recipients = [];
    foreach ($recipient_ids as $rid) {
        if ($rid !== '' && $rid !== $sender_id) {
            $acc = canViewList($link, $rid, $sender_id);
            if (!empty($acc['allowed'])) {
                $anon_recipients[] = $rid;
            }
        }
    }
    if (empty($anon_recipients)) {
        if ($is_ajax) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Anonymous questions can only be sent to someone whose WishList you can view.']);
            exit;
        }
        header("Location: messages.php?error=invalid_recipient");
        exit;
    }

    recordRateLimitAttempt($link, $clientIp, $normSender, 'anon_msg');

    $anonSubject = (stripos($subject, '[Anonymous Gift Shopper]') === 0)
        ? $subject
        : ('[Anonymous Gift Shopper] ' . $subject);
    $anonBody = escapeText($body)
        . "\n\n<hr><i>💡 <b>Tip:</b> This question was sent anonymously by someone shopping your WishList so the surprise stays intact! Since you cannot reply directly to an anonymous shopper, please update the item's description or your top-of-list notes under <b>Modify Your List</b> so they can see your answer.</i>";

    sendEmail(implode(',', $anon_recipients), '', $anonSubject, $anonBody, 0);

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'anonymous' => true]);
        exit;
    }
    header("Location: messages.php?sent=1");
    exit;
}

// Validate recipients exist and are authorized contacts (shared viewList in either direction, or admin)
$valid_recipients = [];
$is_sender_admin = (!empty($_SESSION['admin']) && $_SESSION['admin'] == 1);

if ($is_sender_admin) {
    $placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
    $types = str_repeat('s', count($recipient_ids));
    $stmt_chk = mysqli_prepare($link, "SELECT userid FROM people WHERE userid IN ($placeholders) AND userid != ?");
    $params = array_merge($recipient_ids, [$sender_id]);
    mysqli_stmt_bind_param($stmt_chk, $types . 's', ...$params);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    while ($r = mysqli_fetch_assoc($res_chk)) {
        $valid_recipients[] = $r['userid'];
    }
} else {
    $placeholders = implode(',', array_fill(0, count($recipient_ids), '?'));
    $types = str_repeat('s', count($recipient_ids));
    $sql_chk = "SELECT DISTINCT p.userid
                FROM people p
                LEFT JOIN viewList vl1 ON vl1.pid = p.userid AND vl1.viewer = ?
                LEFT JOIN viewList vl2 ON vl2.pid = ? AND vl2.viewer = p.userid
                WHERE p.userid IN ($placeholders)
                  AND p.userid != ?
                  AND (p.admin = 1 OR vl1.viewer IS NOT NULL OR vl2.viewer IS NOT NULL)";
    $stmt_chk = mysqli_prepare($link, $sql_chk);
    $params = array_merge([$sender_id, $sender_id], $recipient_ids, [$sender_id]);
    mysqli_stmt_bind_param($stmt_chk, 'ss' . $types . 's', ...$params);
    mysqli_stmt_execute($stmt_chk);
    $res_chk = mysqli_stmt_get_result($stmt_chk);
    while ($r = mysqli_fetch_assoc($res_chk)) {
        $valid_recipients[] = $r['userid'];
    }
}

if (empty($valid_recipients)) {
    if ($is_ajax) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'No valid authorized recipients selected.']);
        exit;
    }
    header("Location: messages.php?error=invalid_recipient");
    exit;
}

sendEmail(implode(',', $valid_recipients), $sender_id, $subject, $body, 0);

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

header("Location: messages.php?sent=1");
exit;
