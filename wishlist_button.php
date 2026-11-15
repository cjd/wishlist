<?php
$ignoreSession = true;
include "funcLib.php";

$rawBase = trim((string) ($base_url ?? ''));
if ($rawBase === '' || strpos($rawBase, 'http') !== 0) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $rawBase = ($isHttps ? 'https://' : 'http://') . $host . $dir;
}
$bookmarkletTarget = rtrim($rawBase, '/') . '/modifyList/addItemFromWeb.php';
$jsTargetLiteral = json_encode($bookmarkletTarget, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);
$bookmarkletHref = "javascript:void(open(" . $jsTargetLiteral . "+'?wldesc='+encodeURIComponent(document.title)+'&wlurl='+encodeURIComponent(location.href),'WishList_com','height=600,width=900,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes'));";
$escapedHref = htmlspecialchars($bookmarkletHref, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="manifest" href="manifest.json">
<link rel="stylesheet" href="style.css?v=<?php echo (int)@filemtime(__DIR__ . '/style.css'); ?>" type="text/css">
<title>Add To Your Wishlist from the Web</title>
</head>

<body>
<center>
<table class="pagetable" style="width:850px; max-width:96%;">
<tr><td valign="top">
<?php
if (isset($_SESSION["userid"])) {
    createNavBar("home.php:Home|:Add From Web");
}
?>
<table class="headerBox" style="margin-top: 10px;">
<tr><td class="headerCell" align="center">
Add To Your Wishlist from anywhere on the web
</td></tr>
</table>
<br>
<p>
To add items to your WishList you'll need to put a special, completely safe <b>WishList Site</b> bookmark in your browser's bookmarks list.<br>
</p>
<p>
For those using Chrome, Firefox, Edge, or Safari, drag this &quot;Add to WishList Button&quot; up to your browser's bookmarks/favorites bar.<br>
The <b>WishList Site</b> bookmark conveniently stays on your toolbar.<br>
</p>
<p>
<a class="buttonstyle" href="<?php echo $escapedHref; ?>">Add to Wishlist Button</a><br>
</p>
<p>
Those using mobile browsers or other setups may <i>Right Click</i> (or press and hold) on this link:<br>
<b><a href="<?php echo $escapedHref; ?>">Add to Wishlist Button</a></b><br>
and select &quot;Add to Favorites&quot; or &quot;Bookmark Link&quot;.
</p>
<p>
Once you have the <b>WishList Site</b> bookmark in your browser you can find the specific product you're wishing for anywhere on the Web, then click the <b>WishList Site</b> bookmark to easily add that product's page and title directly to your WishList!
</p>
<hr style="max-width:650px;">
<p style="max-width:650px; text-align:left;">
<b>📱 On Phones &amp; Tablets (Share Sheet):</b><br>
If you install this site as an app (<b>Install App</b> / <b>Add to Home Screen</b> in Chrome, Edge, or Android browsers), <b>WishList</b> appears directly in your device's native <b>Share</b> menu! While browsing in store apps (like Amazon, Target, or eBay) or your mobile browser, simply tap <b>Share &rarr; WishList</b> to send the item straight to your list.
</p>
</td></tr>
</table>
</center>
<?php if (!isset($_SESSION["userid"])): ?>
<script type="text/javascript" src="js/theme.js?v=<?php echo (int)@filemtime(__DIR__ . '/js/theme.js'); ?>"></script>
<?php endif; ?>
</body>
</html>
