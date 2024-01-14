<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/upgrade_functions.php';


class UpgradeTestCase extends KWWebTestCase
{
    protected $PDO;

    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
    }

    public function testAssignBuildsToDefaultGroups()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        $this->clickSubmitByName('AssignBuildToDefaultGroups');
        $this->assertText('Builds have been added to default groups successfully.');
    }

    public function testFixBuildGroups()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        if ($this->clickSubmitByName('FixBuildBasedOnRule')) {
            $this->pass('Passed');
        } else {
            $this->fail('clicking FixBuildBasedOnRule returned false');
        }
    }

    public function testCheckAndDeleteBuildsWrongDate()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        if (!$this->clickSubmitByName('CheckBuildsWrongDate')) {
            $this->fail('clicking CheckBuildsWrongDate returned false');
        }
        if (!$this->clickSubmitByName('DeleteBuildsWrongDate')) {
            $this->fail('clicking DeleteBuildsWrongDate returned false');
        }
        $this->pass('Passed');
    }

    public function testComputeTestTiming()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        if (!$this->clickSubmitByName('ComputeTestTiming')) {
            $this->fail('clicking ComputeTestTiming returned false');
        }
        $this->assertText('Timing for tests has been computed successfully.');
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

    public function testCompressTestOutput()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        set_time_limit(0);
        if (!$this->clickSubmitByName('CompressTestOutput')) {
            $this->fail('clicking CompressTestOutput returned false');
        }
        $this->pass('Passed');
    }

    public function testCleanup()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        set_time_limit(0);
        if (!$this->clickSubmitByName('Cleanup')) {
            $this->fail('clicking Cleanup returned false');
        }
        $this->assertText('Database cleanup complete.');
    }

    public function testGetVendorVersion()
    {


        $version = pdo_get_vendor_version();
        [$major, $minor, ] = $version? explode(".", $version) : [null,null,null];

        $this->assertTrue(is_numeric($major));
        $this->assertTrue(is_numeric($minor));

        return;
    }

    public function getMaintenancePage()
    {
        $this->login();
        $content = $this->connect($this->url . '/upgrade.php');
        if ($content == false) {
            $this->fail('failed to connect to upgrade.php');
            return false;
        }
        return true;
    }
}
