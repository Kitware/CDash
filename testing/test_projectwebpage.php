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
//    $this->clickLink('ProjectTest4Db');
    $content = $this->connect($this->url.'/index.php?project=BatchmakeExample');
    if(!$content)
      {
      return;
      }
    $this->assertText('BatchmakeExample Dashboard');
    $this->submission('BatchmakeExample');
    $this->submission('InsightExample');
    }


  function testSubmission()
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
  
  // In case of the project does not exist yet
  function createProject($name,$description)
    {
    $this->clickLink('[Create new project]');
    $this->setField('name',$name);
    $this->setField('description',$description);
    $this->setField('public','1');
    $this->clickSubmit('Create Project');
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
    
  function submission($projectname)
    {
    if(!strcmp($projectname,'BatchmakeExample'))
      {
      $rep = "data/BatchmakeNightlyExample";
      $url = $this->url.'/submit.php?project=BatchmakeExample';
      $this->uploadfile($url,"$rep/BatchMake_Dash20.kitware_Win32-MSVC2009_20090223-0100-Nightly_Build.xml");
      $this->uploadfile($url,"$rep/BatchMake_Dash20.kitware_Win32-MSVC2009_20090223-0100-Nightly_Configure.xml");
      $this->uploadfile($url,"$rep/BatchMake_Dash20.kitware_Win32-MSVC2009_20090223-0100-Nightly_Notes.xml");
      $this->uploadfile($url,"$rep/BatchMake_Dash20.kitware_Win32-MSVC2009_20090223-0100-Nightly_Test.xml");
      $this->uploadfile($url,"$rep/BatchMake_Dash20.kitware_Win32-MSVC2009_20090223-0100-Nightly_Update.xml");
      return true;
      }
    elseif(!strcmp($projectname,'InsightExample'))
      {
      $url = $this->url.'/submit.php?project=InsightExample';
      $rep = "data/InsightExperimentalExample";
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_Build.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_Configure.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_CoverageLog.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_Coverage.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_DynamicAnalysis.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_Notes.xml");
      $this->uploadfile($url,"$rep/Insight_camelot.kitware_Linux-g++-4.1-LesionSizingSandbox_Debug_20090223-0710-Experimental_Test.xml");
      return true;
      }
    }
    
  function uploadfile($url,$filename)
    {
    $fp = fopen($filename, 'r');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    }  
}
?>