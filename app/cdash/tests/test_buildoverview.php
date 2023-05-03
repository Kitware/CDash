<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class BuildOverviewTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildOverview()
    {
        $this->login();
        $this->get($this->url . '/buildOverview.php');
        if (strpos($this->getBrowser()->getContentAsText(), 'Project not specified') === false) {
            $this->fail("'Project not specified' not found when expected");
            return 1;
        }
        $this->get($this->url . '/buildOverview.php?project=InsightExample');
        if (strpos($this->getBrowser()->getContentAsText(), 'Build summary') === false) {
            $this->fail("'Build summary' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }
}
