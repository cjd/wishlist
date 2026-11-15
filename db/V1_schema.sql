--
-- WishList Database Schema (Baseline V4 - InnoDB / utf8mb4)
--

DROP TABLE IF EXISTS `accessRequests`;
CREATE TABLE `accessRequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `requesterId` varchar(50) NOT NULL,
  `targetId` varchar(50) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `requestDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_target_status` (`targetId`, `status`),
  KEY `idx_requester_notif` (`requesterId`, `notified`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `cid` int unsigned NOT NULL AUTO_INCREMENT,
  `catSortOrder` int NOT NULL DEFAULT '0',
  `userid` varchar(50) NOT NULL DEFAULT '',
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) NOT NULL DEFAULT '',
  `linkname` varchar(255) NOT NULL DEFAULT '',
  `linkurl` varchar(255) NOT NULL DEFAULT '',
  `catSubDescription` text NOT NULL,
  PRIMARY KEY (`cid`),
  KEY `idx_userid_sort` (`userid`, `catSortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `comment_userid` varchar(50) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `commentId` int unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_suggestion` tinyint(1) NOT NULL DEFAULT '0',
  `suggest_title` varchar(255) NOT NULL DEFAULT '',
  `suggest_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `claimed_by` varchar(50) DEFAULT NULL,
  `claimed_date` datetime DEFAULT NULL,
  `split_users` varchar(255) NOT NULL DEFAULT '',
  `split_with` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`commentId`),
  KEY `idx_userid_date` (`userid`, `date`),
  KEY `idx_comment_userid` (`comment_userid`),
  KEY `idx_claimed_by` (`claimed_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `description` varchar(100) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`userid`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `itemPriceHistory`;
CREATE TABLE `itemPriceHistory` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `dateChanged` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `iid` int unsigned NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`),
  KEY `idx_iid` (`iid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `iid` int unsigned NOT NULL AUTO_INCREMENT,
  `cid` int unsigned NOT NULL DEFAULT '0',
  `itemSortOrder` int NOT NULL DEFAULT '0',
  `addStar` char(1) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT '',
  `price` decimal(10,2) DEFAULT '0.00',
  `quantity` smallint unsigned DEFAULT '1',
  `subdesc` text,
  `allowCheck` varchar(5) DEFAULT 'false',
  `link2` varchar(255) DEFAULT '',
  `link2url` varchar(1024) DEFAULT NULL,
  `link3` varchar(255) DEFAULT '',
  `link3url` varchar(1024) DEFAULT NULL,
  `link1` varchar(255) DEFAULT '',
  `link1url` varchar(1024) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `createDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`iid`),
  KEY `idx_cid_sort` (`cid`, `itemSortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `recipient_id` varchar(50) NOT NULL,
  `sender_id` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `sender_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `recipient_deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`message_id`),
  KEY `idx_recipient_unread` (`recipient_id`, `recipient_deleted`, `is_read`, `timestamp`),
  KEY `idx_sender_deleted` (`sender_id`, `sender_deleted`, `timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `admin` char(1) NOT NULL DEFAULT '0',
  `registered` char(1) NOT NULL DEFAULT '0',
  `lastLoginDate` datetime DEFAULT NULL,
  `lastModDate` datetime DEFAULT NULL,
  `lastname` varchar(100) DEFAULT '',
  `firstname` varchar(100) DEFAULT '',
  `suffix` varchar(100) DEFAULT '',
  `street` varchar(100) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `state` varchar(15) DEFAULT '',
  `zip` varchar(20) NOT NULL DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `phone` varchar(100) DEFAULT '',
  `mobilephone` varchar(100) DEFAULT '',
  `bmonth` varchar(15) DEFAULT '',
  `bday` tinyint DEFAULT '0',
  `url` varchar(255) DEFAULT '',
  `password` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `purchaseHistory`;
CREATE TABLE `purchaseHistory` (
  `purchaseId` int unsigned NOT NULL AUTO_INCREMENT,
  `iid` int unsigned NOT NULL DEFAULT '0',
  `userid` varchar(50) NOT NULL DEFAULT '',
  `quantity` smallint unsigned NOT NULL DEFAULT '0',
  `boughtDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `splitWith` varchar(255) NOT NULL DEFAULT '',
  `splitUsers` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`purchaseId`),
  KEY `idx_iid` (`iid`),
  KEY `idx_userid` (`userid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `viewList`;
CREATE TABLE `viewList` (
  `lastViewDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `viewContactInfo` char(1) NOT NULL DEFAULT '0',
  `readOnly` char(1) NOT NULL DEFAULT '1',
  `pid` varchar(50) NOT NULL DEFAULT '',
  `viewer` varchar(50) NOT NULL DEFAULT '',
  `allowEdit` char(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`, `viewer`),
  KEY `idx_viewer_edit` (`viewer`, `allowEdit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `loginAttempts`;
CREATE TABLE `loginAttempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `userid` varchar(50) NOT NULL DEFAULT '',
  `action_type` varchar(20) NOT NULL DEFAULT 'login',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_action_time` (`ip_address`, `action_type`, `attempted_at`),
  KEY `idx_user_action_time` (`userid`, `action_type`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `passwordResetRequests`;
CREATE TABLE `passwordResetRequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `target_userid` varchar(50) NOT NULL,
  `approver_userid` varchar(50) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `status` enum('pending','approved','denied','expired') NOT NULL DEFAULT 'pending',
  `requester_ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_approver_status` (`approver_userid`, `status`),
  KEY `idx_target_status` (`target_userid`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `schema_version`;
CREATE TABLE `schema_version` (
  `version` int NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `schema_version` (`version`) VALUES (4);
