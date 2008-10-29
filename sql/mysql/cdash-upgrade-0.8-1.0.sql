CREATE TABLE IF NOT EXISTS `testmeasurement` (
  `testid` bigint(20) NOT NULL,
  `name` varchar(70) NOT NULL,
  `type` varchar(70) NOT NULL,
  `value` text NOT NULL,
  KEY `testid` (`testid`)
) ENGINE=MyISAM;

CREATE TABLE IF NOT EXISTS `dailyupdate` (
  `id` bigint(11) NOT NULL auto_increment,
  `projectid` int(11) NOT NULL,
  `date` date NOT NULL,
  `command` text NOT NULL,
  `type` varchar(4) NOT NULL default '',
  `status` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`id`)
) ENGINE=MyISAM ;


CREATE TABLE IF NOT EXISTS `dailyupdatefile` (
  `dailyupdateid` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `author` varchar(255) NOT NULL default '',
  `log` text NOT NULL,
  `revision` varchar(10) NOT NULL default '0',
  `priorrevision` varchar(10) NOT NULL default '0',
  KEY `buildid` (`dailyupdateid`)
) ENGINE=MyISAM;