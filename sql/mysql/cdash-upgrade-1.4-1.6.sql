CREATE TABLE IF NOT EXISTS `coveragefilepriority` (
  `fileid` bigint(20) NOT NULL,
  `priority` tinyint(4) NOT NULL,
  PRIMARY KEY  (`fileid`),
  KEY `priority` (`priority`)
);
