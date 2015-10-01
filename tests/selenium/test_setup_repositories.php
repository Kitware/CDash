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

    public function testSetupRepositories()
    {
        $this->open($this->webPath."/index.php");
        $this->click("link=Login");
        $this->waitForPageToLoad("30000");
        $this->type("login", "simpletest@localhost");
        $this->type("passwd", "simpletest");
        $this->click("sent");
        $this->waitForPageToLoad("30000");
        $this->click("//tr[5]/td[2]/a[4]/img");
        $this->waitForPageToLoad("30000");
        $this->click("//div[@id='wizard']/ul/li[3]/a/span");
        $this->type("cvsRepository[0]", ":pserver:anoncvs@itk.org:/cvsroot/Insight");
        $this->type("cvsUsername[0]", "anoncvs");
        $this->click("Update");
        $this->waitForPageToLoad("30000");
        $this->click("//div[@id='wizard']/ul/li[7]/a/span");
        $this->type("ctestTemplateScript", "client testing works");
        $this->click("Update");
        $this->waitForPageToLoad("30000");
        $this->select("projectSelection", "label=PublicDashboard");
        $this->waitForPageToLoad("30000");
        $this->click("//div[@id='wizard']/ul/li[3]/a/span");
        $this->type("cvsRepository[0]", "git://cmake.org/cmake.git");
        $this->select("cvsviewertype", "label=GitWeb");
        $this->type("cvsURL", "cmake.org/gitweb?p=cmake.git");
        $this->click("Update");
        $this->waitForPageToLoad("30000");
        $this->select("projectSelection", "label=EmailProjectExample");
        $this->setSpeed("500");
        $this->click("//div[@id='wizard']/ul/li[3]/a/span");
        $this->type("cvsRepository[0]", "https://www.kitware.com/svn/CDash/trunk");
        $this->type("cvsURL", "https://www.kitware.com/svn/CDash/trunk");
        $this->select("cvsviewertype", "label=WebSVN");
        $this->click("Update");
    }
}
