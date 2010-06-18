<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/builderrordiff.php');
require_once('cdash/pdo.php');

class BuildErrorDiffTestCase extends KWWebTestCase
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
   
  function testBuildErrorDiff()
    {
    xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);

    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $builderrordiff = new BuildErrorDiff();
    
    //no buildid
    $builderrordiff->SetValue("BUILDID", 0);
    ob_start();
    $builderrordiff->Save();
    $output = ob_get_contents();
    ob_end_clean();
    if(strpos($output, "BuildErrorDiff::Save(): BuildId not set") === false)
      {
      $this->fail("'BuildId not set' not found from Save()");
      return 1;
      }
   
    $builderrordiff->SetValue("BUILDID", 1);
    $builderrordiff->SetValue("TYPE", 1);
    $builderrordiff->SetValue("DIFFERENCEPOSITIVE", 1);
    $builderrordiff->SetValue("DIFFERENCENEGATIVE", -1);

    //call save twice to cover different execution paths
    if(!$builderrordiff->Save())
      {
      $this->fail("Save() call #1 returned false when it should be true.\n");
      return 1;
      }
    if(!$builderrordiff->Save())
      {
      $this->fail("Save() call #2 returned false when it should be true.\n");
      return 1;
      }

    $this->pass("Passed");
    if ( extension_loaded('xdebug'))
      {
      $data = xdebug_get_code_coverage();
      xdebug_stop_code_coverage();
      $file = '/projects/CDash/bin/xdebugCoverage'.
          DIRECTORY_SEPARATOR . md5($_SERVER['SCRIPT_FILENAME']);
      file_put_contents(
        $file . '.' . md5(uniqid(rand(), TRUE)) . '.' . "test_builderrordiff",
        serialize($data)
      );
      }
    return 0;
    }
}

?>
