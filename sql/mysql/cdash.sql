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
  `stamp` varchar(255) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  `type` varchar(255) NOT NULL default '',
  `generator` varchar(255) NOT NULL default '',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `submittime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `command` text NOT NULL,
  `log` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `siteid` (`siteid`,`name`),
  KEY `projectid` (`projectid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE `buildgroup` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `projectid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `description` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `projectid` (`projectid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `buildgroupposition`
-- 

CREATE TABLE `buildgroupposition` (
  `buildgroupid` int(11) NOT NULL default '0',
  `position` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `buildgroupid` (`buildgroupid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        


-- --------------------------------------------------------

-- 
-- Table structure for table `build2group`
-- 

CREATE TABLE `build2group` (
  `groupid` int(11) NOT NULL default '0',
  `buildid` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`),
  KEY `groupid` (`groupid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `build2grouprule`
-- 

CREATE TABLE `build2grouprule` (
  `groupid` int(11) NOT NULL default '0',
  `buildtype` varchar(20) NOT NULL default '',
  `buildname` varchar(255) NOT NULL default '',
  `siteid` int(11) NOT NULL default '0',
  `expected` tinyint(4) NOT NULL default '0',
  `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  KEY `groupid` (`groupid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
        




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
  `precontext` text NOT NULL,
  `postcontext` text NOT NULL,
  `repeatcount` int(11) NOT NULL default '0',
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `buildupdate`
-- 

CREATE TABLE `buildupdate` (
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `command` text NOT NULL,
  `type` varchar(4) NOT NULL default '',
  `status` text NOT NULL,
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `configure`
-- 

CREATE TABLE `configure` (
  `buildid` int(11) NOT NULL default '0',
  `starttime` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
  `command` text NOT NULL,
  `log` text NOT NULL,
  `status` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  KEY `fileid` (`fileid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `coveragefile`
-- 

CREATE TABLE `coveragefile` (
  `id` int(11) NOT NULL auto_increment,
  `fullpath` varchar(255) NOT NULL default '',
  `file` blob,
  `crc32` int(11) default NULL,
  PRIMARY KEY  (`id`),
  KEY `fullpath` (`fullpath`),
  KEY `crc32` (`crc32`) 
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `coveragefilelog`
-- 

CREATE TABLE `coveragefilelog` (
  `buildid` int(11) NOT NULL default '0',
  `fileid` int(11) NOT NULL default '0',
  `line` int(11) NOT NULL default '0',
  `code` varchar(10) NOT NULL default '',
  KEY `fileid` (`fileid`),
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `coveragesummary`
-- 

CREATE TABLE `coveragesummary` (
  `buildid` int(11) NOT NULL default '0',
  `loctested` int(11) NOT NULL default '0',
  `locuntested` int(11) NOT NULL default '0',
  PRIMARY KEY  (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  `log` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `dynamicanalysisdefect`
-- 

CREATE TABLE `dynamicanalysisdefect` (
  `dynamicanalysisid` int(11) NOT NULL default '0',
  `type` varchar(50) NOT NULL default '',
  `value` varchar(50) NOT NULL default '',
  KEY `buildid` (`dynamicanalysisid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

  

-- --------------------------------------------------------

-- 
-- Table structure for table `image`
-- 

CREATE TABLE `image` (
  `id` int(11) NOT NULL auto_increment,
  `img` longblob NOT NULL,
  `extension` tinytext NOT NULL,
  `checksum` int NOT NULL,
  KEY `id` (`id`),
  KEY `checksum` (`checksum`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

-- 
-- Table structure for table `test2image`
-- 

CREATE TABLE `test2image` (
  `imgid` int(11) NOT NULL default '0',
  `testid` int(11) NOT NULL default '0',
  `role` tinytext NOT NULL,
  PRIMARY KEY  (`imgid`,`testid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------
-- 
-- Table structure for table `note`
-- 
CREATE TABLE `note` (
  `id` bigint(20) NOT NULL auto_increment,
  `text` mediumtext NOT NULL,
  `name` varchar(255) NOT NULL,
  `crc32` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `crc32` (`crc32`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `project`
-- 

CREATE TABLE `project` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `description` text NOT NULL,
  `homeurl` varchar(255) NOT NULL default '',
  `cvsurl` varchar(255) NOT NULL default '',
  `bugtrackerurl` varchar(255) NOT NULL default '',
  `documentationurl` varchar(255) NOT NULL default '',
  `imageid` int(11) NOT NULL default '0',
  `public` tinyint(4) NOT NULL default '1',
  `coveragethreshold` smallint(6) NOT NULL default '70',
  `nightlytime` varchar(50) NOT NULL default '00:00:00',
  `googletracker` varchar(50) NOT NULL default '',
  `emailbuildmissing` tinyint(4) NOT NULL default '0',
  `emaillowcoverage` tinyint(4) NOT NULL default '0',
  `emailtesttimingchanged` tinyint(4) NOT NULL default '0',
  `emailbrokensubmission` tinyint(4) NOT NULL default '1',
  `cvsviewertype` varchar(10) default NULL,
  `testtimestd` float(3,1) default '4.0',
  `testtimestdthreshold` float(3,1) default '1.0',
  `showtesttime` tinyint(4) default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE `buildinformation` (
  `buildid` int(11) NOT NULL,
  `osname` varchar(255) NOT NULL,
  `osplatform` varchar(255) NOT NULL,
  `osrelease` varchar(255) NOT NULL,
  `osversion` varchar(255) NOT NULL,
  `compilername` varchar(255) NOT NULL,
  `compilerversion` varchar(20) NOT NULL,
  PRIMARY KEY  (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

-- 
-- Table structure for table `site2user`
-- 

CREATE TABLE `site2user` (
  `siteid` int(11) NOT NULL default '0',
  `userid` int(11) NOT NULL default '0',
  KEY `siteid` (`siteid`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `test`
-- 
CREATE TABLE `test` (
  `id` int(11) NOT NULL auto_increment,
  `crc32` int(11) NOT NULL,
  `name` varchar(255) NOT NULL default '',
  `path` varchar(255) NOT NULL default '',
  `command` text NOT NULL,
  `details` text NOT NULL,
  `output` text NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `crc32` (`crc32`),
  KEY `name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  KEY `buildid` (`buildid`),
  KEY `testid` (`testid`),
  KEY `status` (`status`),
  KEY `timestatus` (`timestatus`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------

-- 
-- Table structure for table `updatefile`
-- 

CREATE TABLE `updatefile` (
  `buildid` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  `author` varchar(255) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `log` text NOT NULL,
  `revision` varchar(20) NOT NULL default '0',
  `priorrevision` varchar(20) NOT NULL default '0',
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

-- 
-- Table structure for table `user`
-- 

CREATE TABLE `user` (
  `id` int(11) NOT NULL auto_increment,
  `email` varchar(255) NOT NULL default '',
  `password` varchar(40) NOT NULL default '',
  `firstname` varchar(40) NOT NULL default '',
  `lastname` varchar(40) NOT NULL default '',
  `institution` varchar(255) NOT NULL default '',
  `admin` tinyint(4) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
  PRIMARY KEY  (`userid`,`projectid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `repositories`
--

CREATE TABLE `repositories` (
  `id` int(11) NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `project2repositories`
--

CREATE TABLE `project2repositories` (
  `projectid` int(11) NOT NULL,
  `repositoryid` int(11) NOT NULL,
  PRIMARY KEY  (`projectid`,`repositoryid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


-- --------------------------------------------------------
CREATE TABLE `testmeasurement` (
  `testid` bigint(20) NOT NULL,
  `name` varchar(70) NOT NULL,
  `type` varchar(70) NOT NULL,
  `value` text NOT NULL,
  KEY `testid` (`testid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;



CREATE TABLE `dailyupdate` (
  `id` bigint(11) NOT NULL auto_increment,
  `projectid` int(11) NOT NULL,
  `date` date NOT NULL,
  `command` text NOT NULL,
  `type` varchar(4) NOT NULL default '',
  `status` tinyint(4) NOT NULL default '0',
  KEY `buildid` (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;


CREATE TABLE `dailyupdatefile` (
  `dailyupdateid` int(11) NOT NULL default '0',
  `filename` varchar(255) NOT NULL default '',
  `checkindate` timestamp NOT NULL default CURRENT_TIMESTAMP,
  `author` varchar(255) NOT NULL default '',
  `log` text NOT NULL,
  `revision` varchar(10) NOT NULL default '0',
  `priorrevision` varchar(10) NOT NULL default '0',
  KEY `buildid` (`dailyupdateid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


CREATE TABLE `builderrordiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `testdiff` (
  `buildid` bigint(20) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `difference` int(11) NOT NULL,
  KEY `buildid` (`buildid`,`type`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `build2note` (
  `buildid` bigint(20) NOT NULL,
  `noteid`  bigint(20) NOT NULL,
  `time` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
  KEY `buildid` (`buildid`,`noteid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;


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
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

CREATE TABLE `version` (
  `major` tinyint(4) NOT NULL,
  `minor` tinyint(4) NOT NULL,
  `patch` tinyint(4) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

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
