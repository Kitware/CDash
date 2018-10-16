--
-- Host: localhost
-- Server version: 4.1.15
-- PHP Version: 5.2.3-1+b1

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `cdash`
--

-- --------------------------------------------------------

--
-- Table structure for table `build`
--

CREATE TABLE `build` (
  `id` int(11) NOT NULL auto_increment,
  `siteid` int(11) NOT NULL default '0',
  `projectid` int(11) NOT NULL default '0',
  `parentid` int(11) NOT NULL default '0',
  `stamp` varchar(255) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `type` varchar(255) NOT NULL default '',
  `generator` varchar(255) NOT NULL default '',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `submittime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `command` text NOT NULL DEFAULT '',
  `log` text NOT NULL DEFAULT '',
  `configureerrors` smallint(6) DEFAULT '-1',
  `configurewarnings` smallint(6) DEFAULT '-1',
  `configureduration` float(7,2) NOT NULL default '0.00',
  `builderrors` smallint(6) DEFAULT '-1',
  `buildwarnings` smallint(6) DEFAULT '-1',
  `buildduration` int(11) NOT NULL default '0',
  `testnotrun` smallint(6) DEFAULT '-1',
  `testfailed` smallint(6) DEFAULT '-1',
  `testpassed` smallint(6) DEFAULT '-1',
  `testtimestatusfailed` smallint(6) DEFAULT '-1',
  `notified` tinyint(1) default '0',
  `done` tinyint(1) default '0',
  `uuid` varchar(36) NOT NULL,
  `changeid` varchar(40) DEFAULT '',
  PRIMARY KEY  (`id`),
  KEY `projectid` (`projectid`),
  KEY `starttime` (`starttime`),
  KEY `submittime` (`submittime`),
  KEY `siteid` (`siteid`),
  KEY `stamp` (`stamp`),
  KEY `type` (`type`),
  KEY `name` (`name`),
  KEY `parentid` (`parentid`),
  KEY `projectid_parentid_starttime` (`projectid`,`parentid`,`starttime`),
  UNIQUE KEY uuid (uuid)
);


CREATE TABLE `buildgroup` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `projectid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `autoremovetimeframe` int(11) default '0',
  `description` text NOT NULL default '',
  `summaryemail` tinyint(4) default '0',
  `includesubprojectotal` tinyint(4) default '1',
  `emailcommitters` tinyint(4) default '0',
  `type` varchar(20) NOT NULL default 'Daily',
  PRIMARY KEY  (`id`),
  KEY `projectid` (`projectid`),
  KEY `starttime` (`starttime`),
  KEY `endtime` (`endtime`),
  KEY `type` (`type`)
);

-- --------------------------------------------------------

--
-- Table structure for table `buildgroupposition`
--

CREATE TABLE `buildgroupposition` (
  `buildgroupid` int(11) NOT NULL default '0',
  `position` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `buildgroupid` (`buildgroupid`),
  KEY `endtime` (`endtime`),
  KEY `starttime` (`starttime`),
  KEY `position` (`position`)
);


-- --------------------------------------------------------

--
-- Table structure for table `build2configure`
--

CREATE TABLE `build2configure` (
  `configureid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`buildid`),
  KEY `configureid` (`configureid`)
);


-- --------------------------------------------------------

--
-- Table structure for table `build2group`
--

CREATE TABLE `build2group` (
  `groupid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`),
  KEY `groupid` (`groupid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `build2grouprule`
--

CREATE TABLE `build2grouprule` (
  `groupid` int(11) NOT NULL default '0',
  `parentgroupid` int(11) NOT NULL default '0',
  `buildtype` varchar(20) NOT NULL default '',
  `buildname` varchar(255) NOT NULL default '',
  `siteid` int(11) NOT NULL default '0',
  `expected` tinyint(4) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `groupid` (`groupid`),
  KEY `parentgroupid` (`parentgroupid`),
  KEY `buildtype` (`buildtype`),
  KEY `buildname` (`buildname`),
  KEY `siteid` (`siteid`),
  KEY `expected` (`expected`),
  KEY `starttime` (`starttime`),
  KEY `endtime` (`endtime`)
);

-- --------------------------------------------------------

--
-- Table structure for table `builderror`
--

CREATE TABLE `builderror` (
  `buildid` int(11) NOT NULL default '0',
  `type` tinyint(4) NOT NULL default '0',
  `logline` int(11) NOT NULL default '0',
  `text` text NOT NULL,
  `sourcefile` varchar(255) NOT NULL default '',
  `sourceline` int(11) NOT NULL default '0',
  `precontext` text,
  `postcontext` text,
  `repeatcount` int(11) NOT NULL default '0',
  `crc32` bigint(20) NOT NULL default '0',
  `newstatus` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`buildid`),
  KEY `type` (`type`),
  KEY `crc32` (`crc32`),
  KEY `newstatus` (`newstatus`)
);

-- --------------------------------------------------------

--
-- Table structure for table `buildupdate`
--

