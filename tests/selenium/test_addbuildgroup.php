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

  public function testAddBuildGroup()
  {
    $this->open($this->webPath."/index.php");
    $this->click("link=Login");
    $this->waitForPageToLoad("30000");
    $this->type("login", "simpletest@localhost");
    $this->type("passwd", "simpletest");
    $this->click("sent");
    $this->waitForPageToLoad("30000");
    $this->click("link=InsightExample");
    $this->waitForPageToLoad("30000");
    $this->setSpeed("1000");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/a[4]/img");
    $this->click("link=[mark as expected]");
    $this->waitForPageToLoad("30000");
    $this->click("//table[@id='project_5_15']/tbody[1]/tr[1]/td[2]/a[4]/img");
    $this->click("link=[mark as non expected]");
    $this->waitForPageToLoad("30000");
    $this->click("link=Log Out");
    $this->waitForPageToLoad("30000");
  }
}
?>
