<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);

require_once('models/test.php');
require_once('models/image.php');
require_once('models/testmeasurement.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class TestModelTestCase extends KWWebTestCase
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
   
  function testTestModel()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);
    
    $test = new Test();
    $test->Id = "8967";
    $test->Name = "dummytest";
    $test->ProjectId = 2;
 
    // Cover error condition 
    $test->InsertLabelAssociations('');
    
    $testmeasurement = new TestMeasurement();
    $testmeasurement->Name = "Label";
    $testmeasurement->Value = "Some_Label";
    $test->AddMeasurement($testmeasurement);
    
    $image = new Image();
    $image->Filename = dirname(__FILE__)."/data/smile.gif";
    $image->Data = base64_encode(file_get_contents($image->Filename));
    $image->Checksum = 100;
    $image->Extension = "image/gif";

    $test->AddImage($image);
    
    $test->Insert();
    
    $test->GetCrc32();
    
    if ( extension_loaded('xdebug'))
      {
      include('cdash/config.local.php');
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_testmodel",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