CREATE TABLE `buildupdate` (
  `id` int(11) NOT NULL auto_increment,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `command` text NOT NULL,
  `type` varchar(4) NOT NULL default '',
  `status` text NOT NULL,
  `nfiles` smallint(6) DEFAULT '-1',
  `warnings` smallint(6) DEFAULT '-1',
  `revision` varchar(60) NOT NULL default '0',
  `priorrevision` varchar(60) NOT NULL default '0',
  `path` varchar(255) NOT NULL default '',
  PRIMARY  KEY(`id`),
  KEY `revision` (`revision`)
);


CREATE TABLE IF NOT EXISTS `build2update` (
  `buildid` bigint(11) NOT NULL,
  `updateid` bigint(11) NOT NULL,
  PRIMARY  KEY(`buildid`),
  KEY `updateid` (`updateid`)
);


-- --------------------------------------------------------

--
-- Table structure for table `configure`
--

CREATE TABLE `configure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `command` text NOT NULL,
  `log` MEDIUMTEXT NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '0',
  `warnings` smallint(6) DEFAULT '-1',
  `crc32` bigint(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `crc32` (`crc32`)
);

-- --------------------------------------------------------

--
-- Table structure for table `coverage`
--

CREATE TABLE `coverage` (
  `buildid` int(11) NOT NULL default '0',
  `fileid` int(11) NOT NULL default '0',
  `covered` tinyint(4) NOT NULL default '0',
  `loctested` int(11) NOT NULL default '0',
  `locuntested` int(11) NOT NULL default '0',
  `branchstested` int(11) NOT NULL default '0',
  `branchsuntested` int(11) NOT NULL default '0',
  `functionstested` int(11) NOT NULL default '0',
  `functionsuntested` int(11) NOT NULL default '0',
  KEY `buildid` (`buildid`),
  KEY `fileid` (`fileid`),
  KEY `covered` (`covered`)
);

-- --------------------------------------------------------

--
-- Table structure for table `coveragefile`
--

CREATE TABLE `coveragefile` (
  `id` int(11) NOT NULL auto_increment,
  `fullpath` varchar(255) NOT NULL default '',
  `file` longblob,
  `crc32` bigint(20) default NULL,
  PRIMARY KEY  (`id`),
  KEY `fullpath` (`fullpath`),
  KEY `crc32` (`crc32`)
);

-- --------------------------------------------------------

--
-- Table structure for table `coveragefilelog`
--

CREATE TABLE `coveragefilelog` (
  `buildid` int(11) NOT NULL default '0',
  `fileid` int(11) NOT NULL default '0',
  `log` LONGBLOB NOT NULL,
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `coveragesummary`
--

CREATE TABLE `coveragesummary` (
  `buildid` int(11) NOT NULL default '0',
  `loctested` int(11) NOT NULL default '0',
  `locuntested` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `dynamicanalysis`
--

CREATE TABLE `dynamicanalysis` (
  `id` int(11) NOT NULL auto_increment,
  `buildid` int(11) NOT NULL default '0',
  `status` varchar(10) NOT NULL default '',
  `checker` varchar(60) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `path` varchar(255) NOT NULL default '',
  `fullcommandline` varchar(255) NOT NULL default '',
  `log` longtext NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `buildid` (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `dynamicanalysisdefect`
--

CREATE TABLE `dynamicanalysisdefect` (
  `dynamicanalysisid` int(11) NOT NULL default '0',
  `type` varchar(50) NOT NULL default '',
  `value` int(11) NOT NULL default '0',
  KEY `buildid` (`dynamicanalysisid`)
);


-- --------------------------------------------------------

--
-- Table structure for table `dynamicanalysissummary`
--

CREATE TABLE `dynamicanalysissummary` (
  `buildid` int(11) NOT NULL DEFAULT '0',
  `checker` varchar(60) NOT NULL default '',
  `numdefects` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY `buildid` (`buildid`)
);


-- --------------------------------------------------------

--
-- Table structure for table `image`
--

CREATE TABLE `image` (
  `id` int(11) NOT NULL auto_increment,
  `img` longblob NOT NULL,
  `extension` tinytext NOT NULL,
  `checksum` bigint(20) NOT NULL,
  PRIMARY KEY `id` (`id`),
  KEY `checksum` (`checksum`)
);


-- --------------------------------------------------------

--
-- Table structure for table `test2image`
--
CREATE TABLE `test2image` (
  `id` bigint(20) NOT NULL auto_increment,
  `imgid` int(11) NOT NULL,
  `testid` int(11) NOT NULL,
  `role` tinytext NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `imgid` (`imgid`),
  KEY `testid` (`testid`)
);

-- --------------------------------------------------------
--
-- Table structure for table `note`
--
CREATE TABLE `note` (
  `id` bigint(20) NOT NULL auto_increment,
  `text` mediumtext NOT NULL,
  `name` varchar(255) NOT NULL,
  `crc32` bigint(20) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `crc32` (`crc32`)
) ;

-- --------------------------------------------------------
--
-- Table structure for table `uploadfile`
--
CREATE TABLE IF NOT EXISTS `uploadfile` (
  `id` int(11) NOT NULL auto_increment,
  `filename` varchar(255) NOT NULL,
  `filesize` int(11) NOT NULL DEFAULT '0',
  `sha1sum` varchar(40) NOT NULL,
  `isurl` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY(`id`),
  KEY `sha1sum` (`sha1sum`)
);

-- --------------------------------------------------------
--
-- Table structure for table `build2uploadfile`
--
CREATE TABLE IF NOT EXISTS `build2uploadfile` (
  `fileid` bigint(11) NOT NULL,
  `buildid` bigint(11) NOT NULL,
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `project`
--

CREATE TABLE `project` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `description` text NOT NULL default '',
  `homeurl` varchar(255) NOT NULL default '',
  `cvsurl` varchar(255) NOT NULL default '',
  `bugtrackerurl` varchar(255) NOT NULL default '',
  `bugtrackerfileurl` varchar(255) NOT NULL DEFAULT '',
  `bugtrackernewissueurl` varchar(255) NOT NULL DEFAULT '',
  `bugtrackertype` varchar(16) DEFAULT NULL,
  `documentationurl` varchar(255) NOT NULL default '',
  `imageid` int(11) NOT NULL default '0',
  `public` tinyint(4) NOT NULL default '1',
  `coveragethreshold` smallint(6) NOT NULL default '70',
  `testingdataurl` varchar(255) NOT NULL default '',
  `nightlytime` varchar(50) NOT NULL default '00:00:00',
  `googletracker` varchar(50) NOT NULL default '',
  `emaillowcoverage` tinyint(4) NOT NULL default '0',
  `emailtesttimingchanged` tinyint(4) NOT NULL default '0',
  `emailbrokensubmission` tinyint(4) NOT NULL default '1',
  `emailredundantfailures` tinyint(4) NOT NULL default '0',
  `emailadministrator` tinyint(4) NOT NULL default '1',
  `showipaddresses` tinyint(4) NOT NULL default '1',
  `cvsviewertype` varchar(10) default NULL,
  `testtimestd` float(3,1) default '4.0',
  `testtimestdthreshold` float(3,1) default '1.0',
  `showtesttime` tinyint(4) default '0',
  `testtimemaxstatus` tinyint(4) default '3',
  `emailmaxitems` tinyint(4) default '5',
  `emailmaxchars` int(11) default '255',
  `displaylabels` tinyint(4) default '1',
  `autoremovetimeframe` int(11) default '0',
  `autoremovemaxbuilds` int(11) default '300',
  `uploadquota` bigint(20) default '0',
  `webapikey` varchar(40),
  `tokenduration` int(11),
  `showcoveragecode` tinyint(4) default '1',
  `sharelabelfilters` tinyint(1) default '0',
  `authenticatesubmissions` tinyint(1) default '0',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`),
  KEY `public` (`public`)
);

