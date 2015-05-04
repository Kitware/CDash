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
