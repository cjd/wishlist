# WishList Site Overview

A modern, responsive PHP-based web application designed to manage gift lists for multiple families, facilitating collaborative, organized, and spoiler-free gift-giving. Users can curate personal wishlists, browse friends' and family members' lists, coordinate secret gift ideas, track shared expenses, and check off purchased items without spoiling surprises.

---

## 🌟 Key Features

### 1. 🛡️ Security & Authentication Hardening
* **Modern Cryptography & Timing Safety:** Passwords hashed and verified with PHP's `password_hash()` and `password_verify()` (bcrypt), with automatic on-the-fly migration from legacy hashes and dummy-hash verification to prevent username timing enumeration.
* **DB-Backed Brute-Force Protection:** Login, bookmarklet sign-in, registration, and password reset endpoints enforce IP and account rate limiting via the `loginAttempts` table.
* **Approval-Based Password Recovery:** Submitting `forgotPassword.php` dispatches a single-use SHA-256 hashed approval token (`passwordResetRequests`, 24-hour expiry) via internal message to a verified mutual family contact or administrator, generating a temporary password only after authenticated approval in `approvePasswordReset.php`.
* **Live Session & Authorization Gate:** Every request re-validates user existence, administrator role, delegated `allowEdit` context, and `viewList` permissions (`readOnly`, `allowEdit`, `viewContactInfo`) against live database state. Sessions use 7-day `HttpOnly`, `SameSite=Lax`, proxy-aware `Secure` cookies, `session_regenerate_id(true)`, and `Clear-Site-Data` on logout.
* **SQL Injection, XSS & CSRF Defense:** Parameterized `mysqli` prepared statements throughout, context-aware HTML/URL sanitization (`escapeText()`, `sanitizeHttpUrl()`, `formatMessage()` without quote double-encoding), and strict POST CSRF validation (`verifyCsrfOrDie(true)`).
* **Runtime & Header Hardening:** Enforced PHP-FPM runtime settings in `.user.ini`, `0640` permissions on `config.php`, and `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: strict-origin-when-cross-origin` headers.

### 2. 📝 Wishlist Management & Organizing
* **Drag-and-Drop Item & Category Reordering:** Full drag-and-drop sorting powered by SortableJS:
  * Reorder items within a category or drag items across different categories.
  * Reorder entire categories using the category drag handle (☰) with instant auto-saving and toast confirmations.
* **Real-Time Duplicate Item Detection:** Checks against all existing items across every category as you type an item title or paste a store URL (preserving product query parameters while stripping tracking tags), displaying an instant warning banner on matches.
* **Retailer Auto-Detection & Web Bookmarklet:** Automatically recognizes major retailers from product hostnames (Amazon, Big W, Kmart, Target, EB Games, JB Hi-Fi, Bunnings, Officeworks, Myer, Apple, eBay, Best Buy, Walmart, LEGO, Old Navy, Gap, etc.), formatting **"Find at [Store] ↗"** badges. Includes a browser **"Add to WishList"** bookmarklet ([wishlist_button.php](wishlist_button.php)) to clip items from any store page.
* **Hardened Image Uploads & Thumbnails:** Supports JPEG, PNG, GIF, and WebP with `finfo` MIME validation, decompression-bomb protection, EXIF orientation auto-rotation, alpha-channel white-canvas compositing, non-destructive replacement on edit, and cache-busting URLs.
* **Priority Star Marking:** Highlight most-wanted gifts with star priority badges.

### 3. 👀 Wishlist Browsing & Viewing
* **Batched Query Performance:** Loads categories, items, and purchase histories in 3 indexed SQL queries (eliminating N+1 query overhead).
* **Instant Live Search & Unsaved-Changes Bar:** Real-time search filter bar atop every wishlist plus a sticky save bar whenever purchase selections are modified.
* **Hover Zoom Image Popover:** Hover over any item thumbnail for an instant zoomed preview; click to view in full resolution via Featherlight.
* **Spoiler-Free & Checklist Printing:** Dedicated **🖨️ Print List** views strip navigation and action clutter—preserving purchase marks for shoppers while hiding all purchase indicators on the list owner's print view so surprises stay safe.
* **Secret Viewer Comments:** A private comments section visible exclusively to authorized viewers and hidden from the list owner.

