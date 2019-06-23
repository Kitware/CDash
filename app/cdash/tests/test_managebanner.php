<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ManageBannerTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testManageBannerTest()
    {
        if (!$this->expectsPageRequiresLogin('/manageBanner.php')) {
            return 1;
        }

        //make sure we can visit the page while logged in
        $this->login();
        $content = $this->get($this->url . '/manageBanner.php');
        if (strpos($content, 'Banner Message') === false) {
            $this->fail("'Banner Message' not found when expected");
            return 1;
        }

        //change the banner
        if (!$this->SetFieldByName('message', 'this is a new banner')) {
            $this->fail('SetFieldByName on banner message returned false');
            return 1;
        }
        $this->clickSubmitByName('updateMessage');

        //make sure the banner changed
        $content = $this->get($this->url . '/api/v1/index.php?project=InsightExample');
        if (strpos($content, 'this is a new banner') === false) {
            $this->fail('New banner message not found on dashboard');
            return 1;
        }

        //change it back
        $content = $this->get($this->url . '/manageBanner.php');
        $this->SetFieldByName('message', '');
        $this->clickSubmitByName('updateMessage');

        //make sure it changed back
        $content = $this->connect($this->url . '/api/v1/index.php');
        if (strpos($content, 'this is a new banner') !== false) {
            $this->fail('New banner message still on dashboard after it should have been removed');
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
