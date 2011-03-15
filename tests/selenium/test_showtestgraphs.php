<?php
//
// After including cdash_selenium_test_base.php, subsequent require_once calls
// are relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_selenium_test_case.php');

class Example extends CDashSeleniumTestCase
{
  protected function setUp()
  {
    $this->browserSetUp();
  }

  public function testShowTestGraphs()
  {
    $this->open($this->webPath."/index.php?project=EmailProjectExample&date=2009-02-23");
    $this->click("//table[@id='project_3_7']/tbody[1]/tr[2]/td[12]/div/a");
    $this->waitForPageToLoad("30000");
    $this->click("link=Failed");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Test Time Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Test Time Graph]");
    $this->click("link=[Show Failing/Passing Graph]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Failing/Passing Graph]");
    $this->click("link=curl");
    $this->waitForPageToLoad("30000");
    $this->click("link=[Show Test Failure Trend]");
    $this->click("link=[Zoom out]");
    $this->click("link=[Show Test Failure Trend]");
  }
}
?>
