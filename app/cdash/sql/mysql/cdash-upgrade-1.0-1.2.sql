CREATE TABLE IF NOT EXISTS `builderrordiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `testdiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`,`type`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `build2note` (
  `buildid` bigint(20) NOT NULL,
  `noteid`  bigint(20) NOT NULL,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  KEY `buildid` (`buildid`,`noteid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `userstatistics` (
  `userid` int(11) NOT NULL,
  `projectid` smallint(6) NOT NULL,
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `totalupdatedfiles` bigint(20) NOT NULL,
  `totalbuilds` bigint(20) NOT NULL,
  `nfixedwarnings` bigint(20) NOT NULL,
  `nfailedwarnings` bigint(20) NOT NULL,
  `nfixederrors` bigint(20) NOT NULL,
  `nfailederrors` bigint(20) NOT NULL,
  `nfixedtests` bigint(20) NOT NULL,
  `nfailedtests` bigint(20) NOT NULL,
  KEY `userid` (`userid`),
  KEY `projectid` (`projectid`),
  KEY `checkindate` (`checkindate`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `version` (
  `major` tinyint(4) NOT NULL,
  `minor` tinyint(4) NOT NULL,
  `patch` tinyint(4) NOT NULL
) ENGINE=MyISAM;


CREATE TABLE IF NOT EXISTS `summaryemail` (
  `buildid` bigint(20) NOT NULL,
  `date` date NOT NULL,
  `groupid` smallint(6) NOT NULL,
  KEY `date` (`date`),
  KEY `groupid` (`groupid`)
) ENGINE=MyISAM;


CREATE TABLE IF NOT EXISTS `configureerror` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `text` text NOT NULL,
  KEY `buildid` (`buildid`),
  KEY `type` (`type`)
) ENGINE=MyISAM;


CREATE TABLE IF NOT EXISTS `configureerrordiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`),
  KEY `type` (`type`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `coveragesummarydiff` (
  `buildid` bigint(20) NOT NULL,
  `loctested` int(11) NOT NULL default '0',
  `locuntested` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`)
) ENGINE=MyISAM;
