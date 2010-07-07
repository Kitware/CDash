<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class SubmitTestTimingTestCase extends KWWebTestCase
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
    }

  function submitFile($build, $type)
    {
    $rep = dirname(__FILE__)."/data/SortTestTimingExample";
    $file = "$rep/$build" . "_". "$type.xml";
    if(!$this->submission('InsightExample',$file))
      {
      return false;
      }
    $this->assertTrue(true,"Submission of $file has succeeded");
    echo "I just submitted $file\n";
    }

  function testSubmitTestTiming()
    {
    $builds = array("short", "medium", "long");
    $types = array("Build", "Configure", "Test", "Update", "Notes");
    //$types = array("Build", "Configure", "Notes", "Test", "Update");
    foreach($builds as $build)
      {
      foreach($types as $type)
        {
        $this->submitFile($build, $type);
        }
      }
    }
}
?>
