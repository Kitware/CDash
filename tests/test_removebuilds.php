<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class RemoveBuildsTestCase extends KWWebTestCase
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

  function testRemoveBuilds()
    {
    $this->login();
    $this->get($this->url."/removeBuilds.php?projectid=5");
    $this->clickSubmitByName("Submit");
    if(strpos($this->getBrowser()->getContentAsText(), "Removed") === false)
      {
      $this->fail("'Removed' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
