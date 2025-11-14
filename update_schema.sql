-- Add 'image' column to 'items' table if it doesn't exist
ALTER TABLE `items` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL;

-- Correct the 'userid' column definition in 'purchaseHistory' table
ALTER TABLE `purchaseHistory` MODIFY `userid` VARCHAR(50) NOT NULL DEFAULT '';

-- Create 'accessRequests' table if it doesn't exist
CREATE TABLE IF NOT EXISTS `accessRequests` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `requesterId` varchar(50) NOT NULL,
  `targetId` varchar(50) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL default 'pending',
  `notified` tinyint(1) NOT NULL default '0',
  `requestDate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Create 'messages' table if it doesn't exist
CREATE TABLE IF NOT EXISTS `messages` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient_id` varchar(50) NOT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `recipient_id` (`recipient_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
