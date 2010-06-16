<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class SiteStatisticsTestCase extends KWWebTestCase
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

  function testSiteStatistics()
    {
    $this->login();
    $content = $this->get($this->url."/siteStatistics.php");
    if(strpos($content, "Busy time") === false)
      {
      $this->fail("'Busy time' not found on siteStatistics.php");
      }
    $this->pass("Passed");
    }
}
?>
