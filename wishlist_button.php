<?php

@(include 'config.php');

?>
<html>
<link rel=stylesheet href=style.css type=text/css>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>

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
For those using Microsoft, Firefox, or Mozilla Browsers, drag this &quot;Add to WishList Button&quot; up to your browser's toolbar.<br>
The <b>WishList Site</b> bookmark conveniently stays on your toolbar.<br>
</p><p>

<a href="javascript:void(open('<?php echo $base_url; ?>/modifyList/addItemFromWeb.php?wldesc='+escape(document.title)+'&wlurl='+escape(location.href),'WishList_com','height=600,width=900,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes'));">Add to Wishlist Button</a><br>
</p><p>

Those using other browsers may <i>Right Click</i> on this link:<b><br>
<a href="javascript:void(open('<?php echo $base_url; ?>/modifyList/addItemFromWeb.php?wldesc='+escape(document.title)+'&wlurl='+escape(location.href),'WishList_com','height=600,width=900,left=10,top=10,location=1,scrollbars=yes,menubar=1,toolbars=1,resizable=yes'));">Add to Wishlist Button</a></b><br>
and select &quot;Add to Favorites&quot; or &quot;Add to Bookmark&quot;
</p><p>

Once you have the <b>WishList Site</b> bookmark in your browser you can find the specific product you're wishing for, anywhere on the Web. Then use the <b>WishList Site</b> bookmark to put that product's web page in your WishList!
</p>
</td></tr>
<tr><td></td></tr>
</table>

</body>
</html>
