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

include dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'public/login.php';
include_once 'include/common.php';
include 'include/version.php';
include_once 'include/upgrade_functions.php';

use CDash\Config;

$config = Config::getInstance();

@set_time_limit(0);

checkUserPolicy(@$_SESSION['cdash']['loginid'], 0); // only admin

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

if (isset($_GET['upgrade-0-8'])) {
    // Add the index if they don't exist
    $querycrc32 = pdo_query('SELECT crc32 FROM coveragefile LIMIT 1');
    if (!$querycrc32) {
        pdo_query('ALTER TABLE coveragefile ADD crc32 int(11)');
        pdo_query('ALTER TABLE coveragefile ADD INDEX (crc32)');
    }

    // Compression the coverage
    CompressCoverage();
    return;
}

if (isset($_GET['upgrade-1-0'])) {
    $description = pdo_query('SELECT description FROM buildgroup LIMIT 1');
    if (!$description) {
        pdo_query('ALTER TABLE buildgroup ADD description text');
    }
    $cvsviewertype = pdo_query('SELECT cvsviewertype FROM project LIMIT 1');
    if (!$cvsviewertype) {
        pdo_query('ALTER TABLE project ADD cvsviewertype varchar(10)');
    }

    if (pdo_query('ALTER TABLE site2user DROP PRIMARY KEY')) {
        pdo_query('ALTER TABLE site2user ADD INDEX (siteid)');
        pdo_query('ALTER TABLE build ADD INDEX (starttime)');
    }

    // Add test timing as well as key 'name' for test
    $timestatus = pdo_query('SELECT timestatus FROM build2test LIMIT 1');
    if (!$timestatus) {
        pdo_query("ALTER TABLE build2test ADD timemean float(7,2) default '0.00'");
        pdo_query("ALTER TABLE build2test ADD timestd float(7,2) default '0.00'");
        pdo_query("ALTER TABLE build2test ADD timestatus tinyint(4) default '0'");
        pdo_query('ALTER TABLE build2test ADD INDEX (timestatus)');
        // Add timing test fields in the table project
        pdo_query("ALTER TABLE project ADD testtimestd float(3,1) default '4.0'");
        // Add the index name in the table test
        pdo_query('ALTER TABLE test ADD INDEX (name)');
    }

    // Add the testtimethreshold
    if (!pdo_query('SELECT testtimestdthreshold FROM project LIMIT 1')) {
        pdo_query("ALTER TABLE project ADD testtimestdthreshold float(3,1) default '1.0'");
    }

    // Add an option to show the testtime or not
    if (!pdo_query('SELECT showtesttime FROM project LIMIT 1')) {
        pdo_query("ALTER TABLE project ADD showtesttime tinyint(4) default '0'");
    }
    return;
}

