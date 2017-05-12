CREATE TABLE IF NOT EXISTS `build2configure` (
  `configureid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`buildid`),
  KEY `configureid` (`configureid`)
);

CREATE TABLE IF NOT EXISTS `authtoken` (
  `hash` varchar(128) NOT NULL,
  `userid` int(11) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `description` varchar(255),
  KEY `hash` (`hash`),
  KEY `userid` (`userid`),
  KEY `expires` (`expires`)
);
