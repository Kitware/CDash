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

@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin
 
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Maintenance</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Maintenance</menusubtitle>";
$xml .= "<minversion>".$CDASH_VERSION_MAJOR.".".$CDASH_VERSION_MINOR."</minversion>";

@$CreateDefaultGroups = $_POST["CreateDefaultGroups"];
@$AssignBuildToDefaultGroups = $_POST["AssignBuildToDefaultGroups"];
@$FixBuildBasedOnRule = $_POST["FixBuildBasedOnRule"];
@$FixNewTableTest = $_POST["FixNewTableTest"];
@$DeleteBuildsWrongDate = $_POST["DeleteBuildsWrongDate"];
@$CheckBuildsWrongDate = $_POST["CheckBuildsWrongDate"];
@$ComputeTestTiming = $_POST["ComputeTestTiming"];
@$ComputeUpdateStatistics = $_POST["ComputeUpdateStatistics"];

@$Upgrade = $_POST["Upgrade"];

if(!isset($CDASH_DB_TYPE))
  {
  $db_type = 'mysql';
  }
else
  {
  $db_type = $CDASH_DB_TYPE;
  }
  
if(isset($_GET['upgrade-tables']))
{ 
  // Apply all the patches
  foreach(glob("sql/".$db_type."/cdash-upgrade-*.sql") as $filename)
    {
    $file_content = file($filename);
    $query = "";
    foreach($file_content as $sql_line)
      {
      $tsl = trim($sql_line);
      
      if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) 
         {
         $query .= $sql_line;
         if(preg_match("/;\s*$/", $sql_line)) 
           {
           $query = str_replace(";", "", "$query");
           $result = pdo_query($query);
           if (!$result)
             {
             if($db_type != "pgsql") // postgresql doesn't know CREATE TABLE IF NOT EXITS so we don't die
               {        
               die(pdo_error());
               }
             }
           $query = "";
           }
         }
       } // end for each line
    } // end for each upgrade file
  exit();
}

if(isset($_GET['upgrade-0-8']))
{ 
  // Add the index if they don't exist
  $querycrc32 = pdo_query("SELECT crc32 FROM coveragefile LIMIT 1");
  if(!$querycrc32)
    {
    pdo_query("ALTER TABLE coveragefile ADD crc32 int(11)");
    pdo_query("ALTER TABLE coveragefile ADD INDEX (crc32)");
    }
    
  // Compression the coverage
  CompressCoverage();
  
  exit();
}

