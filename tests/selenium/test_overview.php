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

    public function testOverview()
    {
        $this->open($this->webPath . "/index.php");
        $this->click("link=Login");
        $this->waitForPageToLoad("30000");
        $this->type("login", "simpletest@localhost");
        $this->type("passwd", "simpletest");
        $this->click("sent");
        $this->waitForPageToLoad("30000");

        $this->click("link=InsightExample");
        $this->waitForPageToLoad("30000");

        $this->click("css=#admin > ul > li.endsubmenu > a");
        $this->waitForPageToLoad("30000");

        $this->select("id=newBuildColumn", "label=Experimental");
        $this->click("id=addBuildColumn");
        $this->click("id=saveLayout");
        $this->click("link=Go to overview");
        $this->waitForPageToLoad("30000");

        $this->assertEquals("14.29%", $this->getText("//table[2]//tr[1]//td[2]"));
    }
}
