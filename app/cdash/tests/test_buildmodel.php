<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';




use CDash\Model\Build;
use CDash\Model\BuildError;

class BuildModelTestCase extends KWWebTestCase
{
    private $testDataFiles;
    private $testDataDir;
    private $builds;
    private $parentBuilds;

    public function __construct()
    {
        parent::__construct();

        $this->deleteLog($this->logfilename);

        $this->testDataDir = dirname(__FILE__) . '/data/BuildModel';
        $this->testDataFiles = array('build1.xml', 'build2.xml', 'build3.xml', 'build4.xml',
                                     'build5.xml', 'configure1.xml');

        pdo_query("INSERT INTO project (name) VALUES ('BuildModel')");

        foreach ($this->testDataFiles as $testDataFile) {
            if (!$this->submission('BuildModel', $this->testDataDir . '/' . $testDataFile)) {
                $this->fail('Failed to submit ' . $testDataFile);
                return 1;
            }
        }

        $this->builds = array();
        $builds = pdo_query("SELECT * FROM build WHERE name = 'buildmodel-test-build' ORDER BY id");
        while ($build = pdo_fetch_array($builds)) {
            $this->builds[] = $build;
        }

        $this->parentBuilds = array();
        $parentBuilds = pdo_query("SELECT * FROM build WHERE name = 'buildmodel-test-parent-build' AND parentid = -1 ORDER BY id");
        while ($build = pdo_fetch_array($parentBuilds)) {
            $this->parentBuilds[] = $build;
        }
    }

    public function __destruct()
    {
        foreach ($this->builds as $build) {
            remove_build($build['id']);
        }
    }

    public function getBuildModel($n, $builds=false)
    {
        $this->deleteLog($this->logfilename);
        if ($builds === false) {
            $builds = $this->builds;
        }

        $buildArray = $builds[$n];
        $build = new build();
        $build->Id = $buildArray['id'];
        $build->FillFromId($buildArray['id']);
        return $build;
    }

    public function testBuildModel()
    {
        $this->deleteLog($this->logfilename);

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

        if ($this->config('CDASH_LOG_FILE ')!== false && strpos(file_get_contents($this->logfilename),
            'New subproject detected') === false
        ) {
            $this->fail("'New subproject detected' not found in log after calling SetSubProject for invalid subproject id");
            return 1;
        }

        if ($build->Exists() == false) {
            $this->fail('Exists returned false for a valid build id');
            return 1;
        }

        $build->Id = null;
        $build->SetStamp('20100610-1901-Experimental');
        $build->Type = ''; //force this empty for coverage purposes

        $build->StartTime = '2009-12-18 14:19:11';
        $build->EndTime = '2009-12-18 14:20:23';
        $build->SubmitTime = '2012-01-25 16:43:11';

        if ($build->Exists() == true) {
            $this->fail('Exists returned true for an invalid build id');
            return 1;
        }

        $build->Save();
        $build->Append = true;
        $build->InsertErrors = true;
        $build->AddError($builderror);
        $build->AddError($buildwarning);
        $build->Save();

        return 0;
    }

    public function testBuildModelGetsFailures()
    {
        $build = $this->getBuildModel(0);

        $errorFilter = ['type' => Build::TYPE_ERROR];
        $warnFilter = ['type' => Build::TYPE_WARN];

        // One warning and one error (errors = 0, warnings = 1)
        // Uses some bogus projectid (10)
        $buildFailures = $build->GetFailures($errorFilter);
        $this->assertTrue(count($buildFailures) === 1);

        $buildFailures = $build->GetFailures($warnFilter);
        $this->assertTrue(count($buildFailures) === 1);
    }

    public function testBuildModelGetsBuildFailuresAcrossChildBuilds()
    {
        $build = $this->getBuildModel(0, $this->parentBuilds);

        $buildFailures = $build->GetFailures(['type' => Build::TYPE_WARN]);
        $this->assertTrue(count($buildFailures) === 1);

        $this->assertTrue($buildFailures[0]['subprojectname'] == 'some-test-subproject');
    }

    public function testBuildModelGetsResolvedBuildFailures()
    {
        // Make sure the first build returns no resolved build failures since it can't have any
        $build = $this->getBuildModel(0);
        $this->assertTrue($build->GetResolvedBuildFailures(0)->fetchAll() === array());

        // The second build resolves the errored failure, but not the warning failure
        $build = $this->getBuildModel(1);
        $this->assertTrue(count($build->GetResolvedBuildFailures(0)->fetchAll()) === 1);
        $this->assertTrue($build->GetResolvedBuildFailures(1)->fetchAll() === array());

        // The third build has a resolved failure of type warning
        $build = $this->getBuildModel(2);
        $this->assertTrue(count($build->GetResolvedBuildFailures(0)->fetchAll()) === 0);
        $this->assertTrue(count($build->GetResolvedBuildFailures(1)->fetchAll()) === 1);
    }

    public function testBuildModelGetErrors()
    {
        $errorFilter = ['type' => Build::TYPE_ERROR];
        $warnFilter = ['type' => Build::TYPE_WARN];

        // First build has no errors
        $build = $this->getBuildModel(0);
        $this->assertTrue(count($build->GetErrors($errorFilter)) === 0);
        $this->assertTrue(count($build->GetErrors($warnFilter)) === 0);

        // Second build has a single error of type 0
        $build = $this->getBuildModel(1);
        $this->assertTrue(count($build->GetErrors($errorFilter)) === 1);
        $this->assertTrue(count($build->GetErrors($warnFilter)) === 0);

        // Third build has no errors
        $build = $this->getBuildModel(2);
        $this->assertTrue(count($build->GetErrors($errorFilter)) === 0);
        $this->assertTrue(count($build->GetErrors($warnFilter)) === 0);
    }

    public function testBuildModelGetsBuildErrorsAcrossChildBuilds()
    {
        $build = $this->getBuildModel(0, $this->parentBuilds);

        $buildErrors = $build->GetErrors(['type' => Build::TYPE_ERROR]);
        $this->assertTrue(count($buildErrors) === 1);

        $this->assertTrue($buildErrors[0]['subprojectname'] == 'some-test-subproject');
    }

    public function testBuildModelGetsResolvedBuildErrors()
    {
        // Make sure the first build returns no resolved build errors since it can't have any
        $build = $this->getBuildModel(0);
        $this->assertTrue(count($build->GetResolvedBuildErrors(0)->fetchAll()) === 0);
        $this->assertTrue(count($build->GetResolvedBuildErrors(1)->fetchAll()) === 0);

        // Third build has a single resolved error of type 0
        $build = $this->getBuildModel(2);
        $this->assertTrue(count($build->GetResolvedBuildErrors(0)->fetchAll()) === 1);
        $this->assertTrue(count($build->GetResolvedBuildErrors(1)->fetchAll()) === 0);
    }

    public function testBuildModelGetsConfigures()
    {
        $build = $this->getBuildModel(0);
        $this->assertTrue(count($build->GetConfigures()->fetchAll()) === 1);

        // Test configures work across child builds
    }

    public function testBuildModelAddBuild()
    {
        $build = new Build();
        $this->assertTrue($build->AddBuild());
        $this->assertTrue($build->Id > 0);

        $build2 = new Build();
        $this->assertFalse($build2->AddBuild());

        remove_build($build->Id);
    }
}
