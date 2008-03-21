--
-- Table structure for table `repositories`
--

CREATE TABLE IF NOT EXISTS `repositories` (
  `id` int(11) NOT NULL auto_increment,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Table structure for table `project2repositories`
--

CREATE TABLE IF NOT EXISTS `project2repositories` (
  `projectid` int(11) NOT NULL,
  `repositoryid` int(11) NOT NULL,
  PRIMARY KEY  (`projectid`,`repositoryid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
