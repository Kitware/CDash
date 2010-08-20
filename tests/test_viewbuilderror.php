<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ViewBuildErrorTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testViewBuildError()
    {
    $content = $this->get($this->url . "/viewBuildError.php?buildid=8");
    if($content == false)
      {
      $this->fail("Unable to retrieve viewBuildError.php");
      return 1;
      }
    if(strpos($content, "Errors") === false)
      {
      $this->fail("Expected output not found from viewBuildError.php");
      return 1;
      }
    $content = $this->get($this->url . "/viewBuildError.php?buildid=8&type=1");
    if(strpos($content, "warning") === false)
      {
      $this->fail("Expected output not found from viewBuildError.php");
      return 1;
      }
    $content = $this->get($this->url . "/viewBuildError.php?buildid=8&onlydeltan=1");
    if(strpos($content, "Errors") === false)
      {
      $this->fail("Expected output not found from viewBuildError.php");
      return 1;
      }
    $content = $this->get($this->url . "/viewBuildError.php?buildid=8&onlydeltap=1");
    if(strpos($content, "Errors") === false)
      {
      $this->fail("Expected output not found from viewBuildError.php");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
