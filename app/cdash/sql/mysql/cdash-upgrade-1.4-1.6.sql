CREATE TABLE IF NOT EXISTS `coveragefilepriority` (
  `id` serial NOT NULL,
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
  PRIMARY KEY (`id`),
  KEY `projectid` (`projectid`),
  KEY `status` (`status`)
);

CREATE TABLE IF NOT EXISTS `buildtesttime` (
  `buildid` int(11) NOT NULL default '0',
  `time` float(7,2) NOT NULL default '0.00',
  PRIMARY KEY `buildid` (`buildid`)
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
  `startdate` timestamp NOT NULL default '1980-01-01 00:00:00',
  `enddate` timestamp NOT NULL default '1980-01-01 00:00:00',
  `type` tinyint(4) NOT NULL,
  `starttime` time NOT NULL default '00:00:00',
  `repeattime` decimal(3,2) NOT NULL default '0.00',
  `enable` tinyint(4) NOT NULL,
  `lastrun` timestamp NOT NULL default '1980-01-01 00:00:00',
  `repository` varchar(512) default '',
  `module` varchar(255) default '',
  `buildnamesuffix` varchar(255) default '',
  `tag` varchar(255) default '',
  UNIQUE KEY `id` (`id`),
  KEY `userid` (`userid`),
  KEY `projectid` (`projectid`),
  KEY `enable` (`enable`),
  KEY `starttime` (`starttime`),
  KEY `repeattime` (`repeattime`)
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
-- Table structure for table 'client_jobschedule2toolkit'
--

CREATE TABLE IF NOT EXISTS client_jobschedule2toolkit (
  scheduleid bigint(20) NOT NULL,
  toolkitconfigurationid int(11) NOT NULL,
  UNIQUE KEY scheduleid (scheduleid,toolkitconfigurationid)
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
  osid int(11) DEFAULT NULL,
  systemname varchar(255) DEFAULT NULL,
  `host` varchar(255) DEFAULT NULL,
  basedirectory varchar(512) NOT NULL,
  PRIMARY KEY (id),
  KEY `name` (`name`),
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

-- --------------------------------------------------------

--
-- Table structure for table 'client_toolkit'
--

CREATE TABLE IF NOT EXISTS client_toolkit (
  id int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  projectid bigint(20) NOT NULL DEFAULT '0',
  PRIMARY KEY (id),
  KEY projectid (projectid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_toolkitconfiguration'
--

CREATE TABLE IF NOT EXISTS client_toolkitconfiguration (
  id int(11) NOT NULL AUTO_INCREMENT,
  toolkitversionid bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  cmakecache text,
  environment text,
  binarypath varchar(512) NOT NULL,
  PRIMARY KEY (id),
  KEY `name` (`name`),
  KEY binarypath (binarypath)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_toolkitconfiguration2os'
--

CREATE TABLE IF NOT EXISTS client_toolkitconfiguration2os (
  toolkitconfigurationid bigint(20) NOT NULL,
  osid int(11) NOT NULL,
  KEY toolkitconfigurationid (toolkitconfigurationid),
  KEY osid (osid)
);

-- --------------------------------------------------------

--
-- Table structure for table 'client_toolkitversion'
--

CREATE TABLE IF NOT EXISTS client_toolkitversion (
  id int(11) NOT NULL AUTO_INCREMENT,
  toolkitid int(11) NOT NULL,
  `name` varchar(10) NOT NULL,
  repositoryurl varchar(255) NOT NULL,
  repositorytype tinyint(4) NOT NULL,
  repositorymodule varchar(100) NOT NULL,
  tag varchar(30) DEFAULT NULL,
  sourcepath varchar(512) NOT NULL,
  ctestprojectname varchar(50) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY toolkitid (toolkitid),
  KEY version (`name`)
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
