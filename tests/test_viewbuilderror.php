<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');

class ViewBuildErrorTestCase extends KWWebTestCase
{
  var $url = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
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
