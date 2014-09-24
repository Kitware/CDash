<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ManageSubprojectTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testManageSubproject()
    {
    $this->login();

    //get projectid for PublicDashboards
    $content = $this->connect($this->url.'/manageSubproject.php');
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "SubProjectExample") !== false)
        {
        preg_match('#<option value="([0-9]+)"#', $line, $matches);
        $this->projectid = $matches[1];
        break;
        }
      }

    $this->get($this->url."/manageSubproject.php?projectid=$this->projectid");
    if(strpos($this->getBrowser()->getContentAsText(), "Teuchos") === false)
      {
      $this->fail("'Teuchos' not found when expected.  Here's what we found instead:\n".$this->getBrowser()->getContentAsText()."\n");
      return 1;
      }

    $this->get($this->url."/manageSubproject.php?projectid=$this->projectid&delete=1");
    if(strpos($this->getBrowser()->getContentAsText(), "Teuchos") !== false)
      {
      $this->fail("'Teuchos' found when not expected");
      return 1;
      }

    if(!$this->setFieldByName("dependency_selection_19", "3"))
      {
      $this->fail("Set dependency_selection_19 returned false");
      return 1;
      }
    $this->clickSubmitByName("addDependency");
    if(strpos($this->getBrowser()->getContent(), "- RTOp") === false)
      {
      $this->fail("'- RTOp' not found when expected");
      return 1;
      }

    $this->pass("Passed");
    }
}
?>
