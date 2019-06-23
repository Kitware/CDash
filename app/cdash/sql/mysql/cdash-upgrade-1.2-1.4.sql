CREATE TABLE IF NOT EXISTS `buildemail` (
  `userid` int(11) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `category` tinyint(4) NOT NULL,
  `time` timestamp NOT NULL,
  KEY `userid` (`userid`),
  KEY `buildid` (`buildid`),
  KEY `category` (`category`)
);

CREATE TABLE IF NOT EXISTS `buildfailure` (
  `id` bigint(20) NOT NULL auto_increment,
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `workingdirectory` varchar(255) NOT NULL,
  `arguments` text NOT NULL,
  `stdoutput` text NOT NULL,
  `stderror` text NOT NULL,
  `exitcondition` tinyint(4) NOT NULL,
  `language` varchar(10) NOT NULL,
  `targetname` varchar(255) NOT NULL,
  `outputfile` varchar(255) NOT NULL,
  `outputtype` varchar(255) NOT NULL,
  `sourcefile` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `buildid` (`buildid`),
  KEY `type` (`type`)
);


CREATE TABLE IF NOT EXISTS  `labelemail` (
  `projectid` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `labelid` bigint(20) NOT NULL,
  KEY `projectid` (`projectid`),
  KEY `userid` (`userid`)
);


CREATE TABLE IF NOT EXISTS `buildfailureargument` (
  `id` bigint(20) NOT NULL auto_increment,
  `argument` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `argument` (`argument`)
);

CREATE TABLE IF NOT EXISTS `buildfailure2argument` (
  `buildfailureid` bigint(20) NOT NULL,
  `argumentid` bigint(20) NOT NULL,
  KEY `argumentid` (`argumentid`),
  KEY `buildfailureid` (`buildfailureid`)
);

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
  UNIQUE KEY `text` (`text`)
);


---
--- Drop tables that only existed for a short time
--- in previous svn updates of CDash 1.3
---

DROP TABLE IF EXISTS `label2configure`;
  --- table unnecessary: configure identified by buildid

DROP TABLE IF EXISTS `label2coverage`;
  --- table renamed label2coveragefile


--
-- Table structure for table `label2build`
--
CREATE TABLE IF NOT EXISTS `label2build` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`)
);

--
-- Table structure for table `label2buildfailure`
--
CREATE TABLE IF NOT EXISTS `label2buildfailure` (
  `labelid` bigint(20) NOT NULL,
  `buildfailureid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildfailureid`)
);

--
-- Table structure for table `label2coveragefile`
--
CREATE TABLE IF NOT EXISTS `label2coveragefile` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `coveragefileid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`,`coveragefileid`)
);

--
-- Table structure for table `label2dynamicanalysis`
--
CREATE TABLE IF NOT EXISTS `label2dynamicanalysis` (
  `labelid` bigint(20) NOT NULL,
  `dynamicanalysisid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`dynamicanalysisid`)
);

--
-- Table structure for table `label2test`
--
CREATE TABLE IF NOT EXISTS `label2test` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `testid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`,`testid`)
);

--
-- Table structure for table `label2update`
--
CREATE TABLE IF NOT EXISTS `label2update` (
  `labelid` bigint(20) NOT NULL,
  `updateid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`updateid`)
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
