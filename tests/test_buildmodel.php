<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once('models/build.php');
require_once('models/builderror.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class BuildModelTestCase extends KWWebTestCase
{
  var $url           = null;
  var $db            = null;
  var $projecttestid = null;
  var $logfilename   = null;
  
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
    $this->logfilename = $cdashpath."/backup/cdash.log";
    }
   
  function testBuildModel()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);
    
    $build = new Build();
    $builderror = new BuildError();
    $builderror->Type = 0;
    $builderror->Text = 'error';
    $buildwarning = new BuildError();
    $buildwarning->Type = 1;
    $buildwarning->Text = 'warning';
    
    if($build->GetName() !== false)
      {
      $this->fail("GetName didn't return false for empty build id");
      return 1;
      }

    if($build->GetLabels() !== false)
      {
      $this->fail("GetLabels didn't return false for empty build id");
      return 1;
      }
    
    if($build->GetGroup() !== false)
      {
      $this->fail("GetGroup didn't return false for empty build id");
      return 1;
      }    
    
    if($build->GetNumberOfErrors() !== false)
      {
      $this->fail("GetNumberOfErrors didn't return false for empty build id");
      return 1;
      }
      
    if($build->GetNumberOfWarnings() !== false)
      {
      $this->fail("GetNumberOfWarnings didn't return false for empty build id");
      return 1;
      }
    
    if($build->SetSubProject('1234') !== false)
      {
      $this->fail("SetSubProject didn't return false for empty project id");
      return 1;
      }
      
    if($build->GetSubProjectName() !== false)
      {
      $this->fail("GetSubProjectName didn't return false for empty build id");
      return 1;
      }
      
    if($build->GetErrorDifferences() !== false)
      {
      $this->fail("GetErrorDifferences didn't return false for empty build id");
      return 1;
      }
      
    if($build->ComputeUpdateStatistics() !== false)
      {
      $this->fail("ComputeUpdateStatistics didn't return false for empty build id");
      return 1;
      }
      
    if($build->ComputeDifferences() !== false)
      {
      $this->fail("ComputeDifferences didn't return false for empty build id");
      return 1;
      }
    
    if($build->ComputeConfigureDifferences() !== false)
      {
      $this->fail("ComputeConfigureDifferences didn't return false for empty build id");
      return 1;
      }
    
    if($build->ComputeTestTiming() !== false)
      {
      $this->fail("ComputeTestTiming didn't return false for empty build id");
      return 1;
      }
      
    if($build->InsertLabelAssociations() !== false)
      {
      $this->fail("InsertLabelAssocations didn't return false for empty build id");
      return 1;
      }
      
    if($build->UpdateEndTime('2010-08-07') !== false)
      {
      $this->fail("UpdateEndTime didn't return false for empty build id");
      return 1;
      }
      
    if($build->SaveTotalTestsTime('100') !== false)
      {
      $this->fail("SaveTotalTestsTime didn't return false for empty build id");
      return 1;
      }
    
    $build->Id = '1';
    
    if($build->ComputeTestTiming() !== false)
      {
      $this->fail("ComputeTestTiming didn't return false for empty project id");
      return 1;
      }
      
    if($build->ComputeUpdateStatistics() !== false)
      {
      $this->fail("ComputeUpdateStatistics didn't return false for empty project id");
      return 1;
      }

    $build->ProjectId = '2';
    if($build->SetSubProject('8567') !== false)
      {
      $this->fail("SetSubProject didn't return false for invalid subproject id");
      return 1;
      }
      
    if($build->Exists() == false)
      {
      $this->fail("Exists returned false for a valid build id");
      return 1;
      }
      
    $build->Id = '98765';
    $build->SetStamp('20100610-1901-Experimental');
    $build->Type = ''; //force this empty for coverage purposes
    
    if($build->Exists() == true)
      {
      $this->fail("Exists returned true for an invalid build id");
      return 1;
      }
    
    $build->Save();
    $build->Append = true;
    $build->InsertErrors = true;
    $build->AddError($builderror);
    $build->AddError($buildwarning);
    $build->Save();
    
    if ( extension_loaded('xdebug'))
      {
      include('cdash/config.local.php');
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_buildmodel",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
