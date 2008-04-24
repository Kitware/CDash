CREATE TABLE IF NOT EXISTS `testmeasurement` (
  `testid` bigint(20) NOT NULL,
  `name` varchar(70) NOT NULL,
  `type` varchar(70) NOT NULL,
  `value` text NOT NULL,
  KEY `testid` (`testid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
