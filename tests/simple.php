<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class EmailTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testSimple()
    {
    $content = $this->connect($this->url.'/index.php?project=InsightExample');
    if(!$content)
      {
      return;
      }
    $this->assertText('CDash-CTest-simple');
    }

}

?>
