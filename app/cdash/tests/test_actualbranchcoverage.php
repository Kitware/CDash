<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/test_branchcoverage.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

use CDash\Model\Build;
use CDash\Model\PendingSubmissions;

class ActualBranchCoverageTestCase extends BranchCoverageTestCase
{
    public function __construct()
    {
        parent::__construct();
        // We submit to the TrilinosDriver project just because it
        // already has labels enabled.
        $this->projectname = 'TrilinosDriver';
        $this->buildid = 0;
    }

    public function testBranchCoverage()
    {
        $this->clearPriorResults();
        $this->postSubmit();
        $this->putSubmit();
        $this->verifyResults();
    }
}
