<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class RouterTestCase extends KWWebTestCase
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

  function testRouter()
    {
    $this->login();
    $content = $this->get($this->url."/router.php");
    if(strpos($content, "Available") === false)
      {
      $this->fail("'Available' not found on router.php\n$content\n");
      }
    $content = $this->get($this->url."/router.php?page=login");
    $this->pass("Passed");
    }
}
?>
