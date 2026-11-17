--
-- Migration V6: Drop legacy unused tables and columns
--

DROP TABLE IF EXISTS `favorites`;
DROP TABLE IF EXISTS `itemPriceHistory`;

ALTER TABLE `people`
  DROP COLUMN `registered`,
  DROP COLUMN `url`;

ALTER TABLE `viewList`
  DROP COLUMN `viewContactInfo`;

UPDATE `schema_version` SET `version` = 6;
