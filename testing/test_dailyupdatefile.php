<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

$path = dirname(__FILE__)."/..";
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('models/dailyupdatefile.php');

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
    $dailyupdatefile->SetValue("FILENAME", "");
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

    //cover the various SetValue options
    $dailyupdatefile->SetValue("FILENAME", "dailyupdatefile.log");
    $dailyupdatefile->SetValue("CHECKINDATE", strtotime("two hours ago"));
    $dailyupdatefile->SetValue("AUTHOR", "CDash Tester");
    $dailyupdatefile->SetValue("LOG", "example daily update log");
    $dailyupdatefile->SetValue("REVISION", "2");
    $dailyupdatefile->SetValue("PRIORREVISION", "1");

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
    return 0;
    }
}
?>
