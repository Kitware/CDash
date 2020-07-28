<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/
require_once 'include/pdo.php';
include_once 'include/common.php';
include_once 'include/upgrade_functions.php';

use CDash\Config;

$config = Config::getInstance();

@set_time_limit(0);

$policy = checkUserPolicy(Auth::id(), 0); // only admin
if ($policy !== true) {
    return $policy;
}

$xml = begin_XML_for_XSLT();
$xml .= '<backurl>user.php</backurl>';
$xml .= '<title>CDash - Maintenance</title>';
$xml .= '<menutitle>CDash</menutitle>';
$xml .= '<menusubtitle>Maintenance</menusubtitle>';

// Should be the database version not the current on
$version = pdo_query('SELECT major,minor FROM version');
$version_array = pdo_fetch_array($version);
$xml .= '<minversion>' . $version_array['major'] . '.' . $version_array['minor'] . '</minversion>';

@$CreateDefaultGroups = $_POST['CreateDefaultGroups'];
@$AssignBuildToDefaultGroups = $_POST['AssignBuildToDefaultGroups'];
@$FixBuildBasedOnRule = $_POST['FixBuildBasedOnRule'];
@$DeleteBuildsWrongDate = $_POST['DeleteBuildsWrongDate'];
@$CheckBuildsWrongDate = $_POST['CheckBuildsWrongDate'];
@$ComputeTestTiming = $_POST['ComputeTestTiming'];
@$ComputeUpdateStatistics = $_POST['ComputeUpdateStatistics'];

@$Upgrade = $_POST['Upgrade'];
@$Cleanup = $_POST['Cleanup'];

if (!$config->get('CDASH_DB_TYPE')) {
    $db_type = 'mysql';
} else {
    $db_type = $config->get('CDASH_DB_TYPE');
}

if (isset($_GET['upgrade-tables'])) {
    // Apply all the patches
    foreach (glob($config->get('CDASH_ROOT_DIR') . "/sql/$db_type/cdash-upgrade-*.sql") as $filename) {
        $file_content = file($filename);
        $query = '';
        foreach ($file_content as $sql_line) {
            $tsl = trim($sql_line);

            if (($sql_line != '') && (substr($tsl, 0, 2) != '--') && (substr($tsl, 0, 1) != '#')) {
                $query .= $sql_line;
                if (preg_match("/;\s*$/", $sql_line)) {
                    $query = str_replace(';', '', "$query");
                    $result = pdo_query($query);
                    if (!$result) {
                        if ($db_type != 'pgsql') {
                            // postgresql < 9.1 doesn't know CREATE TABLE IF NOT EXISTS so we don't die

                            die(pdo_error());
                        }
                    }
                    $query = '';
                }
            }
        }
    }
    return;
}

// 2.2 Upgrade
if (isset($_GET['upgrade-2-2'])) {
    AddTableIndex('updatefile', 'author');

    // We need to move the buildupdate build ids to the build2update table
    $query = pdo_query('SELECT buildid FROM buildupdate');
    while ($query_array = pdo_fetch_array($query)) {
        pdo_query("INSERT INTO build2update (buildid,updateid) VALUES ('" . $query_array['buildid'] . "','" . $query_array['buildid'] . "')");
    }
    RemoveTableIndex('buildupdate', 'buildid');
    RenameTableField('buildupdate', 'buildid', 'id', 'int(11)', 'bigint', '0');
    AddTablePrimaryKey('buildupdate', 'id');
    ModifyTableField('buildupdate', 'id', 'int(11)', 'bigint', '', true, true);
    RenameTableField('updatefile', 'buildid', 'updateid', 'int(11)', 'bigint', '0');

    AddTableField('site', 'outoforder', 'tinyint(1)', 'smallint', '0');

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-2');
    return;
}

