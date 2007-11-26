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

@$projectid = $_GET["projectid"];
  
$projects = mysql_query("SELECT id,name FROM project"); // we should check if we are admin on the project
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
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'"));
    $occupyinggroupid = $occupyinggroup_array["id"];
    mysql_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='0000-00-00 00:00:00'");

    // Update the group
    mysql_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'");
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
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'"));
    $occupyinggroupid = $occupyinggroup_array["id"];
    mysql_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='0000-00-00 00:00:00'");

    // Update the group
    mysql_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='0000-00-00 00:00:00'");
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
  $groupstarttime = $groupposition_array["starttime"];
  $now = date("Y-m-d H:i:s");
  $newstarttime = $now;
 
  // If we are adding several groups in a short period of time then
  // we don't create a new set
  /*if(abs(strtotime($now)-strtotime($groupstarttime))<3600) // 1 hour
    {
    $newstarttime = $groupstarttime;
    }*/
    
  // Insert the new group
  $sql = "INSERT INTO buildgroup (name,projectid,starttime) VALUES ('$Name','$projectid','$newstarttime')"; 
  if(mysql_query("$sql"))
    {
    $newgroupid = mysql_insert_id();
    
    //$xml .= "<group_name>$Name</group_name>";
    //$xml .= "<group_created>1</group_created>";
    //$xml .= "<project_name>".get_project_name($projectid)."</project_name>";

    if($newstarttime != $groupstarttime)
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
      }
      
    // Create a new position for this group
    mysql_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime) VALUES ('$newgroupid','$newposition','$newstarttime')");   
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
  $now = date("Y-m-d H:i:s");
    
  // WARNING: So we restore the state of the previous groups if we meet the following conditions
  // 1) No builds have been defined for this group (the table group2build is empty)
  // 2) No rules with expected builds are defined (build2grouprule doesn't include groupid and expected) 
  $nbuilds = mysql_num_rows(mysql_query("SELECT * FROM build2group WHERE groupid='$Groupid'"));
  $nrules = mysql_num_rows(mysql_query("SELECT * FROM build2grouprule WHERE groupid='$Groupid' AND expected='1'"));
  
  // Find the position of the current group
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT bg.position FROM buildgroup AS g, buildgroupposition AS bg 
                                                        WHERE g.id=bg.buildgroupid AND bg.buildgroupid='$Groupid' AND bg.endtime='0000-00-00 00:00:00'"));
  $groupposition = $groupposition_array["position"];
  
  $nposition = mysql_num_rows(mysql_query("SELECT * FROM buildgroupposition WHERE buildgroupid='$Groupid'")); 
  
  if($nposition>1)
    {
    $xml .= "<warning>";
    $xml .= "Are you sure you want to delete this group? can you just delete the last group and rename newly created group?";
    $xml .= "</warning>";
    }
  else if($nbuilds == 0 && $nrules==0)
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
																								
				mysql_query("DELETE FROM build2group WHERE groupid='$prevgroupid' AND buildid='$buildid'");
				
				// Insert into the group
				mysql_query("INSERT INTO build2group(groupid,buildid) VALUES ('$GroupSelection','$buildid')");
  
				// Define a new rule
				// Mark any previous rule as done
				$now = date("Y-m-d H:i:s");
				mysql_query("UPDATE build2grouprule SET endtime='$now'
																	WHERE groupid='$prevgroupid' AND buildtype='$buildtype'
																	AND buildname='$buildname' AND siteid='$siteid' AND endtime='0000-00-00 00:00:00'");
		
				// Add the new rule (begin time is set by default by mysql
				mysql_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime) 
																	VALUES ('$GroupSelection','$buildtype','$buildname','$siteid','$ExpectedMove','$now')");
		  }
} // end GlobalMove

// Find the recent builds for this project
if($projectid>0)
  {
		$end_timestamp = time();
		$beginning_timestamp = $end_timestamp-3600*240;
		
	 $builds = mysql_query("SELECT b.id,s.name AS sitename,b.name,b.type,g.name as groupname,gp.position,g.id as groupid 
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp, site as s
                         WHERE UNIX_TIMESTAMP(b.starttime)<$end_timestamp AND UNIX_TIMESTAMP(b.starttime)>$beginning_timestamp
                         AND b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id  
																									AND s.id = b.siteid
                         AND UNIX_TIMESTAMP(gp.starttime)<$end_timestamp AND (UNIX_TIMESTAMP(gp.endtime)>$end_timestamp OR gp.endtime='0000-00-00 00:00:00')
                         ORDER BY gp.position ASC,b.starttime DESC");
		
		while($build_array = mysql_fetch_array($builds))
    {

				$xml .= "<currentbuild>";
				$xml .= add_XML_value("id",$build_array['id']);
				$xml .= add_XML_value("name",$build_array['sitename']." ".$build_array['name']." [".$build_array['type']."] ".$build_array['groupname']);
				$xml .= "</currentbuild>";
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
  $groups = mysql_query("SELECT g.id,g.name,gp.position FROM buildgroup AS g, buildgroupposition AS gp 
                         WHERE g.id=gp.buildgroupid AND g.projectid='$projectid' 
                         AND g.endtime='0000-00-00 00:00:00' AND gp.endtime='0000-00-00 00:00:00'
                         ORDER BY gp.position ASC");
  while($group_array = mysql_fetch_array($groups))
    {
    $xml .= "<group>";
    $xml .= add_XML_value("id",$group_array['id']);
    $xml .= add_XML_value("name",$group_array['name']);
    $xml .= add_XML_value("position",$group_array['position']);
    $xml .= "</group>";
    }
  $xml .= "</project>";
  }

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageBuildGroup");
?>