-- --------------------------------------------------------

--
-- Table structure for table `site`
--

CREATE TABLE `site` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `ip` varchar(255) NOT NULL default '',
  `latitude` varchar(10) NOT NULL default '',
  `longitude` varchar(10) NOT NULL default '',
  `outoforder` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`name`)
) ;

--
-- Table structure for table `siteinformation`
--

CREATE TABLE `siteinformation` (
  `siteid` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL default '1980-01-01 00:00:00',
  `processoris64bits` tinyint(4) NOT NULL default '-1',
  `processorvendor` varchar(255) NOT NULL default 'NA',
  `processorvendorid` varchar(255) NOT NULL default 'NA',
  `processorfamilyid` int(11) NOT NULL default '-1',
  `processormodelid` int(11) NOT NULL default '-1',
  `processorcachesize` int(11) NOT NULL default '-1',
  `numberlogicalcpus` tinyint(4) NOT NULL default '-1',
  `numberphysicalcpus` tinyint(4) NOT NULL default '-1',
  `totalvirtualmemory` int(11) NOT NULL default '-1',
  `totalphysicalmemory` int(11) NOT NULL default '-1',
  `logicalprocessorsperphysical` int(11) NOT NULL default '-1',
  `processorclockfrequency` int(11) NOT NULL default '-1',
  `description` varchar(255) NOT NULL default 'NA',
  KEY `siteid` (`siteid`,`timestamp`)
);


CREATE TABLE `buildinformation` (
  `buildid` int(11) NOT NULL,
  `osname` varchar(255) NOT NULL,
  `osplatform` varchar(255) NOT NULL,
  `osrelease` varchar(255) NOT NULL,
  `osversion` varchar(255) NOT NULL,
  `compilername` varchar(255) NOT NULL,
  `compilerversion` varchar(20) NOT NULL,
  PRIMARY KEY  (`buildid`)
);


-- --------------------------------------------------------

