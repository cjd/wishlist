<?php
$ignoreSession = true;
include "funcLib.php";
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Expires" content="0"> 
<meta http-equiv="Pragma" content="no-cache"> 
<link rel="stylesheet" type="text/css" media="screen" href="style.css">
<title>Help &amp; Frequently Asked Questions</title>
</head>
<body>
<center>
<table class=pagetable style="width:850px; max-width:96%;">
<tr><td align=center>
<?php
if (isset($_SESSION["userid"])) {
    createNavBar("home.php:Home|:Help");
}
?>
<table class="headerBox" cellspacing="0" cellpadding="5" border="0" style="margin-top: 10px;">
<tr><td class="headerCell" align="center" >
<a name="top"></a>Help &amp; Frequently Asked Questions
</td></tr>
</table>
</td></tr>

<tr><td style="padding: 10px 16px;">

<!-- Search and Controls Toolbar -->
<div class="help-toolbar">
  <div class="wishlist-search-box" style="margin: 0;">
    <label for="helpSearch"><b>Search Help:</b></label>
    <input type="text" id="helpSearch" class="wishlist-search-input" placeholder="Search questions or keywords..." oninput="filterHelp(this.value)">
    <input type="button" id="helpSearchClear" class="actionButton" value="Clear" onclick="clearHelpSearch()" style="display:none;">
    <span id="helpSearchCount" class="wishlist-search-count"></span>
  </div>
  <div>
    <button type="button" id="toggleAllBtn" class="help-toggle-all-btn" onclick="toggleAllFaqs()">Expand All</button>
  </div>
</div>

<!-- Category Tabs -->
<div class="help-tabs-bar" id="helpTabsBar">
  <button type="button" class="help-tab-btn active" data-cat="all" onclick="switchCategory('all')">All Topics</button>
  <button type="button" class="help-tab-btn" data-cat="viewing" onclick="switchCategory('viewing')">👀 Viewing Lists</button>
  <button type="button" class="help-tab-btn" data-cat="managing" onclick="switchCategory('managing')">📝 Managing Lists</button>
  <button type="button" class="help-tab-btn" data-cat="purchases" onclick="switchCategory('purchases')">🎁 Purchases &amp; Budget</button>
  <button type="button" class="help-tab-btn" data-cat="themes" onclick="switchCategory('themes')">🌙 Dark Mode &amp; App</button>
  <button type="button" class="help-tab-btn" data-cat="messaging" onclick="switchCategory('messaging')">💬 Messaging</button>
  <button type="button" class="help-tab-btn" data-cat="account" onclick="switchCategory('account')">👤 Account</button>
</div>

<!-- SECTION 1: Viewing Other People's Lists -->
<div class="faq-section" data-section="viewing">
<h2 class="categoryHeader">👀 Viewing Other People's Lists</h2>

<details class="faq-item" id="viewOther">
  <summary class="faq-question"><span>How do I view someone's list?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>To see someone's wishlist, you first need to be granted permission. Once the list owner has given you access, their name will appear on your homepage. Simply click on their name to view their list.</p>
    <p>If you don't see the person's list you're looking for, you can request access. Go to <b>Update Your Account &gt;&gt; Manage List Access</b> and use the search function to find the person by their username or email. This will send a request for them to approve.</p>
  </div>
</details>

<details class="faq-item" id="accessRequests">
  <summary class="faq-question"><span>How do access requests work?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>When you request access to someone's list, the owner of that list will be notified the next time they log in. They will be presented with a dialog where they can approve or deny your request. They can choose to grant you read-only access, or full access (which includes the ability to see their contact information).</p>
    <p>Once they have made a decision, you will be notified of the outcome the next time you log in. If your request is approved, you will see their name on your homepage and you will be able to view their list.</p>
  </div>
</details>

<details class="faq-item" id="searchList">
  <summary class="faq-question"><span>How do I search or filter a wishlist?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>At the top of each wishlist, you will find a <b>Search List</b> box. As you type, the list instantly filters in real-time, showing only items and categories matching your query (by title, store, or description). Click the <b>Clear</b> button to reset the search.</p>
  </div>
</details>

<details class="faq-item" id="checkOff">
  <summary class="faq-question"><span>How do I mark an item as purchased?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>When you are viewing someone's list, you can mark items that you have purchased to prevent duplicate gifts. The way you do this depends on the quantity the person has requested:</p>
    <ul>
      <li><b>Single Item:</b> If the person wants only one of an item, a checkbox will be displayed next to it. Click the checkbox to mark it as purchased.</li>
      <li><b>Multiple Items:</b> If the person wants more than one of an item, a dropdown menu will be displayed. Select the quantity you have purchased from the menu.</li>
    </ul>
    <p>After making your selections, scroll to the bottom of the page and click the <b>Save Changes</b> button. A notification will confirm your changes are saved, and the item will be marked with an ✖ for all other viewers.</p>
  </div>
