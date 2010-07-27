<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/dailyupdatefile.php');
require_once('cdash/pdo.php');
require_once('cdash/common.php');

class DailyUpdateFileTestCase extends KWWebTestCase
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
   
  function testDailyUpdateFile()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $dailyupdatefile = new DailyUpdateFile();
    
    //no id, no matching database entry
    $dailyupdatefile->DailyUpdateId = 0;
    if($dailyupdatefile->Exists())
      {
      $this->fail("Exists() should return false when DailyUpdateId is 0");
      return 1;
      }

    ob_start();
    $dailyupdatefile->Save();
    $output = ob_get_contents();
    ob_end_clean();
    if($output !== "DailyUpdateFile::Save(): DailyUpdateId not set!")
      {
      $this->fail("'DailyUpdateId not set!' not found from Save()");
      return 1;
      }

    //no filename
    $dailyupdatefile->Filename = "";
    $dailyupdatefile->DailyUpdateId = 1;
    ob_start();
    $dailyupdatefile->Save();
    $output = ob_get_contents();
    ob_end_clean();
    if($output !== "DailyUpdateFile::Save(): Filename not set!")
      {
      $this->fail("'Filename not set!' not found from Save()");
      return 1;
      }

    //no matching database entry
    if($dailyupdatefile->Exists())
      {
      $this->fail("Exists() should return false before Save() has been called");
      return 1;
      }

    $dailyupdatefile->Filename = "dailyupdatefile.log";

    //call save twice to cover different execution paths
    if(!$dailyupdatefile->Save())
      {
      $this->fail("Save() returned false on call #1");
      return 1;
      }
    if(!$dailyupdatefile->Save())
      {
      $this->fail("Save() returned false on call #2");
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
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_dailyupdatefile",
        serialize($data)
      );
      }
    return 0;
    }
}
?>