--
-- Table structure for table `site2user`
--

CREATE TABLE `site2user` (
  `siteid` int(11) NOT NULL default '0',
  `userid` int(11) NOT NULL default '0',
  KEY `siteid` (`siteid`),
  KEY `userid` (`userid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `test`
--
CREATE TABLE `test` (
  `id` int(11) NOT NULL auto_increment,
  `projectid` int(11) NOT NULL,
  `crc32` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL default '',
  `path` varchar(255) NOT NULL default '',
  `command` text NOT NULL,
  `details` text NOT NULL,
  `output` MEDIUMBLOB NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `projectid` (`projectid`),
  KEY `crc32` (`crc32`),
  KEY `name` (`name`)
);

--
-- Table structure for table `build2test`
--
CREATE TABLE `build2test` (
  `buildid` int(11) NOT NULL default '0',
  `testid` int(11) NOT NULL default '0',
  `status` varchar(10) NOT NULL default '',
  `time` float(7,2) NOT NULL default '0.00',
  `timemean` float(7,2) NOT NULL default '0.00',
  `timestd` float(7,2) NOT NULL default '0.00',
  `timestatus` tinyint(4) NOT NULL default '0',
  `newstatus` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`buildid`),
  KEY `testid` (`testid`),
  KEY `status` (`status`),
  KEY `timestatus` (`timestatus`),
  KEY `newstatus` (`newstatus`)
);

--
-- Table structure for table `buildtesttime`
--

CREATE TABLE `buildtesttime` (
  `buildid` int(11) NOT NULL default '0',
  `time` float(7,2) NOT NULL default '0.00',
  PRIMARY KEY `buildid` (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `updatefile`
--

CREATE TABLE `updatefile` (
  `updateid` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `checkindate` timestamp NOT NULL default '1980-01-01 00:00:00',
  `author` varchar(255) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `committer` varchar(255) NOT NULL default '',
  `committeremail` varchar(255) NOT NULL default '',
  `log` text NOT NULL,
  `revision` varchar(60) NOT NULL default '0',
  `priorrevision` varchar(60) NOT NULL default '0',
  `status` varchar(12) NOT NULL default '',
  KEY `updateid` (`updateid`),
  KEY `author` (`author`)
);

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int(11) NOT NULL auto_increment,
  `email` varchar(255) NOT NULL default '',
  `password` varchar(255) NOT NULL default '',
  `firstname` varchar(40) NOT NULL default '',
  `lastname` varchar(40) NOT NULL default '',
  `institution` varchar(255) NOT NULL default '',
  `admin` tinyint(4) NOT NULL default '0',
  `cookiekey` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `email` (`email`)
);


CREATE TABLE `usertemp` (
  `email` varchar(255) NOT NULL default '',
  `password` varchar(255) NOT NULL default '',
  `firstname` varchar(40) NOT NULL default '',
  `lastname` varchar(40) NOT NULL default '',
  `institution` varchar(255) NOT NULL default '',
  `registrationdate` datetime NOT NULL,
  `registrationkey` varchar(40) NOT NULL default '',
  PRIMARY KEY  (`email`),
  KEY `registrationdate` (`registrationdate`)
);


-- --------------------------------------------------------

--
-- Table structure for table `user2project`
--

CREATE TABLE `user2project` (
  `userid` int(11) NOT NULL default '0',
  `projectid` int(11) NOT NULL default '0',
  `role` int(11) NOT NULL default '0',
  `cvslogin` varchar(50) NOT NULL default '',
  `emailtype` tinyint(4) NOT NULL default '0',
  `emailcategory` tinyint(4) NOT NULL default '62',
  `emailsuccess` tinyint(4) NOT NULL default '0',
  `emailmissingsites` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`userid`,`projectid`),
  KEY `cvslogin` (`cvslogin`),
  KEY `emailtype` (`emailtype`),
  KEY `emailsucess` (`emailsuccess`),
  KEY `emailmissingsites` (`emailmissingsites`)
);

--
-- Table structure for table `buildnote`
--
CREATE TABLE `buildnote` (
  `buildid` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `note` mediumtext NOT NULL,
  `timestamp` datetime NOT NULL,
  `status` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`buildid`)
);

-- --------------------------------------------------------

--
-- Table structure for table `repositories`
--

CREATE TABLE `repositories` (
  `id` int(11) NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  `username` varchar(50) NOT NULL default '',
  `password` varchar(50) NOT NULL default '',
  `branch` varchar(60) NOT NULL default '',
  PRIMARY KEY  (`id`)
);

-- --------------------------------------------------------

--
-- Table structure for table `project2repositories`
--

CREATE TABLE `project2repositories` (
  `projectid` int(11) NOT NULL,
  `repositoryid` int(11) NOT NULL,
  PRIMARY KEY  (`projectid`,`repositoryid`)
);


-- --------------------------------------------------------
CREATE TABLE `testmeasurement` (
  `id` bigint(20) NOT NULL auto_increment,
  `testid` bigint(20) NOT NULL,
  `name` varchar(70) NOT NULL,
  `type` varchar(70) NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `testid` (`testid`)
);


CREATE TABLE `dailyupdate` (
  `id` bigint(11) NOT NULL auto_increment,
  `projectid` int(11) NOT NULL,
  `date` date NOT NULL,
  `command` text NOT NULL,
  `type` varchar(4) NOT NULL default '',
  `status` tinyint(4) NOT NULL default '0',
  `revision` varchar(60) NOT NULL default '0',
  PRIMARY KEY (`id`),
  KEY `date` (`date`),
  KEY `projectid` (`projectid`)
);


CREATE TABLE `dailyupdatefile` (
  `dailyupdateid` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `author` varchar(255) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `log` text NOT NULL,
  `revision` varchar(60) NOT NULL default '0',
  `priorrevision` varchar(60) NOT NULL default '0',
  KEY `dailyupdateid` (`dailyupdateid`),
  KEY `author` (`author`)
);


CREATE TABLE `builderrordiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference_positive` int(11) NOT NULL,
  `difference_negative` int(11) NOT NULL,
  KEY `buildid` (`buildid`),
  KEY `type` (`type`),
  KEY `difference_positive` (`difference_positive`),
  KEY `difference_negative` (`difference_negative`),
  UNIQUE KEY `unique_builderrordiff` (`buildid`, `type`)
);

CREATE TABLE `testdiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference_positive` int(11) NOT NULL,
  `difference_negative` int(11) NOT NULL,
  KEY `buildid` (`buildid`),
  KEY `type` (`type`),
  KEY `difference_positive` (`difference_positive`),
  KEY `difference_negative` (`difference_negative`),
  UNIQUE KEY `unique_testdiff` (`buildid`, `type`)
);

CREATE TABLE `build2note` (
  `buildid` bigint(20) NOT NULL,
  `noteid`  bigint(20) NOT NULL,
  `time` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `buildid` (`buildid`),
  KEY `noteid` (`noteid`)
);


CREATE TABLE `userstatistics` (
  `userid` int(11) NOT NULL,
  `projectid` smallint(6) NOT NULL,
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `totalupdatedfiles` bigint(20) NOT NULL,
  `totalbuilds` bigint(20) NOT NULL,
  `nfixedwarnings` bigint(20) NOT NULL,
  `nfailedwarnings` bigint(20) NOT NULL,
  `nfixederrors` bigint(20) NOT NULL,
  `nfailederrors` bigint(20) NOT NULL,
  `nfixedtests` bigint(20) NOT NULL,
  `nfailedtests` bigint(20) NOT NULL,
  KEY `userid` (`userid`),
  KEY `projectid` (`projectid`),
  KEY `checkindate` (`checkindate`)
);

CREATE TABLE `version` (
  `major` tinyint(4) NOT NULL,
  `minor` tinyint(4) NOT NULL,
  `patch` tinyint(4) NOT NULL
);


CREATE TABLE `summaryemail` (
  `buildid` bigint(20) NOT NULL,
  `date` date NOT NULL,
  `groupid` smallint(6) NOT NULL,
  KEY `date` (`date`),
  KEY `groupid` (`groupid`)
);


CREATE TABLE `configureerror` (
  `configureid` int(11) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `text` text NOT NULL,
  KEY `configureid` (`configureid`),
  KEY `type` (`type`)
);


CREATE TABLE `configureerrordiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`),
  KEY `type` (`type`),
  UNIQUE KEY `unique_configureerrordiff` (`buildid`, `type`)
);


CREATE TABLE `coveragesummarydiff` (
  `buildid` bigint(20) NOT NULL,
  `loctested` int(11) NOT NULL default '0',
  `locuntested` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`)
);


