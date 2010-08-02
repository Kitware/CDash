<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class UserStatisticsTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    $this->db  =& new database($db['type']);
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    $this->projectid = -1;
    }

  function testUserStatistics()
    {
    $this->login();
    $this->get($this->url."/userStatistics.php?projectid=1");
    
    // No project selected
    $this->get($this->url."/userStatistics.php");
    
    // Cover all date ranges
    $this->post($this->url."/userStatistics.php?projectid=1", array("range"=>"lastweek"));
    $this->post($this->url."/userStatistics.php?projectid=1", array("range"=>"thismonth"));
    $this->post($this->url."/userStatistics.php?projectid=1", array("range"=>"lastmonth"));
    $this->post($this->url."/userStatistics.php?projectid=1", array("range"=>"thisyear"));
    
    // Cover no user id case
    $this->logout();
    $this->get($this->url."/userStatistics.php");
    }
}
?>
