<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!headers_sent()) {
  header("X-Frame-Options: SAMEORIGIN");
  header("X-Content-Type-Options: nosniff");
  header("Referrer-Policy: strict-origin-when-cross-origin");
}

function isHttpsRequest() {
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    return true;
  }
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    return true;
  }
  return false;
}

@(include __DIR__ . '/config.php');

if (!isset($base_dir)) {
  // This should only be true when phpWishList is first installed.
  if (!isset($doingSetup)) {
    header("Location: admin.php?setup=true");
    exit;
  }
} else {
  $base_dir = rtrim($base_dir, '/');
  $base_url = rtrim($base_url ?? '', '/');
}

$session_timeout = 604800; // 7-day inactivity timeout (7 * 86400)

if (session_status() === PHP_SESSION_NONE) {
  @ini_set('session.gc_maxlifetime', (string)$session_timeout);
  if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => $session_timeout,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isHttpsRequest()
    ]);
  }
  session_name("WishListSite");
  session_start();
}

if (!isset($_SESSION["userid"])) {
  if (!isset($ignoreSession)) {
    header("Location: " . ($base_url ?? '') . "/login.php");
    exit;
  }
} else {
  if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
      );
    }
    session_destroy();
    header("Location: " . ($base_url ?? '') . "/login.php?expired=1");
    exit;
  }
  $_SESSION['last_activity'] = time();

  $userid = $_SESSION["userid"];
  if (isset($_SESSION["euserid"])) {
    $userid = $_SESSION["euserid"];
  }
}

$activeOwnerFullname = $_SESSION["fullname"] ?? "";

if (!isset($doingSetup)) {
  $link = @(mysqli_connect($db_loc, $db_userid, $db_pass));
  if (!$link) {
    error_log("Database connection failed: " . mysqli_connect_error());
    die("<p><font size=6>Danger Will Robinson! It looks like the database is down.<p>Please try again later.</font>");
  } else {
    mysqli_select_db($link, $db_name);
    mysqli_set_charset($link, "utf8mb4");

    // Re-validate active user session and proxy edit permissions against live DB state
    if (isset($_SESSION["userid"])) {
      $stmt_auth = mysqli_prepare($link, "SELECT admin, firstname, lastname, suffix FROM people WHERE userid = ?");
      if ($stmt_auth) {
        mysqli_stmt_bind_param($stmt_auth, "s", $_SESSION["userid"]);
        mysqli_stmt_execute($stmt_auth);
        $res_auth = mysqli_stmt_get_result($stmt_auth);
        if ($auth_row = mysqli_fetch_assoc($res_auth)) {
          $_SESSION["admin"] = (int)$auth_row["admin"];
          $_SESSION["fullname"] = trim($auth_row["firstname"] . " " . $auth_row["lastname"] . " " . ($auth_row["suffix"] ?? ""));
          $activeOwnerFullname = $_SESSION["fullname"];
        } else {
          // Account was deleted or renamed; invalidate session
          $_SESSION = array();
          session_destroy();
          if (!isset($ignoreSession)) {
            header("Location: " . $base_url . "/login.php");
            exit;
          }
        }
      }

      if (isset($_SESSION["euserid"]) && $_SESSION["euserid"] !== $_SESSION["userid"]) {
        $euser_valid = false;
        if (!empty($_SESSION["admin"]) && $_SESSION["admin"] == 1) {
          $euser_valid = true;
        } else {
          $stmt_e = mysqli_prepare($link, "SELECT allowEdit FROM viewList WHERE pid = ? AND viewer = ?");
          if ($stmt_e) {
            mysqli_stmt_bind_param($stmt_e, "ss", $_SESSION["euserid"], $_SESSION["userid"]);
            mysqli_stmt_execute($stmt_e);
            $res_e = mysqli_stmt_get_result($stmt_e);
            if (($erow = mysqli_fetch_assoc($res_e)) && $erow["allowEdit"] == '1') {
              $euser_valid = true;
            }
          }
        }

        if ($euser_valid) {
          $stmt_owner = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
          if ($stmt_owner) {
            mysqli_stmt_bind_param($stmt_owner, "s", $_SESSION["euserid"]);
            mysqli_stmt_execute($stmt_owner);
            $res_owner = mysqli_stmt_get_result($stmt_owner);
            if ($owner_row = mysqli_fetch_assoc($res_owner)) {
              $userid = $_SESSION["euserid"];
              $activeOwnerFullname = trim($owner_row["firstname"] . " " . $owner_row["lastname"] . " " . ($owner_row["suffix"] ?? ""));
            } else {
              unset($_SESSION["euserid"]);
              $userid = $_SESSION["userid"];
            }
          }
        } else {
          unset($_SESSION["euserid"]);
          $userid = $_SESSION["userid"];
        }
      }
    }
  }
}

/**
 * HTML & URL Escaping / Sanitization Helpers
 */
function escapeText($str) {
  return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8', false);
}

function formatMultilineText($str) {
  $escaped = escapeText($str);
  return preg_replace('~&lt;br\s*/?&gt;~i', '<br>', $escaped);
}

function sanitizeHttpUrl($url) {
  $url = trim((string)($url ?? ''));
  if ($url === '') {
    return '';
  }
  // Decode HTML entities for scheme inspection so &#106;avascript: cannot bypass checks
  $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  $decoded = preg_replace('/[\x00-\x1F\x7F]+/', '', trim($decoded));
  if ($decoded === '') {
    return '';
  }
  if (preg_match('~^([a-z][a-z0-9+.-]*):~i', $decoded, $m)) {
    $scheme = strtolower($m[1]);
    if ($scheme !== 'http' && $scheme !== 'https') {
      return '';
    }
    return $decoded;
  }
  if (strpos($decoded, '//') === 0) {
    return 'https:' . $decoded;
  }
  return 'https://' . ltrim($decoded, '/');
}

/**
 * Authorization & Rate-Limiting Helpers
 */
function canViewList($link, $pid, $viewer) {
  $pid = (string)$pid;
  $viewer = (string)$viewer;
  if ($pid !== '' && $pid === $viewer) {
    return ['allowed' => true, 'readOnly' => 0, 'allowEdit' => 1, 'viewContactInfo' => 1];
  }
  $stmt = mysqli_prepare($link, "SELECT readOnly, allowEdit, viewContactInfo FROM viewList WHERE pid = ? AND viewer = ?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ss", $pid, $viewer);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($rs)) {
      $allowEdit = (int)$row['allowEdit'];
      $readOnly = $allowEdit ? 0 : (int)$row['readOnly'];
      return [
        'allowed' => true,
        'readOnly' => $readOnly,
        'allowEdit' => $allowEdit,
        'viewContactInfo' => (int)$row['viewContactInfo']
      ];
    }
  }
  return ['allowed' => false, 'readOnly' => 1, 'allowEdit' => 0, 'viewContactInfo' => 0];
}

function getClientIp() {
  return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function checkRateLimit($link, $ip, $targetUserid = '', $action = 'login', $maxAttempts = 5, $windowSeconds = 300) {
  try {
    $stmt = mysqli_prepare(
      $link,
      "SELECT COUNT(*) AS cnt, MIN(UNIX_TIMESTAMP(attempted_at)) AS oldest_ts
       FROM loginAttempts
       WHERE action_type = ?
         AND attempted_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
         AND (ip_address = ? OR (? != '' AND userid = ?))"
    );
    if (!$stmt) {
      return ['blocked' => false, 'retry_after' => 0];
    }
    mysqli_stmt_bind_param($stmt, "sisss", $action, $windowSeconds, $ip, $targetUserid, $targetUserid);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($rs)) {
      if ((int)$row['cnt'] >= $maxAttempts) {
        $retry = max(60, ((int)$row['oldest_ts'] + $windowSeconds) - time());
        return ['blocked' => true, 'retry_after' => $retry];
      }
    }
  } catch (Throwable $e) {
    error_log("Rate limit check error: " . $e->getMessage());
  }
  return ['blocked' => false, 'retry_after' => 0];
}

function recordRateLimitAttempt($link, $ip, $targetUserid = '', $action = 'login') {
  try {
    if (random_int(1, 50) === 1) {
      mysqli_query($link, "DELETE FROM loginAttempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }
    $stmt = mysqli_prepare($link, "INSERT INTO loginAttempts (ip_address, userid, action_type) VALUES (?, ?, ?)");
    if ($stmt) {
      $u = substr((string)$targetUserid, 0, 50);
      mysqli_stmt_bind_param($stmt, "sss", $ip, $u, $action);
      mysqli_stmt_execute($stmt);
    }
  } catch (Throwable $e) {
    error_log("Rate limit record error: " . $e->getMessage());
  }
}

function clearRateLimitAttempts($link, $ip, $targetUserid = '', $action = 'login') {
  try {
    $stmt = mysqli_prepare($link, "DELETE FROM loginAttempts WHERE action_type = ? AND (ip_address = ? OR (? != '' AND userid = ?))");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, "ssss", $action, $ip, $targetUserid, $targetUserid);
      mysqli_stmt_execute($stmt);
    }
  } catch (Throwable $e) {
    error_log("Rate limit clear error: " . $e->getMessage());
  }
}

function generateRandomPassword($length = 10) {
  $alphabet = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789";
  $max = strlen($alphabet) - 1;
  $pass = "";
  for ($i = 0; $i < $length; $i++) {
    $pass .= $alphabet[random_int(0, $max)];
  }
  return $pass;
}

