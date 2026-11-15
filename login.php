<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (!headers_sent()) {
  header("X-Frame-Options: SAMEORIGIN");
  header("X-Content-Type-Options: nosniff");
  header("Referrer-Policy: strict-origin-when-cross-origin");
}

@(include __DIR__ . '/config.php');

if (!isset($base_dir)) {
  header("Location: admin.php?setup=true");
  exit;
}

$base_dir = rtrim($base_dir, '/');
$base_url = rtrim($base_url ?? '', '/');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

$session_timeout = 604800;
@ini_set('session.gc_maxlifetime', (string)$session_timeout);

if (PHP_VERSION_ID >= 70300) {
  session_set_cookie_params([
    'lifetime' => $session_timeout,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => $isHttps
  ]);
}
session_name("WishListSite");
session_start();

if (isset($_SESSION['userid'])) {
  header("Location: home.php");
  exit;
}

$isError = false;
if (!empty($_GET['expired'])) {
  $errorMessage = "Your session has expired due to inactivity. Please sign in again.";
  $isError = true;
} elseif (!empty($_GET['loggedout'])) {
  $errorMessage = "You have been signed out.";
} else {
  $errorMessage = "Please Sign In";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_POST["userid"]) || !empty($_POST["password"]))) {
  $submitted_userid = trim((string)($_POST["userid"] ?? ""));
  $submitted_password = (string)($_POST["password"] ?? "");

  if ($submitted_userid === "" || $submitted_password === "") {
    $errorMessage = "Please enter both your WishList ID and password.";
    $isError = true;
  } else {
    $link = @mysqli_connect($db_loc, $db_userid, $db_pass);
    if (!$link) {
      error_log("Database connection failed: " . mysqli_connect_error());
      die("<p><font size=+2>Danger Will Robinson! It looks like the database is down.<p>Please try again later.</font>");
    }
    mysqli_select_db($link, $db_name);
    mysqli_set_charset($link, "utf8mb4");

    // Check schema migrations safely
    try {
      $current_version = 0;
      $res_ver = @mysqli_query($link, "SELECT version FROM schema_version LIMIT 1");
      if ($res_ver && mysqli_num_rows($res_ver) > 0) {
        $vrow = mysqli_fetch_assoc($res_ver);
        $current_version = (int)$vrow['version'];
      }

      $schema_files = glob($base_dir . '/db/V*_schema.sql') ?: [];
      $highest_version = 0;
      foreach ($schema_files as $file) {
        if (preg_match('/V(\d+)_schema\.sql$/', basename($file), $matches)) {
          $version = (int)$matches[1];
          if ($version > $highest_version) {
            $highest_version = $version;
          }
        }
      }

      if ($current_version > 0 && $current_version < $highest_version) {
        for ($i = $current_version + 1; $i <= $highest_version; $i++) {
          $schema_file = $base_dir . '/db/V' . $i . '_schema.sql';
          if (is_file($schema_file)) {
            $sql = preg_replace('/^--.*$/m', '', file_get_contents($schema_file));
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $statement) {
              try {
                mysqli_query($link, $statement);
              } catch (mysqli_sql_exception $ex) {
                if (!in_array($ex->getCode(), [1060, 1061, 1068, 1091], true)) {
                  error_log("Error applying schema update V{$i}: " . $ex->getMessage());
                }
              }
            }
            mysqli_query($link, "UPDATE schema_version SET version = " . (int)$i);
          }
        }
      }
    } catch (Throwable $e) {
      error_log("Schema check warning: " . $e->getMessage());
    }

    $clientIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    $rateBlocked = false;
    $remainingMinutes = 5;

    try {
      $stmt_rl = mysqli_prepare(
        $link,
        "SELECT COUNT(*) AS cnt, MIN(UNIX_TIMESTAMP(attempted_at)) AS oldest_ts
         FROM loginAttempts
         WHERE action_type = 'login'
           AND attempted_at >= DATE_SUB(NOW(), INTERVAL 300 SECOND)
           AND (ip_address = ? OR userid = ?)"
      );
      if ($stmt_rl) {
        mysqli_stmt_bind_param($stmt_rl, "ss", $clientIp, $submitted_userid);
        mysqli_stmt_execute($stmt_rl);
        $rs_rl = mysqli_stmt_get_result($stmt_rl);
        if ($rl_row = mysqli_fetch_assoc($rs_rl)) {
          if ((int)$rl_row['cnt'] >= 5) {
            $rateBlocked = true;
            $remainingMinutes = max(1, (int)ceil((((int)$rl_row['oldest_ts'] + 300) - time()) / 60));
          }
        }
      }
    } catch (Throwable $e) {
      error_log("Login rate-limit query failed: " . $e->getMessage());
    }

    if ($rateBlocked) {
      $errorMessage = "Too many failed login attempts. Please wait {$remainingMinutes} minute" . ($remainingMinutes > 1 ? "s" : "") . " before trying again.";
      $isError = true;
    } else {
      $stmt = mysqli_prepare($link, "SELECT * FROM people WHERE userid = ?");
      mysqli_stmt_bind_param($stmt, "s", $submitted_userid);
      mysqli_stmt_execute($stmt);
      $result = mysqli_stmt_get_result($stmt);

      $passValidate = false;
      // Constant-work dummy bcrypt hash to prevent username timing enumeration
      $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

      if ($row = mysqli_fetch_assoc($result)) {
        $password_from_db = (string)($row["password"] ?? "");

        if ($password_from_db !== "" && password_verify($submitted_password, $password_from_db)) {
          $passValidate = true;
          if (password_needs_rehash($password_from_db, PASSWORD_DEFAULT)) {
            $new_hash = password_hash($submitted_password, PASSWORD_DEFAULT);
            $upd = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
            mysqli_stmt_bind_param($upd, "ss", $new_hash, $row["userid"]);
            mysqli_stmt_execute($upd);
          }
        } elseif (strlen($password_from_db) === 32 && hash_equals($password_from_db, md5($submitted_password))) {
          $passValidate = true;
          $new_hash = password_hash($submitted_password, PASSWORD_DEFAULT);
          $update_stmt = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
          mysqli_stmt_bind_param($update_stmt, "ss", $new_hash, $row["userid"]);
          mysqli_stmt_execute($update_stmt);
        }
      } else {
        password_verify($submitted_password, $dummyHash);
      }

      if ($passValidate && isset($row)) {
        session_regenerate_id(true);
        unset($_SESSION['login_attempts'], $_SESSION['login_lockout_until'], $_SESSION['euserid']);

        try {
          $stmt_clr = mysqli_prepare($link, "DELETE FROM loginAttempts WHERE action_type = 'login' AND (ip_address = ? OR userid = ?)");
          if ($stmt_clr) {
            mysqli_stmt_bind_param($stmt_clr, "ss", $clientIp, $row["userid"]);
            mysqli_stmt_execute($stmt_clr);
          }
        } catch (Throwable $e) {
        }

        $_SESSION['last_activity'] = time();
        $_SESSION["userid"] = $row["userid"];
        $_SESSION["fullname"] = trim($row["firstname"] . " " . $row["lastname"] . " " . ($row["suffix"] ?? ""));
        $_SESSION["admin"] = (int)$row["admin"];

        $stmt_notif = mysqli_prepare($link, "UPDATE people SET lastLoginDate = NOW() WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_notif, "s", $row["userid"]);
        mysqli_stmt_execute($stmt_notif);

        mysqli_free_result($result);
        header("Location: home.php");
        exit;
      }

      // Record failed attempt in DB
      try {
        if (random_int(1, 50) === 1) {
          mysqli_query($link, "DELETE FROM loginAttempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        }
        $stmt_ins = mysqli_prepare($link, "INSERT INTO loginAttempts (ip_address, userid, action_type) VALUES (?, ?, 'login')");
        if ($stmt_ins) {
          $safeUid = substr($submitted_userid, 0, 50);
          mysqli_stmt_bind_param($stmt_ins, "ss", $clientIp, $safeUid);
          mysqli_stmt_execute($stmt_ins);
        }
      } catch (Throwable $e) {
      }

      $errorMessage = "Invalid username or password.";
      $isError = true;
    }
  }
}
?>
<!DOCTYPE html>
<HTML>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="WishList">
<link rel="apple-touch-icon" href="images/icons/icon-192x192.png">
<link rel="manifest" href="manifest.json">
<script type="text/javascript">window.WISHLIST_BASE_URL = <?php echo json_encode($base_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;</script>
<script type="text/javascript" src="js/theme.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<link rel=stylesheet href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type=text/css>
<title>WishList Login</title>
</head>

<body bgcolor=#ffffff onload="document.login_form.userid.focus();">
<center>

<table class=pagetable>
<tr>
<td valign="top" align=center>
<table border=0 cellpadding=0 cellspacing=0 width="100%">
  <tr>
    <td colspan=2 align=center>
      <table class=headerBox>
        <tr>
          <td align="center" class=headerCell>
            <font size=+1 color=white><b>Welcome to the WishList Site!</b></font>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td align="center" style="padding: 8px 0;">
      <font color="<?php echo $isError ? '#c62828' : 'green'; ?>"><b>
      <?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
      </b></font>
    </td>
  </tr>
  <tr>
    <td align="center">
      <table border=0 cellspacing=2 cellpadding=0>
        <tr>
          <td align="left" valign="top">
            <form method="post" action="login.php" name="login_form">
            <table border="0" cellspacing="6" cellpadding="6" bgcolor="ffffff" width="100%" class="roundedbox">
              <tr bgcolor="eeeeee">
                <td align="center">
                  <b>Existing WishList users</b><br>
                  <font size="-1"><nobr>&nbsp;Enter your ID and password to sign in&nbsp; </nobr></font>
                  <table border="0" cellpadding="4" cellspacing="0">
                    <tr>
                      <td align="right">
                        <table border="0" cellpadding="2" cellspacing="0">
                          <tr>
                            <td align="right" nowrap>
                              <font size="-1">WishList ID:</font>
                            </td>
                            <td>
                              <input name="userid" size="17" autocomplete="username" value="">
                            </td>
                          </tr>
                          <tr>
                            <td align="right" nowrap>
                              <font size="-1">Password:</font>
                            </td>
                            <td>
                              <input name="password" type="password" size="17" autocomplete="current-password">
                            </td>
                          </tr>
                          <tr>
                            <td>&nbsp;</td>
                            <td>
                              <input name="submitButton" type="submit" value="Sign In" class="buttonstyle">
                            </td>
                          </tr>
                        </table>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr bgcolor="eeeeee">
                <td valign="top" align="center">
                  <table border=0 width="100%">
                    <tr>
                      <td align="center">
                        <font size="-1">
                        <a href="forgotPassword.php">Forgot your password?</a>
                        </font>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
            </form>
          </td>
        </tr>
        <tr>
          <td align="center">
            <font size=+1><b>New to the WishList Site?</b></font>
            <p>
            <a href="registerUser.php">Sign up now</a> to enjoy the WishList site
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<p>
<table>
<tr><td><a href="wishlist_button.php">Add the WishList button to your ToolBar!</a></td></tr>
</table>
</center>
</body>
</html>
