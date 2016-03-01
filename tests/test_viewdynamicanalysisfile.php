<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ViewDynamicAnalysisFileTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewDynamicAnalysisFile()
    {
        $this->get($this->url . '/viewDynamicAnalysisFile.php?id=1');
        if (strpos($this->getBrowser()->getContentAsText(), 'Dynamic analysis started on') === false) {
            $this->fail("'Dynamic analysis started on' not found when expected");
            return 1;
        }
        $this->pass('Passed');
    }
}