// 2.4 Upgrade
if (isset($_GET['upgrade-2-4'])) {
    // Support for subproject groups
    AddTableField('subproject', 'groupid', 'int(11)', 'bigint', '0');
    AddTableIndex('subproject', 'groupid');
    AddTableField('subprojectgroup', 'position', 'int(11)', 'bigint', '0');
    AddTableIndex('subprojectgroup', 'position');
    RemoveTableField('subproject', 'core');
    RemoveTableField('project', 'coveragethreshold2');

    // Support for larger types
    ModifyTableField('buildfailure', 'workingdirectory', 'VARCHAR( 512)', 'VARCHAR( 512 )', '', true, false);
    ModifyTableField('buildfailure', 'outputfile', 'VARCHAR( 512)', 'VARCHAR( 512 )', '', true, false);

    // Support for parent builds
    AddTableField('build', 'parentid', 'int(11)', 'int', '0');
    AddTableIndex('build', 'parentid');

    // Cache configure results similar to build & test
    AddTableField('build', 'configureerrors', 'smallint(6)', 'smallint', '-1');
    AddTableField('build', 'configurewarnings', 'smallint(6)', 'smallint', '-1');

    // Add new multi-column index to build table.
    // This improves the rendering speed of overview.php.
    $multi_index = array('projectid', 'parentid', 'starttime');
    AddTableIndex('build', $multi_index);

    // Support for dynamic BuildGroups.
    AddTableField('buildgroup', 'type', 'varchar(20)', 'character varying(20)', 'Daily');
    AddTableField('build2grouprule', 'parentgroupid', 'int(11)', 'bigint', '0');

    // Support for pull request notifications.
    AddTableField('build', 'notified', 'tinyint(1)', 'smallint', '0');

    // Better caching of buildfailures.
    UpgradeBuildFailureTable('buildfailure', 'buildfailuredetails');
    AddTableIndex('buildfailure', 'detailsid');

    // Add key to label2test.
    // This speeds up viewTest API for builds with lots of tests & labels.
    AddTableIndex('label2test', 'testid');

    // Better caching of build & test time, particularly for parent builds.
    $query = 'SELECT configureduration FROM build LIMIT 1';
    $dbTest = pdo_query($query);
    if ($dbTest === false) {
        AddTableField('build', 'configureduration', 'float(7,2)',
            'numeric(7,2)', '0.00');
        UpgradeConfigureDuration();
        UpgradeTestDuration();
    }
    // Distinguish build step duration from (end time - start time).
    $query = 'SELECT buildduration FROM build LIMIT 1';
    $dbTest = pdo_query($query);
    if ($dbTest === false) {
        AddTableField('build', 'buildduration', 'int(11)', 'integer', '0');
        UpgradeBuildDuration();
    }

    // Support for marking a build as "done".
    AddTableField('build', 'done', 'tinyint(1)', 'smallint', '0');

    // Add a unique uuid field to the build table.
    $uuid_check = pdo_query('SELECT uuid FROM build LIMIT 1');
    if ($uuid_check === false) {
        AddTableField('build', 'uuid', 'varchar(36)', 'character varying(36)', false);
        if ($db_type === 'pgsql') {
            pdo_query('ALTER TABLE build ADD UNIQUE (uuid)');
        } else {
            pdo_query('ALTER TABLE build ADD UNIQUE KEY (uuid)');
        }

        // Also add a new unique constraint to the subproject table.
        if ($db_type === 'pgsql') {
            pdo_query('ALTER TABLE subproject ADD UNIQUE (name, projectid, endtime)');
            pdo_query('CREATE INDEX "subproject_unique2" ON "subproject" ("name", "projectid", "endtime")');
        } else {
            pdo_query('ALTER TABLE subproject ADD UNIQUE KEY (name, projectid, endtime)');
        }
    }

    // Support for subproject path.
    AddTableField('subproject', 'path', 'varchar(512)', 'character varying(512)', '');

    // Remove the errorlog from the DB (we're all log files now).
    pdo_query('DROP TABLE IF EXISTS errorlog');

    // Option to pass label filters from index.php to test pages.
    AddTableField('project', 'sharelabelfilters', 'tinyint(1)', 'smallint', '0');

    // Summarize the number of dynamic analysis defects each build found.
    PopulateDynamicAnalysisSummaryTable();

    // Add index to buildupdate::revision in support of this filter.
    AddTableIndex('buildupdate', 'revision');

    // Store CTEST_CHANGE_ID in the build table.
    AddTableField('build', 'changeid', 'varchar(40)', 'character varying(40)', '');

    // Add unique constraints to the *diff tables.
    AddUniqueConstraintToDiffTables();

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-4');
    return;
}

