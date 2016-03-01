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
        $this->open($this->webPath . "/index.php");
        $this->click("link=Login");
        $this->waitForPageToLoad("30000");
        $this->type("login", "simpletest@localhost");
        $this->type("passwd", "simpletest");
        $this->click("sent");
        $this->waitForPageToLoad("30000");

        $this->click("//tr[4]/td[2]/a[4]/img");
        // the 'Edit project' link for 'EmailProjectExample'
        // "//tr[4]" is the EmailProjectExample table row (4th row, 2 rows of header-ish rows...)
        // "//tr[4]/td[2]" is the Actions column in that row
        // "//tr[4]/td[2]/a[4]/img" is the 4th image from the left in that column

        $this->waitForPageToLoad("30000");
        $this->click("//div[@id='wizard']/ul/li[5]/a/span");
        // tab 5, the 'E-mail' tab
        $this->click("emailBrokenSubmission");
        $this->click("emailRedundantFailures");
        $this->click("emailAdministrator");
        $this->click("emailLowCoverage");
        $this->click("emailTestTimingChanged");
        $this->click("Update");
        $this->click("link=My CDash");
        $this->waitForPageToLoad("30000");

        $this->click("//tr[4]/td[2]/a[1]/img");
        // the 'Edit subscription' link for 'EmailProjectExample'

        $this->sleepWaitingForElement("//div[@id='wizard']/ul/li[2]/a/span");
        // tab 2, the 'Logo' tab
        $this->click("//div[@id='wizard']/ul/li[2]/a/span");
        $this->click("//div[@id='wizard']/ul/li[3]/a/span");
        $this->click("//div[@id='wizard']/ul/li[4]/a/span");
        $this->click("//div[@id='wizard']/ul/li[5]/a/span");
        $this->click("updatesubscription");

        // Completely unsubscribe from the next project down, 'InsightExample'
        $this->sleepWaitingForElement("//tr[5]/td[2]/a[1]/img");
        $this->click("//tr[5]/td[2]/a[1]/img");

        $this->sleepWaitingForElement("unsubscribe");
        $this->click("unsubscribe");

        $this->assertTrue((bool)preg_match('/^Are you sure you want to unsubscribe[\s\S]$/', $this->getConfirmation()));
    }
}
