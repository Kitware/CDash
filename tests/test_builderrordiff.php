<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';
require_once 'include/pdo.php';
require_once 'models/builderrordiff.php';

class BuildErrorDiffTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildErrorDiff()
    {
        $builderrordiff = new BuildErrorDiff();

        //no buildid
        $builderrordiff->BuildId = 0;
        ob_start();
        $builderrordiff->Save();
        $output = ob_get_contents();
        ob_end_clean();
        if (strpos($output, 'BuildErrorDiff::Save(): BuildId not set') === false) {
            $this->fail("'BuildId not set' not found from Save()");
            return 1;
        }

        $builderrordiff->BuildId = 1;
        $builderrordiff->Type = 1;

        //call save twice to cover different execution paths
        if ($builderrordiff->Save()) {
            $this->fail("Save() call #1 returned true when it should be false.\n");
            return 1;
        }

        $builderrordiff->DifferencePositive = 1;
        if ($builderrordiff->Save()) {
            $this->fail("Save() call #2 returned true when it should be false.\n");
            return 1;
        }

        $builderrordiff->DifferenceNegative = 1;
        if (!$builderrordiff->Save()) {
            $this->fail("Save() call #3 returned false when it should be true.\n");
            return 1;
        }
        if (!$builderrordiff->Save()) {
            $this->fail("Save() call #4 returned false when it should be true.\n");
            return 1;
        }

        $this->pass('Passed');
        return 0;
    }
}
