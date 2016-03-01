<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_test_case.php');

class iPhoneTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testiPhone()
    {
        // We now redirect from 'iphone' to 'mobile'.
        // Make sure both URLs work properly.
        $dirs = array('mobile', 'iphone');
        foreach ($dirs as $dir) {
            $this->get($this->url . "/$dir/index.php");
            if (strpos($this->getBrowser()->getContentAsText(), "BatchmakeExample") === false) {
                $this->fail("'BatchmakeExample' not found when expected");
                return 1;
            }
            $this->get($this->url . "/$dir/project.php?project=BatchmakeExample");
            if (strpos($this->getBrowser()->getContentAsText(), "Continuous") === false) {
                $this->fail("'Continuous' not found when expected");
                return 1;
            }
            $this->get($this->url . "/$dir/user.php");
            if (strpos($this->getBrowser()->getContentAsText(), "Wrong login or password") === false) {
                $this->fail("'Wrong login or password' not found when expected");
                return 1;
            }
            $this->get($this->url . "/$dir/buildsummary.php?buildid=1");
            if (strpos($this->getBrowser()->getContentAsText(), "Number of Updates") === false) {
                $this->fail("'Number of Updates' not found when expected");
                return 1;
            }
        }
        $this->pass("Passed");
    }
}
