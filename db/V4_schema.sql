--
-- Migration V4: Claimable Secret Gift Suggestions & Spending Integration
--

ALTER TABLE `comments`
  ADD COLUMN `is_suggestion` tinyint(1) NOT NULL DEFAULT '0',
  ADD COLUMN `suggest_title` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN `suggest_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  ADD COLUMN `claimed_by` varchar(50) DEFAULT NULL,
  ADD COLUMN `claimed_date` datetime DEFAULT NULL,
  ADD COLUMN `split_users` varchar(255) NOT NULL DEFAULT '',
  ADD COLUMN `split_with` varchar(255) NOT NULL DEFAULT '',
  ADD KEY `idx_claimed_by` (`claimed_by`);

UPDATE `comments`
SET `is_suggestion` = 1
WHERE `comment` LIKE '<div class=''secret-suggestion-card''>%'
   OR `comment` LIKE '<div class="secret-suggestion-card">%';

UPDATE `schema_version` SET `version` = 4;
