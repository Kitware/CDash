<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ProcessSubmissionsTestCase extends KWWebTestCase
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
    }

  function testProcessSubmissionsTest()
    {
    $this->login();
    $content = $this->get($this->url."/cdash/processsubmissions.php");
    if(strpos($content, "Wrong project id") === false)
      {
      $this->fail("'Wrong project id' not found when expected");
      return 1;
      }
    $content = $this->get($this->url."/cdash/processsubmissions.php?projectid=1");
    if(strpos($content, "Done") === false)
      {
      $this->fail("'Done' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
