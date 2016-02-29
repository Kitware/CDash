--
-- Host: localhost
-- Server version: 4.1.15
-- PHP Version: 5.2.3-1+b1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Database: `cdash`
--

-- --------------------------------------------------------

--
-- Table structure for table `buildnote`
--

CREATE TABLE IF NOT EXISTS `buildnote` (
  `buildid` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `note` mediumtext NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

--
-- Table structure for table `repositories`
--

CREATE TABLE IF NOT EXISTS `repositories` (
  `id` int(11) NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `project2repositories`
--

CREATE TABLE IF NOT EXISTS `project2repositories` (
  `projectid` int(11) NOT NULL,
  `repositoryid` int(11) NOT NULL,
  PRIMARY KEY  (`projectid`,`repositoryid`)
) ENGINE=MyISAM;

-- --------------------------------------------------------
