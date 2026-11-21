--
-- Migration V7: Per-user text size preference
--

ALTER TABLE `people`
  ADD COLUMN `textSize` varchar(16) NOT NULL DEFAULT 'normal' AFTER `whatsNewSeenVersion`;

UPDATE `schema_version` SET `version` = 7;
