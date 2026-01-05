<?php

use App\Models\Build;

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//

require_once __DIR__ . '/cdash_test_case.php';

class ProjectWebPageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAccessToWebPageProjectTest(): void
    {
        $settings = [
            'Name' => 'BatchmakeExample',
            'Description' => "Project Batchmake's test for cdash testing"];
        $this->createProject($settings);

        $settings = [
            'Name' => 'InsightExample',
            'Description' => 'Project Insight test for cdash testing'];
        $this->createProject($settings);
    }

    public function testSubmissionBatchmakeBuild(): void
    {
        $rep = __DIR__ . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Build.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeConfigure(): void
    {
        $rep = __DIR__ . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Configure.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeNotes(): void
    {
        $rep = __DIR__ . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Notes.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeTest(): void
    {
        $rep = __DIR__ . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Test.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeUpdate(): void
    {
        $rep = __DIR__ . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Update.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightBuild(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Build.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightConfigure(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Configure.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightCoverage(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Coverage.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightCoverageLog(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_CoverageLog.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }

        // Testing if it actually worked
        $this->login();

        // Find buildid for coverage.
        $content = $this->connect($this->url . '/api/v1/index.php?project=InsightExample');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['coverages']) < 1) {
            $this->fail('No coverage build found when expected');
            return;
        }
        $buildid = $jsonobj['coverages'][0]['buildid'];

        // Verify coverage log.
        $this->assertTrue(Build::findOrFail((int) $buildid)->coverage()->count() > 0);

        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightDynamicAnalysis(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_DynamicAnalysis.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightNotes(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Notes.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightTest(): void
    {
        $rep = __DIR__ . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Test.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInDb(): void
    {
        // TODO: (williamjallen) This is a terrible test with hardcoded values.  The ID should be determined dynamically.
        $query = 'SELECT id, stamp, name, type, generator, command FROM build WHERE id=7';
        $result = $this->db->query($query);
        $expected = ['id' => '7',
            'stamp' => '20090223-0100-Nightly',
            'name' => 'Win32-MSVC2009',
            'type' => 'Nightly',
            'generator' => 'ctest2.6-patch 0',
            'command' => 'F:\PROGRA~1\MICROS~1.0\Common7\IDE\VCExpress.exe BatchMake.sln /build Release /project ALL_BUILD',
        ];
        $this->assertEqual($result[0], $expected);
    }

    public function testProjectExperimentalLinkBuildSummary(): void
    {
        $content = $this->connect($this->url . '/api/v1/index.php?project=BatchmakeExample');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['buildgroups']) < 1) {
            $this->fail('No build found when expected');
            return;
        }

        $buildgroup = array_pop($jsonobj['buildgroups']);
        $buildid = $buildgroup['builds'][0]['id'];
        $content = $this->connect($this->url . "/api/v1/buildSummary.php?buildid=$buildid");

        $expected = 'warning C4068: unknown pragma';
        if (!$content) {
            return;
        } elseif (!$this->findString($content, $expected)) {
            $this->assertTrue(false, 'The webpage does not match right the content exepected');
            return;
        }
        $this->assertTrue(true, 'The webpage match the content exepected');
    }
}