/* getSeason : returns the current season (Southern Hemisphere)
 * 1 => winter (jun 21 -> sep 21)
 * 2 => spring (sep 22 -> nov 30)
 * 3 => summer (jan 1  -> mar 20)
 * 4 => autumn (mar 21 -> jun 20)
 * 5 => christmas (dec 1 -> dec 31)
 */
function getSeason() {
  $today = getdate();
  $mmdd = ((int)$today['mon'] * 100) + (int)$today['mday'];

  if ($mmdd < 321) {
    return 3; // Summer
  } elseif ($mmdd < 621) {
    return 4; // Autumn
  } elseif ($mmdd < 922) {
    return 1; // Winter
  } elseif ($mmdd < 1201) {
    return 2; // Spring
  }
  return 5; // Christmas
}

// $data must be of the form url_1:url_1Name|url_2:url_2Name...|:url_nName
// $helpLink indicates where to anchor to in help.php
function createNavBar($data, $displayGreeting = false, $helpLink = '') {
  global $base_url, $link;

  $safeBase = escapeText($base_url);
  $safeHelp = escapeText($helpLink);

  print "<table class=\"navBar\">";
  print "<tr><td class=navBar>";

  $items = explode("|", (string)$data);
  $array_count = count($items);

  for ($i = 0; $i < $array_count; $i++) {
    if ($items[$i] === "") {
      continue;
    }
    $piece = explode(":", $items[$i], 2);
    $href = $piece[0] ?? '';
    $label = $piece[1] ?? '';

    if ($i == ($array_count - 1) || $href === '') {
      if ($label !== '') {
        print "<b>" . escapeText($label) . "</b>";
      }
    } else {
      print "<a class=\"navMenuLink\" href=\"" . escapeText($href) . "\">" . escapeText($label) . "</a>";
      if (isset($items[$i + 1]) && $items[$i + 1] !== "") {
        print " &gt; ";
      }
    }
  }
  print "</td>";

  if ($displayGreeting && !empty($_SESSION["fullname"])) {
    print "<td class=navBar align=center>";
    print "<b>" . escapeText($_SESSION["fullname"]) . "</b>";
    print "</td>";
  }

  print "<td class=navBar align=\"right\"><button type=\"button\" id=\"themeToggleBtn\" class=\"theme-toggle-btn\" onclick=\"toggleTheme()\" title=\"Toggle dark/light theme\" aria-label=\"Toggle theme\">🌙</button> :: <a class=navMenuLink target=\"_blank\" href=\"{$safeBase}/help.php" . ($safeHelp !== '' ? "#{$safeHelp}" : "") . "\">Help</a> :: <a class=\"navMenuLink\" href=\"{$safeBase}/logout.php\">logout</a>";
  print "</td></tr>";

  if (!empty($_SESSION["userid"]) && isset($link) && $link instanceof mysqli) {
    $navUid = (string)$_SESSION["userid"];
    $unreadCnt = 0;
    $pendingReqCnt = 0;
    $jumpGroups = [];

    try {
      $st_u = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM messages WHERE recipient_id = ? AND is_read = 0 AND recipient_deleted = 0");
      if ($st_u) {
        mysqli_stmt_bind_param($st_u, "s", $navUid);
        mysqli_stmt_execute($st_u);
        $ru = mysqli_fetch_assoc(mysqli_stmt_get_result($st_u));
        $unreadCnt = (int)($ru['cnt'] ?? 0);
      }

      $st_p = mysqli_prepare($link, "SELECT COUNT(*) AS cnt FROM accessRequests WHERE targetId = ? AND status = 'pending'");
      if ($st_p) {
        mysqli_stmt_bind_param($st_p, "s", $navUid);
        mysqli_stmt_execute($st_p);
        $rp = mysqli_fetch_assoc(mysqli_stmt_get_result($st_p));
        $pendingReqCnt = (int)($rp['cnt'] ?? 0);
      }

      $st_j = mysqli_prepare(
        $link,
        "SELECT people.userid, people.firstname, people.lastname, people.suffix
         FROM people
         JOIN viewList ON viewList.pid = people.userid
         WHERE viewList.viewer = ? AND people.userid != ?
         GROUP BY people.userid
         ORDER BY people.lastname, people.firstname"
      );
      if ($st_j) {
        mysqli_stmt_bind_param($st_j, "ss", $navUid, $navUid);
        mysqli_stmt_execute($st_j);
        $rj = mysqli_stmt_get_result($st_j);
        while ($jrow = mysqli_fetch_assoc($rj)) {
          $fam = trim((string)($jrow['lastname'] ?? ''));
          if ($fam === '') {
            $fam = 'Other';
          }
          $full = trim($jrow['firstname'] . ' ' . $jrow['lastname'] . ' ' . ($jrow['suffix'] ?? ''));
          $jumpGroups[$fam][] = [
            'userid' => $jrow['userid'],
            'name' => $full
          ];
        }
      }
    } catch (Throwable $e) {
      error_log("Navbar helper query warning: " . $e->getMessage());
    }

    $colspan = ($displayGreeting && !empty($_SESSION["fullname"])) ? 3 : 2;
    print "<tr class=\"navQuickRow\"><td colspan=\"{$colspan}\" class=\"navQuickCell\">";
    print "<div class=\"nav-quick-bar\">";
    print "<div class=\"nav-quick-links\">";
    print "<a class=\"navQuickLink\" href=\"{$safeBase}/home.php\">🏠 Home</a>";
    print "<a class=\"navQuickLink\" href=\"{$safeBase}/modifyList/modifyList.php\">✏️ My List</a>";
    print "<a class=\"navQuickLink\" href=\"{$safeBase}/viewPurchases.php\">🛍️ Purchases</a>";
    print "<a class=\"navQuickLink\" href=\"{$safeBase}/messages.php\">✉️ Messages";
    if ($unreadCnt > 0) {
      print " <span id=\"navUnreadBadge\" class=\"nav-pill-badge\">" . $unreadCnt . "</span>";
    } else {
      print " <span id=\"navUnreadBadge\" class=\"nav-pill-badge\" style=\"display:none;\">0</span>";
    }
    print "</a>";
    print "<a class=\"navQuickLink\" href=\"{$safeBase}/updateAccount/updateAccount.php\">👤 Account";
    if ($pendingReqCnt > 0) {
      print " <span class=\"nav-pill-badge\">" . $pendingReqCnt . "</span>";
    }
    print "</a>";
    if (!empty($_SESSION["admin"]) && (int)$_SESSION["admin"] === 1) {
      print "<a class=\"navQuickLink\" href=\"{$safeBase}/admin.php\">⚙️ Admin</a>";
    }
    print "</div>";

    if (!empty($jumpGroups)) {
      $currentRecip = isset($_GET['recip']) ? (string)$_GET['recip'] : '';
      print "<div class=\"nav-jump-wrap\">";
      print "<select class=\"nav-jump-select\" aria-label=\"Jump to wishlist\" onchange=\"if(this.value){window.location.href=this.value;}\">";
      print "<option value=\"\">Jump to List ▾</option>";
      foreach ($jumpGroups as $famLabel => $members) {
        print "<optgroup label=\"" . escapeText($famLabel) . "\">";
        foreach ($members as $m) {
          $optUrl = $base_url . "/viewList/viewList.php?recip=" . urlencode($m['userid']) . "&name=" . urlencode($m['name']);
          $sel = ($currentRecip !== '' && $currentRecip === $m['userid']) ? " selected" : "";
          print "<option value=\"" . escapeText($optUrl) . "\"{$sel}>" . escapeText($m['name']) . "</option>";
        }
        print "</optgroup>";
      }
      print "</select>";
      print "</div>";
    }

    print "</div>";
    print "</td></tr>";
  }

  print "</table>";
  $themeVer = (int)@filemtime(__DIR__ . '/js/theme.js');
  $toastVer = (int)@filemtime(__DIR__ . '/js/toast.js');
  $bttVer = (int)@filemtime(__DIR__ . '/js/back-to-top.js');
  print "<script type=\"text/javascript\">window.WISHLIST_BASE_URL = " . json_encode($base_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ";</script>";
  print "<script type=\"text/javascript\" src=\"{$safeBase}/js/theme.js?v={$themeVer}\"></script>";
  print "<script type=\"text/javascript\" src=\"{$safeBase}/js/toast.js?v={$toastVer}\"></script>";
  print "<script type=\"text/javascript\" src=\"{$safeBase}/js/back-to-top.js?v={$bttVer}\"></script>";
}

function getFullPath($url) {
  global $base_url;
  $dir = str_replace('\\', '/', dirname($_SERVER["PHP_SELF"] ?? '/'));
  if (!empty($base_url) && preg_match('~^https?://[^/]+~i', $base_url, $m)) {
    $origin = $m[0];
  } else {
    $scheme = isHttpsRequest() ? "https://" : "http://";
    $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', $_SERVER["HTTP_HOST"] ?? 'localhost');
    $origin = $scheme . $host;
  }
  $fp = $origin;
  if ($dir !== "/" && $dir !== ".") {
    $fp .= $dir;
  }
  $fp .= "/" . ltrim((string)$url, '/');
  return $fp;
}

/* $date is assumed to be a date obtained from mysql so it's of the form
 * YYYY-MM-DD hh:mm:ss
 */
function parseDate($date, $shortMonth = 0, $clientLoc = 3) {
  if (empty($date) || $date == '0000-00-00 00:00:00' || $date == '0000-00-00') {
    return "";
  }
  $dt = date_create($date);
  if (!$dt) {
    return "";
  }
  if ($shortMonth) {
    return date_format($dt, "M d, Y g:ia");
  }
  return date_format($dt, "F d, Y g:ia");
}

/* $to should be a comma-separated list of userids.
 * $from is the userid of the sender.
 */
function sendEmail($to, $from, $subject, $message, $debug) {
  global $link;

  $recipients = explode(',', (string)$to);

  foreach ($recipients as $recipient_id) {
    $recipient_id = trim($recipient_id);
    if ($recipient_id === '') {
      continue;
    }

    if ($debug == 1) {
      $stmt = mysqli_prepare($link, "SELECT userid FROM people WHERE admin = '1' ORDER BY userid LIMIT 1");
      if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
          $recipient_id = $row['userid'];
        } else {
          continue;
        }
      }
    }

    $sender_id = !empty($from) ? (string)$from : null;

    $stmt = mysqli_prepare($link, "INSERT INTO messages (recipient_id, sender_id, subject, body, is_read) VALUES (?, ?, ?, ?, 0)");
    if ($stmt) {
      mysqli_stmt_bind_param($stmt, "ssss", $recipient_id, $sender_id, $subject, $message);
      mysqli_stmt_execute($stmt);
    }
  }
  return 1;
}

