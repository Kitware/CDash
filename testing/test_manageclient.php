<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageClientTestCase extends KWWebTestCase
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

  function testManageClientTest()
    {
    //make sure we can't visit the manageClient page while logged out
    $this->logout();
    $content = $this->get($this->url."/manageClient.php");
    if(strpos($content, "<title>Login</title>") === false)
      {
      $this->fail("'<title>Login</title>' not found when expected.");
      return 1;
      }

    //make sure we can visit the page while logged in
    $this->login();
    $content = $this->get($this->url."/manageClient.php");
    if(strpos($content, "Projectid or Schedule id not set") === false)
      {
      $this->fail("'Projectid or Schedule id not set' not found when expected");
      return 1;
      }
    $content = $this->get($this->url."/manageClient.php?projectid=1");
    if(strpos($content, "Schedule a build") === false)
      {
      $this->fail("'Schedule a build' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    return 0;
    }
}
?>
