<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__) . '/cdash_test_case.php');

class ViewChangesTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testViewChanges()
    {
        $content = $this->connect($this->url . "/viewChanges.php?project=TestCompressionExample");
        if ($content == false) {
            return;
        }
        $this->assertText('Nightly Changes');
    }
}
