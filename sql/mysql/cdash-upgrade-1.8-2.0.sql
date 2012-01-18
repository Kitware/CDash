CREATE TABLE IF NOT EXISTS `uploadfile` (
  `id` int(11) NOT NULL auto_increment,
  `filename` varchar(255) NOT NULL,
  `filesize` int(11) NOT NULL DEFAULT '0',
  `sha1sum` varchar(40) NOT NULL,
  `isurl` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY(`id`),
  KEY `sha1sum` (`sha1sum`)
);

CREATE TABLE IF NOT EXISTS `build2uploadfile` (
  `fileid` bigint(11) NOT NULL,
  `buildid` bigint(11) NOT NULL,
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
);

CREATE TABLE IF NOT EXISTS client_jobschedule2submission (
  scheduleid bigint(20) NOT NULL,
  submissionid bigint(11) NOT NULL,
  PRIMARY KEY (`submissionid`),
  UNIQUE KEY `scheduleid` (`scheduleid`)
);


CREATE TABLE IF NOT EXISTS  `usertemp` (
  `email` varchar(255) NOT NULL default '',
  `password` varchar(40) NOT NULL default '',
  `firstname` varchar(40) NOT NULL default '',
  `lastname` varchar(40) NOT NULL default '',
  `institution` varchar(255) NOT NULL default '',
  `registrationdate` datetime NOT NULL,
  `registrationkey` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`email`),
  KEY `registrationdate` (`registrationdate`)
);