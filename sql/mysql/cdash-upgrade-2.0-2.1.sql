CREATE TABLE IF NOT EXISTS `build2update` (
  `buildid` bigint(11) NOT NULL,
  `updateid` bigint(11) NOT NULL,
  PRIMARY  KEY(`buildid`),
  KEY `updateid` (`updateid`)
);
