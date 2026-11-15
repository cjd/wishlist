--
-- Migration V3: Modernise tables to InnoDB + utf8mb4, clean zero-dates, widen columns, add indexes & auth tables
--

SET SESSION sql_mode = '';

ALTER TABLE `people`
  MODIFY COLUMN `lastLoginDate` datetime DEFAULT NULL,
  MODIFY COLUMN `lastModDate` datetime DEFAULT NULL,
  MODIFY COLUMN `zip` varchar(20) NOT NULL DEFAULT '',
  MODIFY COLUMN `password` varchar(255) DEFAULT NULL;

UPDATE `people` SET `lastLoginDate` = NULL WHERE CAST(`lastLoginDate` AS CHAR) LIKE '0000-%';
UPDATE `people` SET `lastModDate` = NULL WHERE CAST(`lastModDate` AS CHAR) LIKE '0000-%';
UPDATE `people` SET `zip` = '' WHERE `zip` = '0';

UPDATE `viewList` SET `lastViewDate` = CURRENT_TIMESTAMP WHERE CAST(`lastViewDate` AS CHAR) LIKE '0000-%';
ALTER TABLE `viewList`
  MODIFY COLUMN `lastViewDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN `pid` varchar(50) NOT NULL DEFAULT '',
  MODIFY COLUMN `viewer` varchar(50) NOT NULL DEFAULT '';

UPDATE `comments` SET `date` = CURRENT_TIMESTAMP WHERE `date` IS NULL OR CAST(`date` AS CHAR) LIKE '0000-%';
ALTER TABLE `comments`
  MODIFY COLUMN `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE `items` SET `createDate` = '2000-01-01 00:00:00' WHERE CAST(`createDate` AS CHAR) LIKE '0000-%';
ALTER TABLE `items`
  MODIFY COLUMN `createDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;

UPDATE `itemPriceHistory` SET `dateChanged` = CURRENT_TIMESTAMP WHERE CAST(`dateChanged` AS CHAR) LIKE '0000-%';
ALTER TABLE `itemPriceHistory`
  ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST,
  MODIFY COLUMN `dateChanged` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  ADD KEY `idx_iid` (`iid`);

UPDATE `purchaseHistory` SET `boughtDate` = CURRENT_TIMESTAMP WHERE CAST(`boughtDate` AS CHAR) LIKE '0000-%';
ALTER TABLE `purchaseHistory`
  MODIFY COLUMN `boughtDate` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `purchaseHistory` ADD COLUMN `splitWith` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE `purchaseHistory` ADD COLUMN `splitUsers` varchar(255) NOT NULL DEFAULT '';

ALTER TABLE `accessRequests`
  ADD KEY `idx_target_status` (`targetId`, `status`),
  ADD KEY `idx_requester_notif` (`requesterId`, `notified`, `status`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `categories`
  ADD KEY `idx_userid_sort` (`userid`, `catSortOrder`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `comments`
  ADD KEY `idx_userid_date` (`userid`, `date`),
  ADD KEY `idx_comment_userid` (`comment_userid`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `favorites`
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `itemPriceHistory`
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `items`
  ADD KEY `idx_cid_sort` (`cid`, `itemSortOrder`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `messages`
  DROP KEY `recipient_id`,
  ADD KEY `idx_recipient_unread` (`recipient_id`, `recipient_deleted`, `is_read`, `timestamp`),
  ADD KEY `idx_sender_deleted` (`sender_id`, `sender_deleted`, `timestamp`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `people`
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `purchaseHistory`
  ADD KEY `idx_iid` (`iid`),
  ADD KEY `idx_userid` (`userid`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `viewList`
  ADD PRIMARY KEY (`pid`, `viewer`),
  ADD KEY `idx_viewer_edit` (`viewer`, `allowEdit`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `schema_version`
  ADD PRIMARY KEY (`version`),
  ENGINE=InnoDB,
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loginAttempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `userid` varchar(50) NOT NULL DEFAULT '',
  `action_type` varchar(20) NOT NULL DEFAULT 'login',
  `attempted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_action_time` (`ip_address`, `action_type`, `attempted_at`),
  KEY `idx_user_action_time` (`userid`, `action_type`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `passwordResetRequests` (
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

UPDATE `schema_version` SET `version` = 3;
