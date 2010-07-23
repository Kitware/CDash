<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('cdash/common.php');
require_once('cdash/pdo.php');

class DeleteDailyUpdateTestCase extends KWWebTestCase
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
    $this->databaseName = $db['name'];
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    $this->projectid = -1;
    $this->logfilename = $cdashpath."/backup/cdash.log";
    $this->deleteLog($this->logfilename);
    }

  function testDeleteDailyUpdate()
    {
    //double check that it's the testing database before doing anything hasty...
    if($this->databaseName !== "cdash4simpletest")
      {
      $this->fail("can only test on a database named 'cdash4simpletest'");
      return 1;
      }

    //remove the daily update entry for some projects so that subsequent tests
    //will cover dailyupdate.php more thoroughly
    $db = pdo_connect($this->db->dbo->host, $this->db->dbo->user, $this->db->dbo->password);
    pdo_select_db("cdash4simpletest", $db);

    $cvsID = get_project_id("InsightExample");
    if(!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$cvsID'"))
      {
      $this->fail("pdo_query returned false");
      return 1;
      }
    $svnID = get_project_id("EmailProjectExample");
    if(!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$svnID'"))
      {
      $this->fail("pdo_query returned false");
      return 1;
      }
    $gitID = get_project_id("PublicDashboard");
    if(!$query = pdo_query("DELETE FROM dailyupdate WHERE projectid='$gitID'"))
      {
      $this->fail("pdo_query returned false");
      return 1;
      }
    $this->pass("Passed");
    }
}
?>
