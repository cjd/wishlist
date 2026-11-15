<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

include "../funcLib.php";

$recip = $_REQUEST["recip"];
$name = $_REQUEST["name"];
$initialType = (isset($_REQUEST["type"]) && $_REQUEST["type"] === 'suggestion') ? 'suggestion' : 'general';

if (isset ($_REQUEST["email"])) {
    $email = $_REQUEST["email"];
} else {
    $email = "";
}

if (isset ($_REQUEST["confirm"])) {
    $confirm = $_REQUEST["confirm"];
} else {
    $confirm = "";
}

if($confirm != ""){
  verifyCsrfOrDie();

$recip = convertString($_REQUEST["recip"] ?? '');
$comment_userid = convertString($_SESSION["userid"] ?? '');
$name = convertString($_REQUEST["name"] ?? '');

if (!empty($_REQUEST["is_suggestion"])) {
    $s_title = htmlspecialchars(trim($_REQUEST["suggest_title"] ?? ''));
    $s_url = trim($_REQUEST["suggest_url"] ?? '');
    $s_price = trim($_REQUEST["suggest_price"] ?? '');
    $s_notes = htmlspecialchars(trim($_REQUEST["suggest_notes"] ?? ''));

    $badgeHtml = "<div class='secret-suggestion-card'>";
    $badgeHtml .= "<div class='suggestion-badge-row'><span class='suggestion-pill'>💡 Secret Gift Idea</span>";
    if (!empty($s_price)) {
        $cleanPrice = str_replace('$', '', $s_price);
        $badgeHtml .= " <span class='price-badge'>$" . htmlspecialchars($cleanPrice) . "</span>";
    }
    $badgeHtml .= "</div>";
    $badgeHtml .= "<div class='suggestion-title'><b>" . $s_title . "</b></div>";
    if (!empty($s_url)) {
        $badgeHtml .= "<div style='margin: 4px 0;'>" . formatStoreButton($s_url) . "</div>";
    }
    if (!empty($s_notes)) {
        $badgeHtml .= "<div class='suggestion-notes'>" . nl2br($s_notes) . "</div>";
    }
    $badgeHtml .= "</div>";

    $comment = $badgeHtml;
    $subject = $_SESSION["fullname"] . " suggested a secret gift idea for " . $name;
    $message = "<b>Secret Gift Suggestion for " . htmlspecialchars($name) . ":</b><br>" . $badgeHtml;
} else {
    $comment = convertString($_REQUEST["comment"] ?? '');
    $subject = $_SESSION["fullname"] . " has added a comment to " . $name . "'s list";
    $message = "<b>Comment:</b><br>" . cleanString($_REQUEST["comment"] ?? '');
}

$stmt = mysqli_prepare($link, "INSERT INTO comments (commentId, userid, comment_userid, comment, date) VALUES (null, ?, ?, ?, NOW())");
mysqli_stmt_bind_param($stmt, "sss", $recip, $comment_userid, $comment);
mysqli_stmt_execute($stmt);

if (isset($_REQUEST["recipients"]) && is_array($_REQUEST["recipients"])) {
  $to = implode(",", $_REQUEST["recipients"]);
  $from = $_SESSION["userid"];

  sendEmail($to, $from, $subject, $message, 0);
}

header("Location: " . getFullPath("viewList.php") . "?recip=" . urlencode($recip) . "&name=" . urlencode($name));
exit;
}
else{
?>
<HTML>
<head><meta name="viewport" content="width=device-width, initial-scale=1.0" /></head>


<link rel=stylesheet href=../style.css type=text/css>

<title><?php echo $name ?>'s WishList</title>
<BODY>
<table class=pagetable>
<tr>
<td valign="top">

<?php
createNavBar("../home.php:Home|viewList.php?recip=" . $_REQUEST["recip"] . "&name=" . $_REQUEST["name"] . ":View List - " . $_REQUEST["name"] . "|:Add Comment", false, "addComment");
?>

<table><tr><td>
<form name="theForm" method=post action=addComment.php>
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES); ?>">

<div style="margin: 12px 0 16px 0; background: #f0f7ff; border: 1px solid #337fed; border-radius: 6px; padding: 10px 14px;">
  <b>What would you like to post?</b><br><br>
  <label style="cursor:pointer; margin-right: 20px;">
    <input type="radio" name="comment_type" value="general" <?php echo $initialType === 'general' ? 'checked' : ''; ?> onclick="setCommentType('general')"> 💬 <b>General Viewer Comment</b>
  </label>
  <label style="cursor:pointer;">
    <input type="radio" name="comment_type" value="suggestion" <?php echo $initialType === 'suggestion' ? 'checked' : ''; ?> onclick="setCommentType('suggestion')"> 💡 <b>Secret Gift Suggestion</b>
  </label>
</div>

