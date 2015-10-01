<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/
include("cdash/config.php");
require_once("cdash/pdo.php");
include('login.php');
include_once("cdash/common.php");
include("cdash/version.php");

set_time_limit(0);

checkUserPolicy(@$_SESSION['cdash']['loginid'], 0); // only admin

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Maintenance</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Maintenance</menusubtitle>";

// Should be the database version not the current on
$version = pdo_query("SELECT major,minor FROM version");
$version_array = pdo_fetch_array($version);
$xml .= "<minversion>".$version_array['major'].".".$version_array['minor']."</minversion>";

@$CreateDefaultGroups = $_POST["CreateDefaultGroups"];
@$AssignBuildToDefaultGroups = $_POST["AssignBuildToDefaultGroups"];
@$FixBuildBasedOnRule = $_POST["FixBuildBasedOnRule"];
@$DeleteBuildsWrongDate = $_POST["DeleteBuildsWrongDate"];
@$CheckBuildsWrongDate = $_POST["CheckBuildsWrongDate"];
@$ComputeTestTiming = $_POST["ComputeTestTiming"];
@$ComputeUpdateStatistics = $_POST["ComputeUpdateStatistics"];

@$Upgrade = $_POST["Upgrade"];
@$Cleanup = $_POST["Cleanup"];

if (!isset($CDASH_DB_TYPE)) {
    $db_type = 'mysql';
} else {
    $db_type = $CDASH_DB_TYPE;
}

if (isset($_GET['upgrade-tables'])) {
    // Apply all the patches
  foreach (glob("sql/".$db_type."/cdash-upgrade-*.sql") as $filename) {
      $file_content = file($filename);
      $query = "";
      foreach ($file_content as $sql_line) {
          $tsl = trim($sql_line);

          if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) {
              $query .= $sql_line;
              if (preg_match("/;\s*$/", $sql_line)) {
                  $query = str_replace(";", "", "$query");
                  $result = pdo_query($query);
                  if (!$result) {
                      if ($db_type != "pgsql") {
                          // postgresql < 9.1 doesn't know CREATE TABLE IF NOT EXISTS so we don't die

               die(pdo_error());
                      }
                  }
                  $query = "";
              }
          }
      } // end for each line
  } // end for each upgrade file
  return;
}

if (isset($_GET['upgrade-0-8'])) {
    // Add the index if they don't exist
  $querycrc32 = pdo_query("SELECT crc32 FROM coveragefile LIMIT 1");
    if (!$querycrc32) {
        pdo_query("ALTER TABLE coveragefile ADD crc32 int(11)");
        pdo_query("ALTER TABLE coveragefile ADD INDEX (crc32)");
    }

  // Compression the coverage
  CompressCoverage();

    return;
}

if (isset($_GET['upgrade-1-0'])) {
    $description = pdo_query("SELECT description FROM buildgroup LIMIT 1");
    if (!$description) {
        pdo_query("ALTER TABLE buildgroup ADD description text");
    }
    $cvsviewertype = pdo_query("SELECT cvsviewertype FROM project LIMIT 1");
    if (!$cvsviewertype) {
        pdo_query("ALTER TABLE project ADD cvsviewertype varchar(10)");
    }

    if (pdo_query("ALTER TABLE site2user DROP PRIMARY KEY")) {
        pdo_query("ALTER TABLE site2user ADD INDEX (siteid)");
        pdo_query("ALTER TABLE build ADD INDEX (starttime)");
    }

  // Add test timing as well as key 'name' for test
  $timestatus = pdo_query("SELECT timestatus FROM build2test LIMIT 1");
    if (!$timestatus) {
        pdo_query("ALTER TABLE build2test ADD timemean float(7,2) default '0.00'");
        pdo_query("ALTER TABLE build2test ADD timestd float(7,2) default '0.00'");
        pdo_query("ALTER TABLE build2test ADD timestatus tinyint(4) default '0'");
        pdo_query("ALTER TABLE build2test ADD INDEX (timestatus)");
    // Add timing test fields in the table project
    pdo_query("ALTER TABLE project ADD testtimestd float(3,1) default '4.0'");
    // Add the index name in the table test
    pdo_query("ALTER TABLE test ADD INDEX (name)");
    }

  // Add the testtimethreshold
  if (!pdo_query("SELECT testtimestdthreshold FROM project LIMIT 1")) {
      pdo_query("ALTER TABLE project ADD testtimestdthreshold float(3,1) default '1.0'");
  }

  // Add an option to show the testtime or not
  if (!pdo_query("SELECT showtesttime FROM project LIMIT 1")) {
      pdo_query("ALTER TABLE project ADD showtesttime tinyint(4) default '0'");
  }
    return;
}

if (isset($_GET['upgrade-1-2'])) {
    // Replace the field 'output' in the table test from 'text' to 'mediumtext'
  $result = pdo_query("SELECT output FROM test LIMIT 1");
    $type  = pdo_field_type($result, 0);
    if ($type == "blob" || $type == "text") {
        $result = pdo_query("ALTER TABLE test CHANGE output output MEDIUMTEXT");
    }

  // Change the file from blob to longblob
  $result = pdo_query("SELECT file FROM coveragefile LIMIT 1");
    $length = mysql_field_len($result, 0);
    if ($length == 65535) {
        $result = pdo_query("ALTER TABLE coveragefile CHANGE file file LONGBLOB");
    }

  // Compress the notes
  if (!pdo_query("SELECT crc32 FROM note LIMIT 1")) {
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
  $timestatus = pdo_query("SELECT testtimemaxstatus FROM project LIMIT 1");
    if (!$timestatus) {
        pdo_query("ALTER TABLE project ADD testtimemaxstatus tinyint(4) default '3'");
        pdo_query("ALTER TABLE project ADD emailmaxitems tinyint(4) default '5'");
        pdo_query("ALTER TABLE project ADD emailmaxchars int(11) default '255'");
    }

  // Add summary email
  $summaryemail = pdo_query("SELECT summaryemail FROM buildgroup LIMIT 1");
    if (!$summaryemail) {
        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("ALTER TABLE \"buildgroup\" ADD \"summaryemail\" smallint DEFAULT '0'");
        } else {
            pdo_query("ALTER TABLE buildgroup ADD summaryemail tinyint(4) default '0'");
        }
    }

  // Add emailcategory
  $emailcategory = pdo_query("SELECT emailcategory FROM user2project LIMIT 1");
    if (!$emailcategory) {
        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("ALTER TABLE \"user2project\" ADD \"emailcategory\" smallint DEFAULT '62'");
        } else {
            pdo_query("ALTER TABLE user2project ADD emailcategory tinyint(4) default '62'");
        }
    }
    return;
}