CREATE TABLE `banner` (
  `projectid` int(11) NOT NULL,
  `text` varchar(500) NOT NULL,
  PRIMARY KEY  (`projectid`)
);


CREATE TABLE `coveragefile2user` (
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

--
-- Table structure for table `label2build`
--
CREATE TABLE `label2build` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`),
  KEY `labelid` (`labelid`),
  KEY `buildid` (`buildid`)
);

--
-- Table structure for table `label2buildfailure`
--
CREATE TABLE `label2buildfailure` (
  `labelid` bigint(20) NOT NULL,
  `buildfailureid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildfailureid`)
);

--
-- Table structure for table `label2coveragefile`
--
CREATE TABLE `label2coveragefile` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `coveragefileid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`,`coveragefileid`)
);

--
-- Table structure for table `label2dynamicanalysis`
--
CREATE TABLE `label2dynamicanalysis` (
  `labelid` bigint(20) NOT NULL,
  `dynamicanalysisid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`dynamicanalysisid`)
);


--
-- Table structure for table `label2test`
--
CREATE TABLE `label2test` (
  `labelid` bigint(20) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `testid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`buildid`,`testid`),
  KEY `buildid` (`buildid`),
  KEY `testid` (`testid`)
);

--
-- Table structure for table `label2update`
--
CREATE TABLE `label2update` (
  `labelid` bigint(20) NOT NULL,
  `updateid` bigint(20) NOT NULL,
  PRIMARY KEY (`labelid`,`updateid`)
);


--
-- Table structure for table `subproject`
--
CREATE TABLE `subproject` (
  `id` bigint(20) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `path` varchar(512) NOT NULL default '',
  `position` smallint(6) unsigned NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY  (`id`),
  KEY `groupid` (`groupid`),
  KEY `projectid` (`projectid`),
  UNIQUE KEY `unique_key` (`name`, `projectid`, `endtime`),
  KEY `path` (`path`)
);


--
-- Table structure for table `subprojectgroup`
--
CREATE TABLE `subprojectgroup` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  `coveragethreshold` smallint(6) NOT NULL default '70',
  `is_default` tinyint(1) NOT NULL,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `position` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `name` (`name`),
  KEY `projectid` (`projectid`),
  KEY `position` (`position`)
);


CREATE TABLE `subproject2subproject` (
  `subprojectid` int(11) NOT NULL,
  `dependsonid` int(11) NOT NULL,
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `subprojectid` (`subprojectid`),
  KEY `dependsonid` (`dependsonid`)
);


CREATE TABLE `subproject2build` (
  `subprojectid` int(11) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  PRIMARY KEY  (`buildid`),
  KEY `subprojectid` (`subprojectid`)
);


CREATE TABLE `buildfailure` (
  `id` bigint(20) NOT NULL auto_increment,
  `buildid` bigint(20) NOT NULL,
  `detailsid` bigint(20) NOT NULL,
  `workingdirectory` varchar(512) NOT NULL,
  `sourcefile` varchar(512) NOT NULL,
  `newstatus` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `buildid` (`buildid`),
  KEY `detailsid` (`detailsid`),
  KEY `newstatus` (`newstatus`)
);


CREATE TABLE `buildfailuredetails` (
  `id` bigint(20) NOT NULL auto_increment,
  `type` tinyint(4) NOT NULL,
  `stdoutput` mediumtext NOT NULL,
  `stderror` mediumtext NOT NULL,
  `exitcondition` varchar(255) NOT NULL,
  `language` varchar(64) NOT NULL,
  `targetname` varchar(255) NOT NULL,
  `outputfile` varchar(512) NOT NULL,
  `outputtype` varchar(255) NOT NULL,
  `crc32` bigint(20) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `type` (`type`),
  KEY `crc32` (`crc32`)
);


CREATE TABLE  `buildfailureargument` (
  `id` bigint(20) NOT NULL auto_increment,
  `argument` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `argument` (`argument`)
);

CREATE TABLE  `buildfailure2argument` (
  `buildfailureid` bigint(20) NOT NULL,
  `argumentid` bigint(20) NOT NULL,
  `place` int(11) NOT NULL default '0',
  KEY `argumentid` (`argumentid`),
  KEY `buildfailureid` (`buildfailureid`)
);


CREATE TABLE `labelemail` (
  `projectid` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `labelid` bigint(20) NOT NULL,
  KEY `projectid` (`projectid`),
  KEY `userid` (`userid`)
);


CREATE TABLE `buildemail` (
  `userid` int(11) NOT NULL,
  `buildid` bigint(20) NOT NULL,
  `category` tinyint(4) NOT NULL,
  `time` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `userid` (`userid`),
  KEY `buildid` (`buildid`),
  KEY `category` (`category`)
);


CREATE TABLE IF NOT EXISTS `coveragefilepriority` (
  `id` bigint(20) NOT NULL auto_increment,
  `priority` tinyint(4) NOT NULL,
  `fullpath` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `priority` (`priority`),
  KEY `fullpath` (`fullpath`),
  KEY `projectid` (`projectid`)
);


CREATE TABLE IF NOT EXISTS `submission` (
  `id` bigint(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(500) NOT NULL,
  `projectid` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT '0',
  `filesize` int(11) NOT NULL DEFAULT '0',
  `filemd5sum` varchar(32) NOT NULL DEFAULT '',
  `lastupdated` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `created` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `started` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `finished` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `status` (`status`),
  KEY `finished` (`finished`)
);


CREATE TABLE IF NOT EXISTS `blockbuild` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `buildname` varchar(255) NOT NULL,
  `sitename` varchar(255) NOT NULL,
  `ipaddress` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `buildname` (`buildname`),
  KEY `sitename` (`sitename`),
  KEY `ipaddress` (`ipaddress`)
);


-- --------------------------------------------------------

--
-- Table structure for table 'client_cmake'
--

CREATE TABLE IF NOT EXISTS client_cmake (
  id int(11) NOT NULL AUTO_INCREMENT,
  version varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_compiler'
--

CREATE TABLE IF NOT EXISTS client_compiler (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_job'
--

CREATE TABLE IF NOT EXISTS client_job (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scheduleid bigint(20) NOT NULL,
  osid tinyint(4) NOT NULL,
  siteid int(11) DEFAULT NULL,
  startdate timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  enddate timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `status` int(11) DEFAULT NULL,
  output text,
  cmakeid int(11) NOT NULL,
  compilerid int(11) NOT NULL,
  UNIQUE KEY id (id),
  KEY scheduleid (scheduleid),
  KEY startdate (startdate),
  KEY enddate (enddate),
  KEY `status` (`status`)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule'
--

CREATE TABLE IF NOT EXISTS client_jobschedule (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `userid` int(11) default NULL,
  `projectid` int(11) default NULL,
  `cmakecache` mediumtext NOT NULL,
  `clientscript` text default '',
  `startdate` timestamp NOT NULL default '1980-01-01 00:00:00',
  `enddate` timestamp NOT NULL default '1980-01-01 00:00:00',
  `type` tinyint(4) NOT NULL,
  `starttime` time NOT NULL default '00:00:00',
  `repeattime` decimal(6,2) NOT NULL default '0.00',
  `enable` tinyint(4) NOT NULL,
  `lastrun` timestamp NOT NULL default '1980-01-01 00:00:00',
  `repository` varchar(512) default '',
  `module` varchar(255) default '',
  `buildnamesuffix` varchar(255) default '',
  `tag` varchar(255) default '',
  `buildconfiguration` tinyint(4) default '0',
  `description` text default '',
  UNIQUE KEY `id` (`id`),
  KEY `userid` (`userid`),
  KEY `projectid` (`projectid`),
  KEY `enable` (`enable`),
  KEY `starttime` (`starttime`),
  KEY `repeattime` (`repeattime`)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2build'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2build (
  scheduleid bigint(20) unsigned NOT NULL,
  buildid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,buildid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2cmake'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2cmake (
  scheduleid bigint(20) NOT NULL,
  cmakeid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,cmakeid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2compiler'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2compiler (
  scheduleid bigint(20) NOT NULL,
  compilerid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,compilerid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2library'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2library (
  scheduleid bigint(20) NOT NULL,
  libraryid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,libraryid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2os'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2os (
  scheduleid bigint(20) NOT NULL,
  osid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,osid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2site'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2site (
  scheduleid bigint(20) NOT NULL,
  siteid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,siteid)
);


-- --------------------------------------------------------

--
-- Table structure for table 'client_jobschedule2submission'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2submission (
  scheduleid bigint(20) NOT NULL,
  submissionid bigint(11) NOT NULL,
  PRIMARY KEY (`submissionid`),
  UNIQUE KEY `scheduleid` (`scheduleid`)
);


-- --------------------------------------------------------

--
-- Table structure for table 'client_library'
--

CREATE TABLE IF NOT EXISTS client_library (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  PRIMARY KEY (id)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_os'
--

CREATE TABLE IF NOT EXISTS client_os (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  version varchar(255) NOT NULL,
  bits tinyint(4) NOT NULL DEFAULT '32',
  PRIMARY KEY (id),
  KEY `name` (`name`),
  KEY version (version),
  KEY bits (bits)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_site'
--

CREATE TABLE IF NOT EXISTS client_site (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `osid` int(11) DEFAULT NULL,
  `systemname` varchar(255) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  `basedirectory` varchar(512) NOT NULL,
  `lastping` timestamp NOT NULL default '1980-01-01 00:00:00',
  PRIMARY KEY (id),
  KEY `name` (`name`),
  KEY `lastping` (`lastping`),
  KEY system (osid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_site2cmake'
--

CREATE TABLE IF NOT EXISTS client_site2cmake (
  siteid int(11) DEFAULT NULL,
  cmakeid int(11) DEFAULT NULL,
  path varchar(512) DEFAULT NULL,
  KEY siteid (siteid),
  KEY version (cmakeid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_site2compiler'
--

CREATE TABLE IF NOT EXISTS client_site2compiler (
  siteid int(11) DEFAULT NULL,
  compilerid int(11) DEFAULT NULL,
  command varchar(512) DEFAULT NULL,
  generator varchar(255) NOT NULL,
  KEY siteid (siteid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_site2library'
--

CREATE TABLE IF NOT EXISTS client_site2library (
  siteid int(11) DEFAULT NULL,
  libraryid int(11) DEFAULT NULL,
  path varchar(512) DEFAULT NULL,
  include varchar(512) NOT NULL,
  KEY siteid (siteid)
);

CREATE TABLE IF NOT EXISTS `client_site2program` (
  `siteid` int(11) NOT NULL,
  `name` varchar(30) NOT NULL,
  `version` varchar(30) NOT NULL,
  `path` varchar(512) NOT NULL,
  KEY `siteid` (`siteid`)
);

CREATE TABLE IF NOT EXISTS `client_site2project` (
  `projectid` int(11) DEFAULT NULL,
  `siteid` int(11) DEFAULT NULL,
  KEY `siteid` (`siteid`)
);

--
-- Table structure for table `projectrobot`
--

CREATE TABLE IF NOT EXISTS `projectrobot` (
  `projectid` int(11) NOT NULL,
  `robotname` varchar(255) NOT NULL,
  `authorregex` varchar(512) NOT NULL,
  KEY `projectid` (`projectid`),
  KEY `robotname` (`robotname`)
);

--
-- Table structure for table `filesum`
--

CREATE TABLE IF NOT EXISTS `filesum` (
  `id` int(11) NOT NULL auto_increment,
  `md5sum` varchar(32) NOT NULL,
  `contents` longblob,
  PRIMARY KEY `id` (`id`),
  KEY `md5sum` (`md5sum`)
);


CREATE TABLE IF NOT EXISTS `projectjobscript` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `script` longtext NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`)
);


