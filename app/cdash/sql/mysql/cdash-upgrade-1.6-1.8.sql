CREATE TABLE IF NOT EXISTS `projectjobscript` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `script` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`)
);


CREATE TABLE IF NOT EXISTS `client_site2program` (
  `siteid` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `version` varchar(30) NOT NULL,
  `path` varchar(512) NOT NULL,
  KEY `siteid` (`siteid`)
);

CREATE TABLE IF NOT EXISTS `client_site2project` (
  `projectid` int(11) DEFAULT NULL,
  `siteid` int(11) DEFAULT NULL,
  KEY `siteid` (`siteid`)
);


CREATE TABLE IF NOT EXISTS `errorlog` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `date` timestamp NOT NULL,
  `type` tinyint(4) NOT NULL,
  `description` mediumtext NOT NULL,
  `resourcetype` tinyint(4) NOT NULL DEFAULT '0',
  `resourceid` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resourceid` (`resourceid`),
  KEY `date` (`date`),
  KEY `resourcetype` (`resourcetype`),
  KEY `projectid` (`projectid`),
  KEY `buildid` (`buildid`)
);


CREATE TABLE IF NOT EXISTS `submissionprocessor` (
  `projectid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `lastupdated` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `locked` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY (`projectid`)
);

CREATE TABLE IF NOT EXISTS `user2repository` (
  `userid` int(11) NOT NULL,
  `credential` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL DEFAULT '0',
  KEY `userid` (`userid`),
  KEY `credential` (`credential`),
  KEY `projectid` (`projectid`)
);

CREATE TABLE IF NOT EXISTS client_jobschedule2build (
  scheduleid bigint(20) unsigned NOT NULL,
  buildid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,buildid)
);

CREATE TABLE IF NOT EXISTS `apitoken` (
  `projectid` int(11) NOT NULL,
  `token` varchar(40),
  `expiration_date` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  KEY `token` (`token`)
);
