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