CREATE TABLE IF NOT EXISTS `submissionprocessor` (
  `projectid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `lastupdated` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `locked` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY (`projectid`)
);

CREATE TABLE IF NOT EXISTS `user2repository` (
  `userid` int(11) NOT NULL,
  `credential` varchar(255) NOT NULL,
  `projectid` int(11) NOT NULL DEFAULT '0',
  KEY `userid` (`userid`),
  KEY `credential` (`credential`),
  KEY `projectid` (`projectid`)
);

CREATE TABLE IF NOT EXISTS `apitoken` (
  `projectid` int(11) NOT NULL,
  `token` varchar(40),
  `expiration_date` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  KEY `token` (`token`)
);

CREATE TABLE IF NOT EXISTS `submission2ip` (
  `submissionid` bigint(11) NOT NULL,
  `ip` varchar(255) NOT NULL default '',
  PRIMARY KEY (`submissionid`)
);

CREATE TABLE IF NOT EXISTS `measurement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `testpage` tinyint(1) NOT NULL,
  `summarypage` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `name` (`name`)
);

CREATE TABLE IF NOT EXISTS `feed` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `projectid` int(11) NOT NULL,
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `buildid` bigint(20) NOT NULL,
  `type` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `date` (`date`)
);

CREATE TABLE IF NOT EXISTS `overview_components` (
  `projectid` int(11) NOT NULL DEFAULT 1,
  `buildgroupid` int(11) NOT NULL DEFAULT 0,
  `position` int(11) NOT NULL DEFAULT 0,
  `type` varchar(32) NOT NULL DEFAULT "build",
  KEY (`projectid`),
  KEY (`buildgroupid`)
);

