<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

class ProjectWebPageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAccessToWebPageProjectTest()
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

    public function testSubmissionBatchmakeBuild()
    {
        $rep = dirname(__FILE__) . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Build.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeConfigure()
    {
        $rep = dirname(__FILE__) . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Configure.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeNotes()
    {
        $rep = dirname(__FILE__) . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Notes.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeTest()
    {
        $rep = dirname(__FILE__) . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Test.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionBatchmakeUpdate()
    {
        $rep = dirname(__FILE__) . '/data/BatchmakeNightlyExample';
        $file = "$rep/BatchMake_Nightly_Update.xml";
        $this->assertTrue($this->submission('BatchmakeExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightBuild()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Build.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightConfigure()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Configure.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightCoverage()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Coverage.xml";
        $this->assertTrue($this->submission('InsightExample', $file), "Submission of $file failed");
    }

    public function testSubmissionInsightCoverageLog()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
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
        $content = $this->connect($this->url . '/ajax/getviewcoverage.php?sEcho=1&iColumns=6&sColumns=&iDisplayStart=0&iDisplayLength=25&mDataProp_0=0&mDataProp_1=1&mDataProp_2=2&mDataProp_3=3&mDataProp_4=4&mDataProp_5=5&sSearch=&bRegex=false&sSearch_0=&bRegex_0=false&bSearchable_0=true&sSearch_1=&bRegex_1=false&bSearchable_1=true&sSearch_2=&bRegex_2=false&bSearchable_2=true&sSearch_3=&bRegex_3=false&bSearchable_3=true&sSearch_4=&bRegex_4=false&bSearchable_4=true&sSearch_5=&bRegex_5=false&bSearchable_5=true&iSortCol_0=2&sSortDir_0=asc&iSortingCols=1&bSortable_0=true&bSortable_1=true&bSortable_2=true&bSortable_3=true&bSortable_4=true&bSortable_5=true&buildid=' . $buildid . '&status=4&nlow=2&nmedium=3&nsatisfactory=43&ncomplete=32&metricerror=0.49&metricpass=0.7&userid=1&displaylabels=0');

        $jsonobj = json_decode($content, true);
        // Find specific fileid in response.
        $url = null;
        foreach ($jsonobj['aaData'] as $row) {
            if (strpos($row[0], 'itkCannyEdgesDistanceAdvectionFieldFeatureGenerator.h') !== false) {
                $url = substr($row[0], 9, 43);
            }
        }
        if ($url === null) {
            $this->fail("Failed to find specific coverage file");
        }
        $url = str_replace('&#38;', '&', $url);
        $content = $this->connect($this->url . '/' . $url);
        $expected = '<span class="normal">    1 | #ifndef __itkNormalVectorDiffusionFunction_txx</span><br><span class="warning">   18</span><span class="normal">    2 | #define __itkNormalVectorDiffusionFunction_txx</span>';

        if (!$this->findString($content, $expected)) {
            $this->fail('Coverage log is wrong');
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightDynamicAnalysis()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_DynamicAnalysis.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightNotes()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Notes.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightTest()
    {
        $url = $this->url . '/submit.php?project=InsightExample';
        $rep = dirname(__FILE__) . '/data/InsightExperimentalExample';
        $file = "$rep/Insight_Experimental_Test.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInDb()
    {
        // TODO: (williamjallen) This is a terrible test with hardcoded values.  The ID should be determined dynamically.
        $query = 'SELECT id, stamp, name, type, generator, command FROM build WHERE id=7';
        $result = $this->db->query($query);
        $expected = ['id' => '7',
            'stamp' => '20090223-0100-Nightly',
            'name' => 'Win32-MSVC2009',
            'type' => 'Nightly',
            'generator' => 'ctest2.6-patch 0',
            'command' => 'F:\PROGRA~1\MICROS~1.0\Common7\IDE\VCExpress.exe BatchMake.sln /build Release /project ALL_BUILD'
        ];
        $this->assertEqual($result[0], $expected);
    }

    public function testProjectExperimentalLinkMachineName()
    {
        $content = $this->connect($this->url . '/api/v1/index.php?project=BatchmakeExample');
        $jsonobj = json_decode($content, true);
        if (count($jsonobj['buildgroups']) < 1) {
            $this->fail('No build found when expected');
            return;
        }
        $buildgroup = array_pop($jsonobj['buildgroups']);
        $siteid = $buildgroup['builds'][0]['siteid'];

        $content = $this->connect($this->url . "/viewSite.php?siteid=$siteid&project=4&currenttime=1235354400");
        if (!$content) {
            return;
        } elseif (!$this->findString($content, '<b>Total Physical Memory: </b>15MiB<br />')) {
            $this->assertTrue(false, 'The webpage does not match the expected content');
            return;
        }
        $this->assertTrue(true, 'The webpage matches the expected content');
    }

    public function testProjectExperimentalLinkBuildSummary()
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