if (isset($_GET['upgrade-1-2'])) {
    // Replace the field 'output' in the table test from 'text' to 'mediumtext'
    $result = pdo_query('SELECT output FROM test LIMIT 1');
    $column_meta = $result->getColumnMeta(0);

    $type = isset($column_meta['driver:decl_type']) ? $column_meta['driver:decl_type'] : 'unknown';
    if ($type == 'blob' || $type == 'text') {
        $result = pdo_query('ALTER TABLE test CHANGE output output MEDIUMTEXT');
    }

    // Change the file from blob to longblob
    $result = pdo_query('SELECT file FROM coveragefile LIMIT 1');
    $meta = $result->getColumnMeta(0);
    $length = $meta['len'];
    if ($length == 65535) {
        $result = pdo_query('ALTER TABLE coveragefile CHANGE file file LONGBLOB');
    }

    // Compress the notes
    if (!pdo_query('SELECT crc32 FROM note LIMIT 1')) {
        CompressNotes();
    }

    // Change the dates for the groups from 0000-00-00 to 1000-01-01
    // This is for mySQL
    pdo_query("UPDATE buildgroup SET starttime='1980-01-01 00:00:00' WHERE starttime='0000-00-00 00:00:00'");
    pdo_query("UPDATE buildgroup SET endtime='1980-01-01 00:00:00' WHERE endtime='0000-00-00 00:00:00'");
    pdo_query("UPDATE build2grouprule SET starttime='1980-01-01 00:00:00' WHERE starttime='0000-00-00 00:00:00'");
    pdo_query("UPDATE build2grouprule SET endtime='1980-01-01 00:00:00' WHERE endtime='0000-00-00 00:00:00'");
    pdo_query("UPDATE buildgroupposition SET starttime='1980-01-01 00:00:00' WHERE starttime='0000-00-00 00:00:00'");
    pdo_query("UPDATE buildgroupposition SET endtime='1980-01-01 00:00:00' WHERE endtime='0000-00-00 00:00:00'");

    pdo_query("ALTER TABLE buildgroup MODIFY starttime timestamp NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE buildgroup MODIFY endtime timestamp NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE build2grouprule MODIFY starttime timestamp NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE build2grouprule MODIFY endtime timestamp NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE buildgroupposition MODIFY starttime timestamp NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE buildgroupposition MODIFY endtime timestamp NOT NULL default '1980-01-01 00:00:00'");

    //  Add fields in the project table
    $timestatus = pdo_query('SELECT testtimemaxstatus FROM project LIMIT 1');
    if (!$timestatus) {
        pdo_query("ALTER TABLE project ADD testtimemaxstatus tinyint(4) default '3'");
        pdo_query("ALTER TABLE project ADD emailmaxitems tinyint(4) default '5'");
        pdo_query("ALTER TABLE project ADD emailmaxchars int(11) default '255'");
    }

    // Add summary email
    $summaryemail = pdo_query('SELECT summaryemail FROM buildgroup LIMIT 1');
    if (!$summaryemail) {
        if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
            pdo_query("ALTER TABLE \"buildgroup\" ADD \"summaryemail\" smallint DEFAULT '0'");
        } else {
            pdo_query("ALTER TABLE buildgroup ADD summaryemail tinyint(4) default '0'");
        }
    }

    // Add emailcategory
    $emailcategory = pdo_query('SELECT emailcategory FROM user2project LIMIT 1');
    if (!$emailcategory) {
        if ($config->get('CDASH_DB_TYPE') == 'pgsql') {
            pdo_query("ALTER TABLE \"user2project\" ADD \"emailcategory\" smallint DEFAULT '62'");
        } else {
            pdo_query("ALTER TABLE user2project ADD emailcategory tinyint(4) default '62'");
        }
    }
    return;
}

