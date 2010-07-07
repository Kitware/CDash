<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class SubmitSortingDataTestCase extends KWWebTestCase
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
    $this->db->setDb($db['name']);
    $this->db->setHost($db['host']);
    $this->db->setUser($db['login']);
    $this->db->setPassword($db['pwd']);
    $this->logfilename = $cdashpath."/backup/cdash.log";
    }

  function submitFile($build, $type)
    {
    $rep = dirname(__FILE__)."/data/SortingExample";
    $file = "$rep/$build" . "_". "$type.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return false;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    }

  function testSubmitSortingData()
    {
    $builds = array("short", "medium", "long");
    $types = array("Build", "Configure", "Test", "Update", "Notes");
    foreach($builds as $build)
      {
      foreach($types as $type)
        {
        $this->submitFile($build, $type);
        }
      }
    $this->deleteLog($this->logfilename);
    }
}
?>
