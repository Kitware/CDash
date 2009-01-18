CREATE TABLE IF NOT EXISTS `banner` (
  `projectid` int(11) NOT NULL,
  `text` varchar(500) NOT NULL,
  PRIMARY KEY  (`projectid`)
);

CREATE TABLE IF NOT EXISTS `coveragefile2user` (
  `fileid` bigint(20) NOT NULL,
  `userid` bigint(20) NOT NULL,
  `position` tinyint(4) NOT NULL,
  KEY `coveragefileid` (`fileid`),
  KEY `userid` (`userid`)
);

CREATE TABLE IF NOT EXISTS `label` (
  `id` bigint(20) NOT NULL auto_increment,
  `text` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `text` (`text`)
);


-- 
-- Table structure for table `label2build`
-- 
CREATE TABLE IF NOT EXISTS `label2build` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  KEY `buildid` (`labelid`,`buildid`)
);

-- 
-- Table structure for table `label2configure`
-- 
CREATE TABLE IF NOT EXISTS `label2configure` (
  `labelid` bigint(20) NOT NULL,
  `configureid` bigint(20) NOT NULL,
  KEY `configureid` (`labelid`,`configureid`)
);

-- 
-- Table structure for table `label2coverage`
-- 
CREATE TABLE IF NOT EXISTS `label2coverage` (
  `labelid` bigint(20) NOT NULL,
  `coverageid` bigint(20) NOT NULL,
  KEY `labelid` (`labelid`,`coverageid`)
);

-- 
-- Table structure for table `label2dynamicanalysis`
-- 
CREATE TABLE IF NOT EXISTS `label2dynamicanalysis` (
  `labelid` bigint(20) NOT NULL,
  `dynamicanalysisid` bigint(20) NOT NULL,
  KEY `dynamicanalysisid` (`labelid`,`dynamicanalysisid`)
);


-- 
-- Table structure for table `label2test`
-- 
CREATE TABLE IF NOT EXISTS `label2test` (
  `labelid` bigint(20) NOT NULL,
  `testid` bigint(20) NOT NULL,
  KEY `labelid` (`labelid`,`testid`)
);

-- 
-- Table structure for table `label2update`
-- 
CREATE TABLE IF NOT EXISTS `label2update` (
  `labelid` bigint(20) NOT NULL,
  `updateid` bigint(20) NOT NULL,
  KEY `labelid` (`labelid`,`updateid`)
);


-- 
-- Table structure for table `subproject`
-- 
CREATE TABLE IF NOT EXISTS `subproject` (
  `id` bigint(20) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `projectid` (`projectid`)
);


CREATE TABLE IF NOT EXISTS `subproject2subproject` (
  `subprojectid` int(11) NOT NULL,
  `dependsonid` int(11) NOT NULL,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `subprojectid` (`subprojectid`),
  KEY `dependsonid` (`dependsonid`)
);


CREATE TABLE IF NOT EXISTS `subproject2build` (
  `subprojectid` int(11) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  PRIMARY KEY  (`buildid`),
  KEY `subprojectid` (`subprojectid`)
);

ALTER TABLE dynamicanalysisdefect MODIFY value INT NOT NULL DEFAULT 0;

ALTER TABLE test2image DROP PRIMARY KEY;
ALTER TABLE test2image CHANGE imgid imgid INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE test2image ADD INDEX ( testid );

ALTER TABLE image CHANGE checksum checksum BIGINT( 20 ) NOT NULL;
ALTER TABLE note CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;
ALTER TABLE test CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;
ALTER TABLE coveragefile CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;