if(isset($_GET['upgrade-1-0']))
{ 
  $description = pdo_query("SELECT description FROM buildgroup LIMIT 1");
  if(!$description)
    {
    pdo_query("ALTER TABLE buildgroup ADD description text");
    }
  $cvsviewertype = pdo_query("SELECT cvsviewertype FROM project LIMIT 1");
  if(!$cvsviewertype)
    {
    pdo_query("ALTER TABLE project ADD cvsviewertype varchar(10)");
    }
  
  if(pdo_query("ALTER TABLE site2user DROP PRIMARY KEY"))
    {
    pdo_query("ALTER TABLE site2user ADD INDEX (siteid)");
    pdo_query("ALTER TABLE build ADD INDEX (starttime)");
    }

  // Add test timing as well as key 'name' for test
  $timestatus = pdo_query("SELECT timestatus FROM build2test LIMIT 1");
  if(!$timestatus)
    {
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
  if(!pdo_query("SELECT testtimestdthreshold FROM project LIMIT 1"))
    {
    pdo_query("ALTER TABLE project ADD testtimestdthreshold float(3,1) default '1.0'");
    }
    
  // Add an option to show the testtime or not   
  if(!pdo_query("SELECT showtesttime FROM project LIMIT 1"))
    {
    pdo_query("ALTER TABLE project ADD showtesttime tinyint(4) default '0'");
    }
  exit();
}

if(isset($_GET['upgrade-1-2']))
{ 
  // Replace the field 'output' in the table test from 'text' to 'mediumtext'
  $result = pdo_query("SELECT output FROM test LIMIT 1");
  $type  = pdo_field_type($result,0);
  if($type == "blob" || $type == "text")
    {
    $result = pdo_query("ALTER TABLE test CHANGE output output MEDIUMTEXT");
    }
  
  // Change the file from blob to longblob
  $result = pdo_query("SELECT file FROM coveragefile LIMIT 1");
  $length = mysql_field_len($result, 0);
  if($length == 65535)
    {
    $result = pdo_query("ALTER TABLE coveragefile CHANGE file file LONGBLOB");
    }

  // Compress the notes
  if(!pdo_query("SELECT crc32 FROM note LIMIT 1"))
    {
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
  if(!$timestatus)
    {
    pdo_query("ALTER TABLE project ADD testtimemaxstatus tinyint(4) default '3'");
    pdo_query("ALTER TABLE project ADD emailmaxitems tinyint(4) default '5'");
    pdo_query("ALTER TABLE project ADD emailmaxchars int(11) default '255'");
    }
  
  // Add summary email
  $summaryemail = pdo_query("SELECT summaryemail FROM buildgroup LIMIT 1");
  if(!$summaryemail)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      pdo_query("ALTER TABLE \"buildgroup\" ADD \"summaryemail\" smallint DEFAULT '0'");
      }
    else
      {
      pdo_query("ALTER TABLE buildgroup ADD summaryemail tinyint(4) default '0'");
      }
    }   
  
  // Add emailcategory 
  $emailcategory = pdo_query("SELECT emailcategory FROM user2project LIMIT 1");
  if(!$emailcategory)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      pdo_query("ALTER TABLE \"user2project\" ADD \"emailcategory\" smallint DEFAULT '62'");
      }
    else
      {
      pdo_query("ALTER TABLE user2project ADD emailcategory tinyint(4) default '62'");
      }
    }     
  exit();
}

// 1.4 Upgrade
if(isset($_GET['upgrade-1-4']))
{    
  //  Add fields in the project table 
  $starttime = pdo_query("SELECT starttime FROM subproject LIMIT 1");
  if(!$starttime)
    {
    pdo_query("ALTER TABLE subproject ADD starttime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
    pdo_query("ALTER TABLE subproject ADD endtime TIMESTAMP NOT NULL default '1980-01-01 00:00:00'");
    }
  
  // Create the right indexes if necessary  
  if(!pdo_check_index_exists('buildfailure','buildid'))
    {
    pdo_query("ALTER TABLE buildfailure ADD INDEX ( buildid )");
    }
    
  if(!pdo_check_index_exists('buildfailure','type'))
    {
    pdo_query("ALTER TABLE buildfailure ADD INDEX ( type )");
    }
  
  // Create the new table buildfailure arguments if the old one is still there
  if(pdo_query("SELECT buildfailureid FROM buildfailureargument"))
    {
    pdo_query("DROP TABLE IF EXISTS buildfailureargument");
    pdo_query("CREATE TABLE IF NOT EXISTS `buildfailureargument` (
              `id` bigint(20) NOT NULL auto_increment,
              `argument` varchar(60) NOT NULL,
              PRIMARY KEY  (`id`),
              KEY `argument` (`argument`))");
    }
  
  if(!pdo_check_index_exists('buildfailureargument','argument'))
    {
    pdo_query("ALTER TABLE buildfailureargument ADD INDEX ( argument )");
    }

  //  Add fields in the buildgroup table 
  $includesubprojectotal = pdo_query("SELECT includesubprojectotal FROM buildgroup LIMIT 1");
  if(!$includesubprojectotal)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      pdo_query("ALTER TABLE \"buildgroup\" ADD \"includesubprojectotal\" smallint DEFAULT '1'");
      }
    else
      {
      pdo_query("ALTER TABLE buildgroup ADD includesubprojectotal tinyint(4) default '1'");
      }
    }
  
  //  Add fields in the project table 
  $includesubprojectotal = pdo_query("SELECT emailredundantfailures FROM project LIMIT 1");
  if(!$includesubprojectotal)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      pdo_query("ALTER TABLE \"project\" ADD \"emailredundantfailures\" smallint DEFAULT '0'");
      }
    else
      {
      pdo_query("ALTER TABLE project ADD emailredundantfailures tinyint(4) default '0'");
      }
    }
  
  // Add the order field in the database
  $buildargumentorder = pdo_query("SELECT place FROM buildfailure2argument LIMIT 1");
  if(!$buildargumentorder)
    {
    if($CDASH_DB_TYPE == "pgsql")
      {
      pdo_query("ALTER TABLE \"buildfailure2argument\" ADD \"place\" bigint DEFAULT '0'");
      }
    else
      {
      pdo_query("ALTER TABLE buildfailure2argument ADD place int(11) default '0'");
      }
    }
        
  // Remove duplicates in buildfailureargument
  //pdo_query("DELETE FROM buildfailureargument WHERE id NOT IN (SELECT buildfailureid as id FROM buildfailure2argument)");
   
  // Set the database version
  setVersion();
  exit();
}

