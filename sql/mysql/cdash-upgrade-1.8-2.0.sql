CREATE TABLE IF NOT EXISTS `uploadfile` (
  `id` int(11) NOT NULL auto_increment,
  `filename` varchar(255) NOT NULL,
  `filesize` int(11) NOT NULL DEFAULT '0',
  `sha1sum` varchar(40) NOT NULL,
  PRIMARY KEY(`id`),
  KEY `sha1sum` (`sha1sum`)
);

CREATE TABLE IF NOT EXISTS `build2uploadfile` (
  `fileid` bigint(11) NOT NULL,
  `buildid` bigint(11) NOT NULL,
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
);
