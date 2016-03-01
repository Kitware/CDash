<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ViewIssuesTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewIssues()
    {
        $this->get($this->url . "/viewIssues.php");
        if (strpos($this->getBrowser()->getContentAsText(), "Dashboards") === false) {
            $this->fail("'Dashboards' not found when expected.");
            return 1;
        }
        $this->pass("Passed");
    }
}