// When adding new tables they should be added to the SQL installation file
// and here as well
if($Upgrade)
{
  $xml .= "<upgrade>1</upgrade>";  
}

// Compute the testtime
if($ComputeTestTiming)
{ 
  @$TestTimingDays = $_POST["TestTimingDays"];
  if(is_numeric($TestTimingDays) && $TestTimingDays>0)
    {
    ComputeTestTiming($TestTimingDays);
    $xml .= add_XML_value("alert","Timing for tests has been computed successfully.");
    }
  else
   {
   $xml .= add_XML_value("alert","Wrong number of days.");
   }
}

// Compute the user statistics
if($ComputeUpdateStatistics)
{ 
  @$UpdateStatisticsDays = $_POST["UpdateStatisticsDays"];
  if(is_numeric($UpdateStatisticsDays) && $UpdateStatisticsDays>0)
    {
    ComputeUpdateStatistics($UpdateStatisticsDays);
    $xml .= add_XML_value("alert","User statistics has been computed successfully.");
    }
  else
   {
   $xml .= add_XML_value("alert","Wrong number of days.");
   } 
}


/** Compress the notes. Since they are almost always the same form build to build */
function CompressNotes()
{
  // Rename the old note table
  if(!pdo_query("RENAME TABLE note TO notetemp"))
    {
    echo pdo_error();
    echo "Cannot rename table note to notetemp";
    return false;
    }

  // Create the new note table
  if(!pdo_query("CREATE TABLE note (
     id bigint(20) NOT NULL auto_increment,
     text mediumtext NOT NULL,
     name varchar(255) NOT NULL,
     crc32 int(11) NOT NULL,
     PRIMARY KEY  (id),
     KEY crc32 (crc32))"))
     {
     echo pdo_error();
     echo "Cannot create new table 'note'";
     return false;
     }
  
  // Move each note from notetemp to the new table
  $note = pdo_query("SELECT * FROM notetemp ORDER BY buildid ASC");
  while($note_array = pdo_fetch_array($note))
    {
    $text = $note_array["text"];
    $name = $note_array["name"];
    $time = $note_array["time"];
    $buildid = $note_array["buildid"];
    $crc32 = crc32($text.$name);
    
    $notecrc32 =  pdo_query("SELECT id FROM note WHERE crc32='$crc32'");
    if(pdo_num_rows($notecrc32) == 0)
      {
      pdo_query("INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')");
      $noteid = pdo_insert_id("note");
      echo pdo_error();
      }
    else // already there
      {
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


  while($project_array = pdo_fetch_array($project))
    {    
    $projectid = $project_array["id"];
    echo "PROJECT id: ".$projectid."<br>";
    $testtimestd = $project_array["testtimestd"];
    $projecttimestdthreshold = $project_array["testtimestdthreshold"]; 
    
    // only test a couple of days
    $now = gmdate(FMT_DATETIME,time()-3600*24*$days);
    
    // Find the builds
    $builds = pdo_query("SELECT starttime,siteid,name,type,id
                               FROM build
                               WHERE build.projectid='$projectid' AND build.starttime>'$now'
                               ORDER BY build.starttime ASC");
    
    $total = pdo_num_rows($builds);
    echo pdo_error();
    
    $i=0;
    $previousperc = 0;
    while($build_array = pdo_fetch_array($builds))
      {                           
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
      if(pdo_num_rows($previousbuild)>0)
        {
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
        while($test_array = pdo_fetch_array($previoustest))
          {
          $test = array();
          $test['id'] = $test_array["testid"];
          $test['name'] = $test_array["name"];
          $testarray[] = $test;
          }

        while($test_array = pdo_fetch_array($tests))
          {
          $testtime = $test_array['time'];
          $testid = $test_array['testid'];
          $testname = $test_array['name'];

         $previoustestid = 0;

         foreach($testarray as $test)
          {
          if($test['name']==$testname)
            {
            $previoustestid = $test['id'];
            break;
            }
          }

                             
        if($previoustestid>0)
            {
            $previoustest = pdo_query("SELECT timemean,timestd FROM build2test
                                       WHERE buildid='$previousbuildid' 
                                       AND build2test.testid='$previoustestid' 
                                       ");

            $previoustest_array = pdo_fetch_array($previoustest);
            $previoustimemean = $previoustest_array["timemean"];
            $previoustimestd = $previoustest_array["timestd"];
          
           // Check the current status
          if($previoustimestd<$projecttimestdthreshold)
            {
            $previoustimestd = $projecttimestdthreshold;
            }
          
            // Update the mean and std
            $timemean = (1-$weight)*$previoustimemean+$weight*$testtime;
            $timestd = sqrt((1-$weight)*$previoustimestd*$previoustimestd + $weight*($testtime-$timemean)*($testtime-$timemean));
            
            // Check the current status
            if($testtime > $previoustimemean+$testtimestd*$previoustimestd) // only do positive std
              {
              $timestatus = 1; // flag
               }
            else
              {
              $timestatus = 0;
              }
            }
         else // the test doesn't exist
            {
            $timestd = 0;
            $timestatus = 0;
            $timemean = $testtime;
            }
          
          
      
          pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus' 
                        WHERE buildid='$buildid' AND testid='$testid'");
       
          }  // end loop through the test  
          
        }
      else // this is the first build
        {
        $timestd = 0;
        $timestatus = 0;
        
        // Loop throught the tests
        $tests = pdo_query("SELECT time,testid FROM build2test WHERE buildid='$buildid'");
        while($test_array = pdo_fetch_array($tests))
          {
          $timemean = $test_array['time'];
          $testid = $test_array['testid'];
        
           pdo_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus' 
                        WHERE buildid='$buildid' AND testid='$testid'");
          }          
      } // loop through the tests
        
      // Progress bar  
      $perc = ($i/$total)*100;
      if($perc-$previousperc>5)
        {
        echo round($perc,3)."% done.<br>";
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
  // Loop through the projects
  $project = pdo_query("SELECT id FROM project");
  
  while($project_array = pdo_fetch_array($project))
    {    
    $projectid = $project_array["id"];
    echo "PROJECT id: ".$projectid."<br>";
    
    // only test a couple of days
    $now = gmdate(FMT_DATETIME,time()-3600*24*$days);
    
    // Find the builds
    $builds = pdo_query("SELECT starttime,siteid,name,type,id
                               FROM build
                               WHERE build.projectid='$projectid' AND build.starttime>'$now'
                               ORDER BY build.starttime ASC");
    
    $total = pdo_num_rows($builds);
    echo pdo_error();
    
    $i=0;
    $previousperc = 0;
    while($build_array = pdo_fetch_array($builds))
      {                           
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
      
      if(pdo_num_rows($previousbuild)>0)
        {
        $previousbuild_array = pdo_fetch_array($previousbuild);
        $previousbuildid = $previousbuild_array["id"];   
        compute_update_statistics($projectid,$buildid,$previousbuildid);
        }
      else
        {
        compute_update_statistics($projectid,$buildid,0);
        }

      // Progress bar  
      $perc = ($i/$total)*100;
      if($perc-$previousperc>5)
        {
        echo round($perc,3)."% done.<br>";
        flush();
        ob_flush();
        $previousperc = $perc;
        }
      $i++;
      } // end looping through builds 
    } // end looping through projects
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
  while(pdo_num_rows($coveragefile)>0)
    {
    while($coveragefile_array = pdo_fetch_array($coveragefile))
      {
      $fullpath = $coveragefile_array["fullpath"];
      $file = $coveragefile_array["file"];
      $id = $coveragefile_array["id"];
      $crc32 = crc32($fullpath.$file);
      pdo_query("UPDATE coveragefile SET crc32='$crc32' WHERE id='$id'");
      }
    $i+=1000;
    $coveragefile = pdo_query("SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000");
    $perc = ($i/$total)*100;
    if($perc-$previousperc>10)
      {
      echo round($perc,3)."% done.<br>";
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
  while($coveragefile_array = pdo_fetch_array($coveragefile))
    {
    $id = $coveragefile_array["id"];
    $crc32 = $coveragefile_array["crc32"];
    if($crc32 == $previouscrc32)
      {
      pdo_query("UPDATE coverage SET fileid='$currentid' WHERE fileid='$id'");
      pdo_query("UPDATE coveragefilelog SET fileid='$currentid' WHERE fileid='$id'");
      pdo_query("DELETE FROM coveragefile WHERE id='$id'");
      }
    else
      {
      $currentid = $id;
      $perc = ($i/$total)*100;
      if($perc-$previousperc>10)
        {
        echo round($perc,3)."% done.<br>";
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
  while($coverage_array = pdo_fetch_array($coverage))
    {
    $cnt = $coverage_array["cnt"];
    if($cnt > 1)
      {
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
if($CheckBuildsWrongDate)
{
  $currentdate = time()+3600*24*3; // or 3 days away from now
  $forwarddate = date(FMT_DATETIME,$currentdate);
   
  $builds = pdo_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
  while($builds_array = pdo_fetch_array($builds))
    {
    $buildid = $builds_array["id"];
    echo $builds_array['name']."-".$builds_array['starttime']."<br>";
    }
}

/** Delete the builds with wrong date */
if($DeleteBuildsWrongDate)
{
  $currentdate = time()+3600*24*3; // or 3 days away from now
  $forwarddate = date(FMT_DATETIME,$currentdate);

  $builds = pdo_query("SELECT id FROM build WHERE starttime<'1975-12-31 23:59:59' OR starttime>'$forwarddate'");
  while($builds_array = pdo_fetch_array($builds))
    {
    $buildid = $builds_array["id"];
    //echo $buildid."<br>";
    remove_build($buildid); 
    }
}

/** */
if($FixNewTableTest)
  {
  $num = pdo_fetch_array(pdo_query("SELECT COUNT(id) FROM test"));
  $n = $num[0];
  echo $n;

  $step = 5000;
  for($j=280682;$j<=$n;$j+=$step)
    {
  $oldtest = pdo_query("SELECT * from test ORDER BY id ASC LIMIT $j,$step");
  while($oldtest_array = pdo_fetch_array($oldtest))
    {
    // Create the variables
    $oldtestid = $oldtest_array["id"];
    $buildid = $oldtest_array["buildid"];
    $name = $oldtest_array["name"];
    $status = $oldtest_array["status"];
    $path = $oldtest_array["path"];
    $fullname = $oldtest_array["fullname"];
    $command = stripslashes($oldtest_array["command"]);
    $time = $oldtest_array["time"];
    $details = $oldtest_array["details"];
    $output = stripslashes($oldtest_array["output"]);
    
    // Add the images
    $images = array();
    
    $oldimages = pdo_query("SELECT * from test2image WHERE testid='$oldtestid'");
    while($oldimages_array = pdo_fetch_array($oldimages))
      {
      $image["id"]=$oldimages_array["imgid"];
      $image["role"]=$oldimages_array["role"];
      $images[] = $image;
      }


   // Do the processing
  
    $command = addslashes($command);
    $output = addslashes($output);
    
    // Check if the test doesn't exist
    $test = pdo_query("SELECT id FROM test2 WHERE name='$name' AND path='$path' AND fullname='$fullname' AND command='$command' AND output='$output' LIMIT 1");
    
    $testexists = false;
    
    if(pdo_num_rows($test) > 0) // test exists
      {  
      while($test_array = pdo_fetch_array($test))
        {
        $currentid = $test_array["id"];
        $sql = "SELECT count(imgid) FROM test2image2 WHERE testid='$currentid' ";
        
        // need to double check that the images are the same as well
        $i=0;
        foreach($images as $image)
          {
          $imgid = $image["id"];
          if($i==0)
            {
            $sql .= "AND (";
            }
          
          if($i>0)
            {
            $sql .= " OR";
            }
            
          $sql .= " imgid='$imagid' ";
             
          $i++;
          if($i==count($images))
            {
            $sql .= ")";
            }   
          } // end for each image
        
         $nimage_array = pdo_fetch_array(pdo_query($sql));  
         $nimages = $nimage_array[0];
      
         if($nimages == count($images))
           {
           $testid = $test_array["id"];
           $testexists = true;
           break;
           } 
         } // end while test_array  
       }   
    
    if(!$testexists)
      {
      // Need to create a new test
      $query = "INSERT INTO test2 (name,path,fullname,command,details, output) 
                VALUES ('$name','$path','$fullname','$command', '$details', '$output')";
      if(pdo_query("$query"))
        {
        $testid = pdo_insert_id("test2");
        foreach($images as $image)
          {
          $imgid = $image["id"];
          $role = $image["role"];
          $query = "INSERT INTO test2image2(imgid, testid, role)
                    VALUES('$imgid', '$testid', '$role')";
          if(!pdo_query("$query"))
            {
            echo pdo_error();
            }
          }
        }
      else
        {
        echo pdo_error();
        } 
      }  
    
    // Add into build2test
    pdo_query("INSERT INTO build2test (buildid,testid,status,time) 
                 VALUES ('$buildid','$testid','$status','$time')");
    } // end loop test
    } // end loop $j
  } // end submit


if($FixBuildBasedOnRule)
  {
  // loop through the list of build2group
  $buildgroups = pdo_query("SELECT * from build2group");
  while($buildgroup_array = pdo_fetch_array($buildgroups))
    {
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
    if(pdo_num_rows($build2grouprule)>0)
      {
      $build2grouprule_array = pdo_fetch_array($build2grouprule);
      $groupid = $build2grouprule_array["groupid"];
      pdo_query ("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
      }
    }
  } // end FixBuildBasedOnRule

if($CreateDefaultGroups)
  {
  // Loop throught the projects
  $n = 0;
  $projects = pdo_query("SELECT id FROM project");
  while($project_array = pdo_fetch_array($projects))
     {
     $projectid = $project_array["id"];
     
     if(pdo_num_rows(pdo_query("SELECT projectid FROM buildgroup WHERE projectid='$projectid'"))==0)
       {
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
     
  $xml .= add_XML_value("alert",$n." projects have now default groups.");
  
  } // end CreateDefaultGroups
else if($AssignBuildToDefaultGroups)
  {
  // Loop throught the builds
  $builds = pdo_query("SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)");
 
  while($build_array = pdo_fetch_array($builds))
     {
     $buildid = $build_array["id"];
     $buildtype = $build_array["type"];
     $projectid = $build_array["projectid"];
     
     $buildgroup_array = pdo_fetch_array(pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));
     
     $groupid = $buildgroup_array["id"];
     pdo_query("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')"); 
     }
     
  $xml .= add_XML_value("alert","Builds have been added to default groups successfully.");

  } // end AssignBuildToDefaultGroups


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"backwardCompatibilityTools");
?>
