<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $RCSfile: common.php,v $
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
include("common.php"); 

@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";


@$CreateDefaultGroups = $_POST["CreateDefaultGroups"];
@$AssignBuildToDefaultGroups = $_POST["AssignBuildToDefaultGroups"];
@$FixBuildBasedOnRule = $_POST["FixBuildBasedOnRule"];
@$FixNewTableTest = $_POST["FixNewTableTest"];

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
    
    $oldimages = mysql_query("SELECT * from image2test WHERE testid='$oldtestid'");
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
        $sql = "SELECT count(imgid) FROM image2test2 WHERE testid='$currentid' ";
        
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
          $query = "INSERT INTO image2test2(imgid, testid, role)
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
