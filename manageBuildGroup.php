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
$projects = mysql_query("SELECT id,name FROM project"); // we should check if we are admin on the project
while($project_array = mysql_fetch_array($projects))
   {
			$xml .= "<project>";
			$xml .= add_XML_value("id",$project_array['id']);
			$xml .= add_XML_value("name",$project_array['name']);
			$xml .= "</project>";
			}

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
  {
  $Name = $_POST["name"];
  $Projectid = $_POST["projectSelection"];
 
  $groupposition_array = mysql_fetch_array(mysql_query("SELECT position FROM buildgroup WHERE projectid='$Projectid' ORDER BY position DESC LIMIT 1"));
		$position = $groupposition_array["position"]+1;
		
  $sql = "INSERT INTO buildgroup (name,position,projectid) VALUES ('$Name','$position','$Projectid')"; 
		if(mysql_query("$sql"))
    {
    $xml .= "<group_name>$Name</group_name>";
    $xml .= "<group_created>1</group_created>";
    $xml .= "<project_name>".get_project_name($Projectid)."</project_name>";
				}
  else
    {
    echo mysql_error();
    }
  } // end submit

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"manageBuildGroup");
?>
