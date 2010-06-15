<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class TestOverviewTestCase extends KWWebTestCase
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

  function testTestOverview()
    {
    $this->login();
    $this->get($this->url."/testOverview.php");
    if(strpos($this->getBrowser()->getContentAsText(), "project not specified") === false)
      {
      $this->fail("'project not specified' not found when expected");
      return 1;
      }
    $this->get($this->url."/testOverview.php?project=InsightExample");
    if(strpos($this->getBrowser()->getContentAsText(), "No failing tests for this date") === false)
      {
      $this->fail("'No failing tests for this date' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
