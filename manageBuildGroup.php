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
include("common.php"); ;
include('login.php');
include_once('common.php');
include('version.php');

if ($session_OK) 
{
@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

$userid = $_SESSION['cdash']['loginid'];
  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
$xml .= "<version>".$CDASH_VERSION."</version>";
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Build Groups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Build Groups</menusubtitle>";
  
@$projectid = $_GET["projectid"];

// If the projectid is not set and there is only one project we go directly to the page
if(!isset($projectid))
{
  $project = mysql_query("SELECT id FROM project");
 if(mysql_num_rows($project)==1)
   {
   $project_array = mysql_fetch_array($project);
   $projectid = $project_array["id"];
   }
}


@$show = $_GET["show"];

$role=0;

$user_array = mysql_fetch_array(mysql_query("SELECT admin FROM user WHERE id='$userid'"));
if($projectid)
  {
  $user2project = mysql_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
  if(mysql_num_rows($user2project)>0)
    {
    $user2project_array = mysql_fetch_array($user2project);
    $role = $user2project_array["role"];
    }  
  }
    
if($user_array["admin"]!=1 && $role<=1)
  {
  echo "You don't have the permissions to access this page";
  return;
  }
  
$sql = "SELECT id,name FROM project";
if($user_array["admin"] != 1)
  {
  $sql .= " WHERE id IN (SELECT projectid AS id FROM user2project WHERE userid='$userid' AND role>0)"; 
  }
$projects = mysql_query($sql);
while($project_array = mysql_fetch_array($projects))
   {
   $xml .= "<availableproject>";
   $xml .= add_XML_value("id",$project_array['id']);
   $xml .= add_XML_value("name",$project_array['name']);
   if($project_array['id']==$projectid)
      {
      $xml .= add_XML_value("selected","1");
      }
   $xml .= "</availableproject>";
   }

/*
function swap_array_element($array,$currentgroupid,$wantedposition)
{
  // Find the current position of the $currentgroupid
 if($wantedposition > count($array))
   {
  $wantedposition = count($array);
   }
 
 $i=1;
 foreach($array as $val)
   {
  if($val['buildgroupid'] == $currentgroupid)
    {
   // does the swapping
   $tempval = $array[$wantedposition];
   $array[$wantedposition] = $val;
   $array[$i] = $tempval;
   return $array;
    }
  $i++;
   }
  return $array;
}  */
  
  
/** Function which consolidates the group position.
 *  Based on the current position we have. 
 *  This makes sure each time segment has a continuous groupposition number and no duplicates nor gaps. */
/*function ConsolidateGroupPosition($projectid)
{
  $query = mysql_query("SELECT bg.buildgroupid,bg.position,bg.starttime FROM buildgroup AS g, buildgroupposition as bg
                                 WHERE g.id=bg.buildgroupid AND g.projectid='$projectid' ORDER BY starttime DESC, position ASC");
  
 $segments = array();
 // Put the result in an array
 $starttime = -1;
 $j=1;
 $i=1;
 while($query_array = mysql_fetch_array($query))
   {
  if($query_array["starttime"] != $starttime)
    {
   if($starttime != -1)
     {
     $segments[$j] = $segment;
     $segment = array();
    $j++;
    }
   $i=1;
   $starttime = $query_array["starttime"];   
    } 
  $segment[$i]['starttime'] = $query_array["starttime"];
  $segment[$i]['buildgroupid'] = $query_array["buildgroupid"];
  $segment[$i]['position'] = $query_array["position"];
  $i++;
  }
  $segments[$j] = $segment;
 
 if(count($segment) == 1) // if we only have one segment no need to consolidate
   {
  return;
   }
 
 // Take the current segment and put the other segements in the same order
 foreach($segments[1] as $last)
   {
  $currentid = $last['buildgroupid'];
  $currentposition = $last['position'];
   for($j=2;$j<=count($segments);$j++)
    {
   $segments[$j] = swap_array_element($segments[$j],$currentid,$currentposition);
   }
  }
   
  // Update the database with the new array
 foreach($segments as $segment)
   {
  $i=1;
  foreach($segment as $p)
    {
   $time = $p['starttime'];
   $groupid = $p['buildgroupid'];
   mysql_query("UPDATE buildgroupposition SET position='$i' WHERE buildgroupid='$groupid' AND starttime='$time'");
   $i++;
    }
  }
}*/
  
// If we should change the position
@$up= $_GET["up"];
if($up)
{
  $Groupid = $_GET["groupid"];
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroupposition WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'"));
  $position = $groupposition_array["position"];
  
  if($position > 1)
    {
    // Compute the new position
    $newpos = $position - 1;
    
    // Update the group occupying the position
    $occupyinggroup_array = mysql_fetch_array(mysql_query("SELECT g.id FROM buildgroup AS g, buildgroupposition as bg
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'
                              AND bg.endtime='0000-00-00 00:00:00'
                              "));
    $occupyinggroupid = $occupyinggroup_array["id"];
    mysql_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='0000-00-00 00:00:00'");

    // Update the group
    mysql_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'");
  
  // Update the previous positions for that group (up and down have a global effect)
  //mysql_query("UPDATE buildgroupposition SET position=position+1 WHERE buildgroupid='$Groupid' AND endtime!='0000-00-00 00:00:00'");
  
  // Consolidate the group positions
  //ConsolidateGroupPosition($projectid);
    }
}

// If we should change the position
@$down= $_GET["down"];
if($down)
{
  $Groupid = $_GET["groupid"];
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroupposition WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'"));
  $position = $groupposition_array["position"];
  
  if($position < mysql_num_rows(mysql_query("SELECT id FROM buildgroup WHERE projectid='$projectid' AND endtime='0000-00-00 00:00:00'")))
    {
    // Compute the new position
    $newpos = $position + 1;  
    // Update the group occupying the position
    $occupyinggroup_array = mysql_fetch_array(mysql_query("SELECT g.id FROM buildgroup AS g, buildgroupposition as bg
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'
                              AND bg.endtime='0000-00-00 00:00:00'
                              "));
    $occupyinggroupid = $occupyinggroup_array["id"];
    mysql_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='0000-00-00 00:00:00'");

    // Update the group
    mysql_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'");
  
   // Update the previous positions for that group (up and down have a global effect)
  //mysql_query("UPDATE buildgroupposition SET position=position-1 WHERE buildgroupid='$Groupid' AND endtime!='0000-00-00 00:00:00'");
  
  // Consolidate the group positions
  //ConsolidateGroupPosition($projectid);
    }
}
  
// If we should rename a group
@$Rename = $_POST["rename"];
if($Rename)
  {
  $Groupid = $_POST["groupid"];
  $Newname = $_POST["newname"];
  $sql = "UPDATE buildgroup SET name='$Newname' WHERE id='$Groupid'"; 
  if(!mysql_query("$sql"))
    {
    echo mysql_error();
    }
  } // end rename group

// If we should create a group
@$CreateGroup = $_POST["createGroup"];
if($CreateGroup)
  {
  $Name = $_POST["name"];
 
  // Find the last position available
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT bg.position,bg.starttime FROM buildgroup AS g, buildgroupposition AS bg 
                                                        WHERE g.id=bg.buildgroupid AND g.projectid='$projectid' 
                                                        AND bg.endtime='0000-00-00 00:00:00' ORDER BY bg.position DESC LIMIT 1"));
  $newposition = $groupposition_array["position"]+1;
  $starttime = '0000-00-00 00:00:00';
    
  // Insert the new group
  $sql = "INSERT INTO buildgroup (name,projectid,starttime) VALUES ('$Name','$projectid','$starttime')"; 
  if(mysql_query("$sql"))
    {
    $newgroupid = mysql_insert_id();
    /*if($newstarttime != $groupstarttime)
      {
      // Create a new set of positions
      $positions = mysql_query("SELECT * FROM buildgroupposition AS bg, buildgroup AS g 
                                WHERE g.projectid='$projectid' AND g.id=bg.buildgroupid AND bg.endtime='0000-00-00 00:00:00'");
      
      while($position_array = mysql_fetch_array($positions))
        {
        $groupid = $position_array["buildgroupid"];
        $currentposition = $position_array["position"];
        // Update the endtime for the current position
        mysql_query("UPDATE buildgroupposition SET endtime='$now' WHERE buildgroupid='$groupid' AND endtime='0000-00-00 00:00:00'"); 
        
        // Create a new position
        mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime) VALUES ('$groupid','$currentposition','$now')");  
        }
      }*/
      
    // Create a new position for this group
    mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime) VALUES ('$newgroupid','$newposition','$starttime')");   
    }
  else
    {
    echo mysql_error();
    }
    
  } // end CreateGroup


// If we should delete a group
@$DeleteGroup = $_POST["deleteGroup"];
if($DeleteGroup)
  {
  $Groupid = $_POST["groupid"];
 
 // We delete all the build2grouprule associated with the group
 mysql_query("DELETE FROM build2grouprule WHERE groupid='$Groupid'"); 
 
 // We delete the buildgroup
  mysql_query("DELETE FROM buildgroup WHERE id='$Groupid'"); 

  // Restore the builds that were associated with this group
 $oldbuilds = mysql_query("SELECT id,type FROM build WHERE id IN (SELECT buildid AS id FROM build2group WHERE groupid='$Groupid')"); 
  echo mysql_error();
  while($oldbuilds_array = mysql_fetch_array($oldbuilds))
  {
  // Move the builds
  $buildid = $oldbuilds_array["id"];
  $buildtype = $oldbuilds_array["type"];
   
  // Find the group corresponding to the build type
  $grouptype_array = mysql_fetch_array(mysql_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'")); 
   echo mysql_error();   
  $grouptype = $grouptype_array["id"];
   
  mysql_query("UPDATE build2group SET groupid='$grouptype' WHERE buildid='$buildid'"); 
   echo mysql_error();   
   }

  // We delete the buildgroupposition and update the position of the other groups
  mysql_query("DELETE FROM buildgoupposition WHERE buildgroupid='$Groupid'"); 

  $buildgoupposition = mysql_query("SELECT bg.buildgroupid FROM buildgroupposition as bg, buildgroup as g
                                        WHERE g.projectid='$projectid' AND bg.buildgroupid=g.id ORDER BY bg.position ASC"); 
   
 $p = 1;
  while($buildgoupposition_array = mysql_fetch_array($buildgoupposition))
   {
  $buildgroupid = $buildgoupposition_array["buildgroupid"];
   mysql_query("UPDATE buildgroupposition SET position='$p' WHERE buildgroupid='$buildgroupid'"); 
  $p++;
   }
 
 } // end DeleteGroup



/** Old Delete group with dates */
/*
if($DeleteGroup)
  {
  $Groupid = $_POST["groupid"];
  $now = date("Y-m-d H:i:s");
    
  // WARNING: We restore the state of the previous groups if we meet the following conditions
  // 1) No builds have been defined for this group (the table group2build is empty)
  // 2) No rules with expected builds are defined (build2grouprule doesn't include groupid and expected) 
  $nbuilds = mysql_num_rows(mysql_query("SELECT * FROM build2group WHERE groupid='$Groupid'"));
  $nrules = mysql_num_rows(mysql_query("SELECT * FROM build2grouprule WHERE groupid='$Groupid' AND expected='1'"));
  
  // Find the position of the current group
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT bg.position FROM buildgroup AS g, buildgroupposition AS bg 
                                                        WHERE g.id=bg.buildgroupid AND bg.buildgroupid='$Groupid' AND bg.endtime='0000-00-00 00:00:00'"));
  $groupposition = $groupposition_array["position"];
  
  $nposition = mysql_num_rows(mysql_query("SELECT * FROM buildgroupposition WHERE buildgroupid='$Groupid'")); 
  
 // If this is not the last group we put it at the last position
  if($nposition>1)
    {
  $newpos = mysql_num_rows(mysql_query("SELECT * FROM buildgroupposition WHERE endtime='0000-00-00 00:00:00'"));
  
    // Update the groups below
    $groupbelow = mysql_query("SELECT g.id FROM buildgroup AS g, buildgroupposition as bg
                        WHERE g.id=bg.buildgroupid AND bg.position>'$groupposition' AND g.projectid='$projectid'
             AND bg.endtime='0000-00-00 00:00:00'");
  
  while($groupbelow_array = mysql_fetch_array($groupbelow))
    {         
      $groupbelowid = $groupbelow_array["id"];      
   mysql_query("UPDATE buildgroupposition SET position=position-1 WHERE buildgroupid='$groupbelowid'");
      }
    // Update the group
    mysql_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'");
 
  // Consolidate the group positions
  ConsolidateGroupPosition($projectid);
  }
 
 // If the group is totally empty we remove it
  if($nbuilds == 0 && $nrules==0) 
    {
  if($nposition>1) // this is not the last created group we delete all the group positions (as to be last group)
    {
   $currentgroupstarttime_array = mysql_fetch_array(mysql_query("SELECT starttime FROM buildgroupposition WHERE buildgroupid='$Groupid' 
                                                                ORDER BY starttime ASC LIMIT 1"));
   $currentgroupstarttime = $currentgroupstarttime_array["starttime"];
   
   $groups = mysql_query("SELECT buildgroupposition.buildgroupid,buildgroupposition.starttime FROM buildgroupposition,buildgroup
               WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
               AND buildgroupposition.buildgroupid!='$Groupid' AND buildgroupposition.endtime='$currentgroupstarttime'");
   echo mysql_error();
               
   while($groups_array = mysql_fetch_array($groups))
    {
    $buildgroupid = $groups_array["buildgroupid"];
    $starttime = $groups_array["starttime"];
    
    // Find the next endtime
    $nextgroup = mysql_query("SELECT endtime FROM buildgroupposition WHERE buildgroupid='$buildgroupid' AND starttime>'$starttime' ORDER BY starttime ASC LIMIT 1"); 
    $nextgroup_array = mysql_fetch_array($nextgroup);
    $nextendtime = $nextgroup_array["endtime"];
    
    mysql_query("UPDATE buildgroupposition SET endtime='$nextendtime' WHERE buildgroupid='$buildgroupid' AND starttime='$starttime'"); 
    echo mysql_error();
    
    echo mysql_error();
    }
   
   // Finish the merging by deleting extra segments
   mysql_query("DELETE FROM buildgroupposition WHERE starttime='$currentgroupstarttime' AND buildgroupid!='$Groupid'"); 

      // Delete all the group positions for the current group
    mysql_query("DELETE FROM buildgroupposition WHERE buildgroupid='$Groupid'");
   
    // Consolidate the group positions
    ConsolidateGroupPosition($projectid);
    }
   else // this is the last added group we restore the previous step
    {
   // Find the start time for this group
   $group_array = mysql_fetch_array(mysql_query("SELECT starttime FROM buildgroup WHERE id='$Groupid'"));
   $groupstarttime = $group_array["starttime"];
   
   // Delete all the groupposition that have this starttime
   // Loop through the group for this project
   $group = mysql_query("SELECT id FROM buildgroup WHERE projectid='$projectid' AND endtime='0000-00-00 00:00:00'");
   while($group_array = mysql_fetch_array($group))
    {
    $groupid = $group_array["id"];
    mysql_query("DELETE FROM buildgroupposition WHERE starttime='$groupstarttime' AND endtime='0000-00-00 00:00:00' AND buildgroupid='$groupid'"); 
    }
    
   // Restore the old endtime
   $group = mysql_query("SELECT id FROM buildgroup WHERE projectid='$projectid'"); // just to make sure this is for the right project
   while($group_array = mysql_fetch_array($group))
    {
    $groupid = $group_array["id"];
    mysql_query("UPDATE buildgroupposition SET endtime='0000-00-00 00:00:00' WHERE endtime='$groupstarttime' AND buildgroupid='$groupid'"); 
    }
      }
       
    // Finally delete the group itself
    mysql_query("DELETE FROM buildgroup WHERE id='$Groupid'"); 
    }
  else // mark the group as done and create a new set of position
    {
    // Update the endtime for the current position
    // WARNING: This has to be before creating a new set of positions
    mysql_query("UPDATE buildgroup SET endtime='$now' WHERE id='$Groupid'"); 
    mysql_query("UPDATE buildgroupposition SET endtime='$now' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'"); 
    
    // Create a new set of positions
    $positions = mysql_query("SELECT * FROM buildgroupposition AS bg, buildgroup AS g 
                              WHERE g.projectid='$projectid' AND g.id=bg.buildgroupid AND bg.endtime='0000-00-00 00:00:00'");
    
    while($position_array = mysql_fetch_array($positions))
      {
      $groupid = $position_array["buildgroupid"];
      $currentposition = $position_array["position"];
      // Update the endtime for the current position
      mysql_query("UPDATE buildgroupposition SET endtime='$now' WHERE buildgroupid='$groupid' AND endtime='0000-00-00 00:00:00'"); 
      
      // We recompute the current position
      if($currentposition>$groupposition)
        {
        $currentposition--;
        }
       
      // Create a new position
      mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime) VALUES ('$groupid','$currentposition','$now')");  
      }
    }
  } // end DeleteGroup
*/

@$GlobalMove = $_POST["globalMove"];
@$ExpectedMove = $_POST["expectedMove"];
@$Movebuilds = $_POST["movebuilds"];
@$GroupSelection = $_POST["groupSelection"];

if($GlobalMove)
{
  foreach($Movebuilds as $buildid)
    {  
    // Find information about the build
    $build_array = mysql_fetch_array(mysql_query("SELECT type,name,siteid FROM build WHERE id='$buildid'"));
    $buildtype = $build_array['type'];
    $buildname = $build_array['name'];  
    $siteid = $build_array['siteid']; 
    
    // Remove the group
    $prevgroup = mysql_fetch_array(mysql_query("SELECT groupid FROM build2group WHERE buildid='$buildid'"));
    $prevgroupid = $prevgroup["groupid"]; 
                        
  // Update the previous group          
  mysql_query("UPDATE build2group SET groupid='$GroupSelection' WHERE groupid='$prevgroupid' AND buildid='$buildid'");
  
    // Define a new rule
    // Mark any previous rule as done
    /*$now = date("Y-m-d H:i:s");
    mysql_query("UPDATE build2grouprule SET endtime='$now'
                 WHERE groupid='$prevgroupid' AND buildtype='$buildtype'
                 AND buildname='$buildname' AND siteid='$siteid' AND endtime='0000-00-00 00:00:00'");*/
  
  // Delete any previous rules       
  mysql_query("DELETE FROM build2grouprule WHERE groupid='$prevgroupid' AND buildtype='$buildtype'
                 AND buildname='$buildname' AND siteid='$siteid'");
           
    // Add the new rule (end time is set by default by mysql)
    mysql_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime) 
                 VALUES ('$GroupSelection','$buildtype','$buildname','$siteid','$ExpectedMove','0000-00-00 00:00:00')");
    }
} // end GlobalMove

/*
@$newDate = $_POST["newDate"];
@$newstartdate = $_POST["newstartdate"];
// If change starting date of a group
if($newDate)
{
  $newstartdatestamp = strtotime($newstartdate);
  if(strlen($newstartdatestamp) == 0 || $newstartdatestamp>time())
   {
  if($newstartdatestamp>time())
    {
   $xml .= "<warning>New time cannot be in the future</warning>";
   }
   else
    {
   $xml .= "<warning>Wrong date/time format</warning>";
   }
  }
  else
   {
  $Groupid = $_POST["groupid"];
  // Change the starttime of the group
  $newdatetime = date("Y-m-d H:i:s",$newstartdatestamp);
  mysql_query("UPDATE buildgroup SET starttime='$newdatetime' WHERE id='$Groupid'"); 
  
  // Change the starttime of the build2group rules
   mysql_query("UPDATE build2grouprule SET starttime='$newdatetime' WHERE endtime='0000-00-00 00:00:00' AND groupid='$Groupid'"); 
  mysql_query("DELETE FROM build2grouprule WHERE starttime>'$newdatetime' AND groupid='$Groupid'"); 
    echo mysql_error();
  
  // Move new builds    
  $newbuilds = mysql_query("SELECT build.id FROM build,build2grouprule WHERE build.starttime>'$newdatetime'
                            AND id NOT IN (SELECT buildid AS id FROM build2group WHERE groupid='$Groupid')
               AND build2grouprule.groupid='$Groupid' AND build2grouprule.buildtype=build.type
               AND build2grouprule.buildname=build.name AND build.projectid='$projectid'"); 
  echo mysql_error();
  while($newbuilds_array = mysql_fetch_array($newbuilds))
    {
   // Move the builds
   $buildid = $newbuilds_array["id"];
   mysql_query("UPDATE build2group SET groupid='$Groupid' WHERE buildid='$buildid'"); 
    echo mysql_error();   
    }
  
  // Delete extra builds ones in their respective sections
   $oldbuilds = mysql_query("SELECT id,type FROM build WHERE starttime<'$newdatetime'
                            AND id IN (SELECT buildid AS id FROM build2group WHERE groupid='$Groupid')"); 
  echo mysql_error();
   while($oldbuilds_array = mysql_fetch_array($oldbuilds))
    {
   // Move the builds
   $buildid = $oldbuilds_array["id"];
   $buildtype = $oldbuilds_array["type"];
   
   // Find the group corresponding to the build type
   $grouptype_array = mysql_fetch_array(mysql_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'")); 
    echo mysql_error();   
   $grouptype = $grouptype_array["id"];
   
   mysql_query("UPDATE build2group SET groupid='$grouptype' WHERE buildid='$buildid'"); 
    echo mysql_error();   
    }
  
  // Merge segments with the current startdate
  $currentgroupstarttime_array = mysql_fetch_array(mysql_query("SELECT starttime FROM buildgroupposition WHERE buildgroupid='$Groupid' 
                                                                ORDER BY starttime ASC LIMIT 1"));
  $currentgroupstarttime = $currentgroupstarttime_array["starttime"];
  
  $groups = mysql_query("SELECT buildgroupposition.buildgroupid,buildgroupposition.starttime FROM buildgroupposition,buildgroup
              WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
                         AND buildgroupposition.buildgroupid!='$Groupid' AND buildgroupposition.endtime='$currentgroupstarttime'");
  echo mysql_error();
              
  while($groups_array = mysql_fetch_array($groups))
    {
   $buildgroupid = $groups_array["buildgroupid"];
   $starttime = $groups_array["starttime"];
   
   // Find the next endtime
   $nextgroup = mysql_query("SELECT endtime FROM buildgroupposition WHERE buildgroupid='$buildgroupid' AND starttime>'$starttime' ORDER BY starttime ASC LIMIT 1"); 
    $nextgroup_array = mysql_fetch_array($nextgroup);
   $nextendtime = $nextgroup_array["endtime"];
   
   mysql_query("UPDATE buildgroupposition SET endtime='$nextendtime' WHERE buildgroupid='$buildgroupid' AND starttime='$starttime'"); 
    echo mysql_error();
    
    echo mysql_error();
   }
  
  // Finish the merging by deleting extra segments
    mysql_query("DELETE FROM buildgroupposition WHERE starttime='$currentgroupstarttime' AND buildgroupid!='$Groupid'"); 

  // Add the group position to each segment
   $groups = mysql_query("SELECT buildgroupposition.buildgroupid,
                  buildgroupposition.starttime,
                 buildgroupposition.endtime,buildgroupposition.position
                         FROM buildgroupposition,buildgroup
              WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
                          AND buildgroupposition.buildgroupid!='$Groupid' 
              AND ('$newdatetime'<buildgroupposition.endtime OR buildgroupposition.endtime='0000-00-00 00:00:00')
              AND (buildgroupposition.starttime<'$newdatetime' OR buildgroupposition.starttime='0000-00-00 00:00:00')");
  while($groups_array = mysql_fetch_array($groups))
    {
   $buildgroupid = $groups_array["buildgroupid"];
   $starttime = $groups_array["starttime"];
   $endtime = $groups_array["endtime"];
   $position = $groups_array["position"];
   
    // Set the current end time of the group to the newdatetime
   mysql_query("UPDATE buildgroupposition SET endtime='$newdatetime' WHERE buildgroupid='$buildgroupid ' AND starttime='$starttime'"); 
    echo mysql_error();

   // Create a new segment after it      
   mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime) 
                                        VALUES ('$buildgroupid','$position','$newdatetime','$endtime')"); 
      echo mysql_error();
    }
 
  // If the date is before the current date we add segments
  if($newdatetime < $currentgroupstarttime)
    {
   // Move the current starttime for the current group to the previous startsegment
   // Find the previous starttime
   $previousgroup = mysql_query("SELECT buildgroupposition.starttime FROM buildgroupposition,buildgroup
                                 WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
                  AND buildgroupposition.starttime<'$currentgroupstarttime' GROUP BY buildgroupposition.starttime 
                  ORDER BY buildgroupposition.starttime DESC LIMIT 1"); 
   echo mysql_error();             
    $previousgroup_array = mysql_fetch_array($previousgroup);
   $nextendtime = $previousgroup_array["starttime"];
   
   mysql_query("UPDATE buildgroupposition SET starttime='$nextendtime' WHERE buildgroupid='$Groupid' AND starttime='$currentgroupstarttime'");   
   echo mysql_error(); 
   // Add Segments               
   $grouptime = mysql_query("SELECT buildgroupposition.starttime FROM buildgroupposition,buildgroup
                             WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
                AND buildgroupposition.starttime>'$newdatetime' 
                             AND buildgroupposition.starttime<'$currentgroupstarttime' GROUP BY buildgroupposition.starttime 
                ORDER BY buildgroupposition.starttime DESC");
   echo mysql_error();
   $segmentstarttime = 0;            
   while($grouptime_array = mysql_fetch_array($grouptime))
     {
    $newsgmentendtime = $grouptime_array["starttime"];
    if($segmentstarttime!=0)
      {
     mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime) 
                                        VALUES ('$Groupid','0','$segmentstarttime','$newsgmentendtime')"); 
          echo mysql_error();
       }
    $segmentstarttime = $newsgmentendtime;
    }
    
   // Insert the first segment
   if(isset($newsgmentendtime))
     {
    mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime) 
                                        VALUES ('$Groupid','0','$newdatetime','$newsgmentendtime')"); 
        echo mysql_error();
     }
    }
   
  
  // If the date is after the current date. we need to remove some segments
   if($newdatetime > $currentgroupstarttime)
   {
   // Find the segment that has the new time in it
   $nextgroup = mysql_query("SELECT buildgroupposition.endtime FROM buildgroupposition,buildgroup
                                 WHERE buildgroup.id=buildgroupposition.buildgroupid AND buildgroup.projectid='$projectid'
                  AND (buildgroupposition.starttime<'$newdatetime' OR buildgroupposition.starttime='0000-00-00 00:00:00')
                  AND ('$newdatetime'<buildgroupposition.endtime OR buildgroupposition.endtime='0000-00-00 00:00:00')
                  "); 
   echo mysql_error();             
    $nextgroup_array = mysql_fetch_array($nextgroup);
   $endtime = $nextgroup_array["endtime"];
   
   mysql_query("UPDATE buildgroupposition SET starttime='$newdatetime' WHERE buildgroupid='$Groupid' AND endtime='$endtime'");   
   echo mysql_error(); 
   
   // Remove segment that are before this one
    mysql_query("DELETE FROM buildgroupposition WHERE starttime<'$newdatetime' AND buildgroupid='$Groupid'"); 
      echo mysql_error();
   }

  // Consolidate the group positions
  ConsolidateGroupPosition($projectid);
   }
} // end change starting date of a group
*/


/** We start generating the XML here */

// Find the recent builds for this project
if($projectid>0)
  {
  $currentUTCTime =  gmdate("YmdHis");
 $beginUTCTime = gmdate("YmdHis",time()-3600*24);
 
  $sql = "";
  if($show>0)
    {
    $sql = "AND g.id='$show'";
    }
 
  
  $builds = mysql_query("SELECT b.id,s.name AS sitename,b.name,b.type,g.name as groupname,gp.position,g.id as groupid 
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp, site as s
                         WHERE b.starttime<'$currentUTCTime' AND b.starttime>'$beginUTCTime'
                         AND b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id  
                         AND s.id = b.siteid ".$sql." ORDER BY b.name ASC");
  
 echo mysql_error();
 
  $names = array();
  
  while($build_array = mysql_fetch_array($builds))
    {
    if(array_search($build_array['sitename'].$build_array['name'],$names) === FALSE)
      {
      $xml .= "<currentbuild>";
      $xml .= add_XML_value("id",$build_array['id']);
      $xml .= add_XML_value("name",$build_array['sitename']." ".$build_array['name']." [".$build_array['type']."] ".$build_array['groupname']);
      $xml .= "</currentbuild>";
      $names[] = $build_array['sitename'].$build_array['name'];
      }
    }
  }

// If we have a project id
// WARNING: We should check for security here
if($projectid>0)
  {
  $project = mysql_query("SELECT id,name FROM project WHERE id='$projectid'");
  $project_array = mysql_fetch_array($project);
  $xml .= "<project>";
  $xml .= add_XML_value("id",$project_array['id']);
  $xml .= add_XML_value("name",$project_array['name']);
  
  // Display the current groups
  $groups = mysql_query("SELECT g.id,g.name,gp.position,g.starttime FROM buildgroup AS g, buildgroupposition AS gp 
                         WHERE g.id=gp.buildgroupid AND g.projectid='$projectid' 
                         AND g.endtime='0000-00-00 00:00:00' AND gp.endtime='0000-00-00 00:00:00'
                         ORDER BY gp.position ASC");
  while($group_array = mysql_fetch_array($groups))
    {
    $xml .= "<group>";
    if($show == $group_array['id'])
      {
      $xml .= add_XML_value("selected","1");
      }
    $xml .= add_XML_value("id",$group_array['id']);
    $xml .= add_XML_value("name",$group_array['name']);
    $xml .= add_XML_value("position",$group_array['position']);
  $xml .= add_XML_value("startdate",$group_array['starttime']);
    $xml .= "</group>";
    }
  $xml .= "</project>";
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageBuildGroup");

} // end session OK
?>