</details>

<details class="faq-item" id="removePurchase">
  <summary class="faq-question"><span>How do I undo a purchase I marked?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>If you have mistakenly marked an item as purchased, you can easily undo it. At the bottom of each wishlist, you will find a link to the <b>Purchase Log</b>. This log shows all the items you have marked as purchased on that list.</p>
    <p>Next to each item in the purchase log, there is a <font color=red>[Remove Purchase]</font> link. Click this link to remove your purchase, and the item will become available again for others to purchase.</p>
  </div>
</details>

<details class="faq-item" id="imageZoom">
  <summary class="faq-question"><span>How do I view item images up close?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>When an item includes an image, you can hover your mouse cursor over the thumbnail to see an instant zoomed popover preview. Clicking the image will open a high-resolution fullscreen lightbox view.</p>
  </div>
</details>

<details class="faq-item" id="printList">
  <summary class="faq-question"><span>How do I print a wishlist?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>At the bottom of every wishlist, there is a <b>🖨️ Print List</b> button. Clicking this prepares an ink-saving printable document that automatically removes navigation bars, search filters, and action buttons, leaving a crisp category checklist formatted for paper printing or saving as a PDF.</p>
  </div>
</details>

<details class="faq-item" id="addComment">
  <summary class="faq-question"><span>How do I add comments?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Each wishlist has a <b>Comments</b> section at the bottom. This is a private space for people viewing the list to communicate and coordinate with each other. <b>The owner of the list cannot see these comments</b>, so it's a great place to ask questions or make suggestions about gifts without spoiling the surprise.</p>
    <p>To add a comment, click the <b>Add New Comment</b> button, type your message, and submit. You can also choose to send your comment as an email to other viewers of the list.</p>
  </div>
</details>
</div>

<!-- SECTION 2: Managing Your List -->
<div class="faq-section" data-section="managing">
<h2 class="categoryHeader">📝 Managing Your List</h2>

<details class="faq-item" id="howToModify">
  <summary class="faq-question"><span>How do I modify my list?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>You have full control over your own wishlist. To get started, click the <b>Modify Your List</b> button on the homepage. This will take you to a page where you can manage your categories and items.</p>
  </div>
</details>

<details class="faq-item" id="addCategory">
  <summary class="faq-question"><span>How do I manage categories?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Categories help you organize the items on your list. You can create categories for different types of items, such as "Books", "Kitchen Gadgets", or "Clothing".</p>
    <ul>
      <li>To <b>add a new category</b>, scroll to the bottom of the 'Modify Your List' page and click the <b>Add New Category</b> button.</li>
      <li>To <b>edit or delete a category</b>, use the edit (✏️) and delete (🗑️) buttons located next to each category name.</li>
    </ul>
  </div>
</details>

<details class="faq-item" id="addItem">
  <summary class="faq-question"><span>How do I add items to my list?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>To add an item, go to the 'Modify Your List' page. In the header of each category, you will find a <b>➕ Add Item</b> button. Clicking this button will take you to a form where you can enter the title, description, price, quantity, an optional image, and web links.</p>
    <p><b>Automatic Store Detection:</b> When you paste a product link from major retailers (such as Amazon, Big W, Kmart, Target, EB Games, JB Hi-Fi, Bunnings, Apple, Best Buy, and many others), the website will automatically detect the retailer and fill in the store name for you. On wishlists, these links display as neat <b>Find at [Store]</b> buttons.</p>
  </div>
</details>

<details class="faq-item" id="deleteItem">
  <summary class="faq-question"><span>How do I modify or delete items?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Next to each item on your list, you will see an edit (✏️) and delete (🗑️) button. Use these to make changes to an item's details or to remove it from your list completely.</p>
  </div>
</details>

<details class="faq-item" id="moveItem">
  <summary class="faq-question"><span>How do I reorder items or move them to a different category?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>You can organize your list by dragging and dropping items. Grab the handle icon (☰) on the right side of each item and drag it to your desired position. You can move items within the same category, or drag them into an entirely different category. A confirmation notification will appear when the new order is saved.</p>
  </div>
</details>

<details class="faq-item" id="notifications">
  <summary class="faq-question"><span>How do I send an update notification?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>After you've made changes to your list, you might want to let people know. At the bottom of the 'Modify Your List' page, you will find a <b>Send Update Notification</b> button. This allows you to send an email notification to everyone who has permission to view your list.</p>
  </div>
