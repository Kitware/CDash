<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('include/common.php');
require_once('include/pdo.php');
require_once('models/build.php');
require_once('models/builderror.php');

class BuildModelTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testBuildModel()
    {
        $this->startCodeCoverage();

        $build = new Build();
        $builderror = new BuildError();
        $builderror->Type = 0;
        $builderror->Text = 'error';
        $buildwarning = new BuildError();
        $buildwarning->Type = 1;
        $buildwarning->Text = 'warning';

        if ($build->GetName() !== false) {
            $this->fail("GetName didn't return false for empty build id");
            return 1;
        }

        if ($build->GetLabels() !== false) {
            $this->fail("GetLabels didn't return false for empty build id");
            return 1;
        }

        if ($build->GetGroup() !== false) {
            $this->fail("GetGroup didn't return false for empty build id");
            return 1;
        }

        if ($build->GetNumberOfErrors() !== false) {
            $this->fail("GetNumberOfErrors didn't return false for empty build id");
            return 1;
        }

        if ($build->GetNumberOfWarnings() !== false) {
            $this->fail("GetNumberOfWarnings didn't return false for empty build id");
            return 1;
        }

        if ($build->SetSubProject('1234') !== false) {
            $this->fail("SetSubProject didn't return false for empty project id");
            return 1;
        }

        if ($build->GetSubProjectName() !== false) {
            $this->fail("GetSubProjectName didn't return false for empty build id");
            return 1;
        }

        if ($build->GetErrorDifferences() !== false) {
            $this->fail("GetErrorDifferences didn't return false for empty build id");
            return 1;
        }

        if ($build->ComputeUpdateStatistics() !== false) {
            $this->fail("ComputeUpdateStatistics didn't return false for empty build id");
            return 1;
        }

        if ($build->ComputeDifferences() !== false) {
            $this->fail("ComputeDifferences didn't return false for empty build id");
            return 1;
        }

        if ($build->ComputeConfigureDifferences() !== false) {
            $this->fail("ComputeConfigureDifferences didn't return false for empty build id");
            return 1;
        }

        if ($build->ComputeTestTiming() !== false) {
            $this->fail("ComputeTestTiming didn't return false for empty build id");
            return 1;
        }

        if ($build->InsertLabelAssociations() !== false) {
            $this->fail("InsertLabelAssocations didn't return false for empty build id");
            return 1;
        }

        if ($build->UpdateEndTime('2010-08-07') !== false) {
            $this->fail("UpdateEndTime didn't return false for empty build id");
            return 1;
        }

        if ($build->SaveTotalTestsTime('100') !== false) {
            $this->fail("SaveTotalTestsTime didn't return false for empty build id");
            return 1;
        }

        $build->Id = '1';

        if ($build->ComputeTestTiming() !== false) {
            $this->fail("ComputeTestTiming didn't return false for empty project id");
            return 1;
        }

        if ($build->ComputeUpdateStatistics() !== false) {
            $this->fail("ComputeUpdateStatistics didn't return false for empty project id");
            return 1;
        }

        $build->ProjectId = '2';
        $build->SiteId = '1';
        $build->SetSubProject('8567');
        global $CDASH_LOG_FILE;
        if ($CDASH_LOG_FILE !== false && strpos(file_get_contents($this->logfilename),
              "New subproject detected") === false) {
            $this->fail("'New subproject detected' not found in log after calling SetSubProject for invalid subproject id");
            return 1;
        }

        if ($build->Exists() == false) {
            $this->fail("Exists returned false for a valid build id");
            return 1;
        }

        $build->Id = '98765';
        $build->SetStamp('20100610-1901-Experimental');
        $build->Type = ''; //force this empty for coverage purposes

    $build->StartTime = '2009-12-18 14:19:11';
        $build->EndTime = '2009-12-18 14:20:23';
        $build->SubmitTime = '2012-01-25 16:43:11';

        if ($build->Exists() == true) {
            $this->fail("Exists returned true for an invalid build id");
            return 1;
        }

        $build->Save();
        $build->Append = true;
        $build->InsertErrors = true;
        $build->AddError($builderror);
        $build->AddError($buildwarning);
        $build->Save();

        $this->stopCodeCoverage();

        return 0;
    }
}