// 1.4 Upgrade
if (isset($_GET['upgrade-1-4'])) {
    //  Add fields in the project table
    $starttime = pdo_query('SELECT starttime FROM subproject LIMIT 1');
    if (!$starttime) {
        pdo_query("ALTER TABLE subproject ADD starttime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
        pdo_query("ALTER TABLE subproject ADD endtime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
    }

    // Create the right indexes if necessary
    AddTableIndex('buildfailure', 'buildid');
    AddTableIndex('buildfailure', 'type');

    // Create the new table buildfailure arguments if the old one is still there
    if (pdo_query('SELECT buildfailureid FROM buildfailureargument')) {
        pdo_query('DROP TABLE IF EXISTS buildfailureargument');
        pdo_query('CREATE TABLE IF NOT EXISTS `buildfailureargument` (
              `id` bigint(20) NOT NULL auto_increment,
              `argument` varchar(60) NOT NULL,
              PRIMARY KEY  (`id`),
              KEY `argument` (`argument`))');
    }

    AddTableIndex('buildfailureargument', 'argument');

    //  Add fields in the buildgroup table
    AddTableField('project', 'emailadministrator', 'tinyint(4)', 'smallint', '1');
    AddTableField('project', 'showipaddresses', 'tinyint(4)', 'smallint', '1');
    AddTableField('buildgroup', 'includesubprojectotal', 'tinyint(4)', 'smallint', '1');
    AddTableField('project', 'emailredundantfailures', 'tinyint(4)', 'smallint', '0');
    AddTableField('buildfailure2argument', 'place', 'int(11)', 'bigint', '0');

    if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
        pdo_query('ALTER TABLE `builderror` CHANGE `precontext` `precontext` TEXT NULL');
        pdo_query('ALTER TABLE `builderror` CHANGE `postcontext` `postcontext` TEXT NULL');
    }

    ModifyTableField('buildfailureargument', 'argument', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
    ModifyTableField('buildfailure', 'exitcondition', 'VARCHAR( 255 )', 'VARCHAR( 255 )', '', true, false);
    ModifyTableField('buildfailure', 'language', 'VARCHAR( 64 )', 'VARCHAR( 64 )', '', true, false);
    ModifyTableField('buildfailure', 'sourcefile', 'VARCHAR( 512)', 'VARCHAR( 512 )', '', true, false);
    RemoveTableField('buildfailure', 'arguments');
    ModifyTableField('configure', 'log', 'MEDIUMTEXT', 'TEXT', '', true, false);

    AddTableIndex('coverage', 'covered');
    AddTableIndex('build2grouprule', 'starttime');
    AddTableIndex('build2grouprule', 'endtime');
    AddTableIndex('build2grouprule', 'buildtype');
    AddTableIndex('build2grouprule', 'buildname');
    AddTableIndex('build2grouprule', 'expected');
    AddTableIndex('build2grouprule', 'siteid');
    RemoveTableIndex('build2note', 'buildid');
    AddTableIndex('build2note', 'buildid');
    AddTableIndex('build2note', 'noteid');
    AddTableIndex('user2project', 'cvslogin');
    AddTableIndex('user2project', 'emailtype');
    AddTableIndex('user', 'email');
    AddTableIndex('project', 'public');
    AddTableIndex('buildgroup', 'starttime');
    AddTableIndex('buildgroup', 'endtime');
    AddTableIndex('buildgroupposition', 'position');
    AddTableIndex('buildgroupposition', 'starttime');
    AddTableIndex('buildgroupposition', 'endtime');
    AddTableIndex('dailyupdate', 'date');
    AddTableIndex('dailyupdate', 'projectid');
    AddTableIndex('builderror', 'type');
    AddTableIndex('build', 'starttime');
    AddTableIndex('build', 'submittime');

    RemoveTableIndex('build', 'siteid');
    AddTableIndex('build', 'siteid');
    AddTableIndex('build', 'name');
    AddTableIndex('build', 'stamp');
    AddTableIndex('build', 'type');
    AddTableIndex('project', 'name');
    AddTableIndex('site', 'name');

    ModifyTableField('image', 'id', 'BIGINT( 11 )', 'BIGINT', '', true, false);
    RemoveTableIndex('image', ' id');
    RemoveTablePrimaryKey('image');
    AddTablePrimaryKey('image', 'id');
    ModifyTableField('image', 'id', 'BIGINT( 11 )', 'BIGINT', '', true, true);

    ModifyTableField('dailyupdate', 'id', 'BIGINT( 11 )', 'BIGINT', '', true, false);
    RemoveTableIndex('dailyupdate', ' buildid');
    RemoveTablePrimaryKey('dailyupdate');
    AddTablePrimaryKey('dailyupdate', 'id');
    ModifyTableField('dailyupdate', 'id', 'BIGINT( 11 )', 'BIGINT', '', true, true);

    ModifyTableField('dynamicanalysisdefect', 'value', 'INT', 'INT', '0', true, false);

    RemoveTablePrimaryKey('test2image');
    AddTableIndex('test2image', 'imgid');
    AddTableIndex('test2image', 'testid');

    ModifyTableField('image', 'checksum', 'BIGINT( 20 )', 'BIGINT', '', true, false);
    ModifyTableField('note ', 'crc32', 'BIGINT( 20 )', 'BIGINT', '', true, false);
    ModifyTableField('test ', 'crc32', 'BIGINT( 20 )', 'BIGINT', '', true, false);
    ModifyTableField('coveragefile ', 'crc32', 'BIGINT( 20 )', 'BIGINT', '', true, false);

    // Remove duplicates in buildfailureargument
    //pdo_query("DELETE FROM buildfailureargument WHERE id NOT IN (SELECT buildfailureid as id FROM buildfailure2argument)");

    AddTableField('project', 'displaylabels', 'tinyint(4)', 'smallint', '1');
    AddTableField('project', 'autoremovetimeframe', 'int(11)', 'bigint', '0');
    AddTableField('project', 'autoremovemaxbuilds', 'int(11)', 'bigint', '300');
    AddTableIndex('coveragefilelog', 'line');

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-1-4');
    return;
}

// 1.6 Upgrade
if (isset($_GET['upgrade-1-6'])) {
    if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
        pdo_query("ALTER TABLE configure CHANGE starttime starttime TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE buildupdate CHANGE starttime starttime TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query('ALTER TABLE test CHANGE output output MEDIUMBLOB NOT NULL '); // change it to blob (cannot do that in PGSQL)
        pdo_query("ALTER TABLE updatefile CHANGE checkindate checkindate TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE build2note CHANGE time time TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE buildemail CHANGE time time TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
    }

    RemoveTableField('project', 'emailbuildmissing');
    AddTableField('project', 'displaylabels', 'tinyint(4)', 'smallint', '1');
    AddTableField('project', 'autoremovetimeframe', 'int(11)', 'bigint', '0');
    AddTableField('project', 'autoremovemaxbuilds', 'int(11)', 'bigint', '300');
    AddTableField('updatefile', 'status', 'VARCHAR(12)', 'VARCHAR( 12 )', '');
    AddTableField('project', 'bugtrackerfileurl', 'VARCHAR(255)', 'VARCHAR( 255 )', '');
    AddTableField('repositories', 'username', 'VARCHAR(50)', 'VARCHAR( 50 )', '');
    AddTableField('repositories', 'password', 'VARCHAR(50)', 'VARCHAR( 50 )', '');
    AddTableIndex('coveragefilelog', 'line');
    AddTableIndex('dailyupdatefile', 'author');

    RenameTableField('testdiff', 'difference', 'difference_positive', 'int(11)', 'bigint', '0');
    AddTableField('testdiff', 'difference_negative', 'int(11)', 'bigint', '0');
    AddTableIndex('testdiff', 'difference_positive');
    AddTableIndex('testdiff', 'difference_negative');
    AddTableField('build2test', 'newstatus', 'tinyint(4)', 'smallint', '0');
    AddTableIndex('build2test', 'newstatus');

    RenameTableField('builderrordiff', 'difference', 'difference_positive', 'int(11)', 'bigint', '0');
    AddTableField('builderrordiff', 'difference_negative', 'int(11)', 'bigint', '0');
    AddTableIndex('builderrordiff', 'difference_positive');
    AddTableIndex('builderrordiff', 'difference_negative');

    AddTableField('builderror', 'crc32', 'bigint(20)', 'BIGINT', '0');
    AddTableField('builderror', 'newstatus', 'tinyint(4)', 'smallint', '0');
    AddTableIndex('builderror', 'crc32');
    AddTableIndex('builderror', 'newstatus');

    AddTableField('buildfailure', 'crc32', 'bigint(20)', 'BIGINT', '0');
    AddTableField('buildfailure', 'newstatus', 'tinyint(4)', 'smallint', '0');
    AddTableIndex('buildfailure', 'crc32');
    AddTableIndex('buildfailure', 'newstatus');

    AddTableField('client_jobschedule', 'repository', 'VARCHAR(512)', 'VARCHAR(512)', '');
    AddTableField('client_jobschedule', 'module', 'VARCHAR(255)', 'VARCHAR(255)', '');
    AddTableField('client_jobschedule', 'buildnamesuffix', 'VARCHAR(255)', 'VARCHAR(255)', '');
    AddTableField('client_jobschedule', 'tag', 'VARCHAR(255)', 'VARCHAR(255)', '');

    ModifyTableField('updatefile', 'revision', 'VARCHAR(60)', 'VARCHAR(60)', '', true, false);
    ModifyTableField('updatefile', 'priorrevision', 'VARCHAR(60)', 'VARCHAR(60)', '', true, false);
    AddTableField('buildupdate', 'revision', 'VARCHAR(60)', 'VARCHAR(60)', '0');
    AddTableField('buildupdate', 'priorrevision', 'VARCHAR(60)', 'VARCHAR(60)', '0');
    AddTableField('buildupdate', 'path', 'VARCHAR(255)', 'VARCHAR(255)', '');

    AddTableField('user2project', 'emailsuccess', 'tinyint(4)', 'smallint', '0');
    AddTableIndex('user2project', 'emailsuccess');
    AddTableField('user2project', 'emailmissingsites', 'tinyint(4)', 'smallint', '0');
    AddTableIndex('user2project', 'emailmissingsites');

    if (!pdo_query('SELECT projectid FROM test LIMIT 1')) {
        AddTableField('test', 'projectid', 'int(11)', 'bigint', '0');
        AddTableIndex('test', 'projectid');

        // Set the project id
        pdo_query('UPDATE test SET projectid=(SELECT projectid FROM build,build2test
               WHERE build2test.testid=test.id AND build2test.buildid=build.id LIMIT 1)');

        echo pdo_error();
    }

    // Add the cookiekey field
    AddTableField('user', 'cookiekey', 'VARCHAR(40)', 'VARCHAR( 40 )', '');
    ModifyTableField('dynamicanalysis', 'log', 'MEDIUMTEXT', 'TEXT', '', true, false);

    // New build, buildupdate and configure fields to speedup reading
    if (!pdo_query('SELECT builderrors FROM build LIMIT 1')) {
        AddTableField('build', 'builderrors', 'smallint(6)', 'smallint', '-1');
        AddTableField('build', 'buildwarnings', 'smallint(6)', 'smallint', '-1');
        AddTableField('build', 'testnotrun', 'smallint(6)', 'smallint', '-1');
        AddTableField('build', 'testfailed', 'smallint(6)', 'smallint', '-1');
        AddTableField('build', 'testpassed', 'smallint(6)', 'smallint', '-1');
        AddTableField('build', 'testtimestatusfailed', 'smallint(6)', 'smallint', '-1');

        AddTableField('buildupdate', 'nfiles', 'smallint(6)', 'smallint', '-1');
        AddTableField('buildupdate', 'warnings', 'smallint(6)', 'smallint', '-1');
        AddTableField('configure', 'warnings', 'smallint(6)', 'smallint', '-1');

        pdo_query("UPDATE configure SET warnings=(SELECT count(buildid) FROM configureerror WHERE buildid=configure.buildid AND type='1')
                 WHERE warnings=-1");
        pdo_query("UPDATE buildupdate SET
                warnings=(SELECT count(buildid) FROM updatefile WHERE buildid=buildupdate.buildid AND revision='-1' AND author='Local User'),
                nfiles=(SELECT count(buildid) FROM updatefile WHERE buildid=buildupdate.buildid)
                WHERE warnings=-1");

        pdo_query("UPDATE build SET
                 builderrors=(SELECT count(buildid) FROM builderror WHERE buildid=build.id AND type='0'),
                 buildwarnings=(SELECT count(buildid) FROM builderror WHERE buildid=build.id AND type='1'),
                 builderrors=builderrors+(SELECT count(buildid) FROM buildfailure WHERE buildid=build.id AND type='0'),
                 buildwarnings=buildwarnings+(SELECT count(buildid) FROM buildfailure WHERE buildid=build.id AND type='1'),
                 testpassed=(SELECT count(buildid) FROM build2test WHERE buildid=build.id AND status='passed'),
                 testfailed=(SELECT count(buildid) FROM build2test WHERE buildid=build.id AND status='failed'),
                 testnotrun=(SELECT count(buildid) FROM build2test WHERE buildid=build.id AND status='notrun'),
                 testtimestatusfailed=(SELECT count(buildid) FROM build2test,project WHERE project.id=build.id
                                       AND buildid=build.id AND timestatus>=project.testtimemaxstatus)
                 WHERE builderrors=-1");

        echo pdo_error();
    }

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-1-6');
    return;
}

// 1.8 Upgrade
if (isset($_GET['upgrade-1-8'])) {
    // If the new coveragefilelog is not set
    if (!pdo_query('SELECT log FROM coveragefilelog LIMIT 1')) {
        AddTableField('coveragefilelog', 'log', 'LONGBLOB', 'bytea', false);

        // Get the lines for each buildid/fileid
        $query = pdo_query('SELECT DISTINCT buildid,fileid FROM coveragefilelog ORDER BY buildid,fileid');
        while ($query_array = pdo_fetch_array($query)) {
            $buildid = $query_array['buildid'];
            $fileid = $query_array['fileid'];

            // Get the lines
            $firstline = false;
            $log = '';
            $lines = pdo_query("SELECT line,code FROM coveragefilelog WHERE buildid='" . $buildid . "' AND fileid='" . $fileid . "' ORDER BY line");
            while ($lines_array = pdo_fetch_array($lines)) {
                $line = $lines_array['line'];
                $code = $lines_array['code'];

                if ($firstline === false) {
                    $firstline = $line;
                }
                $log .= $line . ':' . $code . ';';
            }

            // Update the first line
            pdo_query("UPDATE coveragefilelog SET log='" . $log . "'
                WHERE buildid='" . $buildid . "' AND fileid='" . $fileid . "' AND line='" . $firstline . "'");

            // Delete the other lines
            pdo_query("DELETE FROM coveragefilelog
                 WHERE buildid='" . $buildid . "' AND fileid='" . $fileid . "' AND line!='" . $firstline . "'");
        }

        RemoveTableField('coveragefilelog', 'line');
        RemoveTableField('coveragefilelog', 'code');
    }

    // Missing fields in the client_jobschedule table
    if (!pdo_query('SELECT repository FROM client_jobschedule LIMIT 1')) {
        AddTableField('client_jobschedule', 'repository', 'varchar(512)', 'character varying(512)', '');
        AddTableField('client_jobschedule', 'module', 'varchar(255)', 'character varying(255)', '');
        AddTableField('client_jobschedule', 'buildnamesuffix', 'varchar(255)', 'character varying(255)', '');
        AddTableField('client_jobschedule', 'tag', 'varchar(255)', 'character varying(255)', '');
    }

    AddTableField('project', 'testingdataurl', 'varchar(255)', 'character varying(255)', '');
    AddTableField('buildgroup', 'autoremovetimeframe', 'int(11)', 'bigint', '0');

    ModifyTableField('dailyupdatefile', 'revision', 'VARCHAR(60)', 'VARCHAR(60)', '', true, false);
    ModifyTableField('dailyupdatefile', 'priorrevision', 'VARCHAR(60)', 'VARCHAR(60)', '', true, false);
    AddTableField('dailyupdatefile', 'email', 'VARCHAR(255)', 'character varying(255)', '');
    AddTableIndex('dailyupdatefile', 'email');

    AddTableField('client_jobschedule', 'buildconfiguration', 'tinyint(4)', 'smallint', '0');

    // Remove the toolkits tables
    pdo_query('DROP TABLE IF EXISTS client_toolkit');
    pdo_query('DROP TABLE IF EXISTS client_toolkitconfiguration');
    pdo_query('DROP TABLE IF EXISTS client_toolkitconfiguration2os');
    pdo_query('DROP TABLE IF EXISTS client_toolkitversion');
    pdo_query('DROP TABLE IF EXISTS client_jobschedule2toolkit');

    // Add lastping to the client_site table
    AddTableField('client_site', 'lastping', 'timestamp', 'timestamp(0)', '1980-01-01 00:00:00');
    AddTableIndex('client_site', 'lastping');

    // Remove img index for table test2image
    RenameTableField('test2image', 'imgid', 'imgid', 'int(11)', 'bigint', '0');
    RemoveTablePrimaryKey('test2image');
    AddTableIndex('test2image', 'imgid');

    ModifyTableField('buildfailure', 'stdoutput', 'MEDIUMTEXT', 'TEXT', '', true, false);
    ModifyTableField('buildfailure', 'stderror', 'MEDIUMTEXT', 'TEXT', '', true, false);
    AddTableIndex('builderrordiff', 'type');

    AddTableField('dailyupdate', 'revision', 'varchar(60)', 'character varying(60)', '');
    AddTableField('repositories', 'branch', 'varchar(60)', 'character varying(60)', '');

    // New fields for the submission table to make asynchronous submission
    // processing more robust:
    //
    AddTableField('submission', 'attempts', 'int(11)', 'bigint', '0');
    AddTableField('submission', 'filesize', 'int(11)', 'bigint', '0');
    AddTableField('submission', 'filemd5sum', 'varchar(32)', 'character varying(32)', '');
    AddTableField('submission', 'lastupdated', 'timestamp', 'timestamp(0)', '1980-01-01 00:00:00');
    AddTableField('submission', 'created', 'timestamp', 'timestamp(0)', '1980-01-01 00:00:00');
    AddTableField('submission', 'started', 'timestamp', 'timestamp(0)', '1980-01-01 00:00:00');
    AddTableField('submission', 'finished', 'timestamp', 'timestamp(0)', '1980-01-01 00:00:00');
    AddTableIndex('submission', 'finished');

    AddTableField('client_jobschedule', 'clientscript', 'text', 'text', '');

    AddTableField('project', 'webapikey', 'varchar(40)', 'character varying(40)', '');
    AddTableField('project', 'tokenduration', 'int(11)', 'bigint', '0');

    // Add the users' cvslogin to the user2repository table (by default all projects)
    if (pdo_query('SELECT cvslogin FROM user2project')) {
        // Add all the user's email to the user2repository table
        $emailarray = array();
        $query = pdo_query('SELECT id,email FROM user');
        while ($query_array = pdo_fetch_array($query)) {
            $userid = $query_array['id'];
            $email = $query_array['email'];
            $emailarray[] = $email;
            pdo_query("INSERT INTO user2repository (userid,credential) VALUES ('" . $userid . "','" . $email . "')");
        }

        // Add the repository login
        $query = pdo_query('SELECT userid,projectid,cvslogin FROM user2project GROUP BY userid,cvslogin');
        while ($query_array = pdo_fetch_array($query)) {
            $userid = $query_array['userid'];
            $cvslogin = $query_array['cvslogin'];
            if (!empty($cvslogin) && !in_array($cvslogin, $emailarray)) {
                pdo_query("INSERT INTO user2repository (userid,projectid,credential)
                   VALUES ('" . $userid . "','" . $projectid . "','" . $cvslogin . "')");
            }
        }
        RemoveTableField('user2project', 'cvslogin');
    }

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-1-8');
    return;
}

// 2.0 Upgrade
if (isset($_GET['upgrade-2-0'])) {
    // Add column id to test2image and testmeasurement
    if (!pdo_query('SELECT id FROM test2image LIMIT 1')) {
        if ($config->get('CDASH_DB_TYPE') != 'pgsql') {
            pdo_query('ALTER TABLE testmeasurement ADD id BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)');
            pdo_query('ALTER TABLE test2image ADD id BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)');
        } else {
            pdo_query('ALTER TABLE testmeasurement ADD id SERIAL NOT NULL, ADD PRIMARY KEY (id)');
            pdo_query('ALTER TABLE test2image ADD id SERIAL NOT NULL, ADD PRIMARY KEY (id)');
        }
    }
    AddTableField('project', 'webapikey', 'varchar(40)', 'character varying(40)', '');
    AddTableField('project', 'tokenduration', 'int(11)', 'bigint', '0');
    AddTableField('project', 'uploadquota', 'bigint(20)', 'bigint', '0');
    AddTableField('updatefile', 'committer', 'varchar(255)', 'character varying(255)', '');
    AddTableField('updatefile', 'committeremail', 'varchar(255)', 'character varying(255)', '');
    AddTableField('buildgroup', 'emailcommitters', 'tinyint(4)', 'smallint', '0');
    AddTableField('uploadfile', 'isurl', 'tinyint(1)', 'smallint', '0');

    // Add indexes for the label2... tables
    AddTableIndex('label2build', 'buildid');
    AddTableIndex('label2buildfailure', 'buildfailureid');
    AddTableIndex('label2coveragefile', 'buildid');
    AddTableIndex('label2dynamicanalysis', 'dynamicanalysisid');
    AddTableIndex('label2test', 'buildid');
    AddTableIndex('label2update', 'updateid');

    ModifyTableField('client_jobschedule', 'repeattime', 'decimal(6,2)', 'decimal(6,2)', '0.00', true, false);
    AddTableField('client_jobschedule', 'description', 'text', 'text', '');
    AddTableField('project', 'showcoveragecode', 'tinyint(1)', 'smallint', '1');

    AddTableIndex('updatefile', 'author');

    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-0');
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
    // Set the database version
    setVersion();

    // Put that the upgrade is done in the log
    add_log('Upgrade done.', 'upgrade-2-8');
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

    // check if the rss directory is writable
    if ($config->get('CDASH_ENABLE_FEED') > 0 && !is_writable('rss')) {
        $xml .= '<rsswritable>0</rsswritable>';
    } else {
        $xml .= '<rsswritable>1</rsswritable>';
    }
    $xml .= '<upgrade>1</upgrade>';
}

// Compress the test output
if (isset($_POST['CompressTestOutput'])) {
    // Do it slowly so we don't take all the memory
    $query = pdo_query('SELECT count(*) FROM test');
    $query_array = pdo_fetch_array($query);
    $ntests = $query_array[0];
    $ngroup = 1024;
    for ($i = 0; $i < $ntests; $i += $ngroup) {
        $query = pdo_query('SELECT id,output FROM test ORDER BY id ASC LIMIT ' . $ngroup . ' OFFSET ' . $i);
        while ($query_array = pdo_fetch_array($query)) {
            // Try uncompressing to see if it's already compressed
            if (@gzuncompress($query_array['output']) === false) {
                $compressed = pdo_real_escape_string(gzcompress($query_array['output']));
                pdo_query("UPDATE test SET output='" . $compressed . "' WHERE id=" . $query_array['id']);
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
    delete_unused_rows('test2image', 'testid', 'test');
    delete_unused_rows('testmeasurement', 'testid', 'test');
    delete_unused_rows('label2test', 'testid', 'test');

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
