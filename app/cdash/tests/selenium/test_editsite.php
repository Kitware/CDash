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

    public function testEditSite()
    {
        $this->open($this->webPath . '/index.php');
        $this->click('link=Login');
        $this->waitForPageToLoad('30000');
        $this->type('login', 'simpletest@localhost');
        $this->type('passwd', 'simpletest');
        $this->click('sent');
        $this->waitForPageToLoad('30000');
        $this->click('link=InsightExample');
        $this->waitForPageToLoad('30000');
        $this->click('link=CDashTestingSite');
        $this->waitForPageToLoad('30000');
        $this->click('link=exact:Are you maintaining this site? [claim this site]');
        $this->waitForPageToLoad('30000');
        $this->click('claimsite');
        $this->waitForPageToLoad('30000');
        $this->type('site_ip', '66.194.253.20');
        $this->click('geolocation');
        $this->waitForPageToLoad('30000');
        $this->type('site_description', 'test description');
        $this->click('newdescription_revision');
        $this->click('updatesite');
        $this->waitForPageToLoad('30000');
        $this->click('unclaimsite');
        $this->waitForPageToLoad('30000');
    }
}
