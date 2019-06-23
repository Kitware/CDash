<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\BuildTestDiff;

class BuildTestDiffTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildTestDiff()
    {
        $buildtestdiff = new BuildTestDiff();

        $buildtestdiff->BuildId = 0;
        $buildtestdiff->Type = 0;
        ob_start();
        $result = $buildtestdiff->Insert();
        $output = ob_get_contents();
        ob_end_clean();
        if ($result) {
            $this->fail('Insert() should return false when BuildId is 0');
            return 1;
        }
        if (strpos($output, 'BuildTestDiff::Insert(): BuildId is not set') === false) {
            $this->fail("'BuildId is not set' not found from Insert()");
            return 1;
        }

        $buildtestdiff->BuildId = 1;
        $buildtestdiff->Type = 0;
        if ($buildtestdiff->Insert()) {
            $this->fail("Add() #1 returned true when it should be false.\n");
            return 1;
        }

        $buildtestdiff->DifferenceNegative = 0;
        if ($buildtestdiff->Insert()) {
            $this->fail("Add() #2 returned true when it should be false.\n");
            return 1;
        }

        $buildtestdiff->DifferencePositive = 0;
        //call save twice to cover different execution paths
        if (!$buildtestdiff->Insert()) {
            $this->fail("Add() #3 returned false when it should be true.\n");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
