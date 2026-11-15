--
-- Migration V5: Per-user What's New popup version tracking
--

ALTER TABLE `people`
  ADD COLUMN `whatsNewSeenVersion` int unsigned NOT NULL DEFAULT '0' AFTER `lastLoginDate`;

UPDATE `schema_version` SET `version` = 5;
