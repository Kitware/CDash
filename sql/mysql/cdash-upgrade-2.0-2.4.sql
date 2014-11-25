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

