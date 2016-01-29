<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('include/common.php');
require_once('include/pdo.php');
require_once('models/errorlog.php');

class ErrorLogTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testErrorLog()
    {
        $this->startCodeCoverage();

        $errorlog = new ErrorLog();
        $errorlog->Clean(7);
        $errorlog->BuildId = "foo";
        if ($errorlog->Insert()) {
            $this->fail("Insert() should return false when BuildId is non-numeric");
            return 1;
        }

        $errorlog->BuildId = 1;
        $errorlog->Description = "example error description";
        $errorlog->Insert();

        $this->pass("Passed");

        $this->stopCodeCoverage();

        return 0;
    }
}
