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
        $this->open($this->webPath."/testDetails.php?test=21&build=6");
        $this->waitForPageToLoad("30000");
        $this->select("GraphSelection", "label=Test Time");
        $this->click("link=Zoom out");
        $this->select("GraphSelection", "label=Test Time");
        $this->select("GraphSelection", "label=Failing/Passing");
        $this->click("link=Zoom out");
        $this->click("link=curl");
        $this->waitForPageToLoad("30000");
        $this->click("link=Show Test Failure Trend");
        $this->click("link=Reset zoom");
        $this->click("link=Hide Test Failure Trend");
    }
}
