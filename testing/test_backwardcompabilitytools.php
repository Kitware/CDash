<?php
// kwtest library
require_once('kwtest/kw_web_tester.php');

class BackwardCompatibilityToolsTestCase extends KWWebTestCase
{
  var $url = null;
  
  function __construct()
    {
    parent::__construct();
    require('config.test.php');
    $this->url = $configure['urlwebsite'];
    $this->logfilename = $cdashpath."/backup/cdash.log";
    }
  
  function testAssignBuildsToDefaultGroups()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    $this->clickSubmitByName("AssignBuildToDefaultGroups");
    $this->assertText("Builds have been added to default groups successfully.");
    }
  
  function testFixBuildGroups()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    if($this->clickSubmitByName("FixBuildBasedOnRule"))
      {
      $this->pass("Passed");
      }
    else
      {
      $this->fail("clicking FixBuildBasedOnRule returned false");
      }
    }

  function testCheckAndDeleteBuildsWrongDate()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    if(!$this->clickSubmitByName("CheckBuildsWrongDate"))
      {
      $this->fail("clicking CheckBuildsWrongDate returned false");
      }
    if(!$this->clickSubmitByName("DeleteBuildsWrongDate"))
      {
      $this->fail("clicking DeleteBuildsWrongDate returned false");
      }
    $this->pass("Passed");
    }
  
  function testComputeTestTiming()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    if(!$this->clickSubmitByName("ComputeTestTiming"))
      {
      $this->fail("clicking ComputeTestTiming returned false");
      }
    $this->assertText("Timing for tests has been computed successfully.");
    }

/* functionality seems broken...
  function testComputeUpdateStatistics()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    if(!$this->clickSubmitByName("ComputeUpdateStatistics"))
      {
      $this->fail("clicking ComputeUpdateStatistics returned false");
      }
    $this->assertText("Timing for tests has been computed successfully.");
    }
*/
  function testCompressTestOutput()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    set_time_limit(0);
    if(!$this->clickSubmitByName("CompressTestOutput"))
      {
      $this->fail("clicking CompressTestOutput returned false");
      }
    $this->pass("Passed");
    }
  
  function testCleanup()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    set_time_limit(0);
    if(!$this->clickSubmitByName("Cleanup"))
      {
      $this->fail("clicking Cleanup returned false");
      }
    $this->assertText("Database cleanup complete.");
    }
  
  function testUpgrade()
    {
    if(!$this->getMaintenancePage())
      {
      return 1;
      }
    set_time_limit(0);
    $result = $this->clickSubmitByName("Upgrade");
    if(!$result)
      {
      $this->fail("clicking Upgrade returned false");
      }
      /*
    //fake the javascript calls...
    //doesn't show up in the coverage anyway, for some reason...
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-tables=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-0-8=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-1-0=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-1-2=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-1-4=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-1-6=1");
    $this->get($this->url . "/backwardCompatibilityTools.php?upgrade-1-8=1");
    //some of these upgrades pollute the log file
    //clear it out so that it doesn't cause subsequent tests to fail
    $this->deleteLog($this->logfilename);
    */

    $this->pass("Passed");
    }

  function getMaintenancePage()
  {
    $this->login();
    $content = $this->connect($this->url . "/backwardCompatibilityTools.php");
    if($content == false)
      {
      $this->fail("failed to connect to backwardCompatibilityTools.php");
      return false;
      }
    return true;
  }
}
?>
