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

    public function testShowUpdateGraph()
    {
        $this->open($this->webPath . '/viewUpdate.php?buildid=1');
        $this->click('link=Show Activity Graph');
        $this->click('link=Zoom out');
        $this->click('link=Show Activity Graph');
    }
}
