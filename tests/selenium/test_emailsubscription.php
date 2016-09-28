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

    public function testEmailSubscription()
    {
        $this->open($this->webPath . '/index.php');
        $this->click('link=Login');
        $this->waitForPageToLoad('30000');
        $this->type('login', 'simpletest@localhost');
        $this->type('passwd', 'simpletest');
        $this->click('sent');
        $this->waitForPageToLoad('30000');

        $this->click('//tr[4]/td[2]/a[1]/img');
        // the 'Edit subscription' link for 'EmailProjectExample'

        $this->sleepWaitingForElement("//div[@id='wizard']/ul/li[2]/a/span");
        // tab 2, the 'Logo' tab
        $this->click("//div[@id='wizard']/ul/li[2]/a/span");
        $this->click("//div[@id='wizard']/ul/li[3]/a/span");
        $this->click("//div[@id='wizard']/ul/li[4]/a/span");
        $this->click("//div[@id='wizard']/ul/li[5]/a/span");
        $this->click('updatesubscription');

        // Completely unsubscribe from the next project down, 'InsightExample'
        $this->sleepWaitingForElement('//tr[5]/td[2]/a[1]/img');
        $this->click('//tr[5]/td[2]/a[1]/img');

        $this->sleepWaitingForElement('unsubscribe');
        $this->click('unsubscribe');

        $this->assertTrue((bool)preg_match('/^Are you sure you want to unsubscribe[\s\S]$/', $this->getConfirmation()));
    }
}
