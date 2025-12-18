<?php

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once __DIR__ . '/cdash_test_case.php';

class ViewConfigureTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewConfigure()
    {
        $this->login();
        $this->get($this->url . '/api/v1/viewConfigure.php?buildid=1');
        if (!str_contains($this->getBrowser()->getContentAsText(), 'Win32-VCExpress')) {
            $this->fail("'Win32-VCExpress' not found when expected.");
            return 1;
        }
        $this->pass('Passed');
    }
}
