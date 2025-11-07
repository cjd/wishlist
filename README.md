# WishList Site Overview

This PHP-based web application is designed to manage gift lists for multiple families, facilitating organized gift-giving. Users can create and share their wishlists, and mark items off other people's lists. The application also includes administrative functionalities for user and site management.

## Key Features

*   **User Authentication & Management:** Secure login (though with a known legacy vulnerability), user registration requests, and password reset functionality. Session management is handled via PHP sessions.
*   **Wishlist Management:** Users can create, edit, delete, and reorder categories and items within their personal wishlists. Items can be prioritized, include details like price and links, and be marked as purchasable.
*   **Wishlist Viewing & Sharing:** Users can view others' wishlists based on granted permissions (read-only, allow edit, view contact info). A purchase history allows tracking of gifts bought for others. Email notifications are sent for certain events.
*   **Admin Functionality:** Administrators have elevated privileges for site configuration, user management (add, edit, delete users, grant admin status), and comprehensive control over site settings.
*   **Recent Improvements:**
    *   **Enhanced Admin User Management:** Administrators can now directly access and manage any user's account details from the admin page.
    *   **Persistent User Context in Admin Edits:** When an administrator edits another user's account, the system maintains the correct user context across various actions.
    *   **Corrected Checkbox Handling:** Resolved issues with checkbox values not being correctly persisted during account updates.

## HOW TO INSTALL

1.  Unpack contents of tarball into a webfolder.

2.  Run `changePerm` script to set permissions of files.

3.  Create a MySQL database and import/load `sampleDb.sql`.

4.  Copy `config.php-example` to `config.php` and edit for your settings.

5.  Visit any of the PHP pages. This will redirect you to the WishList setup page.

6.  Set the requested variables.

7.  You should be redirected to the login page. The admin's username and password are both "admin" (without the quotes). Obviously, you should change the password.