function cleanString($str) {
  if ($str === null) return "";
  return str_replace("\\'", "'", str_replace("\\\\", "\\", str_replace("\\\"", "\"", (string)$str)));
}

/* convertString - normalize strings for storage. */
function convertString($str) {
  if ($str === null) return "";
  $str = cleanString($str);
  $str = str_replace(array("\r\n", "\r", "\n"), "<br>", (string)$str);
  $str = str_replace(chr(34), "&quot;", $str);
  return trim($str);
}

function formatMessage($body) {
  if ($body === null || $body === '') {
    return '';
  }

  // 1. Strip script and style blocks entirely
  $body = preg_replace('~<script\b[^>]*>.*?</script>~is', '', (string)$body);
  $body = preg_replace('~<style\b[^>]*>.*?</style>~is', '', $body);

  // 2. Strip document structural tags
  $body = preg_replace('~</?(?:html|body|head|meta|title|doctype)\b[^>]*>~is', '', $body);

  // 3. Unescape any previously double-encoded entities
  $body = str_replace(
    array('&amp;quot;', '&amp;#039;', '&amp;&#39;', '&amp;amp;'),
    array('&quot;', '&#039;', '&#39;', '&amp;'),
    $body
  );

  // 4. Escape HTML special characters for security without double-encoding existing entities
  $body = htmlspecialchars($body, ENT_QUOTES, 'UTF-8', false);

  // 5. Restore safe formatting tags & Secret Gift Suggestion layout classes
  $simple_replacements = array(
    '&lt;b&gt;' => '<b>',
    '&lt;/b&gt;' => '</b>',
    '&lt;strong&gt;' => '<strong>',
    '&lt;/strong&gt;' => '</strong>',
    '&lt;i&gt;' => '<i>',
    '&lt;/i&gt;' => '</i>',
    '&lt;em&gt;' => '<em>',
    '&lt;/em&gt;' => '</em>',
    '&lt;u&gt;' => '<u>',
    '&lt;/u&gt;' => '</u>',
    '&lt;s&gt;' => '<s>',
    '&lt;/s&gt;' => '</s>',
    '&lt;strike&gt;' => '<strike>',
    '&lt;/strike&gt;' => '</strike>',
    '&lt;del&gt;' => '<del>',
    '&lt;/del&gt;' => '</del>',
    '&lt;p&gt;' => '<p>',
    '&lt;/p&gt;' => '</p>',
    '&lt;br&gt;' => '<br>',
    '&lt;br/&gt;' => '<br>',
    '&lt;br /&gt;' => '<br>',
    '&lt;hr&gt;' => '<hr>',
    '&lt;hr/&gt;' => '<hr>',
    '&lt;hr /&gt;' => '<hr>',
    '&lt;dir&gt;' => '<dir>',
    '&lt;/dir&gt;' => '</dir>',
    '&lt;ul&gt;' => '<ul>',
    '&lt;/ul&gt;' => '</ul>',
    '&lt;ol&gt;' => '<ol>',
    '&lt;/ol&gt;' => '</ol>',
    '&lt;li&gt;' => '<li>',
    '&lt;/li&gt;' => '</li>',
    '&lt;blockquote&gt;' => '<blockquote>',
    '&lt;/blockquote&gt;' => '</blockquote>',
    '&lt;/font&gt;' => '</font>',
    '&lt;/a&gt;' => '</a>',
    '&lt;/div&gt;' => '</div>',
    '&lt;/span&gt;' => '</span>',
    // Safe Secret Gift Suggestion card containers
    '&lt;div class=&#039;secret-suggestion-card&#039;&gt;' => '<div class="secret-suggestion-card">',
    '&lt;div class=&quot;secret-suggestion-card&quot;&gt;' => '<div class="secret-suggestion-card">',
    '&lt;div class=&#039;suggestion-badge-row&#039;&gt;' => '<div class="suggestion-badge-row">',
    '&lt;div class=&quot;suggestion-badge-row&quot;&gt;' => '<div class="suggestion-badge-row">',
    '&lt;div class=&#039;suggestion-title&#039;&gt;' => '<div class="suggestion-title">',
    '&lt;div class=&quot;suggestion-title&quot;&gt;' => '<div class="suggestion-title">',
    '&lt;div class=&#039;suggestion-notes&#039;&gt;' => '<div class="suggestion-notes">',
    '&lt;div class=&quot;suggestion-notes&quot;&gt;' => '<div class="suggestion-notes">',
    '&lt;div style=&#039;margin: 4px 0;&#039;&gt;' => '<div style="margin: 4px 0;">',
    '&lt;div style=&quot;margin: 4px 0;&quot;&gt;' => '<div style="margin: 4px 0;">',
    '&lt;span class=&#039;suggestion-pill&#039;&gt;' => '<span class="suggestion-pill">',
    '&lt;span class=&quot;suggestion-pill&quot;&gt;' => '<span class="suggestion-pill">',
    '&lt;span class=&#039;price-badge&#039;&gt;' => '<span class="price-badge">',
    '&lt;span class=&quot;price-badge&quot;&gt;' => '<span class="price-badge">',
    '&lt;span class=&#039;store-button-arrow&#039;&gt;' => '<span class="store-button-arrow">',
    '&lt;span class=&quot;store-button-arrow&quot;&gt;' => '<span class="store-button-arrow">'
  );
  $body = str_ireplace(array_keys($simple_replacements), array_values($simple_replacements), $body);

  // 6. Restore <font color="...">
  $body = preg_replace(
    '/&lt;font\s+color=(?:&quot;|&#039;|&#39;|"|\')?([a-zA-Z0-9#]+)(?:&quot;|&#039;|&#39;|"|\')?\s*&gt;/i',
    '<font color="$1">',
    $body
  );

  // 7. Restore safe <a ... href="..."> links (preserving query-string & parameters and optional store-button / actionButton classes)
  $body = preg_replace_callback(
    '/&lt;a\b([^&]*?(?:&quot;|&#039;|&#39;|&amp;)*[^&]*?)&gt;/i',
    function ($matches) {
      $attrStr = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
      if (!preg_match('/\bhref\s*=\s*(?:"([^"]+)"|\'([^\']+)\'|([^\s"\'>]+))/i', $attrStr, $hm)) {
        return $matches[0];
      }
      $rawHref = trim($hm[1] !== '' ? $hm[1] : ($hm[2] !== '' ? $hm[2] : $hm[3]));
      $isActionLink = false;
      $cleanHref = '';

      if (preg_match('~^(?:https?:|mailto:)~i', $rawHref)) {
        $cleanHref = $rawHref;
      } elseif (preg_match('~^(?:\./|\.\./|/)?approvePasswordReset\.php\?[a-zA-Z0-9_=&%-]+$~', $rawHref)) {
        $cleanHref = $rawHref;
        $isActionLink = true;
      } else {
        return $matches[0];
      }

      $classAttr = '';
      if (preg_match('/\bclass\s*=\s*(?:"([^"]+)"|\'([^\']+)\')/i', $attrStr, $cm)) {
        $cls = trim($cm[1] !== '' ? $cm[1] : $cm[2]);
        if ($cls === 'store-button' || $cls === 'actionButton') {
          $classAttr = ' class="' . escapeText($cls) . '"';
        }
      }

      $safeHref = htmlspecialchars($cleanHref, ENT_QUOTES, 'UTF-8');
      if ($isActionLink) {
        return '<a' . $classAttr . ' href="' . $safeHref . '">';
      }
      return '<a' . $classAttr . ' href="' . $safeHref . '" target="_blank" rel="noopener noreferrer">';
    },
    $body
  );

  // 8. Avoid duplicate line breaks after existing HTML block / break tags
  $body = preg_replace('/(<(?:br|\/p|p|hr|dir|\/dir|ul|\/ul|ol|\/ol|li|\/li|div|\/div)[^>]*>)\s*[\r\n]+/i', '$1', $body);

  return nl2br(trim($body));
}

/**
 * Batch-load items & aggregated purchases for all categories of a user in 1 query
 */
