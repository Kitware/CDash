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
include("config.php");
include('login.php');
include("common.php");
include("version.php");

set_time_limit(0);

@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

checkUserPolicy(@$_SESSION['cdash']['loginid'],0); // only admin

$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Backward Compatibility</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Tools</menusubtitle>";

@$CreateDefaultGroups = $_POST["CreateDefaultGroups"];
@$AssignBuildToDefaultGroups = $_POST["AssignBuildToDefaultGroups"];
@$FixBuildBasedOnRule = $_POST["FixBuildBasedOnRule"];
@$FixNewTableTest = $_POST["FixNewTableTest"];
@$DeleteBuildsWrongDate = $_POST["DeleteBuildsWrongDate"];
@$CheckBuildsWrongDate = $_POST["CheckBuildsWrongDate"];
@$ComputeTestTiming = $_POST["ComputeTestTiming"];

@$Upgrade = $_POST["Upgrade"];

if(isset($_GET['upgrade-tables']))
{ 
  // Apply all the patches
  foreach(glob("sql/cdash-upgrade-*.sql") as $filename)
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
           $result = mysql_query($query);
           if (!$result)
             {        
             die(mysql_error());
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
  $querycrc32 = mysql_query("SELECT crc32 FROM coveragefile LIMIT 1");
  if(!$querycrc32)
    {
    mysql_query("ALTER TABLE coveragefile ADD crc32 int(11)");
    mysql_query("ALTER TABLE coveragefile ADD INDEX (crc32)");
    }
    
  // Compression the coverage
  CompressCoverage();
  
  exit();
}

if(isset($_GET['upgrade-1-0']))
{ 
  $description = mysql_query("SELECT description FROM buildgroup LIMIT 1");
  if(!$description)
    {
    mysql_query("ALTER TABLE buildgroup ADD description text");
    }
  $cvsviewertype = mysql_query("SELECT cvsviewertype FROM project LIMIT 1");
  if(!$cvsviewertype)
    {
    mysql_query("ALTER TABLE project ADD cvsviewertype varchar(10)");
    }
  
  if(mysql_query("ALTER TABLE site2user DROP PRIMARY KEY"))
    {
    mysql_query("ALTER TABLE site2user ADD INDEX (siteid)");
    mysql_query("ALTER TABLE build ADD INDEX (starttime)");
    }

  // Add test timing as well as key 'name' for test
  $timestatus = mysql_query("SELECT timestatus FROM build2test LIMIT 1");
  if(!$timestatus)
    {
    mysql_query("ALTER TABLE build2test ADD timemean float(7,2) default '0.00'");
    mysql_query("ALTER TABLE build2test ADD timestd float(7,2) default '0.00'");  
    mysql_query("ALTER TABLE build2test ADD timestatus tinyint(4) default '0'");
    mysql_query("ALTER TABLE build2test ADD INDEX (timestatus)"); 
    // Add timing test fields in the table project
    mysql_query("ALTER TABLE project ADD testtimestd float(3,1) default '4.0'");
    // Add the index name in the table test
    mysql_query("ALTER TABLE test ADD INDEX (name)");
    }
  
  // Add the testtimethreshold  
  if(!mysql_query("SELECT testtimestdthreshold FROM project LIMIT 1"))
    {
    mysql_query("ALTER TABLE project ADD testtimestdthreshold float(3,1) default '1.0'");
    }
    
  // Add an option to show the testtime or not   
  if(!mysql_query("SELECT showtesttime FROM project LIMIT 1"))
    {
    mysql_query("ALTER TABLE project ADD showtesttime tinyint(4) default '0'");
    }
  exit();
}

if(isset($_GET['upgrade-1-2']))
{ 
  // Replace the field 'output' in the table test from 'text' to 'mediumtext'
  $result = mysql_query("SELECT output FROM test LIMIT 1");
  $type  = mysql_field_type($result,0);
  if($type == "blob" || $type == "text")
    {
    $result = mysql_query("ALTER TABLE test CHANGE output output MEDIUMTEXT");
    }
  
  // Compress the notes
  if(!mysql_query("SELECT crc32 FROM note LIMIT 1"))
    {
    CompressNotes();
    }
  
  exit();
}

// When adding new tables they should be added to the SQL installation file
// and here as well
if($Upgrade)
{
  $xml .= "<upgrade>1</upgrade>";  
}

// Compute the testtime from the previous week (this is a test)
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


/** Compress the notes. Since they are almost always the same form build to build */
function CompressNotes()
{
  // Rename the old note table
  if(!mysql_query("RENAME TABLE note TO notetemp"))
    {
    echo mysql_error();
    echo "Cannot rename table note to notetemp";
    return false;
    }

  // Create the new note table
  if(!mysql_query("CREATE TABLE note (
     id bigint(20) NOT NULL auto_increment,
     text mediumtext NOT NULL,
     name varchar(255) NOT NULL,
     crc32 int(11) NOT NULL,
     PRIMARY KEY  (id),
     KEY crc32 (crc32))"))
     {
     echo mysql_error();
     echo "Cannot create new table 'note'";
     return false;
     }
  
  // Move each note from notetemp to the new table
  $note = mysql_query("SELECT * FROM notetemp ORDER BY buildid ASC");
  while($note_array = mysql_fetch_array($note))
    {
    $text = $note_array["text"];
    $name = $note_array["name"];
    $time = $note_array["time"];
    $buildid = $note_array["buildid"];
    $crc32 = crc32($text.$name);
    
    $notecrc32 =  mysql_query("SELECT id FROM note WHERE crc32='$crc32'");
    if(mysql_num_rows($notecrc32) == 0)
      {
      mysql_query("INSERT INTO note (text,name,crc32) VALUES ('$text','$name','$crc32')");
      $noteid = mysql_insert_id();
      echo mysql_error();
      }
    else // already there
      {
      $notecrc32_array = mysql_fetch_array($notecrc32);
      $noteid = $notecrc32_array["id"];
      }

    mysql_query("INSERT INTO build2note (buildid,noteid,time) VALUES ('$buildid','$noteid','$time')");
    echo mysql_error();
    }
  
  // Drop the old note table  
  mysql_query("DROP TABLE notetemp");
  echo mysql_error();
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
  $project = mysql_query("SELECT id,testtimestd,testtimestdthreshold FROM project");
  $weight = 0.3;


  while($project_array = mysql_fetch_array($project))
    {    
    $projectid = $project_array["id"];
    echo "PROJECT id: ".$projectid."<br>";
    $testtimestd = $project_array["testtimestd"];
    $projecttimestdthreshold = $project_array["testtimestdthreshold"]; 
    
    // only test a couple of days
    $now = gmdate("Y-m-d H:i:s",time()-3600*24*$days);
    
    // Find the builds
    $builds = mysql_query("SELECT starttime,siteid,name,type,id
                               FROM build
                               WHERE build.projectid='$projectid' AND build.starttime>'$now'
                               ORDER BY build.starttime ASC");
    
    $total = mysql_num_rows($builds);
    echo mysql_error();
    
    $i=0;
    $previousperc = 0;
    while($build_array = mysql_fetch_array($builds))
      {                           
      $buildid = $build_array["id"];
      $buildname = $build_array["name"];
      $buildtype = $build_array["type"];
      $starttime = $build_array["starttime"];
      $siteid = $build_array["siteid"];
      
      // Find the previous build
      $previousbuild = mysql_query("SELECT id FROM build
                                    WHERE build.siteid='$siteid' 
                                    AND build.type='$buildtype' AND build.name='$buildname'
                                    AND build.projectid='$projectid' 
                                    AND build.starttime<'$starttime' 
                                    AND build.starttime>'$now'
                                    ORDER BY build.starttime DESC LIMIT 1");

      echo mysql_error();

      // If we have one
      if(mysql_num_rows($previousbuild)>0)
        {
        // Loop through the tests
        $previousbuild_array = mysql_fetch_array($previousbuild);
        $previousbuildid = $previousbuild_array ["id"];
  
        $tests = mysql_query("SELECT build2test.time,build2test.testid,test.name 
                              FROM build2test,test WHERE build2test.buildid='$buildid'
                              AND build2test.testid=test.id
                              ");
        echo mysql_error();
  
        flush();
        ob_flush();
     
        // Find the previous test
        $previoustest = mysql_query("SELECT build2test.testid,test.name FROM build2test,test
                                     WHERE build2test.buildid='$previousbuildid' 
                                     AND test.id=build2test.testid 
                                     ");
        echo mysql_error();
      
        $testarray = array();
        while($test_array = mysql_fetch_array($previoustest))
          {
          $test = array();
          $test['id'] = $test_array["testid"];
          $test['name'] = $test_array["name"];
          $testarray[] = $test;
          }

        while($test_array = mysql_fetch_array($tests))
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
            $previoustest = mysql_query("SELECT timemean,timestd FROM build2test
                                       WHERE buildid='$previousbuildid' 
                                       AND build2test.testid='$previoustestid' 
                                       ");

            $previoustest_array = mysql_fetch_array($previoustest);
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
          
          mysql_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus' 
                        WHERE buildid='$buildid' AND testid='$testid'");
       
          }  // end loop through the test  
          
        }
      else // this is the first build
        {
        $timestd = 0;
        $timestatus = 0;
        
        // Loop throught the tests
        $tests = mysql_query("SELECT time,testid FROM build2test WHERE buildid='$buildid'");
        while($test_array = mysql_fetch_array($tests))
          {
          $timemean = $test_array['time'];
          $testid = $test_array['testid'];
        
           mysql_query("UPDATE build2test SET timemean='$timemean',timestd='$timestd',timestatus='$timestatus' 
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
  $coveragefile =  mysql_query("SELECT count(*) FROM coveragefile WHERE crc32 IS NULL");
  $coveragefile_array = mysql_fetch_array($coveragefile);
  $total = $coveragefile_array["count(*)"];
  
  $i=0;
  $previousperc = 0;
  $coveragefile = mysql_query("SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000");
  while(mysql_num_rows($coveragefile)>0)
    {
    while($coveragefile_array = mysql_fetch_array($coveragefile))
      {
      $fullpath = $coveragefile_array["fullpath"];
      $file = $coveragefile_array["file"];
      $id = $coveragefile_array["id"];
      $crc32 = crc32($fullpath.$file);
      mysql_query("UPDATE coveragefile SET crc32='$crc32' WHERE id='$id'");
      }
    $i+=1000;
    $coveragefile = mysql_query("SELECT * FROM coveragefile WHERE crc32 IS NULL LIMIT 1000");
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
  $coveragefile = mysql_query("SELECT id,crc32 FROM coveragefile ORDER BY crc32 ASC,id ASC");
  $total = mysql_num_rows($coveragefile);
  $i=0;
  $previousperc = 0;
  while($coveragefile_array = mysql_fetch_array($coveragefile))
    {
    $id = $coveragefile_array["id"];
    $crc32 = $coveragefile_array["crc32"];
    if($crc32 == $previouscrc32)
      {
      mysql_query("UPDATE coverage SET fileid='$currentid' WHERE fileid='$id'");
      mysql_query("UPDATE coveragefilelog SET fileid='$currentid' WHERE fileid='$id'");
      mysql_query("DELETE FROM coveragefile WHERE id='$id'");
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
  $coverage = mysql_query("SELECT buildid,fileid,count(*) as cnt FROM coverage GROUP BY buildid,fileid");
  while($coverage_array = mysql_fetch_array($coverage))
    {
    $cnt = $coverage_array["cnt"];
    if($cnt > 1)
      {
      $buildid = $coverage_array["buildid"]; 
      $fileid = $coverage_array["fileid"]; 
      $limit = $cnt-1;
      $sql = "DELETE FROM coverage WHERE buildid='$buildid' AND fileid='$fileid'";
      $sql .= " LIMIT ".$limit;
      mysql_query($sql); 
      }
    }

  /** SECOND STEP */    
}

/** Check the builds with wrong date */
if($CheckBuildsWrongDate)
{
  $builds = mysql_query("SELECT id,name,starttime FROM build WHERE starttime<'1975-12-31 23:59:59'");
  while($builds_array = mysql_fetch_array($builds))
    {
    $buildid = $builds_array["id"];
    echo $builds_array['name']."-".$builds_array['starttime']."<br>";
    remove_build($buildid); 
    }
}

/** Delete the builds with wrong date */
if($DeleteBuildsWrongDate)
{
  $builds = mysql_query("SELECT id FROM build WHERE starttime<'1975-12-31 23:59:59'");
  while($builds_array = mysql_fetch_array($builds))
    {
    $buildid = $builds_array["id"];
    //echo $buildid."<br>";
    remove_build($buildid); 
    }
}

/** */
if($FixNewTableTest)
  {
  $num = mysql_fetch_array(mysql_query("SELECT COUNT(id) FROM test"));
  $n = $num[0];
  echo $n;

  $step = 5000;
  for($j=280682;$j<=$n;$j+=$step)
    {
  $oldtest = mysql_query("SELECT * from test ORDER BY id ASC LIMIT $j,$step");
  while($oldtest_array = mysql_fetch_array($oldtest))
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
    
    $oldimages = mysql_query("SELECT * from test2image WHERE testid='$oldtestid'");
    while($oldimages_array = mysql_fetch_array($oldimages))
      {
      $image["id"]=$oldimages_array["imgid"];
      $image["role"]=$oldimages_array["role"];
      $images[] = $image;
      }


   // Do the processing
  
    $command = addslashes($command);
    $output = addslashes($output);
    
    // Check if the test doesn't exist
    $test = mysql_query("SELECT id FROM test2 WHERE name='$name' AND path='$path' AND fullname='$fullname' AND command='$command' AND output='$output' LIMIT 1");
    
    $testexists = false;
    
    if(mysql_num_rows($test) > 0) // test exists
      {  
      while($test_array = mysql_fetch_array($test))
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
        
         $nimage_array = mysql_fetch_array(mysql_query($sql));  
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
      if(mysql_query("$query"))
        {
        $testid = mysql_insert_id();
        foreach($images as $image)
          {
          $imgid = $image["id"];
          $role = $image["role"];
          $query = "INSERT INTO test2image2(imgid, testid, role)
                    VALUES('$imgid', '$testid', '$role')";
          if(!mysql_query("$query"))
            {
            echo mysql_error();
            }
          }
        }
      else
        {
        echo mysql_error();
        } 
      }  
    
    // Add into build2test
    mysql_query("INSERT INTO build2test (buildid,testid,status,time) 
                 VALUES ('$buildid','$testid','$status','$time')");
    } // end loop test
    } // end loop $j
  } // end submit


if($FixBuildBasedOnRule)
  {
  // loop through the list of build2group
  $buildgroups = mysql_query("SELECT * from build2group");
  while($buildgroup_array = mysql_fetch_array($buildgroups))
    {
    $buildid = $buildgroup_array["buildid"];
    
    $build = mysql_query("SELECT * from build WHERE id='$buildid'");
    $build_array = mysql_fetch_array($build);
    $type = $build_array["type"];
    $name = $build_array["name"];
    $siteid = $build_array["siteid"];
    $projectid = $build_array["projectid"];
    $submittime = $build_array["submittime"];
        
    $build2grouprule = mysql_query("SELECT b2g.groupid FROM build2grouprule AS b2g, buildgroup as bg
                                    WHERE b2g.buildtype='$type' AND b2g.siteid='$siteid' AND b2g.buildname='$name'
                                    AND (b2g.groupid=bg.id AND bg.projectid='$projectid') 
                                    AND '$submittime'>b2g.starttime AND ('$submittime'<b2g.endtime OR b2g.endtime='0000-00-00 00:00:00')");
    echo mysql_error();                              
    if(mysql_num_rows($build2grouprule)>0)
      {
      $build2grouprule_array = mysql_fetch_array($build2grouprule);
      $groupid = $build2grouprule_array["groupid"];
      mysql_query ("UPDATE build2group SET groupid='$groupid' WHERE buildid='$buildid'");
      }
    }
  } // end FixBuildBasedOnRule

if($CreateDefaultGroups)
  {
  // Loop throught the projects
  $n = 0;
  $projects = mysql_query("SELECT id FROM project");
  while($project_array = mysql_fetch_array($projects))
     {
     $projectid = $project_array["id"];
     
     if(mysql_num_rows(mysql_query("SELECT projectid FROM buildgroup WHERE projectid='$projectid'"))==0)
       {
       // Add the default groups
       mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Nightly','$projectid')");
       $id = mysql_insert_id();
       mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','1')");
       echo mysql_error();
       mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Continuous','$projectid')");
       $id = mysql_insert_id();
       mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','2')");
       mysql_query("INSERT INTO buildgroup(name,projectid) VALUES ('Experimental','$projectid')");
       $id = mysql_insert_id();
       mysql_query("INSERT INTO buildgroupposition(buildgroupid,position) VALUES ('$id','3')");
       $n++;
       }
     }
     
  $xml .= add_XML_value("alert",$n." projects have now default groups.");
  
  } // end CreateDefaultGroups
else if($AssignBuildToDefaultGroups)
  {
  // Loop throught the builds
  $builds = mysql_query("SELECT id,type,projectid FROM build WHERE id NOT IN (SELECT buildid as id FROM build2group)");
 
  while($build_array = mysql_fetch_array($builds))
     {
     $buildid = $build_array["id"];
     $buildtype = $build_array["type"];
     $projectid = $build_array["projectid"];
     
     $buildgroup_array = mysql_fetch_array(mysql_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'"));
     
     $groupid = $buildgroup_array["id"];
     mysql_query("INSERT INTO build2group(buildid,groupid) VALUES ('$buildid','$groupid')"); 
     }
     
  $xml .= add_XML_value("alert","Builds have been added to default groups successfully.");

  } // end AssignBuildToDefaultGroups


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"backwardCompatibilityTools");
?>
