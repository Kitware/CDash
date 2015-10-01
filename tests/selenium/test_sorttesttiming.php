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

    public function testSortTestTiming()
    {
        $this->open($this->webPath."/index.php?project=InsightExample&date=2010-07-07");
        $this->click("id=feed");
        $this->click("css=#settings > img");
        $this->click("link=Advanced View");
        $this->click("sort13sort_13");
        $this->click("sort13sort_13");
        try {
            $this->assertEquals("18s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("6s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("0s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        $this->click("sort13sort_13");
        try {
            $this->assertEquals("0s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[1]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("6s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[2]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
        try {
            $this->assertEquals("18s", $this->getText("//table[@id='project_5_13']/tbody[1]/tr[3]/td[14]/div"));
        } catch (PHPUnit_Framework_AssertionFailedError $e) {
            array_push($this->verificationErrors, $e->toString());
        }
    }
}
