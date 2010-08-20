<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ViewSubprojectDependenciesTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testViewSubprojectDependencies()
    {
    $this->login();
    $this->get($this->url."/viewSubprojectDependencies.php?project=SubProjectExample");
    if(strpos($this->getBrowser()->getContentAsText(), "Komplex") === false)
      {
      $this->fail("'Komplex' not found when expected");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