</details>
</div>

<!-- SECTION 3: Gift Purchases & Budget -->
<div class="faq-section" data-section="purchases">
<h2 class="categoryHeader">🎁 Gift Purchases &amp; Spending Tracking</h2>

<details class="faq-item" id="viewPurchases">
  <summary class="faq-question"><span>Where can I see what gifts I have bought?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Click the <b>View Purchases</b> button on your homepage. This page lists all the items you have marked as purchased across all wishlists you have access to, organized by recipient and date.</p>
  </div>
</details>

<details class="faq-item" id="spendingSummary">
  <summary class="faq-question"><span>How does the Gift Spending Summary work?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>At the top of the View Purchases page, a summary card calculates your total money spent and total number of gifts purchased, along with a per-person breakdown. This helps you track gift budgets and make sure everyone is treated fairly during birthdays and holidays.</p>
  </div>
</details>

<details class="faq-item" id="privacyMode">
  <summary class="faq-question"><span>How do I use Privacy Mode to hide prices?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>If someone is standing near your screen while you are shopping or checking your list, click the <b>👁️ Hide Prices</b> button on the spending card. This instantly applies a blur filter over all spending totals and table prices. Your privacy preference is saved, so it stays blurred until you click <b>👁️ Show Prices</b> again.</p>
  </div>
</details>
</div>

<!-- SECTION 4: Dark Mode & App -->
<div class="faq-section" data-section="themes">
<h2 class="categoryHeader">🌙 Dark Mode &amp; Mobile App (PWA)</h2>

<details class="faq-item" id="darkMode">
  <summary class="faq-question"><span>How do I enable Dark Mode?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Click the <b>🌙 / ☀️</b> button in the top navigation bar at any time to toggle between light and dark themes. The site remembers your preference across all pages. By default, the site also respects your device or operating system's dark/light appearance setting.</p>
  </div>
</details>

<details class="faq-item" id="pwa">
  <summary class="faq-question"><span>Can I install this site as an app on my phone or tablet?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Yes! The WishList website is a Progressive Web App (PWA). You can install it directly to your home screen without going through an app store:</p>
    <ul>
      <li><b>iOS (iPhone / iPad):</b> Open Safari, tap the <b>Share</b> button (square with arrow), and choose <b>Add to Home Screen</b>.</li>
      <li><b>Android:</b> Open Chrome, tap the menu (⋮), and choose <b>Install App</b> or <b>Add to Home screen</b>.</li>
      <li><b>Desktop (Chrome / Edge):</b> Click the install icon in your browser's address bar.</li>
    </ul>
    <p>Installed as an app, it opens fullscreen with an app icon and caches stylesheets and essential assets for fast loading.</p>
  </div>
</details>

<details class="faq-item" id="backToTop">
  <summary class="faq-question"><span>How do I quickly return to the top of a long list?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>Whenever you scroll down a page, a floating arrow button (<b>↑</b>) will automatically appear in the bottom-right corner. Click or tap it to smoothly glide back to the top of the page.</p>
  </div>
</details>
</div>

<!-- SECTION 5: Messaging -->
<div class="faq-section" data-section="messaging">
<h2 class="categoryHeader">💬 Messaging</h2>

<details class="faq-item" id="messages">
  <summary class="faq-question"><span>How do I use the messaging system?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>The website has a built-in internal messaging system that allows you to communicate with other users without leaving the site. You can access it by clicking on the <b>Messages</b> button on your homepage.</p>
    <p>On the messages page, you will find your inbox with received messages and a list of sent messages. You can also compose new messages to send to other users.</p>
  </div>
</details>

<details class="faq-item" id="sendMessage">
  <summary class="faq-question"><span>How do I send a message?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>To send a new message, go to the <b>Messages</b> page and find the "Send a New Message" form. You can select one or more recipients from the dropdown list. This list contains all the users whose wishlists you are currently able to view. Fill in the subject and body of the message, and click "Send Message".</p>
  </div>
</details>

<details class="faq-item" id="manageMessages">
  <summary class="faq-question"><span>How do I manage my messages?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>For each message in your inbox, you have the option to mark it as read or unread, and to delete it. Sent messages can be viewed but not edited or deleted.</p>
  </div>
</details>
</div>

<!-- SECTION 6: Account -->
<div class="faq-section" data-section="account">
<h2 class="categoryHeader">👤 Your Account</h2>

<details class="faq-item" id="changeInfo">
  <summary class="faq-question"><span>How do I change my personal information?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>To update your personal details, such as your name or email address, go to the <b>Update Your Account</b> page and click the <b>Edit Personal Info</b> button.</p>
  </div>
