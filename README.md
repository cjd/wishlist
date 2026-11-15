# WishList Site Overview

A modern, responsive PHP-based web application designed to manage gift lists for multiple families, facilitating collaborative, organized, and spoiler-free gift-giving. Users can curate personal wishlists, browse friends' and family members' lists, coordinate secret gift ideas, track shared expenses, and check off purchased items without spoiling surprises.

---

## 🌟 Key Features

### 1. 🛡️ Security & Authentication Hardening
* **Modern Cryptography:** Passwords securely hashed and verified with PHP's `password_hash()` and `password_verify()` (bcrypt) with automatic on-the-fly migration from legacy hashes.
* **SQL Injection Immunity:** All database operations modernized with parameterized prepared statements (`mysqli_prepare` / `mysqli_stmt_bind_param`).
* **CSRF Defense:** State-changing requests throughout list management, purchasing, comments, and settings are validated with cryptographic CSRF tokens (`verifyCsrfOrDie()`).
* **Session Hardening:** Protected against session fixation via `session_regenerate_id(true)` upon authentication, secured with `HttpOnly` and `SameSite=Lax` cookies, alongside 7-day inactivity timeouts.
* **Security Headers:** Enforced `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: strict-origin-when-cross-origin`.

### 2. 📝 Wishlist Management & Organizing
* **Drag-and-Drop Item & Category Reordering:** Full drag-and-drop sorting powered by Sortable.js:
  * Reorder items within a category or drag items across different categories.
  * Reorder entire categories using the category drag handle (☰) with instant auto-saving and toast confirmations.
* **Real-Time Duplicate Item Detection:** Real-time checking against all existing items across all categories as you type an item title or paste a store URL, preventing duplicate gift requests with an interactive warning banner.
* **Retailer Auto-Detection:** Automatically recognizes major retailers when pasting product links (Amazon, Big W, Kmart, Target, EB Games, JB Hi-Fi, Bunnings, Apple, eBay, Best Buy, Walmart, LEGO, Barnes & Noble, etc.), auto-filling store names and formatting attractive **"Find at [Store] ↗"** action badges.
* **Image Uploads & Thumbnails:** Automatic image resizing, thumbnail creation, and modal lightbox zoom previews.
* **Priority Star Marking:** Highlight most-wanted gifts with star priority badges.

### 3. 👀 Wishlist Browsing & Viewing
* **Instant Live Search:** Real-time search filter bar atop every wishlist to quickly filter items by name, store, or description without reloading.
* **Hover Zoom Image Popover:** Hover over any item thumbnail for an instant zoomed preview; click to view in full resolution via Featherlight.
* **Print-Optimized View:** One-click **🖨️ Print List** button generates a clean, ink-saving paper checklist that removes site navigation, headers, and buttons for holiday or shopping trips.
* **Secret Viewer Comments:** A dedicated comments section visible exclusively to authorized viewers. The list owner is permanently barred from viewing these comments to preserve surprises.

### 4. 👥 Group Gifting & Collaboration
* **Split Gift Tracker:** When marking an item as bought, contributors can indicate a group gift:
  * **Searchable User Picker:** Select actual registered site users / family members via a Select2 multi-select dropdown to record all contributors.
  * **Optional Notes:** Add notes or name external non-site contributors (e.g. *"Split with Sarah & Dave (50/50)"*).
  * **Co-Buyer Synchronization:** Automatically links the purchase record so the item appears in the **View Purchases** spending log for all co-buyers.
  * **Public Badges:** Displays purple **`👥 Split with [Names]`** badges on wishlists and in viewer notifications.
* **Secret Gift Suggestion Box:** Viewers can suggest gift ideas that aren't on the recipient's list:
  * Accessible via **"💡 Suggest Secret Gift Idea"** on the wishlist.
  * Prompts for title, retail URL, estimated price, and private notes.
  * Renders as a styled purple suggestion card with store buttons and price tags for all viewers while completely invisible to the list owner.
  * Dispatches on-site notification messages to fellow viewers for group feedback.

### 5. 💰 Gift Purchases & Budgeting
* **Purchase History Log:** Track every gift purchased across all family wishlists.
* **Spending Summary Dashboard:** Calculates total spending, total gifts purchased, and individual per-person budget totals.
* **Privacy Mode:** Built-in **👁️ Hide Prices** toggle that applies a real-time blur filter over spending metrics and item prices when shopping around others.

### 6. 🌙 Modern UI & Mobile Experience
* **Seamless Dark Mode:** Complete dark theme with system preference auto-detection and persistent toggle control in the navigation bar.
* **Progressive Web App (PWA):** Installable as a standalone app on iOS (Safari Add to Home Screen), Android (Chrome install), and Desktop with service worker asset caching.
* **Smooth Navigation:** Floating back-to-top button (↑) for long wishlists and toast notifications for user actions.
* **Interactive Help Center:** Fully modernized [help.php](help.php) featuring collapsible FAQ accordions, real-time keyword search filtering, category tabs, and deep-linkable question URLs.

### 7. 💬 Internal Messaging & Administration
* **Direct Messaging:** Send internal messages to authorized list viewers with safe HTML formatting and searchable Select2 recipient selection.
* **Access Requests:** Flexible access approval system with popups on login for pending permission requests.
* **Admin Control Panel:** Comprehensive user management, password resets, database migration checks, and permission management.

---

## 🛠️ Technology Stack

* **Server:** PHP 8.x (compatible with PHP 7.4 - 8.3+)
* **Database:** MySQL 8.x / MariaDB (strict mode compliant with zero-date migration)
* **Frontend:** HTML5, CSS3 (CSS Variables for Light/Dark themes)
* **Libraries:** jQuery 3.7.1, Sortable.js, Select2 4.1, Featherlight Lightbox
* **Deployment:** Docker / Nginx / PHP-FPM / Certbot SSL

---

## 🚀 Installation & Setup

1. **Clone or Extract:** Place the files in your web server's document root (e.g., `/var/www/lists/`).
2. **Access Setup:** Open the web application in your browser (e.g., `https://your-domain.com/`). If unconfigured, it will automatically route to the installation wizard (`admin.php?setup=true`).
3. **Database Configuration:** Provide database credentials (host, database name, user, password).
4. **Create Administrator:** Complete the setup form to generate `config.php`, create database tables, and register the primary administrator account.
5. **Permissions & Uploads:** Ensure the `uploads/` directory has write permissions for the web server user (`www-data`).
