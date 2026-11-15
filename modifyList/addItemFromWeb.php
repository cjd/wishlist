<?php
// Bookmarklet handler: allows adding an item from an external webpage,
// prompting for authentication first if the user is not already signed in.

$ignoreSession = true;
include "../funcLib.php";

if (isset($_SESSION["userid"])) {
    createPage();
    exit;
}

$displayLogin = true;
$errorMessage = "Please Sign In";
$loginUserid = trim((string)($_POST["userid"] ?? ""));
$password = (string)($_POST["password"] ?? "");
$clientIp = getClientIp();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $loginUserid !== "" && $password !== "") {
    verifyCsrfOrDie(true);
    $normalizedUser = strtolower($loginUserid);

    $rl = checkRateLimit($link, $clientIp, $normalizedUser, 'web_login', 5, 300);

    if ($rl['blocked']) {
        $mins = max(1, (int)ceil($rl['retry_after'] / 60));
        $errorMessage = "Too many failed login attempts. Please try again in {$mins} minute(s).";
    } else {
        $stmt_user = mysqli_prepare($link, "SELECT userid, password, firstname, lastname, suffix, admin FROM people WHERE userid = ?");
        mysqli_stmt_bind_param($stmt_user, "s", $loginUserid);
        mysqli_stmt_execute($stmt_user);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));

        $dummyHash = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
        $passValidate = false;

        if ($row) {
            $storedHash = $row["password"];
            if (password_verify($password, $storedHash)) {
                $passValidate = true;
                if (password_needs_rehash($storedHash, PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $up = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
                    mysqli_stmt_bind_param($up, "ss", $newHash, $row["userid"]);
                    mysqli_stmt_execute($up);
                }
            } elseif (strlen($storedHash) === 32 && ctype_xdigit($storedHash) && hash_equals(strtolower($storedHash), md5($password))) {
                $passValidate = true;
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $up = mysqli_prepare($link, "UPDATE people SET password = ? WHERE userid = ?");
                mysqli_stmt_bind_param($up, "ss", $newHash, $row["userid"]);
                mysqli_stmt_execute($up);
            }
        } else {
            password_verify($password, $dummyHash);
        }

        if ($row && $passValidate) {
            clearRateLimitAttempts($link, $clientIp, $normalizedUser, 'web_login');

            session_regenerate_id(true);
            $_SESSION['last_activity'] = time();
            $_SESSION["userid"] = $row["userid"];
            $_SESSION["fullname"] = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
            $_SESSION["admin"] = (int)$row["admin"];
            $userid = $_SESSION["userid"];
            $activeOwnerFullname = $_SESSION["fullname"];

            $stmt_login = mysqli_prepare($link, "UPDATE people SET lastLoginDate = NOW() WHERE userid = ?");
            mysqli_stmt_bind_param($stmt_login, "s", $row["userid"]);
            mysqli_stmt_execute($stmt_login);

            createPage();
            exit;
        } else {
            recordRateLimitAttempt($link, $clientIp, $normalizedUser, 'web_login');
            $errorMessage = "Invalid User ID or Password. Please try again.";
        }
    }
}

$raw_url = trim((string)($_REQUEST["wlurl"] ?? ""));
$parsed_host = $raw_url !== '' ? parse_url($raw_url, PHP_URL_HOST) : '';
$suggested_linkname = $parsed_host ? preg_replace('/^www\./i', '', $parsed_host) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <title>Add New Item From Web</title>
</head>
<body bgcolor="#ffffff" onload="if(document.login_form && document.login_form.userid) document.login_form.userid.focus();">
<table class="pagetable">
<tr>
<td valign="top">
<center>
<p style="font-weight:bold; color:<?php echo $errorMessage === 'Please Sign In' ? '#333' : '#c00'; ?>;">
  <?php echo escapeText($errorMessage); ?>
</p>

<form method="post" action="addItemFromWeb.php" name="login_form">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">

<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Add New Item From the Web<br>You will have a chance to modify this after you sign in</font></b>
</td></tr>
<tr>
  <td align="right"><b>Name</b></td>
  <td bgcolor="white">
    <input size="50" type="text" name="wldesc" value="<?php echo escapeText($_REQUEST["wldesc"] ?? ""); ?>">
  </td>
</tr>
<tr>
  <td align="right">
    <table>
      <tr><td align="right"><b>Link Name</b></td></tr>
      <tr><td align="right"><b>URL</b></td></tr>
    </table>
  </td>
  <td bgcolor="#eeeeee">
    <input size="50" type="text" name="wlurlname" value="<?php echo escapeText($_REQUEST["wlurlname"] ?? $suggested_linkname); ?>"><br>
    <input size="50" type="text" name="wlurl" value="<?php echo escapeText($raw_url); ?>">
  </td>
</tr>
</table>

<p>
<table>
<tr><td colspan="2" align="center"><b>Please sign in</b></td></tr>
<tr>
  <td align="right">Userid</td>
  <td><input type="text" name="userid" value="<?php echo escapeText($loginUserid); ?>" autocomplete="username" required></td>
