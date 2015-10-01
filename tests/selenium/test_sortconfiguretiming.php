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

    public function testSortConfigureTiming()
    {
        $this->open($this->webPath."/index.php?project=InsightExample&date=2010-07-07");
        $this->click("sort13sort_6");
        try {
            $this->assertEquals("0.2", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("0.1", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("0", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        $this->click("sort13sort_6");
        try {
            $this->assertEquals("0.2", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("0.1", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("0", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[7]"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
    }
}
