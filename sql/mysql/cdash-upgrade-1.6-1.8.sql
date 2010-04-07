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