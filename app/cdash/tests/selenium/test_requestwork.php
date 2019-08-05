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

    public function testRequestWork()
    {
        $this->open($this->webPath . '/index.php');
        $this->click('link=Login');
        $this->waitForPageToLoad('30000');
        $this->type('login', 'simpletest@localhost');
        $this->type('passwd', 'simpletest');
        $this->click('sent');
        $this->waitForPageToLoad('30000');
        $this->open($this->webPath . '/manageClient.php?projectid=5');
        $this->addSelection('system_select', 'index=0');
        $this->click("//option[@value='1']");
        $this->addSelection('compiler_select', 'index=0');
        $this->click("//select[@id='compiler_select']/option");
        $this->addSelection('cmake_select', 'index=0');
        $this->addSelection('library_select', 'index=0');
        $this->click("//select[@id='library_select']/option");
        $this->addSelection('site_select', 'index=0');
        $this->click("//select[@id='site_select']/option");
        $this->click('submit');
        $this->waitForPageToLoad('30000');
    }
}
