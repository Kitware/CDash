<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ProjectWebPageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAccessToWebPageProjectTest()
    {
        $this->login();
    // first project necessary for testing
    $name = 'BatchmakeExample';
        $description = 'Project Batchmake\'s test for cdash testing';
        $this->createProject($name, $description);
        $this->get($this->url.'/user.php'); // comes back to the my user page
    $name = 'InsightExample';
        $description = 'Project Insight test for cdash testing';
        $this->createProject($name, $description);
        $content = $this->connect($this->url.'/index.php?project=BatchmakeExample');
        if (!$content) {
            return;
        }
        $this->assertText('BatchmakeExample Dashboard');
    }

    public function testSubmissionBatchmakeBuild()
    {
        $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
        $file = "$rep/BatchMake_Nightly_Build.xml";
        if (!$this->submission('BatchmakeExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionBatchmakeConfigure()
    {
        $rep  = dirname(__FILE__)."/data/BatchmakeNightlyExample";
        $file = "$rep/BatchMake_Nightly_Configure.xml";
        if (!$this->submission('BatchmakeExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }


    public function testSubmissionBatchmakeNotes()
    {
        $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
        $file = "$rep/BatchMake_Nightly_Notes.xml";
        if (!$this->submission('BatchmakeExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionBatchmakeTest()
    {
        $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
        $file = "$rep/BatchMake_Nightly_Test.xml";
        if (!$this->submission('BatchmakeExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionBatchmakeUpdate()
    {
        $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
        $file = "$rep/BatchMake_Nightly_Update.xml";
        if (!$this->submission('BatchmakeExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightBuild()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_Build.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightConfigure()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_Configure.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightCoverage()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_Coverage.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightCoverageLog()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_CoverageLog.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }

    // Testing if it actually worked
    $this->login();
        $content = $this->connect($this->url.'/index.php?project=InsightExample&date=20090223');
        $content = $this->analyse($this->clickLink('76.43%'));
        $content = $this->connect($this->url.'/ajax/getviewcoverage.php?sEcho=1&iColumns=6&sColumns=&iDisplayStart=0&iDisplayLength=25&mDataProp_0=0&mDataProp_1=1&mDataProp_2=2&mDataProp_3=3&mDataProp_4=4&mDataProp_5=5&sSearch=&bRegex=false&sSearch_0=&bRegex_0=false&bSearchable_0=true&sSearch_1=&bRegex_1=false&bSearchable_1=true&sSearch_2=&bRegex_2=false&bSearchable_2=true&sSearch_3=&bRegex_3=false&bSearchable_3=true&sSearch_4=&bRegex_4=false&bSearchable_4=true&sSearch_5=&bRegex_5=false&bSearchable_5=true&iSortCol_0=2&sSortDir_0=asc&iSortingCols=1&bSortable_0=true&bSortable_1=true&bSortable_2=true&bSortable_3=true&bSortable_4=true&bSortable_5=true&buildid=8&status=4&nlow=2&nmedium=3&nsatisfactory=43&ncomplete=32&metricerror=0.49&metricpass=0.7&userid=1&displaylabels=0');

        $jsonobj = json_decode($content, true);
        $url = substr($jsonobj['aaData'][6][0], 9, 43);
        $url = str_replace('&#38;', '&', $url);
        $content = $this->connect($this->url.'/'.$url);
        $expected = '<span class="normal">    1 | #ifndef __itkNormalVectorDiffusionFunction_txx</span><br><span class="warning">   18</span><span class="normal">    2 | #define __itkNormalVectorDiffusionFunction_txx</span><br>';

        if (!$this->findString($content, $expected)) {
            $this->fail('Coverage log is wrong');
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightDynamicAnalysis()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_DynamicAnalysis.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightNotes()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_Notes.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInsightTest()
    {
        $url  = $this->url.'/submit.php?project=InsightExample';
        $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
        $file = "$rep/Insight_Experimental_Test.xml";
        if (!$this->submission('InsightExample', $file)) {
            return;
        }
        $this->assertTrue(true, "Submission of $file has succeeded");
    }

    public function testSubmissionInDb()
    {
        $query  = "SELECT id, stamp, name, type, generator,command FROM build WHERE id=7";
        $result = $this->db->query($query);
        $expected = array('id'        => '7',
                      'stamp'     => '20090223-0100-Nightly',
                      'name'      => 'Win32-MSVC2009',
                      'type'      => 'Nightly',
                      'generator' => 'ctest2.6-patch 0',
                      'command'   => 'F:\PROGRA~1\MICROS~1.0\Common7\IDE\VCExpress.exe BatchMake.sln /build Release /project ALL_BUILD'
                      );
        $this->assertEqual($result[0], $expected);
    }

    public function testProjectExperimentalLinkMachineName()
    {
        $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-23');
        if (!$content) {
            return;
        }
        $content = $this->analyse($this->clickLink('Dash20.kitware'));
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
        $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-23');
        if (!$content) {
            return;
        }
        $content = $this->analyse($this->clickLink('Win32-MSVC2009'));
        $expected = 'f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma';
        if (!$content) {
            return;
        } elseif (!$this->findString($content, $expected)) {
            $this->assertTrue(false, 'The webpage does not match right the content exepected');
            return;
        }
        $this->assertTrue(true, 'The webpage match the content exepected');
    }
}
