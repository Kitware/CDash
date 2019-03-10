<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class EmailTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSimple()
    {
        $this->login();
        $content = $this->connect($this->url . '/api/v1/index.php?project=InsightExample');
        if (!$content) {
            return;
        }
        $this->assertText('CDash-CTest-simple');
    }
}
