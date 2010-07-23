<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');
require_once('kwtest/kw_db.php');

class ManageBuildGroupTestCase extends KWWebTestCase
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
    $this->projectid = -1;
    }

  function testCurrentGroups()
    {
    if(!$this->connectAndGetProjectId())
      {
      return 1;
      }
    $this->get($this->url."/manageBuildGroup.php?projectid=$this->projectid");
    
    //test down
    $content = $this->get($this->url."/manageBuildGroup.php?projectid=$this->projectid&groupid=4&down=1");
    //make sure nightly comes after continuous
    $foundContinuous = false;
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "Continuous") !== false)
        {
        $foundContinuous = true;
        }
      if(strpos($line, "Nightly") !== false)
        {
        if(!$foundContinuous)
          {
          $this->fail("Nightly should be below continuous");
          return 1;
          }
        else
          {
          break;
          }
        }
      }

    //test up
    $content = $this->get($this->url."/manageBuildGroup.php?projectid=$this->projectid&groupid=4&up=1");
    //make sure nightly comes before continuous
    $foundContinuous = false;
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "Continuous") !== false)
        {
        $foundContinuous = true;
        }
      if(strpos($line, "Nightly") !== false)
        {
        if($foundContinuous)
          {
          $this->fail("Nightly should be above continuous");
          return 1;
          }
        else
          {
          break;
          }
        }
      }

    //test update description
    $this->setFieldByName("description", "Test builds");
    $content = $this->clickSubmitByName("submitDescription");
    if(strpos($content, "Test builds") === false)
      {
      $this->fail("'Test builds' not found after updating description");
      return 1;
      }

    $this->pass("Passed");
    }

  function testNewGroup()
    {
    if(!$this->connectAndGetProjectId())
      {
      return 1;
      }
    $this->get($this->url."/manageBuildGroup.php?projectid=$this->projectid#fragment-2");
    $this->setFieldByName("name", "New Builds");
    $content = $this->clickSubmitByName("createGroup");
    $this->assertText("New Builds");
    return 0;
    }

  function connectAndGetProjectId()
    {
    $this->login();

    //get projectid for PublicDashboards
    $content = $this->connect($this->url.'/manageBuildGroup.php');
    $lines = explode("\n", $content);
    foreach($lines as $line)
      {
      if(strpos($line, "PublicDashboard") !== false)
        {
        preg_match('#<option value="([0-9]+)"#', $line, $matches);
        $this->projectid = $matches[1];
        break;
        }
      }
    if($this->projectid === -1)
      {
      $this->fail("Unable to find projectid for PublicDashboard");
      return false;
      }
    return true;
    }
}
?>
