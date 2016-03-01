<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class QueryTestsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testQueryTests()
    {
        $this->get($this->url . "/queryTests.php");
        if (strpos($this->getBrowser()->getContentAsText(), "matches") === false) {
            $this->fail("'matches' not found when expected");
            return 1;
        }
        $this->pass("Passed");
    }
}