function fetchItemsByUser($link, $ownerUserid, $includePurchases = false) {
  $itemsByCategory = [];
  $hasStar = false;

  if ($includePurchases) {
    $sql = "
      SELECT i.*, DATE_FORMAT(i.createDate, '%M %d, %Y') AS createDateFormatted,
             COALESCE(ph.total_bought, 0) AS total_bought,
             ph.split_notes
      FROM items i
      JOIN categories c ON i.cid = c.cid
      LEFT JOIN (
        SELECT iid,
               SUM(quantity) AS total_bought,
               GROUP_CONCAT(NULLIF(splitWith, '') SEPARATOR '\n') AS split_notes
        FROM purchaseHistory
        GROUP BY iid
      ) ph ON ph.iid = i.iid
      WHERE c.userid = ?
      ORDER BY i.cid, i.itemSortOrder
    ";
  } else {
    $sql = "
      SELECT i.*, DATE_FORMAT(i.createDate, '%M %d, %Y') AS createDateFormatted,
             0 AS total_bought,
             NULL AS split_notes
      FROM items i
      JOIN categories c ON i.cid = c.cid
      WHERE c.userid = ?
      ORDER BY i.cid, i.itemSortOrder
    ";
  }

  $stmt = mysqli_prepare($link, $sql);
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $ownerUserid);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    while ($item = mysqli_fetch_assoc($rs)) {
      $cid = (int)$item['cid'];
      if (!isset($itemsByCategory[$cid])) {
        $itemsByCategory[$cid] = [];
      }
      if ($item['addStar'] == '1' && (int)($item['catSortOrder'] ?? 0) != -10000) {
        $hasStar = true;
      }
      $itemsByCategory[$cid][] = $item;
    }
  }

  return ['by_cid' => $itemsByCategory, 'has_star' => $hasStar];
}

/* printList2
 *
 * $recip : The user whose list to print
 * $buyerUserId : The user requesting to view recip's list
 * $name : The full name of $recip
 * $displayPurchases : if equal to 0 then don't indicate if item has been purchased
 */
function printList2($recip, $buyerUserId, $name, $displayPurchases = 1, $prevViewDate = null) {
  global $link, $base_url;

  $access = canViewList($link, $recip, $buyerUserId);
  if (!$access['allowed']) {
    print "<h2>You do not have permission to view this wishlist.</h2>";
    return;
  }

  $readOnly = $access['readOnly'];
  if ($recip == $buyerUserId) {
    $readOnly = 0;
  }

  if ($readOnly) {
    print "<h2>You have Read Only Access to this list</h2>";
  }

  // Batch-load all categories and items (+ purchase summaries if displaying purchases)
  $stmt_cats = mysqli_prepare($link, "SELECT * FROM categories WHERE userid = ? ORDER BY catSortOrder");
  mysqli_stmt_bind_param($stmt_cats, "s", $recip);
  mysqli_stmt_execute($stmt_cats);
  $rs_cats = mysqli_stmt_get_result($stmt_cats);
  $categories = [];
  $publicCids = [];
  while ($crow = mysqli_fetch_assoc($rs_cats)) {
    $categories[] = $crow;
    if ((int)$crow['catSortOrder'] > -1) {
      $publicCids[(int)$crow['cid']] = true;
    }
  }

  $batched = fetchItemsByUser($link, $recip, $displayPurchases != 0);
  $itemsByCid = $batched['by_cid'];

  $hasStar = false;
  foreach ($publicCids as $pcid => $_) {
    if (!empty($itemsByCid[$pcid])) {
      foreach ($itemsByCid[$pcid] as $it) {
        if ($it['addStar'] == '1') {
          $hasStar = true;
          break 2;
        }
      }
    }
  }

  if ($hasStar) {
    $safeBase = escapeText($base_url);
    print "<table><tr><td>A <img src=\"{$safeBase}/images/star.gif\" alt=\"Star\"> indicates an item " . escapeText($name) . " wants most</td></tr></table>&nbsp;";
  }

  // Find potential co-buyers (restricted strictly to related viewers/family members)
  $coBuyers = [];
  if (!$readOnly && $displayPurchases != 0 && $recip != $buyerUserId) {
    $stmt_cobuyers = mysqli_prepare($link, "
      SELECT DISTINCT p.userid, p.firstname, p.lastname
      FROM people p
      WHERE p.userid != ? AND p.userid != ?
        AND (
          p.userid IN (SELECT viewer FROM viewList WHERE pid = ?)
          OR p.userid IN (SELECT pid FROM viewList WHERE viewer = ?)
        )
      ORDER BY p.lastname, p.firstname
    ");
    if ($stmt_cobuyers) {
      mysqli_stmt_bind_param($stmt_cobuyers, "ssss", $recip, $buyerUserId, $recip, $buyerUserId);
      mysqli_stmt_execute($stmt_cobuyers);
      $rs_cobuyers = mysqli_stmt_get_result($stmt_cobuyers);
      while ($cb = mysqli_fetch_assoc($rs_cobuyers)) {
        $coBuyers[] = $cb;
      }
    }
  }

  foreach ($categories as $row) {
    $cid = (int)$row['cid'];
    $catItems = $itemsByCid[$cid] ?? [];
    printCategory($row, $name, $displayPurchases, $readOnly, 0, -1, -1, 0, $coBuyers, $catItems, $prevViewDate);
  }
}

/* printModifyList
 *
 * $userid : the owner of the list to print
 */
function printModifyList($userid) {
  global $link, $activeOwnerFullname;

  $ownerName = !empty($activeOwnerFullname) ? $activeOwnerFullname : ($_SESSION["fullname"] ?? "");

  print "<div id='categorySortableList'>";
  $stmt = mysqli_prepare($link, "SELECT * FROM categories WHERE catSortOrder > -1 AND userid = ? ORDER BY catSortOrder");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);
  $rs = mysqli_stmt_get_result($stmt);

  $categories = [];
  while ($row = mysqli_fetch_assoc($rs)) {
    if ($row["cid"] != "") {
      $categories[] = $row;
    }
  }

  $batched = fetchItemsByUser($link, $userid, false);
  $itemsByCid = $batched['by_cid'];

  $last = count($categories);
  foreach ($categories as $i => $row) {
    $cid = (int)$row["cid"];
    printCategory($row, $ownerName, 0, 0, 1, $i, $last, 0, [], $itemsByCid[$cid] ?? []);
  }
  print "</div>";
}

