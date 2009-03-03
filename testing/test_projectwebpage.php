<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ProjectWebPageTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    $this->db  =& new database($db['type']);
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    }
   
  function testAccessToWebPageProjectTest()
    {
    $this->login();
    // first project necessary for testing
    $name = 'BatchmakeExample';
    $description = 'Project Batchmake test for cdash testing';
    $this->createProject($name,$description);
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
    
  function testSubmissionInsightCoverageLog()
    {
    $url  = $this->url.'/submit.php?project=InsightExample';
    $rep  = dirname(__FILE__)."/data/InsightExperimentalExample";
    $file = "$rep/Insight_Experimental_CoverageLog.xml";
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
    $query  = "SELECT id, stamp, name, type, generator,command FROM build";
    $result = $this->db->query($query);
    $expected = array('id'        => '1',
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
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-24');
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
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-24');
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
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-24');
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
    
  // In case of the project does not exist yet
  function createProject($name,$description)
    {
    $this->clickLink('[Create new project]');
    $this->setField('name',$name);
    $this->setField('description',$description);
    $this->setField('public','1');
    $this->clickSubmitByName('Submit');
    return $this->clickLink('BACK');
    }
    
  function login()
    {
    $this->get($this->url);
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    return $this->clickSubmit('Login >>');
    }
    
  function submission($projectname,$file)
    {
      $url = $this->url."/submit.php?project=$projectname";
      $result = $this->uploadfile($url,$file);
      if($this->findString($result,'error')   ||
         $this->findString($result,'Warning') ||
         $this->findString($result,'Notice'))
        {
        $this->assertEqual($result,"\n");
        return false;
        }
      return true;
    }
    
  function uploadfile($url,$filename)
    {    
    $fp = fopen($filename, 'r');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
    $page = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $page;
    } 
}
?>