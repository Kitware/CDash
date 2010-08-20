<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ViewDynamicAnalysisTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testViewDynamicAnalysis()
    {
    $this->get($this->url."/viewDynamicAnalysis.php?buildid=1");
    if(strpos($this->getBrowser()->getContentAsText(), "Win32-VCExpress") === false)
      {
      $this->fail("'Win32-VCExpress' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
