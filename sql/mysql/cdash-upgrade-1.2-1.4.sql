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
  KEY `labelid` (`labelid`),
  KEY `updateid` (`updateid`)
  
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

---
--- Place the alter table in reverse order to make sure the 
--- new ones are executed correctly
---
ALTER TABLE build2grouprule ADD INDEX  ( starttime );
ALTER TABLE build2grouprule ADD INDEX  ( endtime );
ALTER TABLE build2grouprule ADD INDEX  ( buildtype );
ALTER TABLE build2grouprule ADD INDEX  ( buildname );
ALTER TABLE build2grouprule ADD INDEX  ( expected );
ALTER TABLE build2grouprule ADD INDEX  ( siteid );

ALTER TABLE build2note DROP INDEX buildid;
ALTER TABLE build2note ADD INDEX ( buildid );
ALTER TABLE build2note ADD INDEX ( noteid );

ALTER TABLE user2project ADD INDEX ( cvslogin );
ALTER TABLE user2project ADD INDEX ( emailtype );

ALTER TABLE user ADD INDEX ( email ); 

ALTER TABLE project ADD INDEX ( public ); 

ALTER TABLE buildgroup ADD INDEX  ( starttime );
ALTER TABLE buildgroup ADD INDEX  ( endtime );

ALTER TABLE buildgroupposition ADD INDEX  ( position );
ALTER TABLE buildgroupposition ADD INDEX  ( starttime );
ALTER TABLE buildgroupposition ADD INDEX  ( endtime );

ALTER TABLE dailyupdate ADD INDEX ( date );
ALTER TABLE dailyupdate ADD INDEX ( projectid );

ALTER TABLE builderror ADD INDEX ( type );

ALTER TABLE build ADD INDEX ( starttime );
ALTER TABLE build ADD INDEX ( submittime );
ALTER TABLE build DROP INDEX siteid;
ALTER TABLE build ADD INDEX ( siteid );
ALTER TABLE build ADD INDEX ( name );
ALTER TABLE build ADD INDEX ( stamp );
ALTER TABLE build ADD INDEX ( type );

ALTER TABLE project ADD INDEX ( name );
ALTER TABLE site ADD INDEX ( name );

ALTER TABLE image CHANGE id id BIGINT( 11 ) NOT NULL;
ALTER TABLE image DROP INDEX id;
ALTER TABLE image ADD PRIMARY KEY ( id );
ALTER TABLE image CHANGE id id BIGINT( 11 ) NOT NULL AUTO_INCREMENT;

ALTER TABLE dailyupdate CHANGE id id BIGINT( 11 ) NOT NULL;
ALTER TABLE dailyupdate DROP INDEX buildid;
ALTER TABLE dailyupdate ADD PRIMARY KEY ( id );
ALTER TABLE dailyupdate CHANGE id id BIGINT( 11 ) NOT NULL AUTO_INCREMENT;

ALTER TABLE dynamicanalysisdefect MODIFY value INT NOT NULL DEFAULT 0;

ALTER TABLE test2image DROP PRIMARY KEY;
ALTER TABLE test2image CHANGE imgid imgid INT( 11 ) NOT NULL AUTO_INCREMENT;
ALTER TABLE test2image ADD INDEX ( testid );

ALTER TABLE image CHANGE checksum checksum BIGINT( 20 ) NOT NULL;
ALTER TABLE note CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;
ALTER TABLE test CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;
ALTER TABLE coveragefile CHANGE crc32 crc32 BIGINT( 20 ) NOT NULL;