/* printCategory */
function printCategory($row, $name, $displayPurchases, $readOnly, $modifyList, $i, $last, $printConsider = 0, $coBuyers = [], $preloadedItems = null, $prevViewDate = null) {
  global $link;

  if (!is_array($row) || empty($row)) {
    return;
  }

  $prevViewTs = (!empty($prevViewDate) && $prevViewDate !== '0000-00-00 00:00:00') ? strtotime($prevViewDate) : false;
  $minNewTs = strtotime('2020-12-31 23:59:59');

  // Handle special categories first
  if ($row["catSortOrder"] < 0) {
    if ($row["catSortOrder"] == -1000) {
      if ($row["catSubDescription"] != "") {
        print "\n<table width=100% border=0 cellpadding=0 cellspacing=0>";
        print "<tr><td valign=top>\n";
        print "<table cellpadding=0 cellspacing=0><tr><td>\n";
        print formatMultilineText($row["catSubDescription"]);
        print "</td></tr><tr><td>&nbsp;</td></tr></table>\n";
        print "</td></tr></table>\n";
      }
      return;
    }
    if ($printConsider == 0 && $row["catSortOrder"] == -10000) {
      return;
    }
  }

  $cidInt = (int)($row["cid"] ?? -1);
  $csoInt = (int)($row["catSortOrder"] ?? 0);
  $cnameEsc = escapeText($row["name"] ?? '');

  print "\n<div class='wishlist-category-block' data-category-id='" . $cidInt . "'>\n";
  print "<table width=100% border=0 cellpadding=0 cellspacing=0><tr>\n";
  print "<td valign=top class=\"categoryHeader\">";
  print "\n<table cellpadding=0 cellspacing=0 width=100%>";
  print "<tr>";

  if ($modifyList && $csoInt != -10000) {
?>
 <td NOWRAP valign=top>
 <form method="post" action="editCategory.php" style="display:inline-block;">
 <input type="hidden" name="cso" value="<?php echo $csoInt; ?>">
 <input type="hidden" name="cid" value="<?php echo $cidInt; ?>">
 <input type="hidden" name="cname" value="<?php echo $cnameEsc; ?>">
 <input type="submit" value="✏️" class="actionButton">
 </form>
 <form method="post" action="deleteCategory.php" style="display:inline-block;">
 <input type="hidden" name="cso" value="<?php echo $csoInt; ?>">
 <input type="hidden" name="cid" value="<?php echo $cidInt; ?>">
 <input type="hidden" name="cname" value="<?php echo $cnameEsc; ?>">
 <input type="hidden" name="referrer" value="modifyList.php">
 <input type="submit" value="🗑️" class="actionButtonRed" title="Click this to delete the category">
 </form>
<?php
    if ($last == 1) {
      print "<img width=26px height=26px src=\"../images/space.GIF\" alt=\"\">";
    } else {
      if ($i != 0) {
        print "<form method=\"post\" action=\"moveCategory.php\" style=\"display:inline;\"><input type=\"hidden\" name=\"csrf_token\" value=\"" . escapeText(getCsrfToken()) . "\"><input type=\"hidden\" name=\"dir\" value=\"up\"><input type=\"hidden\" name=\"cid\" value=\"{$cidInt}\"><button type=\"submit\" style=\"background:none;border:none;padding:0;cursor:pointer;\"><img width=13px height=13px border=0 src=\"../images/up_arrow_lightBlue.gif\" title=\"Click this arrow to move the category up\" alt=\"Move up\"></button></form>";
      }
      if ($i != $last - 1) {
        print "<form method=\"post\" action=\"moveCategory.php\" style=\"display:inline;\"><input type=\"hidden\" name=\"csrf_token\" value=\"" . escapeText(getCsrfToken()) . "\"><input type=\"hidden\" name=\"dir\" value=\"down\"><input type=\"hidden\" name=\"cid\" value=\"{$cidInt}\"><button type=\"submit\" style=\"background:none;border:none;padding:0;cursor:pointer;\"><img width=13px height=13px border=0 src=\"../images/down_arrow_lightBlue.gif\" title=\"Click this arrow to move the category down\" alt=\"Move down\"></button></form>";
        if ($i == 0) {
          print "<img width=13px height=13px src=\"../images/space.GIF\" alt=\"\">";
        }
      } else {
        print "<img width=13px height=13px src=\"../images/space.GIF\" alt=\"\">";
      }
    }
    print "</td><td width=100% align=center>";
  } else {
    print "\n<tr><td>\n";
  }

  $text = "";
  if (!empty($row["name"])) {
    $text = "<b>" . escapeText($row["name"]) . "</b> ";
  }

  if (!empty($row["description"])) {
    $text .= formatMultilineText($row["description"]) . ' ';
  }

  if (!empty($row["linkurl"])) {
    $safeCatUrl = sanitizeHttpUrl($row["linkurl"]);
    if ($safeCatUrl !== '') {
      $escapedCatUrl = escapeText($safeCatUrl);
      $catLinkLabel = !empty($row["linkname"]) ? escapeText($row["linkname"]) : $escapedCatUrl;
      $text .= " - <a href=\"" . $escapedCatUrl . "\" target=\"_blank\" rel=\"noopener noreferrer\">" . $catLinkLabel . "</a>";
    }
  }

  if ($text !== "") {
    print $text;
  }

  if (!empty($row["catSubDescription"])) {
    if ($text !== "") {
      print "<br>";
    }
    print formatMultilineText($row["catSubDescription"]);
  }

  print "</td>";
  if ($modifyList) {
?>
<td align="right" style="white-space: nowrap;">
<form method="post" action="addItem.php" style="display:inline-block;">
<input type="hidden" name="cid" value="<?php echo $cidInt; ?>">
<input type="hidden" name="cname" value="<?php echo $cnameEsc; ?>">
<input type="submit" value="➕ Add Item" class="actionButton">
</form>
<?php if ($csoInt != -10000) { ?>
<span class="category-drag-handle" title="Drag to reorder category">☰</span>
<?php } ?>
</td>
<?php
  }
  print "</tr></table>";
  print "</td></tr></table>\n";

  print "<div class='sortable-list' data-category-id='" . $cidInt . "'>";

  if ($preloadedItems === null) {
    $preloadedItems = [];
    if ($cidInt > 0) {
      $stmt = mysqli_prepare($link, "SELECT *, DATE_FORMAT(createDate, '%M %d, %Y') as createDateFormatted FROM items WHERE cid = ? ORDER BY itemSortOrder");
      mysqli_stmt_bind_param($stmt, "i", $cidInt);
      mysqli_stmt_execute($stmt);
      $rs2 = mysqli_stmt_get_result($stmt);
      while ($itemRow = mysqli_fetch_assoc($rs2)) {
        $preloadedItems[] = $itemRow;
      }
    }
  }

  foreach ($preloadedItems as $row2) {
    $iid = (int)$row2["iid"];
    $isoInt = (int)$row2["itemSortOrder"];

    print "\n<div class='sortable-item' data-item-id='" . $iid . "'>\n";
    print "<div class='item-row'>";

    if ($modifyList) {
      print "<div class='item-cell item-buttons'>";
?>
     <form method="post" action="editItem.php" style="display:inline-block;">
     <input type="hidden" name="iso" value="<?php echo $isoInt; ?>">
     <input type="hidden" name="iid" value="<?php echo $iid; ?>">
     <input type="hidden" name="cid" value="<?php echo $cidInt; ?>">
     <input type="hidden" name="cname" value="<?php echo $cnameEsc; ?>">
     <input type="submit" value="✏️" class="actionButton">
     </form>
     <form method="post" action="deleteItem.php" style="display:inline-block;">
     <input type="hidden" name="iso" value="<?php echo $isoInt; ?>">
     <input type="hidden" name="iid" value="<?php echo $iid; ?>">
     <input type="hidden" name="cid" value="<?php echo $cidInt; ?>">
     <input type="hidden" name="cname" value="<?php echo $cnameEsc; ?>">
     <input type="hidden" name="referrer" value="modifyList.php">
     <input type="submit" value="🗑️" class="actionButtonRed" title="Click this to delete the item">
     </form>
<?php
      print "</div>";
    }

    $bought = 0;
    $quantity = (int)$row2["quantity"];
    $splitNotes = [];

    if ($row2["allowCheck"] == "true") {
      if ($modifyList == 0 && $displayPurchases != 0) {
        if (array_key_exists('total_bought', $row2)) {
          $bought = (int)$row2['total_bought'];
          if (!empty($row2['split_notes'])) {
            foreach (explode("\n", $row2['split_notes']) as $sn) {
              if ($sn !== '') {
                $splitNotes[] = escapeText($sn);
              }
            }
          }
        } else {
          $stmt = mysqli_prepare($link, "SELECT quantity, splitWith FROM purchaseHistory WHERE iid = ?");
          mysqli_stmt_bind_param($stmt, "i", $iid);
          mysqli_stmt_execute($stmt);
          $rs = mysqli_stmt_get_result($stmt);
          while ($sumRow = mysqli_fetch_assoc($rs)) {
            $bought += (int)$sumRow["quantity"];
            if (!empty($sumRow["splitWith"])) {
              $splitNotes[] = escapeText($sumRow["splitWith"]);
            }
          }
        }
      }

      print "<div class='item-cell item-checkbox'>";

      if ($quantity > 1) {
        $remaining = max(0, $quantity - $bought);
        if ($remaining > 0 && !$readOnly && $displayPurchases != 0 && !$modifyList) {
          $titley = "title=\"Select the number you have purchased\"";
          print "<select $titley name=\"sel" . $iid . "\" onchange=\"toggleSplitInput(" . $iid . ", this.value > 0)\">";
          for ($k = 0; $k <= $remaining; $k++) {
            print "<option value='" . $k . "'>" . $k . "</option>";
          }
          print "</select>";
        } elseif ($modifyList || $displayPurchases == 0) {
          print "<select disabled><option>0</option></select>";
        } else {
          print "<select disabled><option>0</option></select>";
        }
        print "</div><div class='item-cell item-content'>";
      } else {
        if ($bought > 0 && $displayPurchases != 0) {
          print "<span class='purchased-checkbox-x' title='This item has been purchased'>&#10006;</span>";
        } else {
          $disabledAttr = ($readOnly || $modifyList || $displayPurchases == 0) ? "disabled" : "";
          $titley = (!$readOnly && $displayPurchases == 1 && !$modifyList) ? "title=\"Click here if you purchased this item\"" : "";
          print "<input $titley type='checkbox' name='chk" . $iid . "' " . $disabledAttr . " onclick=\"toggleSplitInput(" . $iid . ", this.checked)\" onchange=\"toggleSplitInput(" . $iid . ", this.checked)\">";
        }
        print "</div><div class='item-cell item-content'>";
      }
    } else {
      print "<div class='item-cell item-content' colspan=2>";
    }

    $itemCreateTs = !empty($row2['createDate']) ? strtotime($row2['createDate']) : false;
    $isFullyBought = ($row2['allowCheck'] == 'true' && $displayPurchases != 0 && $bought >= $quantity);
    $isNewItem = (!$modifyList && $prevViewTs && $itemCreateTs && $itemCreateTs > $minNewTs && $itemCreateTs > $prevViewTs && !$isFullyBought);

    if ($bought > 0 && $quantity == 1 && $displayPurchases != 0) {
      print "<s>";
    }
    printItem($row2, $name, $quantity, $bought, $row2['createDateFormatted'] ?? '', $isNewItem);
    if ($bought > 0 && $quantity == 1 && $displayPurchases != 0) {
      print "</s>";
    }
    if (!empty($splitNotes) && $displayPurchases != 0) {
      print " <span class='group-gift-badge' title='Group gift'>👥 Split with " . implode(', ', $splitNotes) . "</span>";
    }

    if (!$readOnly && !$modifyList && $displayPurchases != 0 && ($quantity - $bought) > 0) {
      print "<div id='split_box_" . $iid . "' class='split-input-box' style='display:none;'>";
      print "<label class='split-subtext' style='display:block; margin-bottom: 4px;'>👥 Split this gift with site users:</label>";
      if (!empty($coBuyers)) {
        print "<select name='split_users_" . $iid . "[]' id='split_users_" . $iid . "' class='split-user-select' multiple data-placeholder='Select co-buyers...' style='width: 100%; max-width: 320px;'>";
        foreach ($coBuyers as $user) {
          print "<option value='" . escapeText($user["userid"]) . "'>" . escapeText($user["firstname"] . " " . $user["lastname"]) . "</option>";
        }
        print "</select>";
      }
      print "<div style='margin-top: 5px;'>";
      print "<input type='text' name='split_note_" . $iid . "' class='split-input' placeholder='Additional note or other non-user (optional)' style='font-size: 11px; padding: 4px 6px;'>";
      print "</div>";
      print "</div>";
    }

    print "</div>";

    if ($modifyList) {
      print "<div class='item-cell item-handle drag-handle'>&#9776;</div>";
    }

    print "</div>";
    print "</div>\n";
  }
  print "</div>";

  if (!$modifyList) {
    print "<div>&nbsp;</div>";
  }
  print "</div>\n";
}

