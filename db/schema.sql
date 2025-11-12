-- phpMyAdmin SQL Dump
-- version 2.8.0.3
-- http://www.phpmyadmin.net
-- 
-- Generation Time: Jul 29, 2006 at 11:12 PM
-- Server version: 5.0.18
-- PHP Version: 4.4.2
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `categories`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 11:02 PM
-- 

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `cid` int(11) unsigned NOT NULL auto_increment,
  `catSortOrder` int(11) NOT NULL default '0',
  `userid` varchar(50) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `description` varchar(255) NOT NULL default '',
  `linkname` varchar(255) NOT NULL default '',
  `linkurl` varchar(255) NOT NULL default '',
  `catSubDescription` text NOT NULL,
  PRIMARY KEY  (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Table structure for table `comments`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 10:55 PM
-- 

DROP TABLE IF EXISTS `comments`;
CREATE TABLE IF NOT EXISTS `comments` (
  `userid` varchar(50) NOT NULL default '',
  `comment_userid` varchar(50) NOT NULL default '',
  `comment` text NOT NULL,
  `commentId` int(11) unsigned NOT NULL auto_increment,
  `date` datetime default NULL,
  PRIMARY KEY  (`commentId`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `comments`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `favorites`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 10:55 PM
-- 

DROP TABLE IF EXISTS `favorites`;
CREATE TABLE IF NOT EXISTS `favorites` (
  `userid` varchar(50) NOT NULL default '',
  `description` varchar(100) NOT NULL default '',
  `location` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`userid`,`description`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `favorites`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `items`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 11:02 PM
-- 

DROP TABLE IF EXISTS `items`;
CREATE TABLE IF NOT EXISTS `items` (
  `iid` int(11) unsigned NOT NULL auto_increment,
  `cid` int(11) unsigned NOT NULL default '0',
  `itemSortOrder` int(11) NOT NULL default '0',
  `addStar` char(1) NOT NULL default '0',
  `title` varchar(255) NOT NULL default '',
  `description` varchar(255) default '',
  `price` decimal(10,2) default '0.00',
  `quantity` smallint(11) unsigned default '0',
  `subdesc` text,
  `allowCheck` varchar(5) default 'false',
  `link2` varchar(255) default '',
  `link2url` varchar(255) default '',
  `link3` varchar(255) default '',
  `link3url` varchar(255) default '',
  `link1` varchar(255) default '',
  `link1url` varchar(255) default '',
  PRIMARY KEY  (`iid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `items`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `people`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 11:07 PM
-- 

DROP TABLE IF EXISTS `people`;
CREATE TABLE IF NOT EXISTS `people` (
  `userid` varchar(50) NOT NULL default '',
  `admin` char(1) NOT NULL default '0',
  `registered` char(1) NOT NULL default '0',
  `lastLoginDate` datetime default NULL,
  `lastModDate` datetime default NULL,
  `lastname` varchar(100) default '',
  `firstname` varchar(100) default '',
  `suffix` varchar(100) default '',
  `street` varchar(100) default '',
  `city` varchar(100) default '',
  `state` varchar(15) default '',
  `zip` int(11) default '0',
  `email` varchar(100) default '',
  `phone` varchar(100) default '',
  `mobilephone` varchar(100) default '',
  `bmonth` varchar(15) default '',
  `bday` tinyint(11) default '0',
  `url` varchar(255) default '',
  `password` varchar(100) default NULL,
  PRIMARY KEY  (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `people`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `purchaseHistory`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 10:55 PM
-- 

DROP TABLE IF EXISTS `purchaseHistory`;
CREATE TABLE IF NOT EXISTS `purchaseHistory` (
  `purchaseId` int(11) unsigned NOT NULL auto_increment,
  `iid` int(11) unsigned NOT NULL default '0',
  `userid` varchar(50) NOT NULL default '',
  `quantity` smallint(11) unsigned NOT NULL default '0',
  `boughtDate` datetime NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`purchaseId`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `purchaseHistory`
-- 


-- --------------------------------------------------------

-- 
-- Table structure for table `viewList`
-- 
-- Creation: Jul 29, 2006 at 10:55 PM
-- Last update: Jul 29, 2006 at 11:07 PM
-- 

DROP TABLE IF EXISTS `viewList`;
CREATE TABLE IF NOT EXISTS `viewList` (
  `lastViewDate` datetime NOT NULL default CURRENT_TIMESTAMP,
  `viewContactInfo` char(1) NOT NULL default '0',
  `readOnly` char(1) NOT NULL default '1',
  `allowEdit` char(1) NOT NULL default '0',
  `pid` varchar(50) NOT NULL default '0',
  `viewer` varchar(50) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- 
-- Dumping data for table `viewList`
-- 

-- --------------------------------------------------------

--
-- Table structure for table `accessRequests`
--

DROP TABLE IF EXISTS `accessRequests`;
CREATE TABLE IF NOT EXISTS `accessRequests` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `requesterId` varchar(50) NOT NULL,
  `targetId` varchar(50) NOT NULL,
  `status` enum('pending','approved','denied') NOT NULL default 'pending',
  `notified` tinyint(1) NOT NULL default '0',
  `requestDate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