### 4. 👥 Group Gifting & Collaboration
* **Transactional Purchase Tracking:** Single-item checkboxes and multi-quantity dropdowns use `InnoDB` row locks (`FOR UPDATE`) to prevent concurrent over-purchasing.
* **Split Gift Tracker:** When marking an item as bought, contributors can coordinate group gifts:
  * **Searchable User Picker:** Select registered co-buyers via a Select2 multi-select dropdown and add optional split notes (e.g., *"50/50 with Sarah"*).
  * **Targeted Co-Buyer Notifications:** Automatically links the purchase into **View Purchases** for all selected co-buyers and sends internal notifications *only* to those co-buyers (solo purchases never broadcast messages).
  * **Public Badges:** Displays purple **`👥 Split with [Names]`** badges to fellow viewers so nobody buys a duplicate.
* **Secret Gift Suggestion Box:** Viewers can propose off-list gift ideas via **"💡 Suggest Secret Gift Idea"** with title, retail URL, estimated price, and private notes—rendered as styled suggestion cards for viewers while completely invisible to the recipient.

### 5. 💰 Gift Purchases & Budgeting
* **Purchase History Log:** Track every gift purchased (including shared group gifts) across all family wishlists with one-click purchase undo.
* **Spending Summary Dashboard:** Calculates total spending, total gifts purchased, and individual per-person budget totals.
* **Privacy Mode:** Built-in **👁️ Hide Prices** toggle blurs spending metrics and item prices on screen and keeps them hidden when printing.

### 6. 🌙 Modern UI & Mobile Experience
* **Seamless Dark Mode:** Complete dark theme with system preference auto-detection and persistent toggle control in the navigation bar.
* **Privacy-Safe Progressive Web App (PWA):** Installable on iOS, Android, and Desktop via `manifest.json` and `service-worker.js` (`wishlist-v2`), strictly caching static UI assets while never caching authenticated HTML pages or private `/uploads/` images.
* **Interactive Help Center:** Searchable, deep-linkable FAQ in [help.php](help.php) with category tabs and collapsible accordions.

### 7. 💬 Internal Messaging & Administration
* **Direct Messaging:** Send internal messages to authorized list viewers with safe HTML formatting, Secret Gift card rendering, read/unread toggles, and deletion from both **Inbox** and **Sent Messages**.
* **Access Requests:** Streamlined list access requests with login modal notifications for owners and requesters.
* **Transactional Admin Control Panel:** Create users, rename User IDs transactionally across all relational tables, perform cascading user deletions (including uploaded images), manage admin roles, and track automatic schema migrations.

---

## 🛠️ Technology Stack

* **Server:** PHP 8.x (`mysqli` prepared statements, `.user.ini` hardening)
* **Database:** MySQL 8.x / MariaDB (`InnoDB`, `utf8mb4_unicode_ci`, versioned migrations `db/V1_schema.sql` – `db/V3_schema.sql`)
* **Frontend:** HTML5, CSS3 (Light, Dark, and Print stylesheets), Vanilla JS
* **Libraries:** jQuery 3.7.1, SortableJS, Select2 4.1, Featherlight Lightbox
* **Deployment:** Docker / Nginx / PHP-FPM / Syncthing (`.stignore` configured)

---

## 🚀 Installation & Setup

1. **Clone or Extract:** Place the files in your web server's document root (e.g., `/var/www/lists/`).
2. **Access Setup:** Open the web application in your browser (e.g., `https://your-domain.com/`). If unconfigured, it routes to the setup wizard (`admin.php?setup=true`).
3. **Database Configuration:** Provide database credentials (host, database name, user, password).
4. **Create Administrator:** Complete the setup form to generate `config.php` (`0640` permissions), apply `db/V1_schema.sql` – `db/V3_schema.sql`, and create the primary administrator account.
5. **Permissions & Uploads:** Ensure the `uploads/` directory is writable by the PHP-FPM user (`www-data`) and excluded from public PHP execution.