function formatStoreButton($url, $linkText = "") {
  $safeUrl = sanitizeHttpUrl($url);
  if ($safeUrl === '') {
    return "";
  }
  $host = strtolower(parse_url($safeUrl, PHP_URL_HOST) ?? '');
  $domain = preg_replace('/^www\./', '', $host);

  $storeName = "";
  $icon = "🛒 ";

  if (preg_match('/(?:^|\.)amazon\./', $domain)) {
    $storeName = "Amazon";
  } elseif (preg_match('/(?:^|\.)target\.com/', $domain)) {
    $storeName = "Target";
  } elseif (preg_match('/(?:^|\.)walmart\.com$/', $domain)) {
    $storeName = "Walmart";
  } elseif (preg_match('/(?:^|\.)apple\.com$/', $domain)) {
    $storeName = "Apple";
  } elseif (preg_match('/(?:^|\.)etsy\.com$/', $domain)) {
    $storeName = "Etsy";
  } elseif (preg_match('/(?:^|\.)bestbuy\.com$/', $domain)) {
    $storeName = "Best Buy";
  } elseif (preg_match('/(?:^|\.)ebay\./', $domain)) {
    $storeName = "eBay";
  } elseif (preg_match('/(?:^|\.)lego\.com$/', $domain)) {
    $storeName = "LEGO";
  } elseif (preg_match('/(?:^|\.)barnesandnoble\.com$/', $domain)) {
    $storeName = "Barnes & Noble";
  } elseif (preg_match('/(?:^|\.)costco\.com/', $domain)) {
    $storeName = "Costco";
  } elseif (preg_match('/(?:^|\.)ikea\.com$/', $domain)) {
    $storeName = "IKEA";
  } elseif (preg_match('/(?:^|\.)bigw\.com\.au$/', $domain)) {
    $storeName = "Big W";
  } elseif (preg_match('/(?:^|\.)kmart\.com/', $domain)) {
    $storeName = "Kmart";
  } elseif (preg_match('/(?:^|\.)ebgames\./', $domain)) {
    $storeName = "EB Games";
  } elseif (preg_match('/(?:^|\.)jbhifi\.com/', $domain)) {
    $storeName = "JB Hi-Fi";
  } elseif (preg_match('/(?:^|\.)bunnings\.com/', $domain)) {
    $storeName = "Bunnings";
  } elseif (preg_match('/(?:^|\.)officeworks\.com\.au$/', $domain)) {
    $storeName = "Officeworks";
  }

  if (empty($storeName)) {
    if (!empty($linkText) && strtolower(trim($linkText)) !== "link") {
      $label = escapeText($linkText);
    } elseif (!empty($domain)) {
      $label = escapeText($domain);
    } else {
      $label = "Visit Store";
    }
  } else {
    $label = "Find at " . $storeName;
  }

  $cleanUrl = escapeText($safeUrl);
  return "<a class='store-button' target='_blank' rel='noopener noreferrer' href='{$cleanUrl}' title='Open {$cleanUrl}'>{$icon}{$label} <span class='store-button-arrow'>↗</span></a>";
}

function printItem($row2, $name, $quantity, $bought, $createDateFormatted, $isNewItem = false) {
  global $base_url, $base_dir;

  $text = "";
  $dash = 0;

  if (($row2["addStar"] ?? '0') == '1') {
    $text = "<img src=\"" . escapeText($base_url) . "/images/star.gif\" alt=\"Star\">";
  }

  if (!empty($row2["image"])) {
    $imgFile = "image-" . (int)$row2["iid"] . ".jpg";
    $diskPath = $base_dir . "/uploads/" . $imgFile;
    $ver = is_file($diskPath) ? ("?v=" . filemtime($diskPath)) : "";
    $imgUrl = escapeText($base_url . "/uploads/" . $imgFile . $ver);
    $imgTitle = escapeText($row2["title"] ?? '');
    $text .= "<div class=\"item-thumbnail-wrapper\">";
    $text .= "<a href=\"" . $imgUrl . "\" data-lightbox=\"" . $imgUrl . "\" title=\"" . $imgTitle . "\">";
    $text .= "<img src=\"" . $imgUrl . "\" class=\"item-thumbnail\" alt=\"" . $imgTitle . "\" loading=\"lazy\" decoding=\"async\">";
    $text .= "</a>";
    $text .= "</div>";
  }

  if (!empty($row2["title"])) {
    $text .= "<i>" . escapeText($row2["title"]) . "</i>";
    if ($isNewItem) {
      $text .= " <span class=\"new-item-badge no-print\" title=\"Added since your last visit\">✨ NEW</span>";
    }
    $dash = 1;
  } elseif ($isNewItem) {
    $text .= "<span class=\"new-item-badge no-print\" title=\"Added since your last visit\">✨ NEW</span> ";
  }

  if (!empty($row2["description"])) {
    if ($dash) {
      $text .= " - ";
    }
    $text .= formatMultilineText($row2["description"]);
    $dash = 1;
  }

  if (!empty($row2["price"]) && $row2["price"] != "0.00") {
    if ($dash) {
      $text .= " - ";
    }
    $text .= "<span class='price-badge'>$" . escapeText($row2["price"]) . "</span>";
    $dash = 1;
  }

  if (!empty($row2["link1url"])) {
    $btn1 = formatStoreButton($row2["link1url"], $row2["link1"] ?? "");
    if ($btn1 !== "") {
      if ($dash) {
        $text .= " ";
      }
      $text .= $btn1;
      $dash = 1;
    }
  }

  print $text;

  if (!empty($row2['createDate']) && strtotime($row2['createDate']) > strtotime('2020-12-31')) {
    print "<span class='info-icon' data-create-date='" . escapeText($createDateFormatted) . "'>&#9432;</span>";
  }

  $subdesc = $row2["subdesc"] ?? "";
  $link2 = $row2["link2"] ?? "";
  $link2url = $row2["link2url"] ?? "";
  $link3 = $row2["link3"] ?? "";
  $link3url = $row2["link3url"] ?? "";

  $btn2 = !empty($link2url) ? formatStoreButton($link2url, $link2) : "";
  $btn3 = !empty($link3url) ? formatStoreButton($link3url, $link3) : "";

  if ($subdesc != "" || $btn2 != "" || $btn3 != "" || $quantity > 1) {
    print "<div class='item-details'>";

    if ($subdesc != "") {
      print "<div class='item-detail-row'>";
      print formatMultilineText($subdesc);
      print "</div>";
    }

    if ($btn2 != "") {
      print "<div class='item-detail-row'>";
      print $btn2;
      print "</div>";
    }

    if ($btn3 != "") {
      print "<div class='item-detail-row'>";
      print $btn3;
      print "</div>";
    }

    if ($quantity > 1) {
      $remaining = max(0, (int)$quantity - (int)$bought);
      print "<div class='item-detail-row'>";
      print "<font color=red>" . escapeText((string)$name) . " wants " . (int)$quantity . " of these, there are " . $remaining . " left to be purchased</font>";
      print "</div>";
    }
    print "</div>";
  } else {
    print "<br>";
  }
}

/* deleteItem deletes $iid and any purchaseHistory entries for $iid.
 * If $iid has been bought and the item is deleted within 20 days before $userid's bday
 * or Christmas, an internal message is sent to the buyer(s) and co-buyers.
 */
