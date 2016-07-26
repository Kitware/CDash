DROP TABLE IF EXISTS `overviewbuildgroups`;

CREATE TABLE IF NOT EXISTS `overview_components` (
  `projectid` int(11) NOT NULL DEFAULT 1,
  `buildgroupid` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `type` varchar(32) NOT NULL DEFAULT "build",
  KEY (`projectid`),
  KEY (`buildgroupid`)
);

CREATE TABLE IF NOT EXISTS `buildfile` (
  `buildid` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `md5` varchar(40) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT "",
  KEY (`buildid`),
  KEY (`filename`),
  KEY (`type`),
  KEY (`md5`)
);

CREATE TABLE IF NOT EXISTS `subprojectgroup` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  `coveragethreshold` smallint(6) NOT NULL default '70',
  `is_default` tinyint(1) NOT NULL,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`),
  KEY `projectid` (`projectid`)
);

CREATE TABLE IF NOT EXISTS `buildfailuredetails` (
  `id` bigint(20) NOT NULL auto_increment,
  `type` tinyint(4) NOT NULL,
  `stdoutput` mediumtext NOT NULL,
  `stderror` mediumtext NOT NULL,
  `exitcondition` varchar(255) NOT NULL,
  `language` varchar(64) NOT NULL,
  `targetname` varchar(255) NOT NULL,
  `outputfile` varchar(512) NOT NULL,
  `outputtype` varchar(255) NOT NULL,
  `crc32` bigint(20) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `type` (`type`),
  KEY `crc32` (`crc32`)
);

CREATE TABLE IF NOT EXISTS `password` (
  `userid` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(40) NOT NULL default '',
  KEY `userid` (`userid`)
);

CREATE TABLE IF NOT EXISTS `dynamicanalysissummary` (
  `buildid` int(11) NOT NULL DEFAULT '0',
  `checker` varchar(60) NOT NULL default '',
  `numdefects` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY `buildid` (`buildid`)
);

CREATE TABLE IF NOT EXISTS `lockout` (
  `userid` int(11) NOT NULL,
  `failedattempts` tinyint(1) DEFAULT '0',
  `islocked` tinyint(1) NOT NULL DEFAULT '0',
  `unlocktime` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY  (`userid`)
);

CREATE TABLE IF NOT EXISTS `build2configure` (
  `configureid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`buildid`),
  KEY `configureid` (`configureid`)
);
