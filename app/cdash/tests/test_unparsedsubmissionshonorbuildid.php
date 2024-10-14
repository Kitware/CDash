<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'tests/test_branchcoverage.php';

class UnparsedSubmissionsHonorBuildIdTestCase extends BranchCoverageTestCase
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

    public function testBranchCoverage(): void
    {
        $this->clearPriorBranchCoverageResults();

        // Create a build for this file.
        $this->postSubmit(null, '20150128-1436-Experimental');
        // Manually insert a buildfile record for this build.
        $old_buildid = $this->buildid;
        DB::insert("INSERT INTO buildfile (buildid, filename, md5, type)
            VALUES ('{$old_buildid}', 'gcov.tar', '5454e16948a1d58d897e174b75cc5633', 'GcovTar')");

        // Make another submission with the same file but a different stamp
        // and verify that this latter submission gets parsed successfully.

        $this->postSubmit(null, '20150128-1437-Experimental');
        $this->putSubmit();
        $this->verifyResults();

        DB::delete("DELETE FROM buildfile WHERE buildid='{$old_buildid}'");
        remove_build($old_buildid);
    }
}