// 2.6 Upgrade
if (isset($_GET['upgrade-2-6'])) {
    // Add index to label2test::buildid to improve performance of remove_build()
    AddTableIndex('label2test', 'buildid');

    // Expand size of password field to 255 characters.
    if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
        ModifyTableField('password', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
        ModifyTableField('user', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
        ModifyTableField('usertemp', 'password', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
    }

    // Restructure configure table.
    // This reduces the footprint of this table and allows multiple builds
    // to share a configure.
    if (!pdo_query('SELECT id FROM configure LIMIT 1')) {
        // Add id and crc32 columns to configure table.
        if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
            pdo_query(
                'ALTER TABLE configure
                ADD id int(11) NOT NULL AUTO_INCREMENT,
                ADD crc32 bigint(20) NOT NULL DEFAULT \'0\',
                ADD PRIMARY KEY(id)');
        } else {
            pdo_query(
                'ALTER TABLE configure
                ADD id SERIAL NOT NULL,
                ADD crc32 BIGINT DEFAULT \'0\' NOT NULL,
                ADD PRIMARY KEY (id)');
        }

        // Populate build2configure table.
        PopulateBuild2Configure('configure', 'build2configure');

        // Add unique constraint to crc32 column.
        if ($db_type === 'pgsql') {
            pdo_query('ALTER TABLE configure ADD UNIQUE (crc32)');
        } else {
            pdo_query('ALTER TABLE configure ADD UNIQUE KEY (crc32)');
        }

        // Remove columns from configure that have been moved to build2configure.
        if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
            pdo_query('ALTER TABLE "configure"
                        DROP COLUMN "buildid",
                        DROP COLUMN "starttime",
                        DROP COLUMN "endtime"');
        } else {
            pdo_query('ALTER TABLE configure
                        DROP buildid,
                        DROP starttime,
                        DROP endtime');
        }

        // Change configureerror to use configureid instead of buildid.
        UpgradeConfigureErrorTable('configureerror', 'build2configure');
    }

    // Support for authenticated submissions.
    AddTableField('project', 'authenticatesubmissions', 'tinyint(1)', 'smallint', '0');

    // Add position field to subproject table.
    AddTableField('subproject', 'position', 'smallint(6) unsigned', 'smallint', '0');

    // Support for bugtracker issue creation.
    AddTableField('project', 'bugtrackernewissueurl', 'varchar(255)', 'character varying(255)', '');
    AddTableField('project', 'bugtrackertype', 'varchar(16)', 'character varying(16)', '');

    // Add new unique constraint to the site table.
    AddUniqueConstraintToSiteTable('site');

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-6');
    return;
}

// 2.8 Upgrade
if (isset($_GET['upgrade-2-8'])) {
    // Add a 'recheck' field to the pendingsubmission table.
    AddTableField('pending_submissions', 'recheck', 'tinyint(1)', 'smallint', '0');

    // Migrate from buildtesttime.time to build.testduration
    if (!pdo_query('SELECT testduration FROM build LIMIT 1')) {
        // Add testduration column to build table.
        AddTableField('build', 'testduration', 'int(11)', 'integer', '0');

        // Migrate values from buildtesttime.time to build.testduration.
        PopulateTestDuration();

        // Change build.configureduration from float to int
        ModifyTableField('build', 'configureduration', 'int(11)', 'integer', '0', true, false);
    }

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-8');
    $_GET['upgrade-3-0'] = 1;
}

// 3.0 Upgrade
if (isset($_GET['upgrade-3-0'])) {

    // Add Laravel required columns to user and password tables.
    AddTableField('user', 'updated_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
    AddTableField('user', 'created_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
    AddTableField('user', 'remember_token', 'varchar(100)', 'character varying(16)', 'NULL');
    AddTableField('password', 'updated_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');
    AddTableField('password', 'created_at', 'TIMESTAMP', 'TIMESTAMP', '1980-01-01 00:00:00');

    // Call artisan to run Laravel database migrations.
    Artisan::call('migrate --force');

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-3-0');
    return;
}

// When adding new tables they should be added to the SQL installation file
// and here as well
if ($Upgrade) {
    // check if the backup directory is writable
    if (!is_writable($config->get('CDASH_BACKUP_DIRECTORY'))) {
        $xml .= '<backupwritable>0</backupwritable>';
    } else {
        $xml .= '<backupwritable>1</backupwritable>';
    }

    // check if the log directory is writable
    if ($config->get('CDASH_LOG_FILE') !== false && !is_writable($config->get('CDASH_LOG_DIRECTORY'))) {
        $xml .= '<logwritable>0</logwritable>';
    } else {
        $xml .= '<logwritable>1</logwritable>';
    }

    // check if the upload directory is writable
    if (!is_writable($config->get('CDASH_UPLOAD_DIRECTORY'))) {
        $xml .= '<uploadwritable>0</uploadwritable>';
    } else {
        $xml .= '<uploadwritable>1</uploadwritable>';
    }

    $xml .= '<upgrade>1</upgrade>';
}

// Compress the test output
if (isset($_POST['CompressTestOutput'])) {
    // Do it slowly so we don't take all the memory
    $query = pdo_query('SELECT count(*) FROM testoutput');
    $query_array = pdo_fetch_array($query);
    $ntests = $query_array[0];
    $ngroup = 1024;
    for ($i = 0; $i < $ntests; $i += $ngroup) {
        $query = pdo_query('SELECT id,output FROM testoutput ORDER BY id ASC LIMIT ' . $ngroup . ' OFFSET ' . $i);
        while ($query_array = pdo_fetch_array($query)) {
            // Try uncompressing to see if it's already compressed
            if (@gzuncompress($query_array['output']) === false) {
                $compressed = pdo_real_escape_string(gzcompress($query_array['output']));
                pdo_query("UPDATE testoutput SET output='" . $compressed . "' WHERE id=" . $query_array['id']);
                echo pdo_error();
            }
        }
    }
}

// Compute the testtime
if ($ComputeTestTiming) {
    @$TestTimingDays = $_POST['TestTimingDays'];
    if ($TestTimingDays != null) {
        $TestTimingDays = pdo_real_escape_numeric($TestTimingDays);
    }
    if (is_numeric($TestTimingDays) && $TestTimingDays > 0) {
        ComputeTestTiming($TestTimingDays);
        $xml .= add_XML_value('alert', 'Timing for tests has been computed successfully.');
    } else {
        $xml .= add_XML_value('alert', 'Wrong number of days.');
    }
}

// Compute the user statistics
if ($ComputeUpdateStatistics) {
    @$UpdateStatisticsDays = $_POST['UpdateStatisticsDays'];
    if ($UpdateStatisticsDays != null) {
        $UpdateStatisticsDays = pdo_real_escape_numeric($UpdateStatisticsDays);
    }
    if (is_numeric($UpdateStatisticsDays) && $UpdateStatisticsDays > 0) {
        ComputeUpdateStatistics($UpdateStatisticsDays);
        $xml .= add_XML_value('alert', 'User statistics has been computed successfully.');
    } else {
        $xml .= add_XML_value('alert', 'Wrong number of days.');
    }
}

/* Cleanup the database */
if ($Cleanup) {
    delete_unused_rows('banner', 'projectid', 'project');
    delete_unused_rows('blockbuild', 'projectid', 'project');
    delete_unused_rows('build', 'projectid', 'project');
    delete_unused_rows('buildgroup', 'projectid', 'project');
    delete_unused_rows('labelemail', 'projectid', 'project');
    delete_unused_rows('project2repositories', 'projectid', 'project');
    delete_unused_rows('dailyupdate', 'projectid', 'project');
    delete_unused_rows('projectrobot', 'projectid', 'project');
    delete_unused_rows('submission', 'projectid', 'project');
    delete_unused_rows('subproject', 'projectid', 'project');
    delete_unused_rows('coveragefilepriority', 'projectid', 'project');
    delete_unused_rows('test', 'projectid', 'project');
    delete_unused_rows('user2project', 'projectid', 'project');
    delete_unused_rows('userstatistics', 'projectid', 'project');

    delete_unused_rows('build2configure', 'buildid', 'build');
    delete_unused_rows('build2note', 'buildid', 'build');
    delete_unused_rows('build2test', 'buildid', 'build');
    delete_unused_rows('buildemail', 'buildid', 'build');
    delete_unused_rows('builderror', 'buildid', 'build');
    delete_unused_rows('builderrordiff', 'buildid', 'build');
    delete_unused_rows('buildfailure', 'buildid', 'build');
    delete_unused_rows('buildinformation', 'buildid', 'build');
    delete_unused_rows('buildnote', 'buildid', 'build');
    delete_unused_rows('buildtesttime', 'buildid', 'build');
    delete_unused_rows('configure', 'id', 'build2configure', 'configureid');
    delete_unused_rows('configureerror', 'configureid', 'configure');
    delete_unused_rows('configureerrordiff', 'buildid', 'build');
    delete_unused_rows('coverage', 'buildid', 'build');
    delete_unused_rows('coveragefilelog', 'buildid', 'build');
    delete_unused_rows('coveragesummary', 'buildid', 'build');
    delete_unused_rows('coveragesummarydiff', 'buildid', 'build');
    delete_unused_rows('dynamicanalysis', 'buildid', 'build');
    delete_unused_rows('label2build', 'buildid', 'build');
    delete_unused_rows('subproject2build', 'buildid', 'build');
    delete_unused_rows('summaryemail', 'buildid', 'build');
    delete_unused_rows('testdiff', 'buildid', 'build');

    delete_unused_rows('dynamicanalysisdefect', 'dynamicanalysisid', 'dynamicanalysis');
    delete_unused_rows('subproject2subproject', 'subprojectid', 'subproject');

    delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
    delete_unused_rows('coveragefile', 'id', 'coverage', 'fileid');
    delete_unused_rows('coveragefile2user', 'fileid', 'coveragefile');

    delete_unused_rows('dailyupdatefile', 'dailyupdateid', 'dailyupdate');
    delete_unused_rows('test2image', 'outputid', 'testoutput');
    delete_unused_rows('testmeasurement', 'outputid', 'testoutput');
    delete_unused_rows('label2test', 'outputid', 'testoutput');

    $xml .= add_XML_value('alert', 'Database cleanup complete.');
}

/* Check the builds with wrong date */
if ($CheckBuildsWrongDate) {
    $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
    $forwarddate = date(FMT_DATETIME, $currentdate);

    $builds = pdo_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
    while ($builds_array = pdo_fetch_array($builds)) {
        $buildid = $builds_array['id'];
        echo $builds_array['name'] . '-' . $builds_array['starttime'] . '<br>';
    }
}

/* Delete the builds with wrong date */
if ($DeleteBuildsWrongDate) {
    $currentdate = time() + 3600 * 24 * 3; // or 3 days away from now
    $forwarddate = date(FMT_DATETIME, $currentdate);

    $builds = pdo_query(
        "SELECT id FROM build WHERE parentid IN (0, -1) AND
          starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
    while ($builds_array = pdo_fetch_array($builds)) {
        $buildid = $builds_array['id'];
        //echo $buildid."<br>";
        remove_build($buildid);
    }
}

if ($FixBuildBasedOnRule) {
    // loop through the list of build2group
    $buildgroups = pdo_query('SELECT * from build2group');
    while ($buildgroup_array = pdo_fetch_array($buildgroups)) {
        $buildid = $buildgroup_array['buildid'];

        $build = pdo_query("SELECT * from build WHERE id='$buildid'");
        $build_array = pdo_fetch_array($build);
        $type = $build_array['type'];
        $name = $build_array['name'];
        $siteid = $build_array['siteid'];
        $projectid = $build_array['projectid'];
        $submittime = $build_array['submittime'];

        $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
        echo pdo_error();
        if (pdo_num_rows($build2grouprule) > 0) {
            $build2grouprule_array = pdo_fetch_array($build2grouprule);
            $groupid = $build2grouprule_array['groupid'];
            pdo_query("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
        }
    }
}

if ($CreateDefaultGroups) {
    // Loop throught the projects
    $n = 0;
    $projects = pdo_query('SELECT id FROM project');
    while ($project_array = pdo_fetch_array($projects)) {
        $projectid = $project_array['id'];

        if (pdo_num_rows(pdo_query("SELECT projectid FROM buildgroup WHERE projectid='$projectid'")) == 0) {
            // Add the default groups
            pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Nightly','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Nightly Builds')");
            $id = pdo_insert_id('buildgroup');
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','1','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            echo pdo_error();
            pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Continuous','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Continuous Builds')");
            $id = pdo_insert_id('buildgroup');
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','2','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Experimental','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Experimental Builds')");
            $id = pdo_insert_id('buildgroup');
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','3','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            $n++;
        }
    }

    $xml .= add_XML_value('alert', $n . ' projects have now default groups.');
} elseif ($AssignBuildToDefaultGroups) {
    // Loop throught the builds
    $builds = pdo_query('SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)');

    while ($build_array = pdo_fetch_array($builds)) {
        $buildid = $build_array['id'];
        $buildtype = $build_array['type'];
        $projectid = $build_array['projectid'];

        $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));

        $groupid = $buildgroup_array['id'];
        pdo_query("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')");
    }

    $xml .= add_XML_value('alert', 'Builds have been added to default groups successfully.');
}

$xml .= '</cdash>';

// Now doing the xslt transition
generate_XSLT($xml, 'upgrade');
