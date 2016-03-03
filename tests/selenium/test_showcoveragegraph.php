<?php
//
// After including cdash_selenium_test_base.php, subsequent require_once calls
// are relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_selenium_test_case.php';

class Example extends CDashSeleniumTestCase
{
    protected function setUp()
    {
        $this->browserSetUp();
    }

    public function testShowCoverageGraph()
    {
        $this->open($this->webPath . '/index.php?project=InsightExample');
        $this->click("//table[@id='coveragetable']/tbody/tr/td[3]/a");
        $this->waitForPageToLoad('30000');
        $this->click('link=Show coverage over time');
        $this->click('link=Zoom out');
        $this->click('link=Show coverage over time');
    }
}
