<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_test_case.php');

class RemoveBuildsTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testRemoveBuilds()
    {
        $this->login();
        $this->get($this->url . "/removeBuilds.php?projectid=5");
        $this->clickSubmitByName("Submit");
        if (strpos($this->getBrowser()->getContentAsText(), "Removed") === false) {
            $this->fail("'Removed' not found when expected");
            return 1;
        }
        $this->pass("Passed");
    }
}
