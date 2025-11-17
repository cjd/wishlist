ALTER TABLE `items` ADD `createDate` DATETIME NOT NULL DEFAULT '2000-01-01 00:00:00' AFTER `image`;

UPDATE `schema_version` SET `version` = 2;