function deleteItem($iid, $userid, $fullname, $base_dir, $suppressNotify = false) {
  global $link;
  $iid = (int)$iid;

  $stmt = mysqli_prepare($link, "SELECT items.title, items.description, purchaseHistory.userid, purchaseHistory.splitUsers FROM items JOIN purchaseHistory ON items.iid = purchaseHistory.iid WHERE items.iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);
  $rs1 = mysqli_stmt_get_result($stmt);

  $purchasedRows = [];
  while ($prow = mysqli_fetch_assoc($rs1)) {
    $purchasedRows[] = $prow;
  }

  $item_stmt = mysqli_prepare($link, "SELECT image, itemSortOrder, cid FROM items WHERE iid = ?");
  mysqli_stmt_bind_param($item_stmt, "i", $iid);
  mysqli_stmt_execute($item_stmt);
  $item_res = mysqli_stmt_get_result($item_stmt);
  $item_row = mysqli_fetch_assoc($item_res);

  if (!empty($item_row['image'])) {
    $safeImg = basename($item_row['image']);
    $uploadFile = rtrim($base_dir, '/') . '/uploads/' . $safeImg;
    if (is_file($uploadFile)) {
      @unlink($uploadFile);
    }
  }

  $stmt = mysqli_prepare($link, "DELETE FROM purchaseHistory WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);

  $stmt = mysqli_prepare($link, "DELETE FROM itemPriceHistory WHERE iid = ?");
  if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $iid);
    mysqli_stmt_execute($stmt);
  }

  $stmt = mysqli_prepare($link, "DELETE FROM items WHERE iid = ?");
  mysqli_stmt_bind_param($stmt, "i", $iid);
  mysqli_stmt_execute($stmt);

  if ($item_row) {
    $stmt = mysqli_prepare($link, "UPDATE items SET itemSortOrder = itemSortOrder - 1 WHERE itemSortOrder > ? AND cid = ?");
    mysqli_stmt_bind_param($stmt, "ii", $item_row["itemSortOrder"], $item_row["cid"]);
    mysqli_stmt_execute($stmt);
  }

  $stmt = mysqli_prepare($link, "UPDATE people SET lastModDate = NOW() WHERE userid = ?");
  mysqli_stmt_bind_param($stmt, "s", $userid);
  mysqli_stmt_execute($stmt);

  if (!$suppressNotify && !empty($purchasedRows)) {
    $stmt = mysqli_prepare($link, "SELECT firstname, lastname, suffix, bday, bmonth FROM people WHERE userid = ?");
    mysqli_stmt_bind_param($stmt, "s", $userid);
    mysqli_stmt_execute($stmt);
    $rs2 = mysqli_stmt_get_result($stmt);

    $months = array(
      1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May',
      6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September',
      10 => 'October', 11 => 'November', 12 => 'December'
    );

    $todayStart = strtotime('today');
    $currentYear = (int)date('Y');
    $diff = null;

    if ($row2 = mysqli_fetch_assoc($rs2)) {
      if (empty($fullname)) {
        $fullname = trim($row2["firstname"] . " " . $row2["lastname"] . " " . ($row2["suffix"] ?? ""));
      }
      $bmonth = array_search($row2["bmonth"] ?? '', $months, true);
      $bday = (int)($row2["bday"] ?? 0);
      if ($bmonth !== false && $bday >= 1 && $bday <= 31) {
        $bdayTs = mktime(0, 0, 0, $bmonth, $bday, $currentYear);
        if ($bdayTs < $todayStart) {
          $bdayTs = mktime(0, 0, 0, $bmonth, $bday, $currentYear + 1);
        }
        $diff = (int)round(($bdayTs - $todayStart) / 86400);
      }
    }

    $xmasTs = mktime(0, 0, 0, 12, 25, $currentYear);
    if ($xmasTs < $todayStart) {
      $xmasTs = mktime(0, 0, 0, 12, 25, $currentYear + 1);
    }
    $xmas = (int)round(($xmasTs - $todayStart) / 86400);

    $nearBday = ($diff !== null && $diff >= 0 && $diff <= 20);
    $nearXmas = ($xmas >= 0 && $xmas <= 20);

    if ($nearBday || $nearXmas) {
      $notifySet = [];
      foreach ($purchasedRows as $prow) {
        if (!empty($prow["userid"]) && $prow["userid"] !== $userid) {
          $notifySet[$prow["userid"]] = true;
        }
        if (!empty($prow["splitUsers"])) {
          foreach (explode(',', $prow["splitUsers"]) as $su) {
            $su = trim($su);
            if ($su !== '' && $su !== $userid) {
              $notifySet[$su] = true;
            }
          }
        }
      }

      if (!empty($notifySet)) {
        $to = implode(',', array_keys($notifySet));
        $row1 = $purchasedRows[0];
        $safeOwner = escapeText($fullname);
        $safeTitle = escapeText($row1["title"]);
        $safeDesc = escapeText($row1["description"]);

        $countdownParts = [];
        if ($diff !== null) {
          $countdownParts[] = $diff . " days until " . $safeOwner . "'s birthday";
        }
        $countdownParts[] = $xmas . " days until Christmas";

        $subject = $fullname . "'s WishList has been modified";
        $message = "<p><font color=indianred><b>" . $safeOwner .
          "</b></font> has <b>deleted</b> the following item that you have already bought <dir>" .
          "<i>" . $safeTitle . "</i>" . ($safeDesc !== '' ? " - " . $safeDesc : "") . "</dir>" .
          "Remember, " . $safeOwner . " had no way of knowing that the item " .
          "had been purchased. This warning is sent out if a person deletes an item (which has been " .
          "purchased) from their list within 20 days of their birthday or Christmas.<br><br>" .
          implode(" and ", $countdownParts);
        sendEmail($to, "", $subject, $message, 0);
      }
    }
  }
  return "Success";
}

define('THUMBNAIL_IMAGE_MAX_WIDTH', 600);
define('THUMBNAIL_IMAGE_MAX_HEIGHT', 600);
define('IMAGE_UPLOAD_MAX_PIXELS', 25000000); // 25 MP decompression-bomb guard

function generate_image_thumbnail($source_image_path, $thumbnail_image_path) {
  $imgInfo = @getimagesize($source_image_path);
  if (!$imgInfo) {
    return false;
  }
  list($source_image_width, $source_image_height, $source_image_type) = $imgInfo;
  if ($source_image_width <= 0 || $source_image_height <= 0 || ($source_image_width * $source_image_height) > IMAGE_UPLOAD_MAX_PIXELS) {
    return false;
  }

  $source_gd_image = false;
  switch ($source_image_type) {
    case IMAGETYPE_GIF:
      $source_gd_image = @imagecreatefromgif($source_image_path);
      break;
    case IMAGETYPE_JPEG:
      $source_gd_image = @imagecreatefromjpeg($source_image_path);
      break;
    case IMAGETYPE_PNG:
      $source_gd_image = @imagecreatefrompng($source_image_path);
      break;
    case IMAGETYPE_WEBP:
      if (function_exists('imagecreatefromwebp')) {
        $source_gd_image = @imagecreatefromwebp($source_image_path);
      }
      break;
  }
  if ($source_gd_image === false || !$source_gd_image) {
    return false;
  }

  // Auto-rotate JPEG based on EXIF orientation and refresh dimensions after 90-degree turns
  if ($source_image_type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
    $exif = @exif_read_data($source_image_path);
    if (!empty($exif['Orientation'])) {
      $rotated = false;
      switch ((int)$exif['Orientation']) {
        case 3:
          $rotated = @imagerotate($source_gd_image, 180, 0);
          break;
        case 6:
          $rotated = @imagerotate($source_gd_image, -90, 0);
          break;
        case 8:
          $rotated = @imagerotate($source_gd_image, 90, 0);
          break;
      }
      if ($rotated !== false && $rotated) {
        $source_gd_image = $rotated;
        $source_image_width = imagesx($source_gd_image);
        $source_image_height = imagesy($source_gd_image);
      }
    }
  }

  $source_aspect_ratio = $source_image_width / $source_image_height;
  $thumbnail_aspect_ratio = THUMBNAIL_IMAGE_MAX_WIDTH / THUMBNAIL_IMAGE_MAX_HEIGHT;
  if ($source_image_width <= THUMBNAIL_IMAGE_MAX_WIDTH && $source_image_height <= THUMBNAIL_IMAGE_MAX_HEIGHT) {
    $thumbnail_image_width = $source_image_width;
    $thumbnail_image_height = $source_image_height;
  } elseif ($thumbnail_aspect_ratio > $source_aspect_ratio) {
    $thumbnail_image_width = max(1, (int)(THUMBNAIL_IMAGE_MAX_HEIGHT * $source_aspect_ratio));
    $thumbnail_image_height = THUMBNAIL_IMAGE_MAX_HEIGHT;
  } else {
    $thumbnail_image_width = THUMBNAIL_IMAGE_MAX_WIDTH;
    $thumbnail_image_height = max(1, (int)(THUMBNAIL_IMAGE_MAX_WIDTH / $source_aspect_ratio));
  }

  $thumbnail_gd_image = imagecreatetruecolor($thumbnail_image_width, $thumbnail_image_height);
  // Fill canvas with white so transparent PNG/GIF/WebP pixels do not turn black in JPEG
  $white = imagecolorallocate($thumbnail_gd_image, 255, 255, 255);
  imagefill($thumbnail_gd_image, 0, 0, $white);

  imagecopyresampled(
    $thumbnail_gd_image,
    $source_gd_image,
    0, 0, 0, 0,
    $thumbnail_image_width,
    $thumbnail_image_height,
    $source_image_width,
    $source_image_height
  );
  $saved = imagejpeg($thumbnail_gd_image, $thumbnail_image_path, 90);
  if (PHP_VERSION_ID < 80000) {
    imagedestroy($source_gd_image);
    imagedestroy($thumbnail_gd_image);
  }
  return $saved ? true : false;
}

function process_image_upload($field, $destination) {
  if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    return false;
  }
  $temp_image_path = $_FILES[$field]['tmp_name'] ?? '';
  if ($temp_image_path === '' || !is_uploaded_file($temp_image_path)) {
    return false;
  }
  $imgInfo = @getimagesize($temp_image_path);
  if (!$imgInfo) {
    return false;
  }
  $temp_image_type = $imgInfo[2] ?? null;
  $allowed = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];
  if (defined('IMAGETYPE_WEBP')) {
    $allowed[] = IMAGETYPE_WEBP;
  }
  if (!in_array($temp_image_type, $allowed, true)) {
    return false;
  }
  $result = generate_image_thumbnail($temp_image_path, $destination);
  return $result ? array($temp_image_path, $destination) : false;
}

/**
 * SSRF-Safe Public URL Fetching & Remote Thumbnail Import Helpers
 */
