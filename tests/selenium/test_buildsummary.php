<?php
//
// After including cdash_selenium_test_base.php, subsequent require_once calls
// are relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_selenium_test_case.php');

class Example extends CDashSeleniumTestCase
{
    protected function setUp()
    {
        $this->browserSetUp();
    }

    public function testBuildSummary()
    {
        $this->open($this->webPath . "/index.php");
        $this->click("link=Login");
        $this->waitForPageToLoad("30000");
        $this->type("login", "simpletest@localhost");
        $this->type("passwd", "simpletest");
        $this->click("sent");
        $this->waitForPageToLoad("30000");
        $this->open($this->webPath . "/index.php?project=EmailProjectExample&date=2009-02-23");
        $this->waitForPageToLoad("30000");
        $this->click("link=Win32-MSVC2009");
        $this->waitForPageToLoad("30000");
        $this->click("link=Show Build History");
        $this->click("link=Show Build History");
        $this->click("link=Show Build Graphs");
        $this->click("link=Zoom out");
        $this->click("link=Show Build Graphs");
        $this->click("link=Add a Note to this Build");
        for ($second = 0; ; $second++) {
            if ($second >= 60) {
                $this->fail("timeout");
            }
            try {
                if ($this->isElementPresent("TextNote")) {
                    break;
                }
            } catch (Exception $e) {
            }
            sleep(1);
        }
        $this->type("TextNote", "just a simple note");
        $this->click("AddNote");
        $this->waitForPageToLoad("30000");
    }
}
