--
-- Table structure for table `accessRequests`
--

DROP TABLE IF EXISTS `accessRequests`;
CREATE TABLE `accessRequests` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `requesterId` varchar(50) NOT NULL,
  `targetId` varchar(50) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL DEFAULT 'pending',
  `notified` tinyint(1) NOT NULL DEFAULT '0',
  `requestDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;

--
-- Table structure for table `categories`
--

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
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM AUTO_INCREMENT=394 DEFAULT CHARSET=latin1;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `comment_userid` varchar(50) NOT NULL DEFAULT '',
  `comment` text NOT NULL,
  `commentId` int unsigned NOT NULL AUTO_INCREMENT,
  `date` datetime DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`commentId`)
) ENGINE=MyISAM AUTO_INCREMENT=171 DEFAULT CHARSET=latin1;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `description` varchar(100) NOT NULL DEFAULT '',
  `location` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`userid`,`description`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `itemPriceHistory`
--

DROP TABLE IF EXISTS `itemPriceHistory`;
CREATE TABLE `itemPriceHistory` (
  `dateChanged` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `iid` int unsigned NOT NULL DEFAULT '0',
  `price` double(10,2) NOT NULL DEFAULT '0.00'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 ROW_FORMAT=DYNAMIC;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `iid` int unsigned NOT NULL AUTO_INCREMENT,
  `cid` int unsigned NOT NULL DEFAULT '0',
  `itemSortOrder` int NOT NULL DEFAULT '0',
  `addStar` char(1) NOT NULL DEFAULT '0',
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` varchar(255) DEFAULT '',
  `price` decimal(10,2) DEFAULT '0.00',
  `quantity` smallint unsigned DEFAULT '0',
  `subdesc` text,
  `allowCheck` varchar(5) DEFAULT 'false',
  `link2` varchar(255) DEFAULT '',
  `link2url` varchar(1024) DEFAULT NULL,
  `link3` varchar(255) DEFAULT '',
  `link3url` varchar(1024) DEFAULT NULL,
  `link1` varchar(255) DEFAULT '',
  `link1url` varchar(1024) DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `createDate` datetime DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`iid`)
) ENGINE=MyISAM AUTO_INCREMENT=5569 DEFAULT CHARSET=latin1;

--
-- Table structure for table `messages`
--

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
  KEY `recipient_id` (`recipient_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Table structure for table `people`
--

DROP TABLE IF EXISTS `people`;
CREATE TABLE `people` (
  `userid` varchar(50) NOT NULL DEFAULT '',
  `admin` char(1) NOT NULL DEFAULT '0',
  `registered` char(1) NOT NULL DEFAULT '0',
  `lastLoginDate` datetime DEFAULT '0000-00-00 00:00:00',
  `lastModDate` datetime DEFAULT '0000-00-00 00:00:00',
  `lastname` varchar(100) DEFAULT '',
  `firstname` varchar(100) DEFAULT '',
  `suffix` varchar(100) DEFAULT '',
  `street` varchar(100) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `state` varchar(15) DEFAULT '',
  `zip` int DEFAULT '0',
  `email` varchar(100) DEFAULT '',
  `phone` varchar(100) DEFAULT '',
  `mobilephone` varchar(100) DEFAULT '',
  `bmonth` varchar(15) DEFAULT '',
  `bday` tinyint DEFAULT '0',
  `url` varchar(255) DEFAULT '',
  `password` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

--
-- Table structure for table `purchaseHistory`
--

DROP TABLE IF EXISTS `purchaseHistory`;
CREATE TABLE `purchaseHistory` (
  `purchaseId` int unsigned NOT NULL AUTO_INCREMENT,
  `iid` int unsigned NOT NULL DEFAULT '0',
  `userid` varchar(50) NOT NULL DEFAULT '',
  `quantity` smallint unsigned NOT NULL DEFAULT '0',
  `boughtDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`purchaseId`)
) ENGINE=MyISAM AUTO_INCREMENT=3251 DEFAULT CHARSET=latin1;

--
-- Table structure for table `viewList`
--

DROP TABLE IF EXISTS `viewList`;
CREATE TABLE `viewList` (
  `lastViewDate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `viewContactInfo` char(1) NOT NULL DEFAULT '0',
  `readOnly` char(1) NOT NULL DEFAULT '1',
  `pid` varchar(50) NOT NULL DEFAULT '0',
  `viewer` varchar(50) NOT NULL DEFAULT '0',
  `allowEdit` char(1) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
