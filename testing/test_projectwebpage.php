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
      }
    $this->assertTrue(true,'The webpage match the content exepected');
    }
  
  function testProjectExperimentalLinkBuildSummury()
    {
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-24');
    if(!$content)
      {
      return;
      }
    $content = $this->analyse($this->clickLink('Win32-MSVC2009'));
    $expected = '    </a></li><li><a href="#" id="activem">PROJECT</a>';
    $expected .= '<ul><li><a class="submm" href="http://">Home</a></li><li>';
    $expected .= '<a class="submm" href="http://">Doxygen</a></li><li>';
    $expected .= '<a class="submm" href="http://">CVS</a></li>';
    $expected .= '<li><a class="submm" href="http://">Bugs</a></li>';
    $expected .= '</ul></li></ul></td><td height="28" class="insd3">';
    $expected .= '<span id="calendar" class="cal"></span>&nbsp;';
    $expected .= '</td></tr></table></td></tr></table></td></tr></table>';
    $expected .= '<input type="hidden" id="projectname" value="BatchmakeExample" /><br /><br />';
    $expected .= '<b>Site Name: </b>Dash20.kitware<br /><b>Build Name: </b>';
    $expected .= 'Win32-MSVC2009<br /><b>Time: </b>2009-02-23T05:02:03 EST<br /><b>Type: </b>';
    $expected .= 'Nightly<br /><br /><b>OS Name: </b>Windows<br /><b>OS Release: </b>XP Professional<br />';
    $expected .= '<b>OS Version: </b>Service Pack 2(Build 2600)<br /><b>Compiler Name: </b>';
    $expected .= 'unknown<br /><b>Compiler Version: </b>unknown<br /><br /><table>';
    $expected .= '<tr><td><table class="dart"><tr class="table-heading"><th colspan="3">Current Build</th></tr>';
    $expected .= '<tr class="table-heading"><th>Stage</th><th>Errors</th>';
    $expected .= '<th>Warnings</th></tr><tr class="tr-odd"><td><a href="#Stage0">';
    $expected .= '<b>Update</b></a></td><td align="right" class="normal"><b>0</b>';
    $expected .= '</td><td align="right" class="normal"><b>0</b></td></tr>';
    $expected .= '<tr class="tr-even"><td><a href="#Stage1"><b>Configure</b></a></td>';
    $expected .= '<td align="right" class="&#10;                  normal&#10;';
    $expected .= '                  "><b>0</b></td><td align="right" class="&#10;                  normal&#10;';
    $expected .= '                  "><b>0</b></td></tr><tr class="tr-odd"><td><a href="#Stage2"><b>Build</b></a></td>';
    $expected .= '<td align="right" class="&#10;                  normal&#10;';
    $expected .= '                  "><b>0</b></td><td align="right" class="error&#10;';
    $expected .= '               "><b>10</b></td></tr><tr class="tr-even"><td>';
    $expected .= '<a href="#Stage3"><b>Test</b></a></td><td align="right" class="error&#10;               "><b>5</b></td>';
    $expected .= '<td align="right" class="&#10;                  normal&#10;';
    $expected .= '                  "><b>0</b></td></tr></table></td><td></td></tr></table><br />';
    if(!$content)
      {
      return;
      }
    elseif(!$this->findString($content,$expected))
      {
      $this->assertTrue(false,'The webpage does not match right the content exepected');
      }
    $this->assertTrue(true,'The webpage match the content exepected');  
    }
  
  function testProjectExperimentalLinkNotes()
    {
    $content = $this->connect($this->url.'?project=BatchmakeExample&date=2009-02-24');
    $content = $this->analyse($this->clickLink('Notes'));
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
      $rep = dirname(__FILE__)."/data/BatchmakeNightlyExample";
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
      $rep = dirname(__FILE__)."/data/InsightExperimentalExample";
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