<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class DisplayImageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testDisplayImage()
    {
        if ($this->get($this->url . '/image')) {
            $this->fail("Did not fail when accessing /image");
        }
        if (!$this->get($this->url . '/image/1')) {
            $this->fail('display image failed');
            return 1;
        }
        $this->pass('Passed');
    }
}
