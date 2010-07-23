<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class EmailTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  var $logfilename   = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    }
   
  function testSimple()
    {
    $content = $this->connect($this->url.'/index.php?project=InsightExample');
    if(!$content)
      {
      return;
      }
    $this->assertText('CDash-CTest-sameImage');
    }
  
}

?>