// Helper function to alter a table
function AddTableField($table, $field, $mySQLType, $pgSqlType, $default)
{
    include("cdash/config.php");

    $sql = '';
    if ($default !== false) {
        $sql = " DEFAULT '".$default."'";
    }

    $query = pdo_query("SELECT ".$field." FROM ".$table." LIMIT 1");
    if (!$query) {
        add_log("Adding $field to $table", "AddTableField");
        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("ALTER TABLE \"".$table."\" ADD \"".$field."\" ".$pgSqlType.$sql);
        } else {
            pdo_query("ALTER TABLE ".$table." ADD ".$field." ".$mySQLType.$sql);
        }

        add_last_sql_error("AddTableField");
        add_log("Done adding $field to $table", "AddTableField");
    }
}

/** Remove a table field */
function RemoveTableField($table, $field)
{
    include("cdash/config.php");
    $query = pdo_query("SELECT ".$field." FROM ".$table." LIMIT 1");
    if ($query) {
        add_log("Droping $field from $table", "DropTableField");
        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("ALTER TABLE \"".$table."\" DROP COLUMN \"".$field."\"");
        } else {
            pdo_query("ALTER TABLE ".$table." DROP ".$field);
        }
        add_last_sql_error("DropTableField");
        add_log("Done droping $field from $table", "DropTableField");
    }
}

// Rename a table vield
function RenameTableField($table, $field, $newfield, $mySQLType, $pgSqlType, $default)
{
    include("cdash/config.php");
    $query = pdo_query("SELECT ".$field." FROM ".$table." LIMIT 1");
    if ($query) {
        add_log("Changing $field to $newfield for $table", "RenameTableField");
        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("ALTER TABLE \"".$table."\" RENAME \"".$field."\" TO \"".$newfield."\"");
            pdo_query("ALTER TABLE \"".$table."\" ALTER COLUMN \"".$newfield."\" TYPE ".$pgSqlType);
            pdo_query("ALTER TABLE \"".$table."\" ALTER COLUMN \"".$newfield."\" SET DEFAULT ".$default);
        } else {
            pdo_query("ALTER TABLE ".$table." CHANGE ".$field." ".$newfield." ".$mySQLType." DEFAULT '".$default."'");
            add_last_sql_error("RenameTableField");
        }
        add_log("Done renaming $field to $newfield for $table", "RenameTableField");
    }
}

// Helper function to add an index to a table
function AddTableIndex($table, $field)
{
    include("cdash/config.php");

    $index_name = $field;
  // Support for multiple column indices
  if (is_array($field)) {
      $index_name = implode("_", $field);
      $field = implode(",", $field);
  }

    if (!pdo_check_index_exists($table, $field)) {
        add_log("Adding index $field to $table", "AddTableIndex");
        if ($CDASH_DB_TYPE == "pgsql") {
            @pdo_query("CREATE INDEX $index_name ON $table ($field)");
        } else {
            pdo_query("ALTER TABLE $table ADD INDEX $index_name ($field)");
            add_last_sql_error("AddTableIndex");
        }
        add_log("Done adding index $field to $table", "AddTableIndex");
    }
}

// Helper function to remove an index to a table
function RemoveTableIndex($table, $field)
{
    include("cdash/config.php");
    if (pdo_check_index_exists($table, $field)) {
        add_log("Removing index $field from $table", "RemoveTableIndex");

        if ($CDASH_DB_TYPE == "pgsql") {
            pdo_query("DROP INDEX ".$table."_".$field."_idx");
        } else {
            pdo_query("ALTER TABLE ".$table." DROP INDEX ".$field);
        }
        add_log("Done removing index $field from $table", "RemoveTableIndex");
        add_last_sql_error("RemoveTableIndex");
    }
}

// Helper function to modify a table
function ModifyTableField($table, $field, $mySQLType, $pgSqlType, $default, $notnull, $autoincrement)
{
    include("cdash/config.php");

  //$check = pdo_query("SELECT ".$field." FROM ".$table." LIMIT 1");
  //$type  = pdo_field_type($check,0);
  //add_log($type,"ModifyTableField");
  if (1) {
      add_log("Modifying $field to $table", "ModifyTableField");
      if ($CDASH_DB_TYPE == "pgsql") {
          // ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" TYPE VARCHAR( 255 );
      // ALTER TABLE "buildfailureargument" ALTER COLUMN "argument" SET NOT NULL;
      // ALTER TABLE "dynamicanalysisdefect" ALTER COLUMN "value" SET DEFAULT 0;
      pdo_query("ALTER TABLE \"".$table."\" ALTER COLUMN  \"".$field."\" TYPE ".$pgSqlType);
          if ($notnull) {
              pdo_query("ALTER TABLE \"".$table."\" ALTER COLUMN  \"".$field."\" SET NOT NULL");
          }
          if (strlen($default)>0) {
              pdo_query("ALTER TABLE \"".$table."\" ALTER COLUMN  \"".$field."\" SET DEFAULT ".$default);
          }
          if ($autoincrement) {
              pdo_query("DROP INDEX \"".$table."_".$field."_idx\"");
              pdo_query("ALTER TABLE \"".$table."\" ADD PRIMARY KEY (\"".$field."\")");
              pdo_query("CREATE SEQUENCE \"".$table."_".$field."_seq\"");
              pdo_query("ALTER TABLE  \"".$table."\" ALTER COLUMN \"".$field."\" SET DEFAULT nextval('".$table."_".$field."_seq')");
              pdo_query("ALTER SEQUENCE \"".$table."_".$field."_seq\" OWNED BY \"".$table."\".\"".$field."\"");
          }
      } else {
          //ALTER TABLE dynamicanalysisdefect MODIFY value INT NOT NULL DEFAULT 0;
      $sql = "ALTER TABLE ".$table." MODIFY ".$field." ".$mySQLType;
          if ($notnull) {
              $sql .= " NOT NULL";
          }
          if (strlen($default)>0) {
              $sql .= " DEFAULT '".$default."'";
          }
          if ($autoincrement) {
              $sql .= " AUTO_INCREMENT";
          }
          pdo_query($sql);
      }
      add_last_sql_error("ModifyTableField");
      add_log("Done modifying $field to $table", "ModifyTableField");
  }
}

// Helper function to add an index to a table
function AddTablePrimaryKey($table, $field)
{
    include("cdash/config.php");
    add_log("Adding primarykey $field to $table", "AddTablePrimaryKey");
    if ($CDASH_DB_TYPE == "pgsql") {
        pdo_query("ALTER TABLE \"".$table."\" ADD PRIMARY KEY (\"".$field."\")");
    } else {
        pdo_query("ALTER IGNORE TABLE ".$table." ADD PRIMARY KEY ( ".$field." )");
    }
  //add_last_sql_error("AddTablePrimaryKey");
  add_log("Done adding primarykey $field to $table", "AddTablePrimaryKey");
}

