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

  public function testSortBuildName()
  {
    $this->open($this->webPath."/index.php?project=InsightExample&date=2010-07-07");
    $this->click("sort13sort_1");
    try {
        $this->assertEquals("zApp-Win64-Vista-vs9-Release", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("zApps-Darwin-g++-4.0.1", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("zApps-Win32-vs60", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    $this->click("sort13sort_1");
    try {
        $this->assertEquals("zApp-Win64-Vista-vs9-Release", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("zApps-Darwin-g++-4.0.1", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
    try {
        $this->assertEquals("zApps-Win32-vs60", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[2]/a"));
    } catch (PHPUnit_Framework_AssertionFailedError $e) {
        array_push($this->verificationErrors, $e->toString());
    }
  }
}
?>
