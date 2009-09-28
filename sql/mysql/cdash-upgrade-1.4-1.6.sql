CREATE TABLE IF NOT EXISTS `coveragefilepriority` (
  `id` serial NOT NULL,
  `priority` tinyint(4) NOT NULL,
  `fullpath` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `priority` (`priority`),
  KEY `fullpath` (`fullpath`),
  KEY `projectid` (`projectid`)
);

CREATE TABLE IF NOT EXISTS `submission` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(500) NOT NULL,
  `projectid` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `status` (`status`)
);

CREATE TABLE IF NOT EXISTS `build2testtime` (
  `buildid` int(11) NOT NULL default '0',
  `time` float(7,2) NOT NULL default '0.00',
  PRIMARY KEY `buildid` (`buildid`)
);
