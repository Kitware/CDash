<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/buildtestdiff.php');
require_once('cdash/pdo.php');

class BuildTestDiffTestCase extends KWWebTestCase
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
   
  function testBuildTestDiff()
    {

    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $buildtestdiff = new BuildTestDiff();

    $buildtestdiff->BuildId = 0;
    ob_start();
    $result = $buildtestdiff->Insert();
    $output = ob_get_contents();
    ob_end_clean();
    if($result)
      {
      $this->fail("Insert() should return false when BuildId is 0");
      return 1;
      }
    if(strpos($output, "BuildTestDiff::Insert(): BuildId is not set") === false)
      {
      $this->fail("'BuildId is not set' not found from Insert()");
      return 1;
      }

    $buildtestdiff->BuildId = 1;
    $buildtestdiff->SetValue("TESTDIFF", 1);

    //call save twice to cover different execution paths
    if(!$buildtestdiff->Insert())
      {
      $this->fail("Add() returned false when it should be true.\n");
      return 1;
      }

    $this->pass("Passed");
    return 0;
    }
}
?>
