<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

class SVNInfoTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function testSVNInfo()
    {
        $this->login();
        $this->get($this->url . '/gitinfo.php');
        if (strpos($this->getBrowser()->getContentAsText(), 'git version') === false) {
            $this->fail("'git version' not found when expected.");
            return 1;
        }
        $this->pass('Passed');
    }
}
