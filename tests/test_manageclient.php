<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ManageClientTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
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
