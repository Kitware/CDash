CREATE TABLE IF NOT EXISTS `build2configure` (
  `configureid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`buildid`),
  KEY `configureid` (`configureid`)
);