</tr>
<tr>
  <td align="right">Password</td>
  <td><input name="password" type="password" autocomplete="current-password" required></td>
</tr>
</table>
<br>
<input type="submit" value="Sign In &amp; Continue" class="buttonstyle">
</form>
</center>
</td></tr></table>
</body>
</html>
<?php

function createPage() {
    global $link, $userid;
    $targetUid = !empty($userid) ? $userid : $_SESSION["userid"];

    $stmt_cat = mysqli_prepare($link, "SELECT cid, name FROM categories WHERE userid = ? AND catSortOrder != -1000 ORDER BY name");
    mysqli_stmt_bind_param($stmt_cat, "s", $targetUid);
    mysqli_stmt_execute($stmt_cat);
    $rs = mysqli_stmt_get_result($stmt_cat);

    $categories = [];
    $considerCid = null;
    while ($row = mysqli_fetch_assoc($rs)) {
        if ($row["name"] === "Items Under Consideration") {
            $considerCid = (int)$row["cid"];
        } else {
            $categories[] = $row;
        }
    }

    if (empty($categories) && $considerCid === null) {
        echo "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><link rel=\"stylesheet\" href=\"../style.css\"></head><body>";
        echo "<p>You have not created any categories yet. <a href=\"modifyList.php\">Go to Modify List</a> to add one first.</p></body></html>";
        return;
    }

    $rawUrl = trim((string)($_REQUEST["wlurl"] ?? ''));
    $url = '';
    if ($rawUrl !== '') {
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'https://' . $rawUrl;
        }
        $url = sanitizeHttpUrl($rawUrl, false);
    }
    $parsed_host = $url !== '' ? parse_url($url, PHP_URL_HOST) : '';
    $urlName = trim((string)($_REQUEST["wlurlname"] ?? ''));
    if ($urlName === '' && $parsed_host) {
        $urlName = preg_replace('/^www\./i', '', $parsed_host);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css" type="text/css">
  <title>Add New Item From Web</title>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php createNavBar("../home.php:Home|modifyList.php:Modify WishList|:Add Item From Web"); ?>

<center>
<p>&nbsp;</p>
<form method="post" action="validate_addItem.php">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">
<table style="border-collapse: collapse;" border="1" bordercolor="#111111" cellpadding="4" cellspacing="0" bgcolor="lightyellow">
<tr>
  <td colspan="2" align="center" bgcolor="#6702cc">
    <b><font size="3" color="#ffffff">Add New Item from the Web</font></b>
  </td>
</tr>
<tr>
  <td align="right"><b>Category</b></td>
  <td bgcolor="#eeeeee">
    <select name="cid">
      <?php foreach ($categories as $cat): ?>
        <option value="<?php echo (int)$cat["cid"]; ?>"><?php echo escapeText($cat["name"]); ?></option>
      <?php endforeach; ?>
      <?php if ($considerCid !== null): ?>
        <option value="<?php echo $considerCid; ?>" selected>Items Under Consideration</option>
      <?php endif; ?>
    </select>
  </td>
</tr>
<tr>
  <td align="right"><b>Title</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="title" value="<?php echo escapeText($_REQUEST["wldesc"] ?? ''); ?>" required>
  </td>
</tr>
<tr>
  <td align="right"><b>Description</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="desc" value="">
  </td>
</tr>
<tr>
  <td align="right"><b>Price</b></td>
  <td bgcolor="#eeeeee">
    <input type="text" size="10" name="price" value="" placeholder="0.00">
  </td>
</tr>
<tr>
  <td align="right"><b>Quantity</b></td>
  <td bgcolor="#eeeeee">
    <input type="number" min="1" step="1" size="7" name="quantity" value="1" required>
  </td>
</tr>
<tr>
  <td align="right">
    <table>
      <tr><td align="right"><b>Store / Name</b></td></tr>
      <tr><td align="right"><b>URL</b></td></tr>
    </table>
  </td>
  <td bgcolor="#eeeeee">
    <input type="text" size="50" name="link1" id="link1" value="<?php echo escapeText($urlName); ?>"><br>
    <input type="text" size="50" name="link1url" id="link1url" value="<?php echo escapeText($url); ?>">
  </td>
</tr>
<tr>
  <td align="right"><b>Additional<br>Information</b></td>
  <td bgcolor="#eeeeee">
    <textarea rows="3" cols="50" name="subdesc"></textarea>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Allow people to check this item off</b>
    <input type="checkbox" name="allowCheck" checked>
  </td>
</tr>
<tr>
  <td align="center" colspan="2">
    <b>Add a star to this item</b>
    <input type="checkbox" name="addStar">
  </td>
</tr>
<tr>
  <td colspan="2" bgcolor="#c0c0c0" align="center">
    <input type="submit" value="Add" class="buttonstyle">
  </td>
</tr>
</table>
</form>
</center>
</td></tr></table>
<script src="../js/store-detector.js"></script>
</body>
</html>
<?php
}
