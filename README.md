# WishList Site Overview

This PHP-based web application is designed to manage gift lists for multiple families, facilitating organized gift-giving. Users can create and share their wishlists, and mark items off other people's lists. The application also includes administrative functionalities for user and site management.

## Key Features

*   **User Authentication & Management:** Secure login, user registration requests, and password reset functionality. Session management is handled via PHP sessions.
*   **Wishlist Management:** Users can create, edit, and delete categories and items within their personal wishlists. Items can be prioritized, include details like price and links, and be marked as purchasable.
*   **Wishlist Viewing & Sharing:** Users can view others' wishlists based on granted permissions. A purchase history allows tracking of gifts bought for others. Email notifications are sent for certain events.
*   **Admin Functionality:** Administrators have elevated privileges for site configuration, user management, and comprehensive control over site settings.

## Recent Improvements

*   **Drag-and-Drop Reordering:** Users can now easily reorder items within a category, or move them to a different category, using a simple drag-and-drop interface. The old up/down arrow buttons have been removed in favor of this more intuitive system.
*   **Streamlined Item Creation:** An "Add Item" button (➕) has been added to each category header on the 'Modify List' page, allowing for quicker and more direct item creation within the desired category.
*   **Access Request System:** A new workflow for managing list access has been implemented.
    *   When a user requests access to another's list, the list owner is now presented with a popup dialog on their homepage to approve or deny the request.
    *   The owner can grant read-only access or full access (including contact info).
    *   The requesting user is notified of the decision when they next log in.
*   **Modernized UI:**
    *   The styling of category headers has been updated for a cleaner, more modern look.
    *   The Help page has been completely revamped with clearer, more comprehensive documentation and a consistent design.
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
