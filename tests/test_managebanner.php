<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageBannerTestCase extends KWWebTestCase
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

  function testManageBannerTest()
    {
    //make sure we can't visit the manageBanner page while logged out
    $this->logout();
    $content = $this->get($this->url."/manageBanner.php");
    if(strpos($content, "<title>Login</title>") === false)
      {
      $this->fail("'<title>Login</title>' not found when expected");
      return 1;
      }

    //make sure we can visit the page while logged in
    $this->login();
    $content = $this->get($this->url."/manageBanner.php");
    if(strpos($content, "Banner Message") === false)
      {
      $this->fail("'Banner Message' not found when expected");
      return 1;
      }

    //change the banner
    if(!$this->SetFieldByName("message", "this is a new banner"))
      {
      $this->fail("SetFieldByName on banner message returned false");
      return 1;
      }
    $this->clickSubmitByName("updateMessage");

    //make sure the banner changed
    $content = $this->get($this->url."/index.php?project=InsightExample");
    if(strpos($content, "this is a new banner") === false)
      {
      $this->fail("New banner message not found on dashboard");
      return 1;
      }

    //change it back
    $content = $this->get($this->url."/manageBanner.php");
    $this->SetFieldByName("message", "");
    $this->clickSubmitByName("updateMessage");

    //make sure it changed back
    $content = $this->connect($this->url."/index.php");
    if(strpos($content, "this is a new banner") !== false)
      {
      $this->fail("New banner message still on dashboard after it should have been removed");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
