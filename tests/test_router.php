<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class RouterTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testRouter()
    {
    $this->login();
    $content = $this->get($this->url."/router.php");
    if(strpos($content, "Dashboards") === false)
      {
      $this->fail("'Dashboards' not found on router.php\n$content\n");
      }
    $content = $this->get($this->url."/router.php?page=login");
    $this->pass("Passed");
    }
}
?>