function isPublicIpAddress($ip) {
  if (!is_string($ip) || $ip === '') {
    return false;
  }
  return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function resolveSafePublicHostIps($host) {
  $cleanHost = strtolower(trim((string)$host, "[] \t\n\r\0\x0B."));
  if ($cleanHost === '' || $cleanHost === 'localhost' || preg_match('/\.(?:local|internal|localhost|arpa|lan|home)$/i', $cleanHost)) {
    return [];
  }

  if (filter_var($cleanHost, FILTER_VALIDATE_IP) !== false) {
    return isPublicIpAddress($cleanHost) ? [$cleanHost] : [];
  }

  $ips = [];
  if (function_exists('dns_get_record')) {
    $records = @dns_get_record($cleanHost, DNS_A + DNS_AAAA);
    if (is_array($records)) {
      foreach ($records as $rec) {
        if (!empty($rec['ip'])) {
          $ips[] = $rec['ip'];
        }
        if (!empty($rec['ipv6'])) {
          $ips[] = $rec['ipv6'];
        }
      }
    }
  }
  if (empty($ips)) {
    $v4 = @gethostbynamel($cleanHost);
    if (is_array($v4)) {
      $ips = array_merge($ips, $v4);
    }
  }

  $ips = array_values(array_unique($ips));
  if (empty($ips)) {
    return [];
  }
  // Reject if ANY resolved address is private, loopback, link-local, or reserved
  foreach ($ips as $ip) {
    if (!isPublicIpAddress($ip)) {
      return [];
    }
  }
  return $ips;
}

function resolveRelativeHttpUrl($baseUrl, $relativeUrl) {
  $rel = trim((string)$relativeUrl);
  if ($rel === '') {
    return '';
  }
  if (preg_match('#^https?://#i', $rel)) {
    return $rel;
  }
  $baseParts = @parse_url($baseUrl);
  if (!$baseParts || empty($baseParts['scheme']) || empty($baseParts['host'])) {
    return '';
  }
  $scheme = strtolower($baseParts['scheme']);
  if ($scheme !== 'http' && $scheme !== 'https') {
    return '';
  }
  if (strpos($rel, '//') === 0) {
    return $scheme . ':' . $rel;
  }
  $portPart = !empty($baseParts['port']) ? (':' . (int)$baseParts['port']) : '';
  $origin = $scheme . '://' . $baseParts['host'] . $portPart;
  if ($rel[0] === '/') {
    return $origin . $rel;
  }
  $basePath = $baseParts['path'] ?? '/';
  $dir = preg_replace('#/[^/]*$#', '/', $basePath);
  return $origin . $dir . $rel;
}

function fetchSafePublicUrl($rawUrl, $maxBytes = 1572864, $timeoutSec = 4, $abortOnOverflow = false) {
  if (!function_exists('curl_init')) {
    return ['ok' => false, 'error' => 'HTTP client unavailable.'];
  }
  $currentUrl = trim((string)$rawUrl);
  if ($currentUrl === '') {
    return ['ok' => false, 'error' => 'Missing URL.'];
  }
  if (!preg_match('#^https?://#i', $currentUrl)) {
    $currentUrl = 'https://' . $currentUrl;
  }

  $maxRedirects = 4;
  for ($hop = 0; $hop <= $maxRedirects; $hop++) {
    $parts = @parse_url($currentUrl);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
      return ['ok' => false, 'error' => 'Invalid URL format.'];
    }
    $scheme = strtolower($parts['scheme']);
    if ($scheme !== 'http' && $scheme !== 'https') {
      return ['ok' => false, 'error' => 'Only http and https links are allowed.'];
    }
    if (!empty($parts['user']) || !empty($parts['pass'])) {
      return ['ok' => false, 'error' => 'Credentials in URLs are not allowed.'];
    }
    $defaultPort = ($scheme === 'https') ? 443 : 80;
    $port = isset($parts['port']) ? (int)$parts['port'] : $defaultPort;
    if ($port !== 80 && $port !== 443) {
      return ['ok' => false, 'error' => 'Non-standard ports are not allowed.'];
    }

    $host = $parts['host'];
    $safeIps = resolveSafePublicHostIps($host);
    if (empty($safeIps)) {
      return ['ok' => false, 'error' => 'Could not resolve a public address for that link.'];
    }
    $pinnedIp = $safeIps[0];
    $resolveHost = trim($host, '[]');
    $resolveEntry = $resolveHost . ':' . $port . ':' . (strpos($pinnedIp, ':') !== false ? '[' . $pinnedIp . ']' : $pinnedIp);

    $body = '';
    $overflowed = false;
    $redirectLocation = '';
    $contentType = '';

    $ch = curl_init($currentUrl);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => false,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
      CURLOPT_CONNECTTIMEOUT => 3,
      CURLOPT_TIMEOUT => max(2, (int)$timeoutSec),
      CURLOPT_ENCODING => '',
      CURLOPT_RESOLVE => [$resolveEntry],
      CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; WishListMetadataPreview/1.0)',
      CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*;q=0.8,*/*;q=0.5',
        'Accept-Language: en-US,en;q=0.9'
      ],
      CURLOPT_HEADERFUNCTION => function($ch, $headerLine) use (&$redirectLocation, &$contentType) {
        $len = strlen($headerLine);
        $colonPos = strpos($headerLine, ':');
        if ($colonPos !== false) {
          $hName = strtolower(trim(substr($headerLine, 0, $colonPos)));
          $hVal = trim(substr($headerLine, $colonPos + 1));
          if ($hName === 'location') {
            $redirectLocation = $hVal;
          } elseif ($hName === 'content-type') {
            $contentType = $hVal;
          }
        }
        return $len;
      },
      CURLOPT_WRITEFUNCTION => function($ch, $chunk) use (&$body, $maxBytes, $abortOnOverflow, &$overflowed) {
        $chunkLen = strlen($chunk);
        $remaining = $maxBytes - strlen($body);
        if ($remaining <= 0) {
          $overflowed = true;
          return 0;
        }
        if ($chunkLen > $remaining) {
          if (!$abortOnOverflow) {
            $body .= substr($chunk, 0, $remaining);
          }
          $overflowed = true;
          return 0;
        }
        $body .= $chunk;
        return $chunkLen;
      }
    ]);

    $execOk = @curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if (PHP_VERSION_ID < 80000) {
      curl_close($ch);
    }

    if (in_array($statusCode, [301, 302, 303, 307, 308], true) && $redirectLocation !== '') {
      if ($hop === $maxRedirects) {
        return ['ok' => false, 'error' => 'Too many redirects.'];
      }
      $nextUrl = resolveRelativeHttpUrl($currentUrl, $redirectLocation);
      if ($nextUrl === '') {
        return ['ok' => false, 'error' => 'Invalid redirect target.'];
      }
      $currentUrl = $nextUrl;
      continue;
    }

    // CURLE_WRITE_ERROR (23) occurs intentionally when we stop early after reaching $maxBytes on large HTML pages
    if ($abortOnOverflow && $overflowed) {
      return ['ok' => false, 'error' => 'Remote file exceeds maximum allowed size.'];
    }
    if (!$execOk && !(!$abortOnOverflow && $overflowed && $curlErrNo === 23)) {
      return ['ok' => false, 'error' => 'Could not connect to store page.'];
    }
    if ($statusCode < 200 || $statusCode >= 300) {
      return ['ok' => false, 'error' => 'Store page blocked automated preview (HTTP ' . $statusCode . ').', 'status' => $statusCode];
    }

    return [
      'ok' => true,
      'body' => $body,
      'final_url' => $currentUrl,
      'content_type' => $contentType,
      'status' => $statusCode
    ];
  }

  return ['ok' => false, 'error' => 'Failed to fetch URL.'];
}

function import_remote_image_to_thumbnail($imageUrl, $destination) {
  $cleanUrl = sanitizeHttpUrl(trim((string)$imageUrl), false);
  if ($cleanUrl === '') {
    return false;
  }
  $fetched = fetchSafePublicUrl($cleanUrl, 5242880, 5, true);
  if (empty($fetched['ok']) || empty($fetched['body'])) {
    return false;
  }

  $tmpFile = @tempnam(sys_get_temp_dir(), 'wlimg_');
  if ($tmpFile === false) {
    return false;
  }

  $savedOk = false;
  try {
    if (@file_put_contents($tmpFile, $fetched['body']) === false) {
      return false;
    }
    if (function_exists('finfo_open')) {
      $finfo = @finfo_open(FILEINFO_MIME_TYPE);
      if ($finfo) {
        $mime = (string)@finfo_file($finfo, $tmpFile);
        if (PHP_VERSION_ID < 80500) {
          @finfo_close($finfo);
        }
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(strtolower($mime), $allowedMimes, true)) {
          return false;
        }
      }
    }
    $imgInfo = @getimagesize($tmpFile);
    if (!$imgInfo) {
      return false;
    }
    $allowedTypes = [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG];
    if (defined('IMAGETYPE_WEBP')) {
      $allowedTypes[] = IMAGETYPE_WEBP;
    }
    if (!in_array($imgInfo[2] ?? null, $allowedTypes, true)) {
      return false;
    }
    $savedOk = generate_image_thumbnail($tmpFile, $destination);
  } finally {
    if (is_file($tmpFile)) {
      @unlink($tmpFile);
    }
  }
  return $savedOk;
}

/**
 * CSRF Protection Functions
 */
function ensureCsrfSession() {
  if (session_status() === PHP_SESSION_NONE) {
    $timeout = 604800;
    @ini_set('session.gc_maxlifetime', (string)$timeout);
    if (PHP_VERSION_ID >= 70300) {
      session_set_cookie_params([
        'lifetime' => $timeout,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isHttpsRequest()
      ]);
    }
    session_name("WishListSite");
    session_start();
  }
}

function getCsrfToken() {
  ensureCsrfSession();
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
  ensureCsrfSession();
  if (empty($token) || empty($_SESSION['csrf_token']) || !is_string($token)) {
    return false;
  }
  return hash_equals($_SESSION['csrf_token'], $token);
}

function verifyCsrfOrDie($postOnly = false) {
  if ($postOnly && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    die("Method not allowed.");
  }
  $token = $_POST['csrf_token'] ?? ($postOnly ? '' : ($_GET['csrf_token'] ?? ''));
  if (!validateCsrfToken($token)) {
    http_response_code(403);
    die("Security validation failed (invalid or missing CSRF token). Please refresh the page and try again.");
  }
}
