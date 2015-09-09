<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');
include_once("upgrade_functions.php");

class UpgradeTestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
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
    //fake the javascript calls...
    $this->get($this->url . "/upgrade.php?upgrade-tables=1");
    $this->get($this->url . "/upgrade.php?upgrade-0-8=1");
    $this->get($this->url . "/upgrade.php?upgrade-1-0=1");
    $this->get($this->url . "/upgrade.php?upgrade-1-2=1");
    $this->get($this->url . "/upgrade.php?upgrade-1-4=1");
    $this->get($this->url . "/upgrade.php?upgrade-1-6=1");
    $this->get($this->url . "/upgrade.php?upgrade-1-8=1");
    //some of these upgrades pollute the log file
    //clear it out so that it doesn't cause subsequent tests to fail
    $this->deleteLog($this->logfilename);

    $this->pass("Passed");
    }

  function testBuildFailureDetailsUpgrade()
    {
    require_once(dirname(__FILE__).'/cdash_test_case.php');
    require_once('cdash/common.php');
    require_once('cdash/pdo.php');

    $retval = 0;
    $old_table = "testbuildfailure";
    $new_table = "testdetails";

    global $CDASH_DB_TYPE;
    if ($CDASH_DB_TYPE == 'pgsql')
      {
      $create_old_query = '
        CREATE TABLE "'. $old_table . '" (
          "id" bigserial NOT NULL,
          "buildid" bigint NOT NULL,
          "type" smallint NOT NULL,
          "workingdirectory" character varying(512) NOT NULL,
          "stdoutput" text NOT NULL,
          "stderror" text NOT NULL,
          "exitcondition" character varying(255) NOT NULL,
          "language" character varying(64) NOT NULL,
          "targetname" character varying(255) NOT NULL,
          "outputfile" character varying(512) NOT NULL,
          "outputtype" character varying(255) NOT NULL,
          "sourcefile" character varying(512) NOT NULL,
          "crc32" bigint DEFAULT \'0\' NOT NULL,
          "newstatus" smallint DEFAULT \'0\' NOT NULL,
          PRIMARY KEY ("id")
        )';

      $create_new_query = '
        CREATE TABLE "' . $new_table . '" (
          "id" bigserial NOT NULL,
          "type" smallint NOT NULL,
          "stdoutput" text NOT NULL,
          "stderror" text NOT NULL,
          "exitcondition" character varying(255) NOT NULL,
          "language" character varying(64) NOT NULL,
          "targetname" character varying(255) NOT NULL,
          "outputfile" character varying(512) NOT NULL,
          "outputtype" character varying(255) NOT NULL,
          "crc32" bigint DEFAULT \'0\' NOT NULL,
          PRIMARY KEY ("id")
        )';
      }
    else
      {
      // MySQL
      $create_old_query = "
        CREATE TABLE `$old_table` (
          `id` bigint(20) NOT NULL auto_increment,
          `buildid` bigint(20) NOT NULL,
          `type` tinyint(4) NOT NULL,
          `workingdirectory` varchar(512) NOT NULL,
          `stdoutput` mediumtext NOT NULL,
          `stderror` mediumtext NOT NULL,
          `exitcondition` varchar(255) NOT NULL,
          `language` varchar(64) NOT NULL,
          `targetname` varchar(255) NOT NULL,
          `outputfile` varchar(512) NOT NULL,
          `outputtype` varchar(255) NOT NULL,
          `sourcefile` varchar(512) NOT NULL,
          `crc32` bigint(20) NOT NULL default '0',
          `newstatus` tinyint(4) NOT NULL default '0',
          PRIMARY KEY  (`id`)
        )";

      $create_new_query = "
        CREATE TABLE `$new_table` (
          `id` bigint(20) NOT NULL auto_increment,
          `type` tinyint(4) NOT NULL,
          `stdoutput` mediumtext NOT NULL,
          `stderror` mediumtext NOT NULL,
          `exitcondition` varchar(255) NOT NULL,
          `language` varchar(64) NOT NULL,
          `targetname` varchar(255) NOT NULL,
          `outputfile` varchar(512) NOT NULL,
          `outputtype` varchar(255) NOT NULL,
          `crc32` bigint(20) NOT NULL default '0',
          PRIMARY KEY  (`id`)
        )";
      }

    // Create testing tables.
    if (!pdo_query($create_old_query))
      {
      $this->fail("pdo_query returned false");
      $retval = 1;
      }
    if (!pdo_query($create_new_query))
      {
      $this->fail("pdo_query returned false");
      $retval = 1;
      }

    // Insert two identical buildfailures into the old table.
    $insert_query = "
      INSERT INTO $old_table
        (buildid, type, workingdirectory, stdoutput, stderror, exitcondition,
         language, targetname, outputfile, outputtype, sourcefile, crc32,
         newstatus)
      VALUES
        (1, 1, '/tmp/', 'this is stdout', 'this is stderror', '0',
         'C', 'foo', 'foo.o', 'object file', 'foo.c', '1234',
         '0')";
    if (!pdo_query($insert_query))
      {
      $this->fail("pdo_query returned false");
      $retval = 1;
      }
    if (!pdo_query($insert_query))
      {
      $this->fail("pdo_query returned false");
      $retval = 1;
      }

    // Run the upgrade function.
    UpgradeBuildFailureTable($old_table, $new_table);

    // Verify that we now have two buildfailures and one buildfailuredetails.
    $count_query = "
      SELECT COUNT(DISTINCT id) AS numfails,
             COUNT(DISTINCT detailsid) AS numdetails
      FROM $old_table";

    $count_results = pdo_single_row_query($count_query);
    if ($count_results['numfails'] != 2)
      {
      $this->fail(
        "Expected 2 buildfailures, found " . $count_results['numfails']);
      $retval = 1;
      }
    if ($count_results['numdetails'] != 1)
      {
      $this->fail(
        "Expected 1 buildfailuredetails, found " . $count_results['numdetails']);
      $retval = 1;
      }

    // Drop the testing tables.
    pdo_query("DROP TABLE $old_table");
    pdo_query("DROP TABLE $new_table");

    if ($retval == 0)
      {
      $this->pass("Passed");
      }
    return $retval;
    }

  function getMaintenancePage()
    {
    $this->login();
    $content = $this->connect($this->url . "/upgrade.php");
    if($content == false)
      {
      $this->fail("failed to connect to upgrade.php");
      return false;
      }
    return true;
    }
}

?>
