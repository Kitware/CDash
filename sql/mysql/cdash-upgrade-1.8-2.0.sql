CREATE TABLE IF NOT EXISTS `uploadfile` (
  `id` int(11) NOT NULL auto_increment,
  `file` longblob NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filesize` int(11) NOT NULL DEFAULT '0',
  `md5sum` varchar(32) NOT NULL,
  PRIMARY KEY(`id`),
  KEY `md5sum` (`md5sum`)
);

CREATE TABLE IF NOT EXISTS `build2uploadfile` (
  `fileid` bigint(11) NOT NULL,
  `buildid` bigint(11) NOT NULL,
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
);
