<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/upgrade_functions.php';

use CDash\Config;
use CDash\Database;
use CDash\Model\BuildGroup;
use CDash\Model\BuildGroupRule;

class UpgradeTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
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
        require_once 'include/pdo.php';

        $version = pdo_get_vendor_version();
        list($major, $minor, ) = $version? explode(".", $version) : array(null,null,null);

        $this->assertTrue(is_numeric($major));
        $this->assertTrue(is_numeric($minor));

        return;
    }

    public function testUpgrade()
    {
        if (!$this->getMaintenancePage()) {
            return 1;
        }
        set_time_limit(0);
        $result = $this->clickSubmitByName('Upgrade');
        if (!$result) {
            $this->fail('clicking Upgrade returned false');
        }
        //fake the javascript calls...
        $this->get($this->url . '/upgrade.php?upgrade-tables=1');
        //some of these upgrades pollute the log file
        //clear it out so that it doesn't cause subsequent tests to fail
        $this->deleteLog($this->logfilename);

        $this->pass('Passed');
    }

    public function testBuildFailureDetailsUpgrade()
    {
        require_once dirname(__FILE__) . '/cdash_test_case.php';
        require_once 'include/common.php';
        require_once 'include/pdo.php';

        $retval = 0;
        $old_table = 'testbuildfailure';
        $new_table = 'testdetails';

        if (config('database.default') == 'pgsql') {
            $create_old_query = '
                CREATE TABLE "' . $old_table . '" (
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
        } else {
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
        if (!pdo_query($create_old_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        if (!pdo_query($create_new_query)) {
            $this->fail('pdo_query returned false');
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
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
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
        if ($count_results['numfails'] != 2) {
            $this->fail(
                'Expected 2 buildfailures, found ' . $count_results['numfails']);
            $retval = 1;
        }
        if ($count_results['numdetails'] != 1) {
            $this->fail(
                'Expected 1 buildfailuredetails, found ' . $count_results['numdetails']);
            $retval = 1;
        }

        // Drop the testing tables.
        pdo_query("DROP TABLE $old_table");
        pdo_query("DROP TABLE $new_table");

        if ($retval == 0) {
            $this->pass('Passed');
        }
        return $retval;
    }

    public function testUpgradeDurations()
    {
        require_once dirname(__FILE__) . '/cdash_test_case.php';
        require_once 'include/common.php';
        require_once 'include/pdo.php';

        $retval = 0;

        // Get the ID of the parent Trilinos build that we will use to verify
        // that this upgrade was successful.
        $query =
            "SELECT id FROM build
            WHERE name = 'Windows_NT-MSVC10-SERIAL_DEBUG_DEV' AND parentid=-1";
        $row = pdo_single_row_query($query);
        $id = qnum($row['id']);

        // Set build.configureduration to 0 for all builds.
        // This will force the upgrade function to recompute these values
        // based on configure start & end time.
        pdo_query('UPDATE build SET configureduration = 0');

        // Run the configure duration upgrade function.
        UpgradeConfigureDuration();

        // Make sure that our exemplar build has the value that we expect.
        $query = "SELECT configureduration FROM build WHERE id = $id";
        $row = pdo_single_row_query($query);
        if ($row['configureduration'] != 309.00) {
            $this->fail(
                'Expected configure duration to be 309.00, found ' . $row['configureduration']);
            $retval = 1;
        }

        // Similarly test our buildduration and testduration upgrade functions.
        $query = "SELECT buildduration, testduration FROM build WHERE id = $id";
        $row = pdo_single_row_query($query);
        $saved_build_duration = $row['buildduration'];
        $saved_test_duration = $row['testduration'];
        pdo_query(
            "UPDATE build SET buildduration = 0, testduration = 0
                WHERE id = $id");

        UpgradeBuildDuration($id);
        UpgradeTestDuration();
        $row = pdo_single_row_query($query);
        $buildduration = $row['buildduration'];
        $testduration = $row['testduration'];
        if ($buildduration != 1383) {
            $this->fail(
                "Expected build duration to be 1383, found $buildduration");
            $retval = 1;
        }
        if ($testduration != 48) {
            $this->fail("Expected test duration to be 48, found $testduration");
            $retval = 1;
        }

        pdo_query(
            "UPDATE build
                SET buildduration = $saved_build_duration,
                    testduration = $saved_test_duration
                WHERE id = $id");

        if ($retval == 0) {
            $this->pass('Passed');
        }
        return $retval;
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

    public function testSiteConstraintUpgrade()
    {
        require_once dirname(__FILE__) . '/cdash_test_case.php';
        require_once 'include/common.php';
        require_once 'include/pdo.php';

        $retval = 0;
        $table_name = 'testsite';

        if (config('database.default') == 'pgsql') {
            $create_query = '
                CREATE TABLE "' . $table_name . '" (
                        "id" serial NOT NULL,
                        "name" character varying(255) DEFAULT \'\' NOT NULL,
                        "ip" character varying(255) DEFAULT \'\' NOT NULL,
                        "latitude" character varying(10) DEFAULT \'\' NOT NULL,
                        "longitude" character varying(10) DEFAULT \'\' NOT NULL,
                        "outoforder" smallint DEFAULT \'0\' NOT NULL,
                        PRIMARY KEY ("id")
                        )';
        } else {
            // MySQL
            $create_query = "
                CREATE TABLE `$table_name` (
                        `id` int(11) NOT NULL auto_increment,
                        `name` varchar(255) NOT NULL default '',
                        `ip` varchar(255) NOT NULL default '',
                        `latitude` varchar(10) NOT NULL default '',
                        `longitude` varchar(10) NOT NULL default '',
                        `outoforder` tinyint(4) NOT NULL default '0',
                        PRIMARY KEY  (`id`)
                        )";
        }

        // Create testing table.
        if (!pdo_query($create_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }

        // Find the largest siteid from the real site table.
        $row = pdo_single_row_query(
            'SELECT id FROM site ORDER BY id DESC LIMIT 1');
        $i = $row['id'];
        $dupes = array();
        $keepers = array();

        // Insert sites into our testing table that will violate
        // the unique constraint on the name column.
        //
        // Case 1: No lat/lon info, so the lowest number siteid will be kept.
        $nolatlon_keeper = ++$i;
        $keepers[] = $nolatlon_keeper;
        $insert_query = "
            INSERT INTO $table_name
            (id, name, ip)
            VALUES
            ($nolatlon_keeper, 'case1_site', '')";
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        $nolatlon_dupe = ++$i;
        $dupes[] = $nolatlon_dupe;
        $insert_query = "
            INSERT INTO $table_name
            (id, name, ip)
            VALUES
            ($nolatlon_dupe, 'case1_site', '')";
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }

        // Case 2: Lat/lon info is present, so the newest build with this data
        // will be kept.
        $latlon_dupe1 = ++$i; // Has lat/lon, but not the newest.
        $dupes[] = $latlon_dupe1;
        $insert_query = "
            INSERT INTO $table_name
            (id, name, ip, latitude, longitude)
            VALUES
            ($latlon_dupe1, 'case2_site', '', '40.70', '-74.00')";
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        $latlon_keeper = ++$i; // Has newest lat/lon, will be kept.
        $keepers[] = $latlon_keeper;
        $insert_query = "
            INSERT INTO $table_name
            (id, name, ip, latitude, longitude)
            VALUES
            ($latlon_keeper, 'case2_site', '', '40.71', '-73.97')";
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        $latlon_dupe2 = ++$i; // Does not have lat/lon.
        $dupes[] = $latlon_dupe2;
        $insert_query = "
            INSERT INTO $table_name
            (id, name, ip)
            VALUES
            ($latlon_dupe2, 'case2_site', '')";
        if (!pdo_query($insert_query)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }

        // We also need to verify that siteids in other tables get updated
        // properly as duplicates are removed.
        $tables_to_update = array('build', 'build2grouprule', 'site2user',
            'client_job', 'client_site2cmake', 'client_site2compiler',
            'client_site2library', 'client_site2program',
            'client_site2project');
        foreach ($tables_to_update as $table_to_update) {
            foreach ($dupes as $dupe) {
                if ($table_to_update === 'build') {
                    // Handle unique constraint here.
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid, uuid)
                        VALUES ($dupe, '$dupe')";
                } elseif ($table_to_update === 'client_job') {
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid, scheduleid, osid, cmakeid, compilerid)
                        VALUES ($dupe, 0, 0, 0, 0)";
                } elseif ($table_to_update === 'client_site2compiler') {
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid, generator)
                        VALUES ($dupe, 'asdf')";
                } elseif ($table_to_update === 'client_site2library') {
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid, include)
                        VALUES ($dupe, 'asdf')";
                } elseif ($table_to_update === 'client_site2program') {
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid, name, version, path)
                        VALUES ($dupe, 'asdf', 'asdf', 'asdf')";
                } else {
                    $insert_query =
                        "INSERT INTO $table_to_update (siteid) VALUES ($dupe)";
                }
                if (!pdo_query($insert_query)) {
                    $this->fail('pdo_query returned false');
                    $retval = 1;
                }
            }
        }

        // Run the upgrade function.
        AddUniqueConstraintToSiteTable($table_name);

        // Verify that all of the keepers still exist.
        foreach ($keepers as $keeper) {
            $count_query = "
                SELECT COUNT(id) AS numsites FROM $table_name WHERE id=$keeper";
            $count_results = pdo_single_row_query($count_query);
            if ($count_results['numsites'] != 1) {
                $this->fail(
                    'Expected 1 site, found ' . $count_results['numsites']);
                $retval = 1;
            }
        }

        // Verify that none of the duplicates still exist.
        foreach ($dupes as $dupe) {
            $count_query = "
                SELECT COUNT(id) AS numsites FROM $table_name WHERE id=$dupe";
            $count_results = pdo_single_row_query($count_query);
            if ($count_results['numsites'] != 0) {
                $this->fail(
                    'Expected 0 site, found ' . $count_results['numsites']);
                $retval = 1;
            }
        }

        // Verify that the other references were also updated properly.
        foreach ($tables_to_update as $table_to_update) {
            foreach ($keepers as $keeper) {
                $expected_matches = 1;
                if ($keeper === $latlon_keeper) {
                    $expected_matches = 2;
                }
                $count_query = "SELECT COUNT(siteid) AS numsites
                    FROM $table_to_update WHERE siteid=$keeper";
                $count_results = pdo_single_row_query($count_query);
                if ($count_results['numsites'] != $expected_matches) {
                    $this->fail(
                        "Expected $expected_matches match for siteid $keeper
                            in $table_to_update, found " .
                        $count_results['numsites']);
                    $retval = 1;
                }
            }
            foreach ($dupes as $dupe) {
                $count_query = "SELECT COUNT(siteid) AS numsites
                    FROM $table_to_update WHERE siteid=$dupe";
                $count_results = pdo_single_row_query($count_query);
                if ($count_results['numsites'] != 0) {
                    $this->fail("Expected 0 matches for siteid $dupe
                            in $table_to_update, found " .
                        $count_results['numsites']);
                    $retval = 1;
                }
            }
        }

        // Remove any testing data that we inserted in the existing tables.
        foreach ($tables_to_update as $table_to_update) {
            foreach ($keepers as $keeper) {
                pdo_query("DELETE FROM $table_to_update WHERE siteid=$keeper");
            }
            foreach ($dupes as $dupe) {
                pdo_query("DELETE FROM $table_to_update WHERE siteid=$dupe");
            }
        }

        // Drop the testing table.
        pdo_query("DROP TABLE $table_name");

        if ($retval == 0) {
            $this->pass('Passed');
        }
        return $retval;
    }

    public function testBuild2ConfigureUpgrade()
    {
        require_once dirname(__FILE__) . '/cdash_test_case.php';
        require_once 'include/common.php';
        require_once 'include/pdo.php';

        $retval = 0;
        $configure_table_name = 'testconfigure';
        $b2c_table_name = 'testbuild2configure';
        $error_table_name = 'testconfigureerror';

        if (config('database.default') == 'pgsql') {
            $create_query_1 = '
                CREATE TABLE "' . $configure_table_name . '" (
                "id" serial NOT NULL,
                "buildid" integer DEFAULT \'0\' NOT NULL,
                "command" text NOT NULL,
                "log" text NOT NULL,
                "status" smallint DEFAULT \'0\' NOT NULL,
                "warnings" smallint DEFAULT \'-1\',
                "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
                "endtime" timestamp(0) DEFAULT \'1980-01-01 00:00:00\' NOT NULL,
                "crc32" bigint DEFAULT NULL,
                PRIMARY KEY ("id"))';
            $create_query_2 = '
                CREATE TABLE "' . $b2c_table_name . '" (
                "configureid" integer DEFAULT \'0\' NOT NULL,
                "buildid" integer DEFAULT \'0\' NOT NULL,
                "starttime" timestamp(0) DEFAULT CURRENT_TIMESTAMP NOT NULL,
                "endtime" timestamp(0) DEFAULT \'1980-01-01 00:00:00\' NOT NULL,
                PRIMARY KEY ("buildid"))';
            $create_query_3 = '
                CREATE TABLE "' . $error_table_name . '" (
                  "buildid" bigint NOT NULL,
                  "type" smallint NOT NULL,
                  "text" text NOT NULL
                )';
        } else {
            // MySQL
            $create_query_1 = "
                CREATE TABLE `$configure_table_name` (
                `id` int(11) NOT NULL auto_increment,
                `buildid` bigint(11) NOT NULL,
                `command` text NOT NULL,
                `log` MEDIUMTEXT NOT NULL,
                `status` tinyint(4) NOT NULL DEFAULT '0',
                `warnings` smallint(6) DEFAULT '-1',
                `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
                `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
                `crc32` bigint(20) NOT NULL,
                PRIMARY KEY  (`id`))";
            $create_query_2 = "
                CREATE TABLE `$b2c_table_name` (
                `configureid` int(11) NOT NULL default '0',
                `buildid` int(11) NOT NULL default '0',
                `starttime` timestamp NOT NULL default '1980-01-01 00:00:00',
                `endtime` timestamp NOT NULL default '1980-01-01 00:00:00',
                PRIMARY KEY  (`buildid`))";
            $create_query_3 = "
                CREATE TABLE `$error_table_name` (
                `buildid` int(11) NOT NULL,
                `type` tinyint(4) NOT NULL,
                `text` text NOT NULL,
                KEY `buildid` (`buildid`),
                KEY `type` (`type`))";
        }

        // Create testing tables.
        if (!pdo_query($create_query_1)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        if (!pdo_query($create_query_2)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }
        if (!pdo_query($create_query_3)) {
            $this->fail('pdo_query returned false');
            $retval = 1;
        }

        // Insert testing data in configure table.
        // Two rows that duplicate each other
        $dupe_buildids = array(1, 2);
        foreach ($dupe_buildids as $buildid) {
            pdo_query(
                "INSERT INTO $configure_table_name
                (buildid, command, log, status, crc32)
                VALUES ($buildid, 'dupe command', 'dupe log', 0, 0)");
            pdo_query(
                "INSERT INTO $error_table_name
                (buildid, type, text)
                VALUES ($buildid, 0, 'dupe error')");
        }

        // ...and one that does not.
        pdo_query(
            "INSERT INTO $configure_table_name
            (buildid, command, log, status, crc32)
            VALUES (3, 'unique command', 'unique log', 0, 0)");
        pdo_query(
            "INSERT INTO $error_table_name
            (buildid, type, text)
            VALUES (3, 0, 'unique error')");

        // Verify that the right number of testing rows made it into the database.
        foreach (array($configure_table_name, $error_table_name) as $table_name) {
            $count_query = "
                SELECT COUNT(*) AS numrows FROM $table_name";
            $count_results = pdo_single_row_query($count_query);
            if ($count_results['numrows'] != 3) {
                $this->fail(
                    "Expected 3 rows in $table_name, found " . $count_results['numrows']);
                $retval = 1;
            }
        }

        // Run the build2configure upgrade function.
        PopulateBuild2Configure($configure_table_name, $b2c_table_name);

        // Verify that we now have only two configure rows.
        $count_query = "
            SELECT COUNT(id) AS numrows FROM $configure_table_name";
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numrows'] != 2) {
            $this->fail(
                'Expected 2 configure rows, found ' . $count_results['numrows']);
            $retval = 1;
        }

        // Verify that we have three build2configure rows.
        $count_query = "
            SELECT COUNT(buildid) AS numrows FROM $b2c_table_name";
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numrows'] != 3) {
            $this->fail(
                'Expected three b2c rows, found ' . $count_results['numrows']);
            $retval = 1;
        }

        // Run the configureerror upgrade function.
        UpgradeConfigureErrorTable($error_table_name, $b2c_table_name);

        // Verify only two configureerror rows.
        $count_query = "
            SELECT COUNT(*) AS numrows FROM $error_table_name";
        $count_results = pdo_single_row_query($count_query);
        if ($count_results['numrows'] != 2) {
            $this->fail(
                'Expected 2 configureerror rows, found ' . $count_results['numrows']);
            $retval = 1;
        }

        // Drop testing tables.
        pdo_query("DROP TABLE $configure_table_name");
        pdo_query("DROP TABLE $b2c_table_name");
        pdo_query("DROP TABLE $error_table_name");

        if ($retval == 0) {
            $this->pass('Passed');
        }
        return $retval;
    }

    public function testPopulateTestDuration()
    {
        require_once dirname(__FILE__) . '/cdash_test_case.php';
        require_once 'include/common.php';
        require_once 'include/pdo.php';

        $config = Config::getInstance();
        $pdo = Database::getInstance()->getPdo();

        $build_table_name = 'testbuild';
        $btt_table_name = 'testbuildtesttime';

        if (config('database.default') == 'pgsql') {
            $create_query_1 = '
                CREATE TABLE "' . $build_table_name . '" (
                "id" serial NOT NULL,
                "testduration" integer DEFAULT \'0\' NOT NULL,
                PRIMARY KEY ("id"))';
            $create_query_2 = '
                CREATE TABLE "' . $btt_table_name . '" (
                "buildid" integer DEFAULT \'0\' NOT NULL,
                "time" numeric(7,2) DEFAULT \'0.00\' NOT NULL,
                PRIMARY KEY ("buildid"))';
        } else {
            // MySQL
            $create_query_1 = "
                CREATE TABLE `$build_table_name` (
                `id` int(11) NOT NULL auto_increment,
                `testduration` int(11) NOT NULL default '0',
                PRIMARY KEY  (`id`))";
            $create_query_2 = "
                CREATE TABLE `$btt_table_name` (
                `buildid` int(11) NOT NULL default '0',
                `time` float(7,2) NOT NULL default '0.00',
                PRIMARY KEY  (`buildid`))";
        }

        // Create testing tables.
        if ($pdo->exec($create_query_1) === false) {
            $this->fail('create build table returned false');
        }
        if ($pdo->exec($create_query_2) === false) {
            $this->fail('create btt table returned false');
        }

        // Insert some builds and test times.
        $buildids = [1, 2];
        foreach ($buildids as $buildid) {
            $pdo->exec(
                "INSERT INTO $build_table_name
                    (id, testduration)
                    VALUES ($buildid, 0)");
            $pdo->exec(
                "INSERT INTO $btt_table_name
                    (buildid, time)
                    VALUES ($buildid, $buildid)");
        }

        // Also insert one build with no test timing.
        $pdo->exec(
            "INSERT INTO $build_table_name
                (id, testduration)
                VALUES (3, 0)");

        // Verify that the right number of testing rows made it into the database.
        $expected_results = [$build_table_name => 3, $btt_table_name => 2];
        foreach ($expected_results as $table_name => $expected) {
            $stmt = $pdo->query("SELECT COUNT(*) AS numrows FROM $table_name");
            $found = $stmt->fetchColumn();
            if ($found != $expected) {
                $this->fail(
                    "Expected $expected rows in $table_name, found $found");
            }
        }

        // Run the upgrade function.
        PopulateTestDuration($btt_table_name, $build_table_name);

        // Verify results in build table.
        $expected = [
            1 => 1,
            2 => 2,
            3 => 0
        ];
        $stmt = $pdo->query(
            "SELECT id, testduration FROM $build_table_name");
        $num_rows = 0;
        while ($row = $stmt->fetch()) {
            $num_rows++;
            $buildid = $row['id'];
            if (!array_key_exists($buildid, $expected)) {
                $this->fail("No row found for build #$buildid");
            }
            if ($expected[$buildid] != $row['testduration']) {
                $this->fail("Expected {$expected[$buildid]} but found {$row['testduration']} for build #$buildid");
            }
        }
        if ($num_rows != 3) {
            $this->fail("Expected 3 rows found but $num_rows in build table");
        }

        // Verify that the btt table was cleaned out.
        $stmt = $pdo->query("SELECT COUNT(*) FROM $btt_table_name");
        $num_rows = $stmt->fetchColumn();
        if ($num_rows != 0) {
            $this->fail("Expected zero rows in btt table but found $num_rows");
        }

        // Drop testing tables.
        $pdo->exec(
            "DROP TABLE $build_table_name");
        pdo_query("DROP TABLE $btt_table_name");
    }

    public function testUpdateDynamicRules()
    {
        // Populate some testing data.
        $buildgroup = new BuildGroup();
        $buildgroup->SetProjectId(1);
        $buildgroup->SetName('TempLatest');
        $buildgroup->SetType('Latest');
        $buildgroup->Save();

        $buildgrouprule = new BuildGroupRule();
        $buildgrouprule->GroupId = $buildgroup->GetId();
        $buildgrouprule->SiteId = 0;
        $buildgrouprule->ParentGroupId = 1;
        $buildgrouprule->StartTime = gmdate(FMT_DATETIME);
        $buildgrouprule->BuildName = 'this needs wildcards';
        $buildgrouprule->BuildType = 'this should be blank';
        $buildgrouprule->Save();

        // Run the upgrade function.
        UpdateDynamicRules();

        // Verify that the outdated rule gets fixed.
        $this->PDO = Database::getInstance();
        $stmt = $this->PDO->prepare(
            'SELECT * FROM build2grouprule WHERE groupid = :groupid');
        $this->PDO->execute($stmt, [':groupid' => $buildgroup->GetId()]);
        $row = $stmt->fetch();
        $this->assertEqual('%this needs wildcards%', $row['buildname']);
        $this->assertEqual('', $row['buildtype']);

        // Delete testing data.
        $buildgroup->Delete();
        $buildgrouprule->BuildName = $row['buildname'];
        $buildgrouprule->BuildType = '';
        $buildgrouprule->Delete(true);
    }
}
