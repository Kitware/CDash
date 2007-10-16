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

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  $Name = $_POST["name"];
  $Description = addslashes($_POST["description"]);
		$HomeURL = $_POST["homeURL"];
		$CVSURL = $_POST["cvsURL"];
		$BugURL = $_POST["bugURL"];
				
		$handle = fopen($_FILES['logo']['tmp_name'],"r");
		$contents = addslashes(fread($handle,$_FILES['logo']['size']));
		fclose($handle);
		
		$sql = "INSERT INTO project(name,description,homeurl,cvsurl,bugtrackerurl,logo) 
	    				VALUES ('$Name','$Description','$HomeURL','$CVSURL','$BugURL','$contents')"; 
  if(mysql_query("$sql"))
		  {
				$xml .= "<project_name>$Name</project_name>";
				$xml .= "<project_created>1</project_created>";
		  }
		
		echo mysql_error();
} // end submit

$xml .= "</cdash>";

// Now doing the xslt transition
generate_XSLT($xml,"createProject");
?>
