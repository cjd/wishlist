-- Add 'image' column to 'items' table if it doesn't exist
ALTER TABLE `items` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL;

-- Correct the 'userid' column definition in 'purchaseHistory' table
ALTER TABLE `purchaseHistory` MODIFY `userid` VARCHAR(50) NOT NULL DEFAULT '';

-- Add 'sender_deleted' and 'recipient_deleted' columns to 'messages' table
ALTER TABLE `messages` ADD COLUMN `sender_deleted` tinyint(1) NOT NULL DEFAULT '0';
ALTER TABLE `messages` ADD COLUMN `recipient_deleted` tinyint(1) NOT NULL DEFAULT '0';