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
include_once("cdash/common.php");
include('login.php');
include('cdash/version.php');
include("models/project.php");

if ($session_OK)
{
@$db = pdo_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
pdo_select_db("$CDASH_DB_NAME",$db);

$userid = $_SESSION['cdash']['loginid'];
// Checks
if(!isset($userid) || !is_numeric($userid))
  {
  echo "Not a valid userid!";
  return;
  }

$xml = begin_XML_for_XSLT();
$xml .= "<backurl>user.php</backurl>";
$xml .= "<title>CDash - Build Groups</title>";
$xml .= "<menutitle>CDash</menutitle>";
$xml .= "<menusubtitle>Build Groups</menusubtitle>";

@$projectid = $_GET["projectid"];
if ($projectid != NULL)
  {
  $projectid = pdo_real_escape_numeric($projectid);
  }

// If the projectid is not set and there is only one project we go directly to the page
if(!isset($projectid))
{
  $project = pdo_query("SELECT id FROM project");
  if(pdo_num_rows($project)==1)
    {
    $project_array = pdo_fetch_array($project);
    $projectid = $project_array["id"];
    }
}

@$submitAutoRemoveSettings = $_POST["submitAutoRemoveSettings"];
if($submitAutoRemoveSettings)
{
  foreach($_POST as $key=>$value)
    {
    $value = pdo_real_escape_numeric($value);
    if(substr($key, 0, 20) == 'autoremovetimeframe_' && is_numeric($value))
      {
      list(,$id) = explode('_',$key);
      pdo_query("UPDATE buildgroup SET autoremovetimeframe='$value' WHERE id=".qnum($id));
      }
    }
}

@$show = $_GET["show"];

$role=0;

 $user_array = pdo_fetch_array(pdo_query("SELECT admin FROM ".qid("user")." WHERE id='$userid'"));
if($projectid && is_numeric($projectid))
  {
  $user2project = pdo_query("SELECT role FROM user2project WHERE userid='$userid' AND projectid='$projectid'");
  if(pdo_num_rows($user2project)>0)
    {
    $user2project_array = pdo_fetch_array($user2project);
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
$projects = pdo_query($sql);
while($project_array = pdo_fetch_array($projects))
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
  $Groupid = pdo_real_escape_numeric($_GET["groupid"]);
  // Checks
  if(!isset($Groupid) || !is_numeric($Groupid))
    {
    echo "Not a valid Groupid!";
    return;
    }

  $groupposition_array = pdo_fetch_array(pdo_query("SELECT position FROM buildgroupposition WHERE buildgroupid='$Groupid' AND endtime='1980-01-01 00:00:00'"));
  $position = $groupposition_array["position"];

  if($position > 1)
    {
    // Compute the new position
    $newpos = $position - 1;

    // Update the group occupying the position
    $occupyinggroup_array = pdo_fetch_array(pdo_query("SELECT g.id FROM buildgroup AS g, buildgroupposition as bg
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'
                              AND bg.endtime='1980-01-01 00:00:00'
                              "));
    $occupyinggroupid = $occupyinggroup_array["id"];
    pdo_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='1980-01-01 00:00:00'");

    // Update the group
    pdo_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='1980-01-01 00:00:00'");

    }
}

// If we should change the position
@$down= $_GET["down"];
if($down)
{
  $Groupid = pdo_real_escape_numeric($_GET["groupid"]);
  // Checks
  if(!isset($Groupid) || !is_numeric($Groupid))
    {
    echo "Not a valid Groupid!";
    return;
    }

  $groupposition_array = pdo_fetch_array(pdo_query("SELECT position FROM buildgroupposition WHERE buildgroupid='$Groupid' AND endtime='1980-01-01 00:00:00'"));
  $position = $groupposition_array["position"];

  if($position < pdo_num_rows(pdo_query("SELECT id FROM buildgroup WHERE projectid='$projectid' AND endtime='1980-01-01 00:00:00'")))
    {
    // Compute the new position
    $newpos = $position + 1;
    // Update the group occupying the position
    $occupyinggroup_array = pdo_fetch_array(pdo_query("SELECT g.id FROM buildgroup AS g, buildgroupposition as bg
                                                           WHERE g.id=bg.buildgroupid AND bg.position='$newpos' AND g.projectid='$projectid'
                              AND bg.endtime='1980-01-01 00:00:00'
                              "));
    $occupyinggroupid = $occupyinggroup_array["id"];
    pdo_query("UPDATE buildgroupposition SET position='$position' WHERE buildgroupid='$occupyinggroupid' AND endtime='1980-01-01 00:00:00'");

    // Update the group
    pdo_query("UPDATE buildgroupposition SET position='$newpos' WHERE buildgroupid='$Groupid' AND endtime='1980-01-01 00:00:00'");

    }
}

// If we should update the description
@$submitDescription = $_POST["submitDescription"];
if($submitDescription)
  {
  $Groupid = pdo_real_escape_numeric($_POST["groupid"]);
  $Description = htmlspecialchars(pdo_real_escape_string($_POST["description"]));
  $sql = "UPDATE buildgroup SET description='$Description' WHERE id='$Groupid'";
  if(!pdo_query("$sql"))
    {
    echo pdo_error();
    }
  } // end submitDescription group

// If we should rename a group
@$Rename = $_POST["rename"];
if($Rename)
  {
  $Groupid = pdo_real_escape_numeric($_POST["groupid"]);
  $Newname = htmlspecialchars(pdo_real_escape_string($_POST["newname"]));
  $sql = "UPDATE buildgroup SET name='$Newname' WHERE id='$Groupid'";
  if(!pdo_query("$sql"))
    {
    echo pdo_error();
    }
  } // end rename group

// If we should create a group
@$CreateGroup = $_POST["createGroup"];
if($CreateGroup)
  {
  $Name = htmlspecialchars(pdo_real_escape_string($_POST["name"]));

  // Avoid creating a group that is Nightly, Experimental or Continuous
  if($Name == "Nightly" || $Name == "Experimental" || $Name == "Continuous")
    {
     $xml .= add_XML_value("warning","You cannot create a group named 'Nightly','Experimental' or 'Continuous'");
    }
  else
    {
    // Find the last position available
    $groupposition_array = pdo_fetch_array(pdo_query("SELECT bg.position,bg.starttime FROM buildgroup AS g, buildgroupposition AS bg
                                                          WHERE g.id=bg.buildgroupid AND g.projectid='$projectid'
                                                          AND bg.endtime='1980-01-01 00:00:00' ORDER BY bg.position DESC LIMIT 1"));
    $newposition = $groupposition_array["position"]+1;
    $starttime = '1980-01-01 00:00:00';
    $endtime = '1980-01-01 00:00:00';

    // Insert the new group
    $sql = "INSERT INTO buildgroup (name,projectid,starttime,endtime,description) VALUES ('$Name','$projectid','$starttime','$endtime','')";
    if(pdo_query("$sql"))
      {
      $newgroupid = pdo_insert_id("buildgroup");

      // Create a new position for this group
      pdo_query("INSERT INTO buildgroupposition (buildgroupid,position,starttime,endtime) VALUES ('$newgroupid','$newposition','$starttime','$endtime')");
      }
    else
      {
      echo pdo_error();
      }
    } // end not Nightly or Experimental or Continuous
  } // end CreateGroup


// If we should delete a group
@$DeleteGroup = $_POST["deleteGroup"];
if($DeleteGroup)
  {
  $Groupid = pdo_real_escape_numeric($_POST["groupid"]);

  // We delete all the build2grouprule associated with the group
  pdo_query("DELETE FROM build2grouprule WHERE groupid='$Groupid'");

  // We delete the buildgroup
  pdo_query("DELETE FROM buildgroup WHERE id='$Groupid'");

  // Restore the builds that were associated with this group
  $oldbuilds = pdo_query("SELECT id,type FROM build WHERE id IN (SELECT buildid AS id FROM build2group WHERE groupid='$Groupid')");
  echo pdo_error();
  while($oldbuilds_array = pdo_fetch_array($oldbuilds))
    {
    // Move the builds
    $buildid = $oldbuilds_array["id"];
    $buildtype = $oldbuilds_array["type"];

    // Find the group corresponding to the build type
    $query = pdo_query("SELECT id FROM buildgroup WHERE name='$buildtype' AND projectid='$projectid'");
    if(pdo_num_rows($query) == 0)
      {
      $query = pdo_query("SELECT id FROM buildgroup WHERE name='Experimental' AND projectid='$projectid'");
      }
    echo pdo_error();
    $grouptype_array = pdo_fetch_array($query);
    $grouptype = $grouptype_array["id"];

    pdo_query("UPDATE build2group SET groupid='$grouptype' WHERE buildid='$buildid'");
    echo pdo_error();
    }

  // We delete the buildgroupposition and update the position of the other groups
  pdo_query("DELETE FROM buildgroupposition WHERE buildgroupid='$Groupid'");

  $buildgroupposition = pdo_query("SELECT bg.buildgroupid FROM buildgroupposition as bg, buildgroup as g
                                        WHERE g.projectid='$projectid' AND bg.buildgroupid=g.id ORDER BY bg.position ASC");

  $p = 1;
  while($buildgroupposition_array = pdo_fetch_array($buildgroupposition))
    {
    $buildgroupid = $buildgroupposition_array["buildgroupid"];
    pdo_query("UPDATE buildgroupposition SET position='$p' WHERE buildgroupid='$buildgroupid'");
    $p++;
    }
  } // end DeleteGroup


@$GlobalMove = $_POST["globalMove"];
@$ExpectedMove = $_POST["expectedMove"];
@$Movebuilds = $_POST["movebuilds"];
@$GroupSelection = $_POST["groupSelection"];

if($GlobalMove)
{
  if($GroupSelection == 0)
    {
    $xml .= add_XML_value("warning","Please select a group to add these builds");
    }
  else
    {
    foreach($Movebuilds as $buildid)
      {
      // Find information about the build
      $build_array = pdo_fetch_array(pdo_query("SELECT type,name,siteid FROM build WHERE id='$buildid'"));
      $buildtype = $build_array['type'];
      $buildname = $build_array['name'];
      $siteid = $build_array['siteid'];

      // Remove the group
      $prevgroup = pdo_fetch_array(pdo_query("SELECT groupid FROM build2group WHERE buildid='$buildid'"));
      $prevgroupid = $prevgroup["groupid"];

      // Update the previous group
      pdo_query("UPDATE build2group SET groupid='$GroupSelection' WHERE groupid='$prevgroupid' AND buildid='$buildid'");

      // Delete any previous rules
      pdo_query("DELETE FROM build2grouprule WHERE groupid='$prevgroupid' AND buildtype='$buildtype'
                   AND buildname='$buildname' AND siteid='$siteid'");

      // Add the new rule
      pdo_query("INSERT INTO build2grouprule(groupid,buildtype,buildname,siteid,expected,starttime,endtime)
            VALUES ('$GroupSelection','$buildtype','$buildname','$siteid','$ExpectedMove','1980-01-01 00:00:00','1980-01-01 00:00:00')");
      }
    }
} // end GlobalMove



// Update summary email
if(isset($_POST["groupid"]))
{
  $Groupid = $_POST["groupid"];
  @$SummaryEmail = $_POST["summaryEmail"];
  @$EmailCommitters = $_POST["emailCommitters"];
  @$IncludeInSummary = $_POST["includeInSummary"];

  if(!isset($SummaryEmail))
    {
    $SummaryEmail = 0;
    }
  if(!isset($EmailCommitters))
    {
    $EmailCommitters = 0;
    }
   if(!isset($IncludeInSummary))
    {
    $IncludeInSummary = 0;
    }

  $EmailCommitters = pdo_real_escape_numeric($EmailCommitters);
  $IncludeInSummary = pdo_real_escape_numeric($IncludeInSummary);
  $Groupid = pdo_real_escape_numeric($Groupid);
  $SummaryEmail = pdo_real_escape_numeric($SummaryEmail);

  $sql = "UPDATE buildgroup SET summaryemail='$SummaryEmail', ".
    "emailcommitters='$EmailCommitters', ".
    "includesubprojectotal='$IncludeInSummary' ".
    "WHERE id='$Groupid'";
  if(!pdo_query("$sql"))
    {
    echo pdo_error();
    }
}

/** We start generating the XML here */

// Find the recent builds for this project
if($projectid>0)
  {
  $currentUTCTime =  gmdate(FMT_DATETIME);
  $beginUTCTime = gmdate(FMT_DATETIME,time()-3600*7*24); // 7 days

  $sql = "";
  if($show>0)
    {
    $sql = "AND g.id='$show'";
    }


  $builds = pdo_query("SELECT b.id,s.name AS sitename,b.name,b.type,g.name as groupname,g.id as groupid
                         FROM build AS b, build2group AS b2g,buildgroup AS g, buildgroupposition AS gp, site as s
                         WHERE b.starttime<'$currentUTCTime' AND b.starttime>'$beginUTCTime'
                         AND b.projectid='$projectid' AND b2g.buildid=b.id AND gp.buildgroupid=g.id AND b2g.groupid=g.id
                         AND s.id = b.siteid ".$sql." ORDER BY b.name ASC");

  echo pdo_error();

  $names = array();
  while($build_array = pdo_fetch_array($builds))
    {
    // Avoid adding the same build twice
    if(array_search($build_array['sitename'].$build_array['name'].$build_array['type'],$names) === FALSE)
      {
      $xml .= "<currentbuild>";
      $xml .= add_XML_value("id",$build_array['id']);
      $xml .= add_XML_value("name",$build_array['sitename']." ".$build_array['name']." [".$build_array['type']."] ".$build_array['groupname']);
      $xml .= "</currentbuild>";
      $names[] = $build_array['sitename'].$build_array['name'].$build_array['type'];
      }
    }

  // Add expected builds
  $builds = pdo_query("SELECT b.id,s.name AS sitename,b.name,b.type,g.name as groupname,g.id as groupid
                         FROM site AS s,build AS b,build2group AS b2g,buildgroup as g
                         WHERE
                         g.id=b2g.groupid AND b2g.buildid=b.id AND g.endtime='1980-01-01 00:00:00'
                         AND b.projectid='$projectid' AND s.id = b.siteid ".$sql." ORDER BY b.name ASC");
  echo pdo_error();

  while($build_array = pdo_fetch_array($builds))
    {
    // Avoid adding the same build twice
    if(array_search($build_array['sitename'].$build_array['name'].$build_array['type'],$names) === FALSE)
      {
      $xml .= "<currentbuild>";
      $xml .= add_XML_value("id",$build_array['id']);
      $xml .= add_XML_value("name",$build_array['sitename']." ".$build_array['name']." [".$build_array['type']."] ".$build_array['groupname']." (expected)");
      $xml .= "</currentbuild>";
      $names[] = $build_array['sitename'].$build_array['name'].$build_array['type'];
      }
    }
}

$Project = new Project();
$Project->Id = $projectid;
$buildgroups = $Project->GetBuildGroups();
foreach($buildgroups as $buildgroup)
    {
    $xml .= "<buildgroup>";
    $xml .= add_XML_value('id',$buildgroup['id']);
    $xml .= add_XML_value('name',$buildgroup['name']);
    $xml .= add_XML_value('autoremovetimeframe',$buildgroup['autoremovetimeframe']);
    $xml .= "</buildgroup>";
    }

// If we have a project id
// WARNING: We should check for security here
if($projectid>0)
  {
  $project = pdo_query("SELECT id,name FROM project WHERE id='$projectid'");
  $project_array = pdo_fetch_array($project);
  $xml .= "<project>";
  $xml .= add_XML_value("id",$project_array['id']);
  $xml .= add_XML_value("name",$project_array['name']);
  $xml .= add_XML_value("name_encoded",urlencode($project_array['name']));

  // Display the current groups

  $groups = pdo_query("SELECT g.id,g.name,g.description,g.summaryemail,g.emailcommitters,g.includesubprojectotal,
                              gp.position,g.starttime FROM buildgroup AS g, buildgroupposition AS gp
                         WHERE g.id=gp.buildgroupid AND g.projectid='$projectid'
                         AND g.endtime='1980-01-01 00:00:00' AND gp.endtime='1980-01-01 00:00:00'
                         ORDER BY gp.position ASC");
  $color = 0;
  while($group_array = pdo_fetch_array($groups))
    {
    $xml .= "<group>";
    if($color == 0)
      {
      $xml .= add_XML_value("bgcolor","#FFFFFF");
      $color = 1;
      }
    else
      {
      $xml .= add_XML_value("bgcolor","#DDDDDD");
      $color = 0;
      }
    if($show == $group_array['id'])
      {
      $xml .= add_XML_value("selected","1");
      }
    $xml .= add_XML_value("id",$group_array['id']);
    $xml .= add_XML_value("name",$group_array['name']);
    $xml .= add_XML_value("description",$group_array['description']);
    $xml .= add_XML_value("summaryemail",$group_array['summaryemail']);
    $xml .= add_XML_value("emailcommitters",$group_array['emailcommitters']);
    $xml .= add_XML_value("includeinsummary",$group_array['includesubprojectotal']);
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

