<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');

class ViewChangesTestCase extends KWWebTestCase
{
  var $url = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    }
  
  function testViewChanges()
    {
    $content = $this->connect($this->url . "/viewChanges.php?project=TestCompressionExample");
    if($content == false)
      {
      return;
      }
    $this->assertText('Nightly Changes');
    }
}
?>
