<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class EditSiteTestCase extends KWWebTestCase
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

  function testEditSite()
    {
    $this->login();
    $this->get($this->url."/editSite.php?projectid=5");
    $content = $this->clickSubmitByName("claimsites");
    if(strpos($content, "Claimed sites updated") === false)
      {
      $this->fail("expected output not found from editSite.php");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
