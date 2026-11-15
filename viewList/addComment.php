<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 2 of the License, or
// (at your option) any later version.

include "../funcLib.php";

$viewerUserid = $_SESSION["userid"];
$recip = trim((string)($_REQUEST["recip"] ?? ""));
$name = trim((string)($_REQUEST["name"] ?? ""));
$initialType = (isset($_REQUEST["type"]) && $_REQUEST["type"] === 'suggestion') ? 'suggestion' : 'general';

// Viewers cannot post secret comments on their own wishlist, and must have viewList access
if ($recip === "" || $recip === $viewerUserid) {
    header("Location: " . getFullPath("../home.php"));
    exit;
}

$access = canViewList($link, $recip, $viewerUserid);
if (!$access['allowed']) {
    http_response_code(403);
    die("You do not have permission to comment on this wishlist.");
}

$stmt_p = mysqli_prepare($link, "SELECT firstname, lastname, suffix FROM people WHERE userid = ?");
mysqli_stmt_bind_param($stmt_p, "s", $recip);
mysqli_stmt_execute($stmt_p);
if ($prow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))) {
    $name = trim($prow["firstname"] . " " . $prow["lastname"] . " " . ($prow["suffix"] ?? ""));
}

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST["confirm"])) {
    verifyCsrfOrDie(true);

    $isSuggestion = !empty($_POST["is_suggestion"]);
    $isSuggestionInt = 0;
    $suggestTitleDb = '';
    $suggestPriceDb = 0.0;
    $claimImmediately = false;
    if ($isSuggestion) {
        $initialType = 'suggestion';
        $isSuggestionInt = 1;
        $s_title = trim((string)($_POST["suggest_title"] ?? ''));
        $s_url_in = trim((string)($_POST["suggest_url"] ?? ''));
        $s_price = trim((string)($_POST["suggest_price"] ?? ''));
        $s_notes = trim((string)($_POST["suggest_notes"] ?? ''));
        $claimImmediately = (!empty($_POST["claim_immediately"]) && !$access['readOnly']);

        if ($s_title === '') {
            $error_msg = "Please enter a Gift Title for your secret suggestion.";
        } else {
            $suggestTitleDb = $s_title;
            $priceNumericRaw = str_replace([',', '$'], '', $s_price);
            if (is_numeric($priceNumericRaw)) {
                $suggestPriceDb = max(0.0, round((float)$priceNumericRaw, 2));
            }

            $safe_url = '';
            if ($s_url_in !== '') {
                if (!preg_match('#^https?://#i', $s_url_in)) {
                    $s_url_in = 'https://' . $s_url_in;
                }
                $safe_url = sanitizeHttpUrl($s_url_in, false);
            }

            $badgeHtml = "<div class='secret-suggestion-card'>";
            $badgeHtml .= "<div class='suggestion-badge-row'><span class='suggestion-pill'>💡 Secret Gift Idea</span>";
            if ($suggestPriceDb > 0) {
                $badgeHtml .= " <span class='price-badge'>$" . number_format($suggestPriceDb, 2) . "</span>";
            } elseif ($s_price !== '') {
                $cleanPrice = ltrim($s_price, '$ ');
                $badgeHtml .= " <span class='price-badge'>$" . escapeText($cleanPrice) . "</span>";
            }
            $badgeHtml .= "</div>";
            $badgeHtml .= "<div class='suggestion-title'><b>" . escapeText($s_title) . "</b></div>";
            if ($safe_url !== '') {
                $badgeHtml .= "<div style='margin: 4px 0;'>" . formatStoreButton($safe_url) . "</div>";
            }
            if ($s_notes !== '') {
                $badgeHtml .= "<div class='suggestion-notes'>" . nl2br(escapeText($s_notes)) . "</div>";
            }
            $badgeHtml .= "</div>";

            $comment = $badgeHtml;
            $subject = $_SESSION["fullname"] . " suggested a secret gift idea for " . $name;
            $message = "<b>Secret Gift Suggestion for " . escapeText($name) . ":</b><br>" . $badgeHtml;
        }
    } else {
        $initialType = 'general';
        $raw_comment = trim((string)($_POST["comment"] ?? ''));
        if ($raw_comment === '') {
            $error_msg = "Please enter a comment before submitting.";
        } else {
            $comment = $raw_comment;
            $subject = $_SESSION["fullname"] . " has added a comment to " . $name . "'s list";
            $message = "<b>Comment:</b><br>" . formatMultilineText($raw_comment);
        }
    }

    if ($error_msg === '') {
        if ($claimImmediately) {
            $stmt = mysqli_prepare($link, "INSERT INTO comments (userid, comment_userid, comment, date, is_suggestion, suggest_title, suggest_price, claimed_by, claimed_date) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, "sssisds", $recip, $viewerUserid, $comment, $isSuggestionInt, $suggestTitleDb, $suggestPriceDb, $viewerUserid);
        } else {
            $stmt = mysqli_prepare($link, "INSERT INTO comments (userid, comment_userid, comment, date, is_suggestion, suggest_title, suggest_price) VALUES (?, ?, ?, NOW(), ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssisd", $recip, $viewerUserid, $comment, $isSuggestionInt, $suggestTitleDb, $suggestPriceDb);
        }
        mysqli_stmt_execute($stmt);

        if (!empty($_POST["recipients"]) && is_array($_POST["recipients"])) {
            // Only allow sending to verified viewers of $recip (strictly excluding $recip!)
            $allowedViewers = [];
            $stmt_v = mysqli_prepare($link, "SELECT viewer FROM viewList WHERE pid = ? AND viewer != ?");
            mysqli_stmt_bind_param($stmt_v, "ss", $recip, $recip);
            mysqli_stmt_execute($stmt_v);
            $res_v = mysqli_stmt_get_result($stmt_v);
            while ($vr = mysqli_fetch_assoc($res_v)) {
                $allowedViewers[$vr['viewer']] = true;
            }

            $validRecipients = [];
            foreach (array_unique($_POST["recipients"]) as $rUid) {
                $rClean = trim((string)$rUid);
                if ($rClean !== '' && $rClean !== $recip && isset($allowedViewers[$rClean])) {
                    $validRecipients[] = $rClean;
                }
            }

            if (!empty($validRecipients)) {
                sendEmail(implode(",", $validRecipients), $viewerUserid, $subject, $message, 0);
            }
        }

        header("Location: " . getFullPath("viewList.php") . "?recip=" . urlencode($recip) . "&name=" . urlencode($name));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../style.css?v=<?php echo (int)@filemtime(__DIR__ . '/../style.css'); ?>" type="text/css">
  <link rel="manifest" href="../manifest.json">
  <title><?php echo escapeText($name); ?>'s WishList - Add Comment</title>
  <script>
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

  function setAllRecipients(checked) {
      var boxes = document.querySelectorAll('input[name="recipients[]"]');
      for (var i = 0; i < boxes.length; i++) {
          boxes[i].checked = checked;
      }
  }
  </script>
</head>
<body>
<table class="pagetable">
<tr>
<td valign="top">
<?php
createNavBar("../home.php:Home|viewList.php?recip=" . urlencode($recip) . "&name=" . urlencode($name) . ":View List - " . $name . "|:Add Comment", false, "addComment");
?>

<?php if ($error_msg !== ''): ?>
  <p style="color:#c00; font-weight:bold;"><?php echo escapeText($error_msg); ?></p>
<?php endif; ?>

<table><tr><td>
<form name="theForm" method="post" action="addComment.php">
<input type="hidden" name="csrf_token" value="<?php echo escapeText(getCsrfToken()); ?>">

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
  <textarea rows="5" cols="90" name="comment" id="generalCommentInput" style="width:100%; max-width:650px;" placeholder="Type your comment for other viewers here..."><?php echo escapeText($_POST['comment'] ?? ''); ?></textarea>
</div>

<div id="suggestionArea" style="display:<?php echo $initialType === 'suggestion' ? 'block' : 'none'; ?>; background: #faf5ff; border: 1px solid #d8b4fe; border-left: 4px solid #9333ea; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; max-width:650px;">
  <input type="hidden" name="is_suggestion" id="isSuggestionFlag" value="<?php echo $initialType === 'suggestion' ? '1' : '0'; ?>">
  <div style="color:#6b21a8; font-weight:bold; font-size:14px; margin-bottom: 10px;">💡 Secret Gift Suggestion (Hidden from <?php echo escapeText($name); ?>)</div>
  <label><b>Gift Title / Item Name:</b></label><br>
  <input type="text" name="suggest_title" id="suggestTitle" value="<?php echo escapeText($_POST['suggest_title'] ?? ''); ?>" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="e.g. Leather Travel Duffel Bag"><br>

  <label><b>Store Link (optional):</b></label><br>
  <input type="text" name="suggest_url" id="suggestUrl" value="<?php echo escapeText($_POST['suggest_url'] ?? ''); ?>" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="https://..."><br>

  <label><b>Estimated Price (optional):</b></label><br>
  <input type="text" name="suggest_price" id="suggestPrice" value="<?php echo escapeText($_POST['suggest_price'] ?? ''); ?>" style="width:140px; padding:6px; box-sizing:border-box; margin: 4px 0 10px 0;" placeholder="e.g. $79.00"><br>

  <label><b>Notes / Comments for other viewers:</b></label><br>
  <textarea name="suggest_notes" id="suggestNotes" rows="3" style="width:100%; padding:6px; box-sizing:border-box; margin: 4px 0 8px 0;" placeholder="e.g. Found this on sale, thought it would be perfect for their upcoming trip!"><?php echo escapeText($_POST['suggest_notes'] ?? ''); ?></textarea>
  <?php if (!$access['readOnly']): ?>
  <label style="display:block; margin-top:4px; cursor:pointer; font-size:13.5px; color:#4c1d95;">
    <input type="checkbox" name="claim_immediately" value="1" <?php echo !empty($_POST['claim_immediately']) ? 'checked' : ''; ?>>
    🎁 <b>I am buying this gift</b> (mark as Claimed right away so others don't buy it)
  </label>
  <?php endif; ?>
</div>

</td></tr>
<tr><td align="center">
<table style="border: 1px solid lightBlue;">
<tr><td colspan="2" align="center" bgcolor="#6702cc">
  <b><font size="3" color="#ffffff">Send Message?</font></b>
</td></tr>
<tr><td align="center">
  <b>Your comment will be sent as a message to each person checked below</b><br>
  You may include your own name to receive a copy.
</td></tr>
<tr><td align="center">
<table><tr><td align="left">
<?php
$stmt_recips = mysqli_prepare($link, "SELECT people.userid, people.firstname, people.lastname, people.suffix FROM people JOIN viewList ON viewList.viewer = people.userid WHERE viewList.pid = ? AND viewList.viewer != ? ORDER BY people.lastname, people.firstname");
mysqli_stmt_bind_param($stmt_recips, "ss", $recip, $recip);
mysqli_stmt_execute($stmt_recips);
$rs = mysqli_stmt_get_result($stmt_recips);

while ($row = mysqli_fetch_assoc($rs)):
    $rName = trim($row["firstname"] . ' ' . $row["lastname"] . ' ' . ($row["suffix"] ?? ''));
?>
  <label style="cursor:pointer;">
    <input type="checkbox" name="recipients[]" value="<?php echo escapeText($row["userid"]); ?>">
    <?php echo escapeText($rName); ?>
  </label><br>
<?php endwhile; ?>
</td></tr></table>
</td></tr>
<tr><td colspan="2" bgcolor="#c0c0c0" align="center">
  <input type="button" value="Check All" onclick="setAllRecipients(true)" class="buttonstyle">
  <input type="button" value="Uncheck All" onclick="setAllRecipients(false)" class="buttonstyle">
</td></tr>
</table>

<br>
<input type="hidden" name="confirm" value="yes">
<input type="hidden" name="recip" value="<?php echo escapeText($recip); ?>">
<input type="hidden" name="name" value="<?php echo escapeText($name); ?>">
<input type="submit" id="submitCommentBtn" value="<?php echo $initialType === 'suggestion' ? 'Post Secret Suggestion' : 'Add Comment'; ?>" class="buttonstyle">
</form>
</td></tr></table>
</td></tr></table>
</body>
</html>