// Helper function to add an index to a table
function RemoveTablePrimaryKey($table)
{
    include("cdash/config.php");
    add_log("Removing primarykey from $table", "RemoveTablePrimaryKey");
    if ($CDASH_DB_TYPE == "pgsql") {
        pdo_query("ALTER TABLE \"".$table."\" DROP CONSTRAINT \"value_pkey\"");
        pdo_query("ALTER TABLE \"".$table."\" DROP CONSTRAINT \"".$table."_pkey\"");
    } else {
        pdo_query("ALTER TABLE ".$table." DROP PRIMARY KEY");
    }
  //add_last_sql_error("RemoveTablePrimaryKey");
  add_log("Done removing primarykey from $table", "RemoveTablePrimaryKey");
}


// 1.4 Upgrade
if (isset($_GET['upgrade-1-4'])) {
    //  Add fields in the project table
  $starttime = pdo_query("SELECT starttime FROM subproject LIMIT 1");
    if (!$starttime) {
        pdo_query("ALTER TABLE subproject ADD starttime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
        pdo_query("ALTER TABLE subproject ADD endtime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
    }

  // Create the right indexes if necessary
  AddTableIndex('buildfailure', 'buildid');
    AddTableIndex('buildfailure', 'type');

  // Create the new table buildfailure arguments if the old one is still there
  if (pdo_query("SELECT buildfailureid FROM buildfailureargument")) {
      pdo_query("DROP TABLE IF EXISTS buildfailureargument");
      pdo_query("CREATE TABLE IF NOT EXISTS `buildfailureargument` (
              `id` bigint(20) NOT NULL auto_increment,
              `argument` varchar(60) NOT NULL,
              PRIMARY KEY  (`id`),
              KEY `argument` (`argument`))");
  }

    AddTableIndex('buildfailureargument', 'argument');

  //  Add fields in the buildgroup table
  AddTableField("project", "emailadministrator", "tinyint(4)", "smallint", "1");
    AddTableField("project", "showipaddresses", "tinyint(4)", "smallint", "1");
    AddTableField("buildgroup", "includesubprojectotal", "tinyint(4)", "smallint", "1");
    AddTableField("project", "emailredundantfailures", "tinyint(4)", "smallint", "0");
    AddTableField("buildfailure2argument", "place", "int(11)", "bigint", "0");

    if ($CDASH_DB_TYPE != "pgsql") {
        pdo_query("ALTER TABLE `builderror` CHANGE `precontext` `precontext` TEXT NULL");
        pdo_query("ALTER TABLE `builderror` CHANGE `postcontext` `postcontext` TEXT NULL");
    }

    ModifyTableField("buildfailureargument", "argument", "VARCHAR( 255 )", "VARCHAR( 255 )", "", true, false);
    ModifyTableField("buildfailure", "exitcondition", "VARCHAR( 255 )", "VARCHAR( 255 )", "", true, false);
    ModifyTableField("buildfailure", "language", "VARCHAR( 64 )", "VARCHAR( 64 )", "", true, false);
    ModifyTableField("buildfailure", "sourcefile", "VARCHAR( 512)", "VARCHAR( 512 )", "", true, false);
    RemoveTableField("buildfailure", "arguments");
    ModifyTableField("configure", "log", "MEDIUMTEXT", "TEXT", "", true, false);

    AddTableIndex('coverage', 'covered');
    AddTableIndex('build2grouprule', 'starttime');
    AddTableIndex('build2grouprule', 'endtime');
    AddTableIndex('build2grouprule', 'buildtype');
    AddTableIndex('build2grouprule', 'buildname');
    AddTableIndex('build2grouprule', 'expected');
    AddTableIndex('build2grouprule', 'siteid');
    RemoveTableIndex("build2note", "buildid");
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

    ModifyTableField("image", "id", "BIGINT( 11 )", "BIGINT", "", true, false);
    RemoveTableIndex("image", " id");
    RemoveTablePrimaryKey("image");
    AddTablePrimaryKey("image", "id");
    ModifyTableField("image", "id", "BIGINT( 11 )", "BIGINT", "", true, true);

    ModifyTableField("dailyupdate", "id", "BIGINT( 11 )", "BIGINT", "", true, false);
    RemoveTableIndex("dailyupdate", " buildid");
    RemoveTablePrimaryKey("dailyupdate");
    AddTablePrimaryKey("dailyupdate", "id");
    ModifyTableField("dailyupdate", "id", "BIGINT( 11 )", "BIGINT", "", true, true);

    ModifyTableField("dynamicanalysisdefect", "value", "INT", "INT", "0", true, false);

    RemoveTablePrimaryKey("test2image");
    AddTableIndex('test2image', 'imgid');
    AddTableIndex('test2image', 'testid');

    ModifyTableField("image", "checksum", "BIGINT( 20 )", "BIGINT", "", true, false);
    ModifyTableField("note ", "crc32", "BIGINT( 20 )", "BIGINT", "", true, false);
    ModifyTableField("test ", "crc32", "BIGINT( 20 )", "BIGINT", "", true, false);
    ModifyTableField("coveragefile ", "crc32", "BIGINT( 20 )", "BIGINT", "", true, false);

  // Remove duplicates in buildfailureargument
  //pdo_query("DELETE FROM buildfailureargument WHERE id NOT IN (SELECT buildfailureid as id FROM buildfailure2argument)");

  AddTableField("project", "displaylabels", "tinyint(4)", "smallint", "1");
    AddTableField("project", "autoremovetimeframe", "int(11)", "bigint", "0");
    AddTableField("project", "autoremovemaxbuilds", "int(11)", "bigint", "300");
    AddTableIndex('coveragefilelog', 'line');

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-1-4");

    return;
}

// 1.6 Upgrade
if (isset($_GET['upgrade-1-6'])) {
    if ($CDASH_DB_TYPE != "pgsql") {
        pdo_query("ALTER TABLE configure CHANGE starttime starttime TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE buildupdate CHANGE starttime starttime TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE test CHANGE output output MEDIUMBLOB NOT NULL "); // change it to blob (cannot do that in PGSQL)
    pdo_query("ALTER TABLE updatefile CHANGE checkindate checkindate TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE build2note CHANGE time time TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
        pdo_query("ALTER TABLE buildemail CHANGE time time TIMESTAMP NOT NULL DEFAULT '1980-01-01 00:00:00' ");
    }

    RemoveTableField("project", "emailbuildmissing");
    AddTableField("project", "displaylabels", "tinyint(4)", "smallint", "1");
    AddTableField("project", "autoremovetimeframe", "int(11)", "bigint", "0");
    AddTableField("project", "autoremovemaxbuilds", "int(11)", "bigint", "300");
    AddTableField("updatefile", "status", "VARCHAR(12)", "VARCHAR( 12 )", "");
    AddTableField("project", "bugtrackerfileurl", "VARCHAR(255)", "VARCHAR( 255 )", "");
    AddTableField("repositories", "username", "VARCHAR(50)", "VARCHAR( 50 )", "");
    AddTableField("repositories", "password", "VARCHAR(50)", "VARCHAR( 50 )", "");
    AddTableIndex('coveragefilelog', 'line');
    AddTableIndex('dailyupdatefile', 'author');

    RenameTableField("testdiff", "difference", "difference_positive", "int(11)", "bigint", "0");
    AddTableField("testdiff", "difference_negative", "int(11)", "bigint", "0");
    AddTableIndex('testdiff', 'difference_positive');
    AddTableIndex('testdiff', 'difference_negative');
    AddTableField("build2test", "newstatus", "tinyint(4)", "smallint", "0");
    AddTableIndex('build2test', 'newstatus');

    RenameTableField("builderrordiff", "difference", "difference_positive", "int(11)", "bigint", "0");
    AddTableField("builderrordiff", "difference_negative", "int(11)", "bigint", "0");
    AddTableIndex('builderrordiff', 'difference_positive');
    AddTableIndex('builderrordiff', 'difference_negative');

    AddTableField("builderror", "crc32", "bigint(20)", "BIGINT", "0");
    AddTableField("builderror", "newstatus", "tinyint(4)", "smallint", "0");
    AddTableIndex('builderror', 'crc32');
    AddTableIndex('builderror', 'newstatus');

    AddTableField("buildfailure", "crc32", "bigint(20)", "BIGINT", "0");
    AddTableField("buildfailure", "newstatus", "tinyint(4)", "smallint", "0");
    AddTableIndex('buildfailure', 'crc32');
    AddTableIndex('buildfailure', 'newstatus');

    AddTableField("client_jobschedule", "repository", "VARCHAR(512)", "VARCHAR(512)", "");
    AddTableField("client_jobschedule", "module", "VARCHAR(255)", "VARCHAR(255)", "");
    AddTableField("client_jobschedule", "buildnamesuffix", "VARCHAR(255)", "VARCHAR(255)", "");
    AddTableField("client_jobschedule", "tag", "VARCHAR(255)", "VARCHAR(255)", "");

    ModifyTableField("updatefile", "revision", "VARCHAR(60)", "VARCHAR(60)", "", true, false);
    ModifyTableField("updatefile", "priorrevision", "VARCHAR(60)", "VARCHAR(60)", "", true, false);
    AddTableField("buildupdate", "revision", "VARCHAR(60)", "VARCHAR(60)", "0");
    AddTableField("buildupdate", "priorrevision", "VARCHAR(60)", "VARCHAR(60)", "0");
    AddTableField("buildupdate", "path", "VARCHAR(255)", "VARCHAR(255)", "");

    AddTableField("user2project", "emailsuccess", "tinyint(4)", "smallint", "0");
    AddTableIndex('user2project', 'emailsuccess');
    AddTableField("user2project", "emailmissingsites", "tinyint(4)", "smallint", "0");
    AddTableIndex('user2project', 'emailmissingsites');

    if (!pdo_query("SELECT projectid FROM test LIMIT 1")) {
        AddTableField("test", "projectid", "int(11)", "bigint", "0");
        AddTableIndex('test', 'projectid');

    // Set the project id
    pdo_query("UPDATE test SET projectid=(SELECT projectid FROM build,build2test
               WHERE build2test.testid=test.id AND build2test.buildid=build.id LIMIT 1)");

        echo pdo_error();
    }

  // Add the cookiekey field
  AddTableField("user", "cookiekey", "VARCHAR(40)", "VARCHAR( 40 )", "");
    ModifyTableField("dynamicanalysis", "log", "MEDIUMTEXT", "TEXT", "", true, false);

  // New build, buildupdate and configure fields to speedup reading
  if (!pdo_query("SELECT builderrors FROM build LIMIT 1")) {
      AddTableField("build", "builderrors", "smallint(6)", "smallint", "-1");
      AddTableField("build", "buildwarnings", "smallint(6)", "smallint", "-1");
      AddTableField("build", "testnotrun", "smallint(6)", "smallint", "-1");
      AddTableField("build", "testfailed", "smallint(6)", "smallint", "-1");
      AddTableField("build", "testpassed", "smallint(6)", "smallint", "-1");
      AddTableField("build", "testtimestatusfailed", "smallint(6)", "smallint", "-1");

      AddTableField("buildupdate", "nfiles", "smallint(6)", "smallint", "-1");
      AddTableField("buildupdate", "warnings", "smallint(6)", "smallint", "-1");
      AddTableField("configure", "warnings", "smallint(6)", "smallint", "-1");

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
  } // end new table build

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-1-6");

    return;
}

// 1.8 Upgrade
if (isset($_GET['upgrade-1-8'])) {
    // If the new coveragefilelog is not set
  if (!pdo_query("SELECT log FROM coveragefilelog LIMIT 1")) {
      AddTableField("coveragefilelog", "log", "LONGBLOB", "bytea", false);

    // Get the lines for each buildid/fileid
    $query = pdo_query("SELECT DISTINCT buildid,fileid FROM coveragefilelog ORDER BY buildid,fileid");
      while ($query_array = pdo_fetch_array($query)) {
          $buildid = $query_array['buildid'];
          $fileid = $query_array['fileid'];

      // Get the lines
      $firstline = false;
          $log = '';
          $lines = pdo_query("SELECT line,code FROM coveragefilelog WHERE buildid='".$buildid."' AND fileid='".$fileid."' ORDER BY line");
          while ($lines_array = pdo_fetch_array($lines)) {
              $line = $lines_array['line'];
              $code = $lines_array['code'];

              if ($firstline === false) {
                  $firstline = $line;
              }
              $log .= $line.':'.$code.';';
          }

      // Update the first line
      pdo_query("UPDATE coveragefilelog SET log='".$log."'
                WHERE buildid='".$buildid."' AND fileid='".$fileid."' AND line='".$firstline."'");

      // Delete the other lines
      pdo_query("DELETE FROM coveragefilelog
                 WHERE buildid='".$buildid."' AND fileid='".$fileid."' AND line!='".$firstline."'");
      } // end looping through buildid/fileid

    RemoveTableField("coveragefilelog", "line");
      RemoveTableField("coveragefilelog", "code");
  }

  // Missing fields in the client_jobschedule table
  if (!pdo_query("SELECT repository FROM client_jobschedule LIMIT 1")) {
      AddTableField("client_jobschedule", "repository", "varchar(512)", "character varying(512)", "");
      AddTableField("client_jobschedule", "module", "varchar(255)", "character varying(255)", "");
      AddTableField("client_jobschedule", "buildnamesuffix", "varchar(255)", "character varying(255)", "");
      AddTableField("client_jobschedule", "tag", "varchar(255)", "character varying(255)", "");
  }

    AddTableField("project", "testingdataurl", "varchar(255)", "character varying(255)", "");
    AddTableField('buildgroup', 'autoremovetimeframe', 'int(11)', 'bigint', '0');

    ModifyTableField("dailyupdatefile", "revision", "VARCHAR(60)", "VARCHAR(60)", "", true, false);
    ModifyTableField("dailyupdatefile", "priorrevision", "VARCHAR(60)", "VARCHAR(60)", "", true, false);
    AddTableField("dailyupdatefile", "email", "VARCHAR(255)", "character varying(255)", "");
    AddTableIndex('dailyupdatefile', 'email');

    AddTableField("client_jobschedule", "buildconfiguration", "tinyint(4)", "smallint", "0");

  // Remove the toolkits tables
  pdo_query("DROP TABLE IF EXISTS client_toolkit");
    pdo_query("DROP TABLE IF EXISTS client_toolkitconfiguration");
    pdo_query("DROP TABLE IF EXISTS client_toolkitconfiguration2os");
    pdo_query("DROP TABLE IF EXISTS client_toolkitversion");
    pdo_query("DROP TABLE IF EXISTS client_jobschedule2toolkit");

  // Add lastping to the client_site table
  AddTableField("client_site", "lastping", "timestamp", "timestamp(0)", "1980-01-01 00:00:00");
    AddTableIndex('client_site', 'lastping');

  // Remove img index for table test2image
  RenameTableField('test2image', 'imgid', 'imgid', "int(11)", "bigint", "0");
    RemoveTablePrimaryKey('test2image');
    AddTableIndex('test2image', 'imgid');

    ModifyTableField("buildfailure", "stdoutput", "MEDIUMTEXT", "TEXT", "", true, false);
    ModifyTableField("buildfailure", "stderror", "MEDIUMTEXT", "TEXT", "", true, false);
    AddTableIndex('builderrordiff', 'type');

    AddTableField("dailyupdate", "revision", "varchar(60)", "character varying(60)", "");
    AddTableField("repositories", "branch", "varchar(60)", "character varying(60)", "");

  // New fields for the submission table to make asynchronous submission
  // processing more robust:
  //
  AddTableField("submission", "attempts", "int(11)", "bigint", "0");
    AddTableField("submission", "filesize", "int(11)", "bigint", "0");
    AddTableField("submission", "filemd5sum", "varchar(32)", "character varying(32)", "");
    AddTableField("submission", "lastupdated", "timestamp", "timestamp(0)", "1980-01-01 00:00:00");
    AddTableField("submission", "created", "timestamp", "timestamp(0)", "1980-01-01 00:00:00");
    AddTableField("submission", "started", "timestamp", "timestamp(0)", "1980-01-01 00:00:00");
    AddTableField("submission", "finished", "timestamp", "timestamp(0)", "1980-01-01 00:00:00");
    AddTableIndex("submission", "finished");

    AddTableField("client_jobschedule", "clientscript", "text", "text", "");

    AddTableField("project", "webapikey", "varchar(40)", "character varying(40)", "");
    AddTableField("project", "tokenduration", "int(11)", "bigint", "0");

  // Add the users' cvslogin to the user2repository table (by default all projects)
  if (pdo_query("SELECT cvslogin FROM user2project")) {
      // Add all the user's email to the user2repository table
    $emailarray = array();
      $query = pdo_query("SELECT id,email FROM user");
      while ($query_array = pdo_fetch_array($query)) {
          $userid = $query_array['id'];
          $email = $query_array['email'];
          $emailarray[] = $email;
          pdo_query("INSERT INTO user2repository (userid,credential) VALUES ('".$userid."','".$email."')");
      }

    // Add the repository login
    $query = pdo_query("SELECT userid,projectid,cvslogin FROM user2project GROUP BY userid,cvslogin");
      while ($query_array = pdo_fetch_array($query)) {
          $userid = $query_array['userid'];
          $cvslogin = $query_array['cvslogin'];
          if (!empty($cvslogin) && !in_array($cvslogin, $emailarray)) {
              pdo_query("INSERT INTO user2repository (userid,projectid,credential)
                   VALUES ('".$userid."','".$projectid."','".$cvslogin."')");
          }
      }
      RemoveTableField("user2project", "cvslogin");
  }

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-1-8");

    return;
}

// 2.0 Upgrade
if (isset($_GET['upgrade-2-0'])) {
    // Add column id to test2image and testmeasurement
  if (!pdo_query("SELECT id FROM test2image LIMIT 1")) {
      include("cdash/config.php");
      if ($CDASH_DB_TYPE != "pgsql") {
          pdo_query("ALTER TABLE testmeasurement ADD id BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)");
          pdo_query("ALTER TABLE test2image ADD id BIGINT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)");
      } else {
          pdo_query("ALTER TABLE testmeasurement ADD id SERIAL NOT NULL, ADD PRIMARY KEY (id)");
          pdo_query("ALTER TABLE test2image ADD id SERIAL NOT NULL, ADD PRIMARY KEY (id)");
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

    ModifyTableField("client_jobschedule", "repeattime", "decimal(6,2)", "decimal(6,2)", "0.00", true, false);
    AddTableField('client_jobschedule', 'description', 'text', 'text', '');
    AddTableField('project', 'showcoveragecode', 'tinyint(1)', 'smallint', '1');

    AddTableIndex('updatefile', 'author');

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-2-0");
    return;
}

// 2.2 Upgrade
if (isset($_GET['upgrade-2-2'])) {
    AddTableIndex('updatefile', 'author');

  // We need to move the buildupdate build ids to the build2update table
  $query = pdo_query("SELECT buildid FROM buildupdate");
    while ($query_array = pdo_fetch_array($query)) {
        pdo_query("INSERT INTO build2update (buildid,updateid) VALUES ('".$query_array['buildid']."','".$query_array['buildid']."')");
    }
    RemoveTableIndex("buildupdate", "buildid");
    RenameTableField("buildupdate", "buildid", "id", "int(11)", "bigint", "0");
    AddTablePrimaryKey("buildupdate", "id");
    ModifyTableField("buildupdate", "id", "int(11)", "bigint", "", true, true);
    RenameTableField("updatefile", "buildid", "updateid", "int(11)", "bigint", "0");

    AddTableField('site', 'outoforder', 'tinyint(1)', 'smallint', '0');

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-2-2");
    return;
}

// 2.4 Upgrade
if (isset($_GET['upgrade-2-4'])) {
    // Support for subproject groups
  AddTableField('subproject', 'groupid', 'int(11)', 'bigint', '0');
    AddTableIndex('subproject', 'groupid');
    RemoveTableField("subproject", "core");
    RemoveTableField('project', 'coveragethreshold2');

  // Support for larger types
  ModifyTableField("buildfailure", "workingdirectory", "VARCHAR( 512)", "VARCHAR( 512 )", "", true, false);
    ModifyTableField("buildfailure", "outputfile", "VARCHAR( 512)", "VARCHAR( 512 )", "", true, false);

  // Support for parent builds
  AddTableField('build', 'parentid', 'int(11)', 'int', '0');
    AddTableIndex('build', 'parentid');

  // Cache configure results similar to build & test
  AddTableField('build', 'configureerrors', 'smallint(6)', 'smallint', '-1');
    AddTableField('build', 'configurewarnings', 'smallint(6)', 'smallint', '-1');

  // Add new multi-column index to build table.
  // This improves the rendering speed of overview.php.
  $multi_index = array("projectid", "parentid", "starttime");
    AddTableIndex("build", $multi_index);

  // Support for dynamic BuildGroups.
  AddTableField('buildgroup', 'type', 'varchar(20)', 'character varying(20)', 'Daily');
    AddTableField('build2grouprule', 'parentgroupid', 'int(11)', 'bigint', '0');

  // Support for pull request notifications.
  AddTableField('build', 'notified', 'tinyint(1)', 'smallint', '0');

  // Set the database version
  setVersion();

  // Put that the upgrade is done in the log
  add_log("Upgrade done.", "upgrade-2-4");
    return;
}

// When adding new tables they should be added to the SQL installation file
// and here as well
if ($Upgrade) {
    // check if the backup directory is writable
  if (!is_writable($CDASH_BACKUP_DIRECTORY)) {
      $xml .= "<backupwritable>0</backupwritable>";
  } else {
      $xml .= "<backupwritable>1</backupwritable>";
  }

  // check if the upload directory is writable
  if (!is_writable($CDASH_UPLOAD_DIRECTORY)) {
      $xml .= "<uploadwritable>0</uploadwritable>";
  } else {
      $xml .= "<uploadwritable>1</uploadwritable>";
  }

  // check if the rss directory is writable
  if (!is_writable("rss")) {
      $xml .= "<rsswritable>0</rsswritable>";
  } else {
      $xml .= "<rsswritable>1</rsswritable>";
  }
    $xml .= "<upgrade>1</upgrade>";
}

// Compress the test output
if (isset($_POST["CompressTestOutput"])) {
    // Do it slowly so we don't take all the memory
  $query = pdo_query("SELECT count(*) FROM test");
    $query_array = pdo_fetch_array($query);
    $ntests = $query_array[0];
    $ngroup = 1024;
    for ($i=0;$i<$ntests;$i+=$ngroup) {
        $query = pdo_query("SELECT id,output FROM test ORDER BY id ASC LIMIT ".$ngroup." OFFSET ".$i);
        while ($query_array = pdo_fetch_array($query)) {
            // Try uncompressing to see if it's already compressed
      if (@gzuncompress($query_array['output']) === false) {
          $compressed = pdo_real_escape_string(gzcompress($query_array['output']));
          pdo_query("UPDATE test SET output='".$compressed."' WHERE id=".$query_array['id']);
          echo pdo_error();
      }
        }
    }
}


// Compute the testtime
if ($ComputeTestTiming) {
    @$TestTimingDays = $_POST["TestTimingDays"];
    if ($TestTimingDays != null) {
        $TestTimingDays = pdo_real_escape_numeric($TestTimingDays);
    }
    if (is_numeric($TestTimingDays) && $TestTimingDays>0) {
        ComputeTestTiming($TestTimingDays);
        $xml .= add_XML_value("alert", "Timing for tests has been computed successfully.");
    } else {
        $xml .= add_XML_value("alert", "Wrong number of days.");
    }
}

// Compute the user statistics
if ($ComputeUpdateStatistics) {
    @$UpdateStatisticsDays = $_POST["UpdateStatisticsDays"];
    if ($UpdateStatisticsDays != null) {
        $UpdateStatisticsDays = pdo_real_escape_numeric($UpdateStatisticsDays);
    }
    if (is_numeric($UpdateStatisticsDays) && $UpdateStatisticsDays>0) {
        ComputeUpdateStatistics($UpdateStatisticsDays);
        $xml .= add_XML_value("alert", "User statistics has been computed successfully.");
    } else {
        $xml .= add_XML_value("alert", "Wrong number of days.");
    }
}


/** Compress the notes. Since they are almost always the same form build to build */
function CompressNotes()
{
    // Rename the old note table
  if (!pdo_query("RENAME TABLE note TO notetemp")) {
      echo pdo_error();
      echo "Cannot rename table note to notetemp";
      return false;
  }

  // Create the new note table
  if (!pdo_query("CREATE TABLE note (
     id bigint(20) NOT NULL auto_increment,
     text mediumtext NOT NULL,
     name varchar(255) NOT NULL,
     crc32 int(11) NOT NULL,
     PRIMARY KEY  (id),
     KEY crc32 (crc32))")) {
      echo pdo_error();
      echo "Cannot create new table 'note'";
      return false;
  }

  // Move each note from notetemp to the new table
  $note = pdo_query("SELECT * FROM notetemp ORDER BY buildid ASC");
    while ($note_array = pdo_fetch_array($note)) {
        $text = $note_array["text"];
        $name = $note_array["name"];
        $time = $note_array["time"];
        $buildid = $note_array["buildid"];
        $crc32 = crc32($text.$name);

        $notecrc32 =  pdo_query("SELECT id FROM note WHERE crc32='$crc32'");
        if (pdo_num_rows($notecrc32) == 0) {
            pdo_query("INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')");
            $noteid = pdo_insert_id("note");
            echo pdo_error();
        } else {
            // already there

      $notecrc32_array = pdo_fetch_array($notecrc32);
            $noteid = $notecrc32_array["id"];
        }

        pdo_query("INSERT INTO build2note (buildid,noteid,time) VALUES ('$buildid','$noteid','$time')");
        echo pdo_error();
    }

  // Drop the old note table
  pdo_query("DROP TABLE notetemp");
    echo pdo_error();
} // end CompressNotes()

/** Compute the timing for test
 *  For each test we compare with the previous build and if the percentage time
 *  is more than the project.testtimepercent we increas test.timestatus by one.
 *  We also store the test.reftime which is the time of the test passing
 *
 *  If test.timestatus is more than project.testtimewindow we reset
 *  the test.timestatus to zero and we set the test.reftime to the previous build time.
 */
function ComputeTestTiming($days = 4)
{
    // Loop through the projects
  $project = pdo_query("SELECT id,testtimestd,testtimestdthreshold FROM project");
    $weight = 0.3;


    while ($project_array = pdo_fetch_array($project)) {
        $projectid = $project_array["id"];
        $testtimestd = $project_array["testtimestd"];
        $projecttimestdthreshold = $project_array["testtimestdthreshold"];

    // only test a couple of days
    $now = gmdate(FMT_DATETIME, time()-3600*24*$days);

    // Find the builds
    $builds = pdo_query("SELECT starttime,siteid,name,type,id
                               FROM build
                               WHERE build.projectid='$projectid' AND build.starttime>'$now'
                               ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i=0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $buildid = $build_array["id"];
            $buildname = $build_array["name"];
            $buildtype = $build_array["type"];
            $starttime = $build_array["starttime"];
            $siteid = $build_array["siteid"];

      // Find the previous build
      $previousbuild = pdo_query("SELECT id FROM build
                                    WHERE build.siteid='$siteid'
                                    AND build.type='$buildtype' AND build.name='$buildname'
                                    AND build.projectid='$projectid'
                                    AND build.starttime<'$starttime'
                                    AND build.starttime>'$now'
                                    ORDER BY build.starttime DESC LIMIT 1");

            echo pdo_error();

      // If we have one
      if (pdo_num_rows($previousbuild)>0) {
          // Loop through the tests
        $previousbuild_array = pdo_fetch_array($previousbuild);
          $previousbuildid = $previousbuild_array ["id"];

          $tests = pdo_query("SELECT build2test.time,build2test.testid,test.name
                              FROM build2test,test WHERE build2test.buildid='$buildid'
                              AND build2test.testid=test.id
                              ");
          echo pdo_error();

          flush();
          ob_flush();

        // Find the previous test
        $previoustest = pdo_query("SELECT build2test.testid,test.name FROM build2test,test
                                     WHERE build2test.buildid='$previousbuildid'
                                     AND test.id=build2test.testid
                                     ");
          echo pdo_error();

          $testarray = array();
          while ($test_array = pdo_fetch_array($previoustest)) {
              $test = array();
              $test['id'] = $test_array["testid"];
              $test['name'] = $test_array["name"];
              $testarray[] = $test;
          }

          while ($test_array = pdo_fetch_array($tests)) {
              $testtime = $test_array['time'];
              $testid = $test_array['testid'];
              $testname = $test_array['name'];

              $previoustestid = 0;

              foreach ($testarray as $test) {
                  if ($test['name']==$testname) {
                      $previoustestid = $test['id'];
                      break;
                  }
              }


              if ($previoustestid>0) {
                  $previoustest = pdo_query("SELECT timemean,timestd FROM build2test
                                       WHERE buildid='$previousbuildid'
                                       AND build2test.testid='$previoustestid'
                                       ");

                  $previoustest_array = pdo_fetch_array($previoustest);
                  $previoustimemean = $previoustest_array["timemean"];
                  $previoustimestd = $previoustest_array["timestd"];

           // Check the current status
          if ($previoustimestd<$projecttimestdthreshold) {
              $previoustimestd = $projecttimestdthreshold;
          }

            // Update the mean and std
            $timemean = (1-$weight)*$previoustimemean+$weight*$testtime;
                  $timestd = sqrt((1-$weight)*$previoustimestd*$previoustimestd + $weight*($testtime-$timemean)*($testtime-$timemean));

            // Check the current status
            if ($testtime > $previoustimemean+$testtimestd*$previoustimestd) {
                // only do positive std

              $timestatus = 1; // flag
            } else {
                $timestatus = 0;
            }
              } else {
                  // the test doesn't exist

            $timestd = 0;
                  $timestatus = 0;
                  $timemean = $testtime;
              }



              pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                        WHERE buildid='$buildid' AND testid='$testid'");
          }  // end loop through the test
      } else {
          // this is the first build

        $timestd = 0;
          $timestatus = 0;

        // Loop throught the tests
        $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid='$buildid'");
          while ($test_array = pdo_fetch_array($tests)) {
              $timemean = $test_array['time'];
              $testid = $test_array['testid'];

              pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus'
                        WHERE buildid='$buildid' AND testid='$testid'");
          }
      } // loop through the tests

      // Progress bar
      $perc = ($i/$total)*100;
            if ($perc-$previousperc>5) {
                echo round($perc, 3)."% done.<br>";
                flush();
                ob_flush();
                $previousperc = $perc;
            }
            $i++;
        } // end looping through builds
    } // end looping through projects
}


/** Compute the statistics for the updated file. Number of produced errors, warning, test failings. */
function ComputeUpdateStatistics($days = 4)
{
    include_once('models/build.php');

  // Loop through the projects
  $project = pdo_query("SELECT id FROM project");

    while ($project_array = pdo_fetch_array($project)) {
        $projectid = $project_array["id"];

    // only test a couple of days
    $now = gmdate(FMT_DATETIME, time()-3600*24*$days);

    // Find the builds
    $builds = pdo_query("SELECT starttime,siteid,name,type,id
                               FROM build
                               WHERE build.projectid='$projectid' AND build.starttime>'$now'
                               ORDER BY build.starttime ASC");

        $total = pdo_num_rows($builds);
        echo pdo_error();

        $i=0;
        $previousperc = 0;
        while ($build_array = pdo_fetch_array($builds)) {
            $Build = new Build();
            $Build->Id = $build_array["id"];
            $Build->ProjectId = $projectid;
            $Build->ComputeUpdateStatistics();

      // Progress bar
      $perc = ($i/$total)*100;
            if ($perc-$previousperc>5) {
                echo round($perc, 3)."% done.<br>";
                flush();
                ob_flush();
                $previousperc = $perc;
            }
            $i++;
        } // end looping through builds
    } // end looping through projects
}

/** Delete unused rows */
function delete_unused_rows($table, $field, $targettable, $selectfield='id')
{
    pdo_query("DELETE FROM $table WHERE $field NOT IN (SELECT $selectfield AS $field FROM $targettable)");
    echo pdo_error();
}

/** Cleanup the database */
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

    delete_unused_rows('build2note', 'buildid', 'build');
    delete_unused_rows('build2test', 'buildid', 'build');
    delete_unused_rows('buildemail', 'buildid', 'build');
    delete_unused_rows('builderror', 'buildid', 'build');
    delete_unused_rows('builderrordiff', 'buildid', 'build');
    delete_unused_rows('buildfailure', 'buildid', 'build');
    delete_unused_rows('buildinformation', 'buildid', 'build');
    delete_unused_rows('buildnote', 'buildid', 'build');
    delete_unused_rows('buildtesttime', 'buildid', 'build');
    delete_unused_rows('configure', 'buildid', 'build');
    delete_unused_rows('configureerror', 'buildid', 'build');
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

    $xml .= add_XML_value("alert", "Database cleanup complete.");
}


/** Support for compressed coverage.
 *  This is done in two steps.
 *  First step: Reducing the size of the coverage file by computing the crc32 in coveragefile
 *              and changing the appropriate fileid in coverage and coveragefilelog
 *  Second step: Reducing the size of the coveragefilelog by computing the crc32 of the groupid
 *               if the same coverage is beeing stored over and over again then it's discarded (same groupid)
 */
function CompressCoverage()
{
    /** FIRST STEP */
  // Compute the crc32 of the fullpath+file
  $coveragefile =  pdo_query("SELECT count(*) AS num FROM coveragefile WHERE crc32 IS NULL");
    $coveragefile_array = pdo_fetch_array($coveragefile);
    $total = $coveragefile_array["num"];

    $i=0;
    $previousperc = 0;
    $coveragefile = pdo_query("SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000");
    while (pdo_num_rows($coveragefile)>0) {
        while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
            $fullpath = $coveragefile_array["fullpath"];
            $file = $coveragefile_array["file"];
            $id = $coveragefile_array["id"];
            $crc32 = crc32($fullpath.$file);
            pdo_query("UPDATE coveragefile SET crc32='$crc32' WHERE id='$id'");
        }
        $i+=1000;
        $coveragefile = pdo_query("SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000");
        $perc = ($i/$total)*100;
        if ($perc-$previousperc>10) {
            echo round($perc, 3)."% done.<br>";
            flush();
            ob_flush();
            $previousperc = $perc;
        }
    }

  // Delete files with the same crc32 and upgrade
  $previouscrc32 = 0;
    $coveragefile = pdo_query("SELECT id,crc32 FROM coveragefile ORDER BY crc32 ASC,id ASC");
    $total = pdo_num_rows($coveragefile);
    $i=0;
    $previousperc = 0;
    while ($coveragefile_array = pdo_fetch_array($coveragefile)) {
        $id = $coveragefile_array["id"];
        $crc32 = $coveragefile_array["crc32"];
        if ($crc32 == $previouscrc32) {
            pdo_query("UPDATE coverage SET fileid='$currentid' WHERE fileid='$id'");
            pdo_query("UPDATE coveragefilelog SET fileid='$currentid' WHERE fileid='$id'");
            pdo_query("DELETE FROM coveragefile WHERE id='$id'");
        } else {
            $currentid = $id;
            $perc = ($i/$total)*100;
            if ($perc-$previousperc>10) {
                echo round($perc, 3)."% done.<br>";
                flush();
                ob_flush();
                $previousperc = $perc;
            }
        }
        $previouscrc32 = $crc32;
        $i++;
    }

  /** Remove the Duplicates in the coverage section */
  $coverage = pdo_query("SELECT buildid,fileid,count(*) as cnt FROM coverage GROUP BY buildid,fileid");
    while ($coverage_array = pdo_fetch_array($coverage)) {
        $cnt = $coverage_array["cnt"];
        if ($cnt > 1) {
            $buildid = $coverage_array["buildid"];
            $fileid = $coverage_array["fileid"];
            $limit = $cnt-1;
            $sql = "DELETE FROM coverage WHERE buildid='$buildid' AND fileid='$fileid'";
            $sql .= " LIMIT ".$limit;
            pdo_query($sql);
        }
    }

  /** SECOND STEP */
}

/** Check the builds with wrong date */
if ($CheckBuildsWrongDate) {
    $currentdate = time()+3600*24*3; // or 3 days away from now
  $forwarddate = date(FMT_DATETIME, $currentdate);

    $builds = pdo_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
    while ($builds_array = pdo_fetch_array($builds)) {
        $buildid = $builds_array["id"];
        echo $builds_array['name']."-".$builds_array['starttime']."<br>";
    }
}

/** Delete the builds with wrong date */
if ($DeleteBuildsWrongDate) {
    $currentdate = time()+3600*24*3; // or 3 days away from now
  $forwarddate = date(FMT_DATETIME, $currentdate);

    $builds = pdo_query("SELECT id FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
    while ($builds_array = pdo_fetch_array($builds)) {
        $buildid = $builds_array["id"];
    //echo $buildid."<br>";
    remove_build($buildid);
    }
}

if ($FixBuildBasedOnRule) {
    // loop through the list of build2group
  $buildgroups = pdo_query("SELECT * from build2group");
    while ($buildgroup_array = pdo_fetch_array($buildgroups)) {
        $buildid = $buildgroup_array["buildid"];

        $build = pdo_query("SELECT * from build WHERE id='$buildid'");
        $build_array = pdo_fetch_array($build);
        $type = $build_array["type"];
        $name = $build_array["name"];
        $siteid = $build_array["siteid"];
        $projectid = $build_array["projectid"];
        $submittime = $build_array["submittime"];

        $build2grouprule = pdo_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid')
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='1980-01-01 00:00:00')");
        echo pdo_error();
        if (pdo_num_rows($build2grouprule)>0) {
            $build2grouprule_array = pdo_fetch_array($build2grouprule);
            $groupid = $build2grouprule_array["groupid"];
            pdo_query("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
        }
    }
} // end FixBuildBasedOnRule

if ($CreateDefaultGroups) {
    // Loop throught the projects
  $n = 0;
    $projects = pdo_query("SELECT id FROM project");
    while ($project_array = pdo_fetch_array($projects)) {
        $projectid = $project_array["id"];

        if (pdo_num_rows(pdo_query("SELECT projectid FROM buildgroup WHERE projectid='$projectid'"))==0) {
            // Add the default groups
       pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Nightly','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Nightly Builds')");
            $id = pdo_insert_id("buildgroup");
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','1','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            echo pdo_error();
            pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Continuous','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Continuous Builds')");
            $id = pdo_insert_id("buildgroup");
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','2','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            pdo_query("INSERT INTO buildgroup(name,projectid,starttime,endtime,description)
                  VALUES ('Experimental','$projectid','1980-01-01 00:00:00','1980-01-01 00:00:00','Experimental Builds')");
            $id = pdo_insert_id("buildgroup");
            pdo_query("INSERT INTO buildgroupposition(buildgroupid,position,starttime,endtime)
                  VALUES ('$id','3','1980-01-01 00:00:00','1980-01-01 00:00:00')");
            $n++;
        }
    }

    $xml .= add_XML_value("alert", $n." projects have now default groups.");
} // end CreateDefaultGroups
elseif ($AssignBuildToDefaultGroups) {
    // Loop throught the builds
  $builds = pdo_query("SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)");

    while ($build_array = pdo_fetch_array($builds)) {
        $buildid = $build_array["id"];
        $buildtype = $build_array["type"];
        $projectid = $build_array["projectid"];

        $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));

        $groupid = $buildgroup_array["id"];
        pdo_query("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')");
    }

    $xml .= add_XML_value("alert", "Builds have been added to default groups successfully.");
} // end AssignBuildToDefaultGroups


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml, "upgrade");
