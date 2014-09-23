DROP TABLE IF EXISTS `overviewbuildgroups`;

CREATE TABLE IF NOT EXISTS `overview_components` (
  `projectid` int(11) NOT NULL DEFAULT 1,
  `buildgroupid` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `type` varchar(32) NOT NULL DEFAULT "build",
  KEY (`projectid`),
  KEY (`buildgroupid`)
);
