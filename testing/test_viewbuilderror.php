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
    /*
    $content = $this->get($this->url . "/index.php?project=InsightExample");
    if($content == false)
      {
      $this->fail("Unable to retrieve InsightExample dashboard"); 
      return 1;
      }
    
    //get a buildid
    $buildid = -1;
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "buildid") !== false)
        {
        preg_match('#buildid=([0-9]+)#', $line, $matches);
        $buildid = $matches[1];
        break;
        }
      }
    if($buildid === -1)
      {
      $this->fail("Unable to find a buildid for InsightExamples");
      return 1;
      }
    //connect to viewBuildError.php
    $content = $this->get($this->url . "/viewBuildError.php?buildid=$buildid");
     */
    $content = $this->get($this->url . "/viewBuildError.php?buildid=12");
    if($content == false)
      {
      $this->fail("Unable to retrieve viewBuildError.php"); 
      return 1;
      }

    $this->assertText('Errors');
    }
}
?>
