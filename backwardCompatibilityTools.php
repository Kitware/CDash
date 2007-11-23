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
					  mysql_query("INSERT INTO buildgroup(name,position,projectid) VALUES ('Nightly','1','$projectid')"); 
					  mysql_query("INSERT INTO buildgroup(name,position,projectid) VALUES ('Continuous','2','$projectid')"); 
					  mysql_query("INSERT INTO buildgroup(name,position,projectid) VALUES ('Experimental','3','$projectid')");
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
				 mysql_query("INSERT INTO build2group(buildid,groupid,expected) VALUES ('$buildid','$groupid','0')"); 
					}
					
		$xml .= add_XML_value("alert","Builds have been added to default groups successfully.");

  } // end AssignBuildToDefaultGroups


$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"backwardCompatibilityTools");
?>
