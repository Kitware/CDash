<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ViewErrorLogTestCase extends KWWebTestCase
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

  function testViewErrorLog()
    {
    $this->login();
    $this->get($this->url."/viewErrorLog.php");
    if(strpos($this->getBrowser()->getContentAsText(), "Error Log") === false)
      {
      $this->fail("'Error Log' not found when expected.");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