<div id="generalCommentArea" style="display:<?php echo $initialType === 'general' ? 'block' : 'none'; ?>;">
  <b>Comment:</b><br>
  <textarea ROWS='5' COLS='90' name=comment id="generalCommentInput" style="width:100%; max-width:650px;" placeholder="Type your comment for other viewers here..."></textarea>
</div>

<div id="suggestionArea" style="display:<?php echo $initialType === 'suggestion' ? 'block' : 'none'; ?>; background: #faf5ff; border: 1px solid #d8b4fe; border-left: 4px solid #9333ea; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; max-width:650px;">
  <input type="hidden" name="is_suggestion" id="isSuggestionFlag" value="<?php echo $initialType === 'suggestion' ? '1' : '0'; ?>">
  <div style="color:#6b21a8; font-weight:bold; font-size:14px; margin-bottom: 10px;">💡 Secret Gift Suggestion (Hidden from <?php echo htmlspecialchars($name); ?>)</div>
  <label><b>Gift Title / Item Name:</b></label><br>
  <input type="text" name="suggest_title" id="suggestTitle" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="e.g. Leather Travel Duffel Bag"><br>

  <label><b>Store Link (optional):</b></label><br>
  <input type="text" name="suggest_url" id="suggestUrl" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="https://..."><br>

  <label><b>Estimated Price (optional):</b></label><br>
  <input type="text" name="suggest_price" id="suggestPrice" style="width:140px; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="e.g. $79.00"><br>

  <label><b>Notes / Comments for other viewers:</b></label><br>
  <textarea name="suggest_notes" id="suggestNotes" rows="3" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 4px 0;" placeholder="e.g. Found this on sale, thought it would be perfect for their upcoming trip!"></textarea>
</div>

</td></tr>
<tr><td align=center>
<table style="border: 1px solid lightBlue" >
<tr><td colspan="2" align="center" bgcolor="#6702cc"> <b><font size=3 color="#ffffff">
Send Message?</font></b>
</td>
</tr>
<tr>
<td align=center>

<b>Your comment will be sent as a message to each person who has a check next to their name</b>
<br>You may want to include your own name to verify the messages are sent
</td></tr>
<tr><td align=center>
<table><tr><td align=center>
<table><tr><td>
<?php

$stmt_recips = mysqli_prepare($link, "SELECT * FROM people, viewList WHERE pid = ? AND viewer != ? AND viewer = people.userid ORDER BY lastname, firstname");
mysqli_stmt_bind_param($stmt_recips, "ss", $recip, $recip);
mysqli_stmt_execute($stmt_recips);
$rs = mysqli_stmt_get_result($stmt_recips);

while($row = mysqli_fetch_assoc($rs)){
?>

<input type=checkbox name=recipients[] value="<?php echo htmlspecialchars($row["userid"]); ?>"><?php echo htmlspecialchars($row["firstname"] . ' ' . $row["lastname"] . ' ' . $row["suffix"]); ?><br>
<?php
}
?>
</td></tr></table>
</td></tr>
<tr><td>

<SCRIPT LANGUAGE="JavaScript">
<!--    

function setCommentType(type) {
    var genArea = document.getElementById('generalCommentArea');
    var sugArea = document.getElementById('suggestionArea');
    var flag = document.getElementById('isSuggestionFlag');
    var submitBtn = document.getElementById('submitCommentBtn');

    if (type === 'suggestion') {
        genArea.style.display = 'none';
        sugArea.style.display = 'block';
        flag.value = '1';
        if (submitBtn) submitBtn.value = 'Post Secret Suggestion';
    } else {
        genArea.style.display = 'block';
        sugArea.style.display = 'none';
        flag.value = '0';
        if (submitBtn) submitBtn.value = 'Add Comment';
    }
}

function checkAll(field)
{
for (i = 0; i < this.document.theForm.elements['recipients[]'].length; i++)
    this.document.theForm.elements['recipients[]'][i].checked = true;
}

function uncheckAll(field)
{
for (i = 0; i < this.document.theForm.elements['recipients[]'].length; i++)
    this.document.theForm.elements['recipients[]'][i].checked = false;
}

//  End -->
</script>

</td></tr>
<tr><td colspan="2" bgcolor="#c0c0c0" align=center>
<input type=button value="Check All" onClick="checkAll(document.theForm.email)"; class="buttonstyle">
<input type=button value="Uncheck All" onClick="uncheckAll(document.theForm.email)"; class="buttonstyle">

</td></tr></table>

</td></tr></table>

<br>
<input type=hidden name=confirm value=yes>
<input type=hidden name=recip value="<?php echo htmlspecialchars($recip); ?>">
<input type=hidden name=name value="<?php echo htmlspecialchars($name); ?>">
<input type=submit id="submitCommentBtn" value="<?php echo $initialType === 'suggestion' ? 'Post Secret Suggestion' : 'Add Comment'; ?>" class="buttonstyle">

</form>
</td><tr><table>
<?php
}
?>
