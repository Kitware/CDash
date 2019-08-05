<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ViewMapTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewMap()
    {
        $this->get($this->url . '/viewMap.php?project=InsightExample');
        if (strpos($this->getBrowser()->getContentAsText(), 'Maintainer') === false) {
            $this->fail("'Maintainer' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }
}
