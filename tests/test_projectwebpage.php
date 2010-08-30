<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

class ProjectWebPageTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testAccessToWebPageProjectTest()
    {
    $this->login();
    // first project necessary for testing
    $name = 'BatchmakeExample';
    $description = 'Project Batchmake\'s test for cdash testing';
    $this->createProject($name,$description);
    $this->get($this->url.'/user.php'); // comes back to the my user page
    $name = 'InsightExample';
    $description = 'Project Insight test for cdash testing';
    $this->createProject($name,$description);
    $content = $this->connect($this->url.'/index.php?project=BatchmakeExample');
    if(!$content)
      {
      return;
      }
    $this->assertText('BatchmakeExample Dashboard');
    }

  function testSubmissionBatchmakeBuild()
    {
    $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
    $file = "$rep/BatchMake_Nightly_Build.xml";
    if(!$this->submission('BatchmakeExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionBatchmakeConfigure()
    {
    $rep  = dirname(__FILE__)."/data/BatchmakeNightlyExample";
    $file = "$rep/BatchMake_Nightly_Configure.xml";
    if(!$this->submission('BatchmakeExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }


  function testSubmissionBatchmakeNotes()
    {
    $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
    $file = "$rep/BatchMake_Nightly_Notes.xml";
    if(!$this->submission('BatchmakeExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionBatchmakeTest()
    {
    $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
    $file = "$rep/BatchMake_Nightly_Test.xml";
    if(!$this->submission('BatchmakeExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionBatchmakeUpdate()
    {
    $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
    $file = "$rep/BatchMake_Nightly_Update.xml";
    if(!$this->submission('BatchmakeExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightBuild()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_Build.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightConfigure()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_Configure.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightCoverage()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_Coverage.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightCoverageLog()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_CoverageLog.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }

    // Testing if it actually worked
    $this->login();
    $content = $this->connect($this->url.'/index.php?project=InsightExample&date=20090223');
    $content = $this->analyse($this->clickLink('76.43%'));
    $content = $this->analyse($this->clickLink('Satisfactory (75)'));
    $content = $this->analyse($this->clickLink('./Source/itkCannyEdgesDistanceAdvectionFieldFeatureGenerator.h'));
    $expected = '<span class="lineCov">    1 | #ifndef __itkNormalVectorDiffusionFunction_txx</span><br><span class="lineNum">   18</span><span class="lineCov">    2 | #define __itkNormalVectorDiffusionFunction_txx</span><br>';

    if(!$this->findString($content,$expected))
       {
       $this->fail('Coverage log is wrong');
       return;
       }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightDynamicAnalysis()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_DynamicAnalysis.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightNotes()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_Notes.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInsightTest()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_Test.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmissionInDb()
    {
    $query  = "SELECT id, stamp, name, type, generator,command FROM build WHERE id=6";
    $result = $this->db->query($query);
    $expected = array('id'        => '6',
                      'stamp'     => '20090223-0100-Nightly',
                      'name'      => 'Win32-MSVC2009',
                      'type'      => 'Nightly',
                      'generator' => 'ctest2.6-patch 0',
                      'command'   => 'F:\PROGRA~1\MICROS~1.0\Common7\IDE\VCExpress.exe BatchMake.sln /build Release /project ALL_BUILD'
                      );
    $this->assertEqual($result[0],$expected);
    }

  function testProjectExperimentalLinkMachineName()
    {
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-23');
    if(!$content)
      {
      return;
      }
    $content = $this->analyse($this->clickLink('Dash20.kitware'));
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,'<b>Total Virtual Memory: </b>2GB<br /><b>Total Physical Memory: </b>15MB<br />'))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      return;
      }
    $this->assertTrue(true,'The webpage match the content exepected');
    }

  function testProjectExperimentalLinkBuildSummary()
    {
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-23');
    if(!$content)
      {
      return;
      }
    $content = $this->analyse($this->clickLink('Win32-MSVC2009'));
    $expected = 'f:\program files\microsoft sdks\windows\v6.0a\include\servprov.h(79) : warning C4068: unknown pragma';
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,$expected))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      return;
      }
    $this->assertTrue(true,'The webpage match the content exepected');
    }

  function testProjectExperimentalLinkNotes()
    {
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-23');
    if(!$content)
      {
      return;
      }
    $content = $this->analyse($this->clickLink('Notes'));
    if(!$content)
      {
      return;
      }
    $expected = '-- F:/Dashboards/Dash20_batchmake_vs9.cmake';
    $this->assertText($expected);
    }
}
?>
