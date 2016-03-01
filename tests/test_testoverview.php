<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_test_case.php');

class TestOverviewTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testTestOverview()
    {
        $this->login();
        $this->get($this->url . "/testOverview.php");
        if (strpos($this->getBrowser()->getContentAsText(), " project not found") === false) {
            $this->fail("' project not found' not found when expected");
            return 1;
        }
        $this->get($this->url . "/testOverview.php?project=InsightExample");
        if (strpos($this->getBrowser()->getContentAsText(), "No failing tests for this date") === false) {
            $this->fail("'No failing tests for this date' not found when expected");
            return 1;
        }
        $this->pass("Passed");
    }
}
