<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/buildusernote.php');
require_once('cdash/pdo.php');

class BuildUserNoteTestCase extends KWWebTestCase
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
   
  function testBuildUserNote()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $buildusernote = new BuildUserNote();

    $buildusernote->BuildId = 0;
    ob_start();
    $result = $buildusernote->Insert();
    $output = ob_get_contents();
    ob_end_clean();
    if($result)
      {
      $this->fail("Insert() should return false when BuildId is 0");
      return 1;
      }
    if(strpos($output, "BuildUserNote::Insert(): BuildId is not set") === false)
      {
      $this->fail("'BuildId is not set' not found from Insert()");
      return 1;
      }

    $buildusernote->BuildId = 1;
    $buildusernote->UserId = 0;
    ob_start();
    $result = $buildusernote->Insert();
    $output = ob_get_contents();
    ob_end_clean();
    if($result)
      {
      $this->fail("Insert() should return false when UserId is 0");
      return 1;
      }
    if(strpos($output, "BuildUserNote::Insert(): UserId is not set") === false)
      {
      $this->fail("'UserId is not set' not found from Insert()");
      return 1;
      }

    $buildusernote->UserId = 1;

    if(!$buildusernote->Insert())
      {
      $this->fail("Insert() returned false when it should be true.\n");
      return 1;
      }
    $this->pass("Passed");

    if ( extension_loaded('xdebug'))
      {
      include('cdash/config.local.php');
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_buildusernote",
        serialize($data)
      );
      }
    return 0;
    }
}
?>
