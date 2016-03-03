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
        $content = $this->get($this->url . '/displayImage.php');
        if (strpos($content, 'Not a valid imgid!') === false) {
            $this->fail("'Not a valid imgid!' not found on displayImage.php");
        }
        if (!$this->get($this->url . '/displayImage.php?imgid=1')) {
            $this->fail('display image failed');
            return 1;
        }
        $this->pass('Passed');
    }
}
