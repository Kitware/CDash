CREATE TABLE IF NOT EXISTS `overviewbuildgroups` (
  `projectid` int(11) NOT NULL DEFAULT 1,
  `buildgroupid` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  KEY (`projectid`),
  KEY (`buildgroupid`)
);
