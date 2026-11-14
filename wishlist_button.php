<?php

@(include 'config.php');

$clean_base_url = htmlspecialchars($base_url ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel=stylesheet href=style.css type=text/css>
<title>Add To Your Wishlist from the Web</title>
</head>

<body>

<table class=pagetable>
<tr><td valign="top">
<table class="headerBox">
<tr><td class="headerCell" align="center">
Add To Your Wishlist from anywhere on the web
</td></tr>
</table>
<br>
<p>
To add items to your WishList you'll need to put a special, &amp; completely safe <b>WishList Site</b> bookmark in your browser's bookmarks list.<br>
</p><p>
For those using Chrome, Firefox, Edge, or Safari, drag this &quot;Add to WishList Button&quot; up to your browser's bookmarks/favorites bar.<br>
The <b>WishList Site</b> bookmark conveniently stays on your toolbar.<br>
</p><p>

<a href="javascript:void(open('<?php echo $clean_base_url; ?>/modifyList/addItemFromWeb.php?wldesc='+encodeURIComponent(document.title)+'&wlurl='+encodeURIComponent(location.href),'WishList_com','height=600,width=900,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes'));">Add to Wishlist Button</a><br>
</p><p>

Those using mobile browsers or other setups may <i>Right Click</i> (or press and hold) on this link:<b><br>
<a href="javascript:void(open('<?php echo $clean_base_url; ?>/modifyList/addItemFromWeb.php?wldesc='+encodeURIComponent(document.title)+'&wlurl='+encodeURIComponent(location.href),'WishList_com','height=600,width=900,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes'));">Add to Wishlist Button</a></b><br>
and select &quot;Add to Favorites&quot; or &quot;Bookmark Link&quot;
</p><p>

Once you have the <b>WishList Site</b> bookmark in your browser you can find the specific product you're wishing for, anywhere on the Web. Then click the <b>WishList Site</b> bookmark to easily add that product's page and title directly to your WishList!
</p>
</td></tr>
<tr><td></td></tr>
</table>

</body>
</html>
