<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/image.php');
require_once('models/testimage.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class ImageTestCase extends KWWebTestCase
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
   
  function testImage()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $image = new Image();
    
    //no id, no matching checksum
    $image->Id = 0;
    if($image->Exists())
      {
      $this->fail("Exists() should return false when Id is 0");
      return 1;
      }

    //id, no matching checksum
    $image->Id = 1;
    if($image->Exists())
      {
      $this->fail("Exists() should return false with no matching checksum\n");
      }

    $pathToImage = dirname(__FILE__)."/data/smile.gif";

    //dummy checksum so we don't break the test on pgSQL
    $image->Checksum=100;
    
    //call save twice to cover different execution paths
    if(!$image->Save())
      {
      $this->fail("Save() call #1 returned false when it should be true.\n");
      return 1;
      }
    if(!$image->Save())
      {
      $this->fail("Save() call #2 returned false when it should be true.\n");
      return 1;
      }

    //exercise the TestImage class as well
    $testimage = new TestImage();

    $testimage->Id = 1;
    $testimage->TestId = 1;

    if($testimage->Exists())
      {
      $this->fail("testimage shouldn't exist yet.\n");
      return 1;
      }

    if(!$testimage->Insert())
      {
      $this->fail("testimage::Insert() shouldn't have returned false.\n");
      return 1;
      }
    
    if(!$testimage->Exists())
      {
      $this->fail("testimage should exist at this point.\n");
      return 1;
      }

    $this->pass("Passed");
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    if ( extension_loaded('xdebug'))
      {
      include('cdash/config.local.php');
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_image",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