</details>

<details class="faq-item" id="password">
  <summary class="faq-question"><span>How do I change my password?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>You can change your password from the <b>Update Your Account</b> page. Click the <b>Change Password</b> button and follow the instructions.</p>
  </div>
</details>

<details class="faq-item" id="listAccess">
  <summary class="faq-question"><span>How do I manage who can view my list?</span><span class="faq-icon">▾</span></summary>
  <div class="faq-answer">
    <p>You have control over who can see your wishlist. Go to <b>Update Your Account &gt;&gt; Manage List Access</b>. On this page, you can see a list of people who currently have access. You can also grant access to new people or revoke access from existing people.</p>
  </div>
</details>
</div>

</td></tr>

<tr><td align=center style="padding: 15px 0;">
<a href="#top" class="actionButton" style="padding: 6px 14px;">↑ Return to Top</a>
</td></tr>
</table>
</center>

<script type="text/javascript" src="js/theme.js"></script>
<script type="text/javascript" src="js/back-to-top.js"></script>
<script type="text/javascript">
var currentCategory = 'all';

function switchCategory(cat) {
    currentCategory = cat;
    var tabBtns = document.querySelectorAll('.help-tab-btn');
    tabBtns.forEach(function(btn) {
        if (btn.getAttribute('data-cat') === cat) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    var sections = document.querySelectorAll('.faq-section');
    sections.forEach(function(sec) {
        if (cat === 'all' || sec.getAttribute('data-section') === cat) {
            sec.style.display = '';
        } else {
            sec.style.display = 'none';
        }
    });

    // Re-apply search filter if one exists
    var query = document.getElementById('helpSearch').value;
    if (query) {
        filterHelp(query);
    }
}

function filterHelp(query) {
    var q = (query || '').toLowerCase().trim();
    var clearBtn = document.getElementById('helpSearchClear');
    var countBadge = document.getElementById('helpSearchCount');

    if (clearBtn) {
        clearBtn.style.display = q.length > 0 ? 'inline-block' : 'none';
    }

    var totalMatches = 0;
    var sections = document.querySelectorAll('.faq-section');

    sections.forEach(function(sec) {
        var secCat = sec.getAttribute('data-section');
        var isSecActive = (currentCategory === 'all' || currentCategory === secCat);
        var secHasVisible = false;

        var items = sec.querySelectorAll('.faq-item');
        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            var matches = (q === '' || text.indexOf(q) !== -1);

            if (matches && isSecActive) {
                item.style.display = '';
                secHasVisible = true;
                totalMatches++;
                if (q !== '') {
                    item.setAttribute('open', '');
                }
            } else if (!isSecActive) {
                item.style.display = 'none';
            } else {
                item.style.display = 'none';
            }
        });

        if (isSecActive && (q === '' || secHasVisible)) {
            sec.style.display = '';
        } else {
            sec.style.display = 'none';
        }
    });

    if (countBadge) {
        if (q.length > 0) {
            countBadge.textContent = totalMatches + ' found';
            countBadge.style.display = 'inline-block';
        } else {
            countBadge.style.display = 'none';
        }
    }
}

function clearHelpSearch() {
    var input = document.getElementById('helpSearch');
    if (input) {
        input.value = '';
        filterHelp('');
        input.focus();
    }
}

var allExpanded = false;
function toggleAllFaqs() {
    allExpanded = !allExpanded;
    var items = document.querySelectorAll('.faq-item');
    items.forEach(function(item) {
        if (allExpanded) {
            item.setAttribute('open', '');
        } else {
            item.removeAttribute('open');
        }
    });
    var btn = document.getElementById('toggleAllBtn');
    if (btn) {
        btn.textContent = allExpanded ? 'Collapse All' : 'Expand All';
    }
}

function handleDeepLink() {
    var hash = window.location.hash;
    if (!hash) return;
    var id = hash.replace(/^#/, '');
    if (!id) return;

    var target = document.getElementById(id);
    if (!target) return;

    // If anchor is inside a section or is an item
    var item = target.classList.contains('faq-item') ? target : target.closest('.faq-item');
    if (item) {
        // Ensure its section is visible
        var sec = item.closest('.faq-section');
        if (sec) {
            var cat = sec.getAttribute('data-section');
            if (currentCategory !== 'all' && currentCategory !== cat) {
                switchCategory('all');
            }
        }
        item.setAttribute('open', '');
        item.classList.add('faq-highlight');
        setTimeout(function() {
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 150);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    handleDeepLink();
});
window.addEventListener('hashchange', handleDeepLink);
</script>
</body>
</html>
