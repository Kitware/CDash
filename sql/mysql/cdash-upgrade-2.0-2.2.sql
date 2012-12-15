CREATE TABLE IF NOT EXISTS `build2update` (
  `buildid` bigint(11) NOT NULL,
  `updateid` bigint(11) NOT NULL,
  PRIMARY  KEY(`buildid`),
  KEY `updateid` (`updateid`)
);

CREATE TABLE IF NOT EXISTS `submission2ip` (
  `submissionid` bigint(11) NOT NULL,
  `ip` varchar(255) NOT NULL default '',
  PRIMARY KEY (`submissionid`)
);
