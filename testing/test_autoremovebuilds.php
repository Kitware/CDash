<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/image.php');
require_once('models/testimage.php');
require_once('cdash/pdo.php');

class AutoRemoveBuildsTestCase extends KWWebTestCase
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
    $this->directory = $configure['svnroot'];
    }
   
  function testAutoRemoveBuilds()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    include('cdash/config.local.php');
    chdir($this->directory);
    $argv[0] = "autoRemoveBuilds.php";
    $argv[1] = "InsightExample";

    $argc = 1;
    ob_start();
    include('autoRemoveBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "Usage: php") === false)
      {
      $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
      }

    $argc = 2;
    ob_start();
    include('autoRemoveBuilds.php');
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "removing builds for InsightExample") === false)
      {
      $this->fail("Expected output not found from autoRemoveBuilds.php.\n$output\n");
      }
    else
      {
      $this->pass("Passed");
      }
    $this->deleteLog($this->logfilename);

    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
    if ( extension_loaded('xdebug'))
      {
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_autoremovebuilds",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
