<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/test_branchcoverage.php';




class ActualBranchCoverageTestCase extends BranchCoverageTestCase
{
    protected $projectname;

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
        $this->clearPriorBranchCoverageResults();
        $this->postSubmit();
        $this->putSubmit();
        $this->verifyResults();
    }
}
