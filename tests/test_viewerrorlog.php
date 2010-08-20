<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ViewErrorLogTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
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