CREATE TABLE IF NOT EXISTS `buildfile` (
  `buildid` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `md5` varchar(40) NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT "",
  KEY (`buildid`),
  KEY (`filename`),
  KEY (`type`),
  KEY (`md5`)
);

CREATE TABLE IF NOT EXISTS `password` (
  `userid` int(11) NOT NULL,
  `password` varchar(255) NOT NULL default '',
  `date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `userid` (`userid`)
);

CREATE TABLE IF NOT EXISTS `lockout` (
  `userid` int(11) NOT NULL,
  `failedattempts` tinyint(1) DEFAULT '0',
  `islocked` tinyint(1) NOT NULL DEFAULT '0',
  `unlocktime` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  PRIMARY KEY  (`userid`)
);

CREATE TABLE IF NOT EXISTS `authtoken` (
  `hash` varchar(128) NOT NULL,
  `userid` int(11) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires` timestamp NOT NULL DEFAULT '1980-01-01 00:00:00',
  `description` varchar(255),
  KEY `hash` (`hash`),
  KEY `userid` (`userid`),
  KEY `expires` (`expires`)
);

CREATE TABLE IF NOT EXISTS `buildproperties` (
  `buildid` int(11) NOT NULL DEFAULT '0',
  `properties` mediumtext NOT NULL DEFAULT '',
  PRIMARY KEY  (`buildid`)
);

--
-- Table structure for table `related_builds`
--
CREATE TABLE `related_builds` (
  `buildid` bigint(20) NOT NULL,
  `relatedid` bigint(20) NOT NULL,
  `relationship` varchar(255),
  PRIMARY KEY (`buildid`,`relatedid`),
  KEY `buildid` (`buildid`),
  KEY `relatedid` (`relatedid`)
);

--
-- Change the table maximum size to be more than 4GB
--
alter table test max_rows = 200000000000 avg_row_length = 3458;
alter table builderror max_rows = 200000000000 avg_row_length = 3458;
alter table coverage max_rows = 200000000000 avg_row_length = 3458;
alter table coveragefilelog max_rows = 200000000000 avg_row_length = 3458;
alter table coveragefile max_rows = 200000000000 avg_row_length = 3458;
alter table image max_rows = 200000000000 avg_row_length = 3458;
alter table note max_rows = 200000000000 avg_row_length = 3458;
alter table buildnote max_rows = 200000000000 avg_row_length = 3458;
