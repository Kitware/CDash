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

    public function testFindUsers()
    {
        $this->open($this->webPath . '/index.php');
        $this->click('link=Login');
        $this->waitForPageToLoad('30000');
        $this->type('login', 'simpletest@localhost');
        $this->type('passwd', 'simpletest');
        $this->click('sent');
        $this->waitForPageToLoad('30000');
        $this->click('link=Manage users');
        $this->waitForPageToLoad('30000');
        $this->type('search', 'simple');
        $this->keyUp('search', 'e');
        sleep(1);
        $this->click('makeadmin');
        $this->waitForPageToLoad('30000');
        $this->type('search', 'simpl');
        $this->keyUp('search', 'l');
        sleep(1);
        $this->click('makenormaluser');
        $this->waitForPageToLoad('30000');
    }
}
