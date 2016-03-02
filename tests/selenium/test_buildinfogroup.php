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

    public function testBuildInfoGroup()
    {
        $this->open($this->webPath . '/index.php?project=EmailProjectExample&date=2009-02-23');
        $this->click("//img[@alt='info']");
        sleep(1);
        $this->click("//img[@alt='info']");
    }
}
