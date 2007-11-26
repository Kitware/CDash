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
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroup WHERE id='$Groupid'"));
		$position = $groupposition_array["position"];
		
  if($position > 1)
		  {
		  // Compute the new position
				$newpos = $position - 1;		
				// Update the group occupying the position
				$occupyinggroup_array = mysql_fetch_array(mysql_query("SELECT id FROM buildgroup WHERE position='$newpos'"));
		  $occupyinggroupid = $occupyinggroup_array["id"];
				mysql_query("UPDATE buildgroup SET position='$position' WHERE id='$occupyinggroupid'");

				// Update the group
				mysql_query("UPDATE buildgroup SET position='$newpos' WHERE id='$Groupid'");
				}
}

// If we should change the position
@$down= $_GET["down"];
if($down)
{
  $Groupid = $_GET["groupid"];
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroup WHERE id='$Groupid'"));
		$position = $groupposition_array["position"];
		
  if($position < mysql_num_rows(mysql_query("SELECT position FROM buildgroup WHERE projectid='$projectid'")))
		  {
		  // Compute the new position
				$newpos = $position + 1;		
				// Update the group occupying the position
				$occupyinggroup_array = mysql_fetch_array(mysql_query("SELECT id FROM buildgroup WHERE position='$newpos'"));
		  $occupyinggroupid = $occupyinggroup_array["id"];
				mysql_query("UPDATE buildgroup SET position='$position' WHERE id='$occupyinggroupid'");

				// Update the group
				mysql_query("UPDATE buildgroup SET position='$newpos' WHERE id='$Groupid'");
				}
}

// If we should delete a group
@$DeleteGroup = $_POST["deleteGroup"];
if($DeleteGroup)
  {
		$Groupid = $_POST["groupid"];
		$sql = "DELETE FROM buildgroup WHERE id='$Groupid'"; 
		if(!mysql_query("$sql"))
    {
    echo mysql_error();
    }
  } // end DeleteGroup
		
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

// If we should delete the group
@$CreateGroup = $_POST["createGroup"];
if($CreateGroup)
  {
  $Name = $_POST["name"];
 
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroup WHERE projectid='$projectid' ORDER BY position DESC LIMIT 1"));
		$position = $groupposition_array["position"]+1;
		
  $sql = "INSERT INTO buildgroup (name,position,projectid) VALUES ('$Name','$position','$projectid')"; 
		if(mysql_query("$sql"))
    {
    $xml .= "<group_name>$Name</group_name>";
    $xml .= "<group_created>1</group_created>";
    $xml .= "<project_name>".get_project_name($projectid)."</project_name>";
				}
  else
    {
    echo mysql_error();
    }
  } // end CreateGroup

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
